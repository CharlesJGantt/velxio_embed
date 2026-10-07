<?php

namespace Drupal\velxio_embed\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Url;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Renders embedded Velxio circuits from CKEditor placeholders.
 *
 * The CKEditor plugin inserts a plain <div data-velxio-project data-velxio-
 * board data-velxio-height> placeholder (no iframe -- CKEditor never
 * executes arbitrary embedded apps inside the editor). This filter runs at
 * render time and turns that placeholder into the real iframe pointed at
 * the self-hosted Velxio instance's /embed route.
 *
 * @Filter(
 *   id = "velxio_embed",
 *   title = @Translation("Velxio Circuit Embed"),
 *   description = @Translation("Renders embedded Velxio circuits inserted via the CKEditor plugin."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 10
 * )
 */
class VelxioEmbedFilter extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $result = new FilterProcessResult($text);

    if (strpos($text, 'velxio-embed-wrapper') === FALSE) {
      return $result;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query('//div[contains(@class, "velxio-embed-wrapper")]');

    if ($nodes->length === 0) {
      return $result;
    }

    $config = \Drupal::config('velxio_embed.settings');
    $base_url = rtrim((string) $config->get('base_url'), '/');
    $default_height = (int) ($config->get('default_embed_height') ?: 600);
    // Without this, a cached render of this filter's output (page cache,
    // render cache) keeps pointing at the OLD base_url after the settings
    // form saves a new one, until something else happens to clear it --
    // exactly what made switching the instance between prod and a local
    // dev server during testing look like it silently didn't take effect.
    $result->addCacheableDependency($config);
    // The project file URL is rebuilt below from the current host, so the
    // rendered output differs per site (local, staging, live).
    $result->addCacheContexts(['url.site']);

    foreach ($nodes as $node) {
      $project = $this->normalizeProjectUrl($node->getAttribute('data-velxio-project'));
      $board = $node->getAttribute('data-velxio-board');
      $height_attr = $node->getAttribute('data-velxio-height');
      $height = ctype_digit($height_attr) ? (int) $height_attr : $default_height;

      $iframe_src = $this->buildEmbedUrl($base_url, $project, $board);

      $render = [
        '#theme' => 'velxio_embed',
        '#iframe_src' => $iframe_src,
        '#height' => $height,
      ];

      $embed_html = (string) \Drupal::service('renderer')->renderInIsolation($render);

      // Parse the rendered embed HTML into a temporary DOM document, then
      // import nodes into the main DOM (avoids appendXML XML parsing).
      $embed_dom = Html::load($embed_html);
      $embed_body = $embed_dom->getElementsByTagName('body')->item(0);
      if ($embed_body) {
        foreach ($embed_body->childNodes as $child) {
          $imported = $dom->importNode($child, TRUE);
          $node->parentNode->insertBefore($imported, $node);
        }
      }
      $node->parentNode->removeChild($node);
    }

    $result->setProcessedText(Html::serialize($dom));
    $result->addAttachments([
      'library' => ['velxio_embed/embed'],
    ]);

    return $result;
  }

  /**
   * Rebuilds a stored project-file URL against the current site's host.
   *
   * Placeholders saved while the site ran on another host (for example a
   * local development site) keep that host in data-velxio-project, which the public
   * Lab can't fetch once the site is live. The project is identified by its
   * media ID in the path, so regenerate the absolute URL from the route.
   * Anything that doesn't look like a project-file URL is returned unchanged.
   */
  private function normalizeProjectUrl(string $project): string {
    if (preg_match('#/velxio-project/(\d+)/file/?(?:[?\#].*)?$#', $project, $m)) {
      return Url::fromRoute('velxio_embed.project_file', ['media' => $m[1]], ['absolute' => TRUE])->toString();
    }
    return $project;
  }

  /**
   * Builds the /embed URL for a placeholder's stored attributes.
   *
   * A project URL, if given, wins over a board kind -- matches the
   * frontend's own EmbedPage.tsx precedence (see the Velxio fork).
   */
  private function buildEmbedUrl(string $base_url, string $project, string $board): string {
    $query = [];

    if ($project !== '' && UrlHelper::isValid($project, TRUE)) {
      $query['project'] = Xss::filter($project);
    }
    elseif ($board !== '') {
      // Board kinds are short identifiers (e.g. "arduino-uno", "esp32") --
      // never free text, so strip anything that isn't alphanumeric/hyphen.
      $safe_board = preg_replace('/[^a-z0-9\-]/i', '', $board);
      if ($safe_board !== '') {
        $query['board'] = $safe_board;
      }
    }

    return $base_url . '/embed' . ($query ? ('?' . UrlHelper::buildQuery($query)) : '');
  }

  /**
   * {@inheritdoc}
   */
  public function tips($long = FALSE) {
    return $this->t('Velxio circuits inserted via the toolbar button will be rendered as live interactive simulators.');
  }

}
