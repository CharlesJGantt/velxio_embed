<?php

namespace Drupal\velxio_embed\Plugin\EntityUsage\Track;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_usage\Attribute\EntityUsageTrack;
use Drupal\entity_usage\Plugin\EntityUsage\Track\TextFieldEmbedBase;

/**
 * Tracks usage of Velxio Project media items embedded via the toolbar button.
 *
 * The CKEditor plugin inserts a <div class="velxio-embed-wrapper"
 * data-velxio-project="<absolute project_file URL>"> placeholder (see
 * VelxioEmbedFilter, which turns it into the real iframe at render time).
 * That URL is not a normal Drupal entity link or file URI -- it's a custom
 * controller route (velxio_embed.project_file) -- so entity_usage's built-in
 * "HTML links" tracker never recognizes it. This plugin fills that gap so
 * "where is this circuit embedded" is actually answerable from the media
 * entity's Usage tab instead of only discoverable by grepping node bodies.
 */
#[EntityUsageTrack(
  id: 'velxio_embed',
  label: new TranslatableMarkup('Velxio circuit embeds'),
  description: new TranslatableMarkup('Tracks usage of Velxio Project media items embedded via the Velxio Circuit toolbar button.'),
  field_types: ["text", "text_long", "text_with_summary"],
  source_entity_class: FieldableEntityInterface::class,
)]
class VelxioEmbedTrack extends TextFieldEmbedBase {

  /**
   * {@inheritdoc}
   */
  public function parseEntitiesFromText($text) {
    $entities = [];
    if (strpos($text, 'velxio-embed-wrapper') === FALSE) {
      return $entities;
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    foreach ($xpath->query('//div[contains(@class, "velxio-embed-wrapper")]') as $element) {
      /** @var \DOMElement $element */
      $project_url = $element->getAttribute('data-velxio-project');
      if ($project_url === '' || !preg_match('#/velxio-project/(\d+)/file#', $project_url, $matches)) {
        continue;
      }

      $media_id = (int) $matches[1];
      $media = $this->entityTypeManager->getStorage('media')->load($media_id);
      if (!$media || $media->bundle() !== 'velxio_project') {
        // Entity does not exist (or isn't the bundle we expect) -- text
        // fields aren't automatically updated when a referenced entity is
        // removed, so a stale/broken reference shouldn't be tracked as real.
        continue;
      }

      $entities[$media->uuid()] = self::VALID_ENTITY_ID_PREFIX . 'media|' . $media_id;
    }

    return $entities;
  }

}
