/**
 * @file Defines the editing behavior for the Velxio Circuit plugin.
 */

import { Plugin } from 'ckeditor5/src/core';
import { Widget, toWidget } from 'ckeditor5/src/widget';
import InsertVelxioEmbedCommand from './insertvelxioembedcommand';

export default class VelxioEmbedEditing extends Plugin {
  static get requires() {
    return [Widget];
  }

  init() {
    this._defineSchema();
    this._defineConverters();

    this.editor.commands.add(
      'insertVelxioEmbed',
      new InsertVelxioEmbedCommand(this.editor)
    );
  }

  _defineSchema() {
    const schema = this.editor.model.schema;

    schema.register('velxioEmbed', {
      isObject: true,
      allowWhere: '$block',
      isBlock: true,
      allowAttributes: [
        'data-velxio-project',
        'data-velxio-board',
        'data-velxio-height',
      ],
    });
  }

  _defineConverters() {
    const { conversion } = this.editor;

    // Upcast: HTML -> Model.
    conversion.for('upcast').elementToElement({
      view: {
        name: 'div',
        classes: ['velxio-embed-wrapper'],
      },
      model: (viewElement, { writer }) => {
        return writer.createElement('velxioEmbed', {
          'data-velxio-project': viewElement.getAttribute('data-velxio-project') || '',
          'data-velxio-board': viewElement.getAttribute('data-velxio-board') || '',
          'data-velxio-height': viewElement.getAttribute('data-velxio-height') || '',
        });
      },
    });

    // Data Downcast: Model -> saved HTML.
    conversion.for('dataDowncast').elementToElement({
      model: 'velxioEmbed',
      view: (modelElement, { writer }) => {
        return writer.createContainerElement('div', {
          class: 'velxio-embed-wrapper',
          'data-velxio-project': modelElement.getAttribute('data-velxio-project') || '',
          'data-velxio-board': modelElement.getAttribute('data-velxio-board') || '',
          'data-velxio-height': modelElement.getAttribute('data-velxio-height') || '',
        });
      },
    });

    // Editing Downcast: Model -> editor display. The real simulator never
    // renders inside the editor itself -- just a labeled placeholder, same
    // approach as the Cults3D card widget.
    conversion.for('editingDowncast').elementToElement({
      model: 'velxioEmbed',
      view: (modelElement, { writer }) => {
        const project = modelElement.getAttribute('data-velxio-project') || '';
        const board = modelElement.getAttribute('data-velxio-board') || '';
        const summary = project
          ? `Velxio circuit: ${project}`
          : `Velxio circuit: blank ${board || 'arduino-uno'} project`;

        const container = writer.createContainerElement('div', {
          class: 'velxio-embed-wrapper velxio-embed-editor',
        });

        const label = writer.createContainerElement('div', {
          class: 'velxio-card-editor-label',
        });

        writer.insert(writer.createPositionAt(label, 0), writer.createText(summary));
        writer.insert(writer.createPositionAt(container, 0), label);

        return toWidget(container, writer, {
          label: 'Velxio Circuit',
        });
      },
    });
  }
}
