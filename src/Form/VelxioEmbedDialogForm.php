<?php

namespace Drupal\velxio_embed\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\editor\Ajax\EditorDialogSave;

/**
 * The CKEditor toolbar button's "insert a circuit" dialog.
 *
 * Opened via Drupal.ckeditor5.openDialog() (see insertvelxioembedcommand.js),
 * which renders this form in a modal and, on submit, expects an
 * EditorDialogSave AJAX command carrying the values the JS save callback
 * will read -- the same mechanism core's own drupallink/drupalmedialibrary
 * CKEditor5 plugins use for their own dialogs.
 */
class VelxioEmbedDialogForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'velxio_embed_dialog_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<div id="velxio-embed-dialog-wrapper">';
    $form['#suffix'] = '</div>';

    $project_options = ['' => $this->t('-- Blank project (choose a board below) --')];
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $ids = $storage->getQuery()
      ->condition('bundle', 'velxio_project')
      ->accessCheck(TRUE)
      ->sort('name')
      ->execute();
    foreach ($storage->loadMultiple($ids) as $media) {
      $project_options[$media->id()] = $media->label();
    }

    $form['project_media_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Existing project'),
      '#options' => $project_options,
      '#default_value' => '',
      '#description' => $this->t('Projects come from Content > Media > Add media > Velxio Project.'),
    ];

    $form['board'] = [
      '#type' => 'select',
      '#title' => $this->t('Board'),
      '#options' => $this->boardOptions(),
      '#default_value' => 'arduino-uno',
      '#description' => $this->t('Used only for a blank project (no existing project selected above).'),
      '#states' => [
        'visible' => [
          ':input[name="project_media_id"]' => ['value' => ''],
        ],
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Insert'),
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Everything happens in ajaxSubmit() -- this form is only ever reached
    // through CKEditor's AJAX modal, never a plain non-JS submission.
  }

  /**
   * AJAX submit handler: hands the picked project/board back to CKEditor.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    $media_id = $form_state->getValue('project_media_id');
    $project_url = '';
    $board = '';

    if ($media_id) {
      $media = \Drupal::entityTypeManager()->getStorage('media')->load($media_id);
      if ($media && $media->bundle() === 'velxio_project') {
        $project_url = Url::fromRoute('velxio_embed.project_file', ['media' => $media_id])
          ->setAbsolute()
          ->toString();
      }
    }
    else {
      $board = (string) $form_state->getValue('board');
    }

    $response->addCommand(new EditorDialogSave(['project' => $project_url, 'board' => $board]));
    $response->addCommand(new CloseModalDialogCommand());
    return $response;
  }

  /**
   * Board choices for a blank project.
   *
   * A short list of common boards. The Velxio fork's BoardKind type
   * (frontend/src/types/board.ts) has the full supported list if more are
   * needed here.
   */
  protected function boardOptions(): array {
    return [
      'arduino-uno' => $this->t('Arduino Uno'),
      'arduino-nano' => $this->t('Arduino Nano'),
      'arduino-mega' => $this->t('Arduino Mega'),
      'esp32' => $this->t('ESP32'),
      'esp32-s3' => $this->t('ESP32-S3'),
      'esp32-c3' => $this->t('ESP32-C3'),
      'raspberry-pi-pico' => $this->t('Raspberry Pi Pico'),
      'attiny85' => $this->t('ATtiny85'),
    ];
  }

}
