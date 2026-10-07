<?php

namespace Drupal\velxio_embed\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a "Velxio Project" media item's .vlx file with CORS headers.
 *
 * The embed that fetches this file (loadProjectFromUrl.ts) runs on the
 * self-hosted Velxio instance's own origin (e.g. velxio.example.com),
 * a different origin than this Drupal site -- a plain link to the file under
 * sites/default/files would be blocked by the browser's CORS check, since
 * Drupal's default file serving sends no Access-Control-Allow-Origin header.
 * Routing through this controller instead lets us add it explicitly.
 */
class ProjectFileController extends ControllerBase {

  /**
   * Streams the media's underlying file, with CORS enabled.
   */
  public function serve(MediaInterface $media): Response {
    if ($media->bundle() !== 'velxio_project') {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $source_field = $media->getSource()->getConfiguration()['source_field'] ?? 'field_media_velxio_project';
    $file = $media->get($source_field)->entity;
    if (!$file instanceof FileInterface) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    $response = new BinaryFileResponse($file->getFileUri());
    $response->headers->set('Content-Type', 'application/json');
    $response->headers->set('Access-Control-Allow-Origin', '*');
    // Project files rarely change once embedded in a published article;
    // a short cache keeps repeat embed loads cheap without going stale for
    // long if an author does re-upload one.
    $response->setPublic();
    $response->setMaxAge(3600);
    return $response;
  }

}
