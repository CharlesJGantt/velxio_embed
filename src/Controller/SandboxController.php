<?php

namespace Drupal\velxio_embed\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Renders the standalone Velxio Lab sandbox page.
 *
 * Unlike the article embed (VelxioEmbedFilter), this is the full Velxio
 * editor -- same app, no chrome stripped -- used as a free-play sandbox.
 */
class SandboxController extends ControllerBase {

  /**
   * Builds the sandbox page render array.
   */
  public function build() {
    $base_url = rtrim($this->config('velxio_embed.settings')->get('base_url'), '/');

    return [
      '#theme' => 'velxio_embed_sandbox_page',
      '#iframe_src' => $base_url . '/editor',
      '#attached' => [
        'library' => ['velxio_embed/embed'],
      ],
    ];
  }

}
