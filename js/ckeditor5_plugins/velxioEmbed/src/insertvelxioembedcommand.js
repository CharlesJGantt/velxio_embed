/**
 * @file Defines InsertVelxioEmbedCommand, which is executed when the
 * toolbar button is pressed.
 */

import { Command } from 'ckeditor5/src/core';

export default class InsertVelxioEmbedCommand extends Command {
  execute() {
    const editor = this.editor;
    const { model } = editor;

    // Drupal.ckeditor5.openDialog (core/ckeditor5.js) renders
    // VelxioEmbedDialogForm in a modal -- the dropdown of existing
    // "Velxio Project" media items, plus a board picker for a blank
    // project. On submit the form's ajaxSubmit() fires an EditorDialogSave
    // command, which core's editor.dialog.js turns into an
    // `editor:dialogsave` event that invokes this saveCallback.
    Drupal.ckeditor5.openDialog(
      Drupal.url('admin/config/content/velxio/embed-dialog'),
      (values) => {
        const project = (values.project || '').trim();
        const board = (values.board || '').trim();

        model.change((writer) => {
          const embedElement = writer.createElement('velxioEmbed', {
            'data-velxio-project': project,
            'data-velxio-board': board,
            'data-velxio-height': '',
          });

          model.insertContent(embedElement);
        });
      },
      { title: Drupal.t('Insert Velxio Circuit') }
    );
  }

  refresh() {
    const { model } = this.editor;
    const { selection } = model.document;

    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      'velxioEmbed'
    );

    this.isEnabled = allowedIn !== null;
  }
}
