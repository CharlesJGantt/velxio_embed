<?php

namespace Drupal\velxio_embed\Controller;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts a .vlx project from the Lab and files it as a Velxio Project media.
 *
 * This is what "Save to Media Library" in the Lab's File menu calls. It
 * exists so a project built in the sandbox can be pushed straight into
 * Drupal's media library without the manual export -> download -> hand-built
 * File+Media entity round trip that creating this endpoint replaced.
 *
 * Auth is a static bearer token (velxio_embed.settings:save_api_token_hash),
 * not a Drupal session -- the Lab runs on a different origin (its own
 * domain, e.g. velxio.example.com, or a local dev server) with no
 * Drupal session cookie to send. The token is generated once from the
 * module's settings form and pasted into the Lab's own settings; only its
 * hash is ever stored here.
 */
class SaveProjectController extends ControllerBase {

  /**
   * Handles POST (the actual save) and OPTIONS (CORS preflight).
   */
  public function save(Request $request): Response {
    $config = $this->config('velxio_embed.settings');
    $origin = $request->headers->get('Origin', '');
    $cors_headers = $this->corsHeaders($origin, $config->get('save_allowed_origins') ?: []);

    if ($request->getMethod() === 'OPTIONS') {
      // Preflight: no auth check -- browsers never send the Authorization
      // header (or the real body) on the preflight itself.
      $response = new Response('', 204);
      foreach ($cors_headers as $name => $value) {
        $response->headers->set($name, $value);
      }
      return $response;
    }

    $token_hash = (string) $config->get('save_api_token_hash');
    if ($token_hash === '') {
      return $this->jsonError('No API token has been configured for this endpoint yet. Generate one at /admin/config/content/velxio.', 503, $cors_headers);
    }

    $auth = (string) $request->headers->get('Authorization', '');
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
      return $this->jsonError('Missing or malformed Authorization header. Expected: Bearer <token>.', 401, $cors_headers);
    }
    $provided_hash = Crypt::hashBase64(trim($m[1]));
    if (!hash_equals($token_hash, $provided_hash)) {
      return $this->jsonError('Invalid API token.', 401, $cors_headers);
    }

    $raw = $request->getContent();
    $payload = json_decode($raw, TRUE);
    if (!is_array($payload) || ($payload['format'] ?? NULL) !== 'velxio-project') {
      return $this->jsonError('Request body is not a valid velxio-project payload (expected the same JSON shape as a .vlx export).', 422, $cors_headers);
    }

    $name = trim((string) ($payload['name'] ?? '')) ?: 'Untitled Project';
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-')) ?: 'project';
    $filename = $slug . '-' . \Drupal::time()->getRequestTime() . '.vlx';

    $file_system = \Drupal::service('file_system');
    $directory = 'public://velxio-projects';
    $file_system->prepareDirectory($directory, $file_system::CREATE_DIRECTORY | $file_system::MODIFY_PERMISSIONS);

    $uri = $directory . '/' . $filename;
    // Re-encode (not a raw byte copy of $raw) so a save always produces
    // canonically-formatted JSON on disk regardless of what the client sent.
    $pretty = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $file_system->saveData($pretty, $uri, $file_system::EXISTS_RENAME);

    try {
      $file = File::create([
        'uri' => $uri,
        'status' => 1,
        'uid' => 1,
      ]);
      $file->save();

      $media = Media::create([
        'bundle' => 'velxio_project',
        'name' => $name,
        'field_media_velxio_project' => [
          'target_id' => $file->id(),
        ],
        'uid' => 1,
        'status' => 1,
      ]);
      $media->save();
    }
    catch (\Exception $e) {
      return $this->jsonError('Saved the file but failed to create the media entity: ' . $e->getMessage(), 500, $cors_headers);
    }

    $project_file_url = Url::fromRoute('velxio_embed.project_file', ['media' => $media->id()])
      ->setAbsolute()
      ->toString();
    $edit_url = Url::fromRoute('entity.media.edit_form', ['media' => $media->id()])
      ->setAbsolute()
      ->toString();

    $response = new JsonResponse([
      'media_id' => (int) $media->id(),
      'name' => $media->label(),
      'project_file_url' => $project_file_url,
      'edit_url' => $edit_url,
    ], 201);
    foreach ($cors_headers as $header_name => $value) {
      $response->headers->set($header_name, $value);
    }
    return $response;
  }

  /**
   * Builds the CORS response headers for an allowed origin, or none.
   *
   * Deliberately never falls back to '*': this endpoint accepts a bearer
   * token in a custom header and writes content, so unlike
   * ProjectFileController's public read-only file serve, the origin
   * allowlist here is a real part of the access story, not just
   * convenience -- an unlisted origin gets no CORS headers at all, which
   * makes the browser block the response client-side even if the token
   * happened to be valid.
   */
  private function corsHeaders(string $origin, array $allowed_origins): array {
    if ($origin === '' || !in_array($origin, $allowed_origins, TRUE)) {
      return [];
    }
    return [
      'Access-Control-Allow-Origin' => $origin,
      'Access-Control-Allow-Methods' => 'POST, OPTIONS',
      'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
      'Access-Control-Max-Age' => '600',
      'Vary' => 'Origin',
    ];
  }

  /**
   * Builds a JSON error response carrying the same CORS headers.
   */
  private function jsonError(string $message, int $status, array $cors_headers): JsonResponse {
    $response = new JsonResponse(['error' => $message], $status);
    foreach ($cors_headers as $name => $value) {
      $response->headers->set($name, $value);
    }
    return $response;
  }

}
