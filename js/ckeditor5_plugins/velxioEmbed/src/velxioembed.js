/**
 * @file Registers the Velxio Circuit toolbar button and binds functionality.
 */

import { Plugin } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';
import VelxioEmbedEditing from './velxioembedediting';

// Inline SVG string, not a raw-loader `.svg` import -- the latter doesn't
// render correctly in this build setup (same issue and same fix as the
// site's printables_embed module, which switched to an inline string for
// the same reason: "to ensure it displays correctly").
const velxioIcon =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20">' +
  '<rect x="5" y="5" width="10" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.4"/>' +
  '<rect x="8" y="8" width="4" height="4" fill="none" stroke="currentColor" stroke-width="1.2"/>' +
  '<path d="M8 1v4M12 1v4M8 15v4M12 15v4M1 8h4M1 12h4M15 8h4M15 12h4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>' +
  '</svg>';

export default class VelxioEmbed extends Plugin {
  static get requires() {
    return [VelxioEmbedEditing];
  }

  init() {
    const editor = this.editor;

    editor.ui.componentFactory.add('VelxioEmbed', (locale) => {
      const command = editor.commands.get('insertVelxioEmbed');
      const buttonView = new ButtonView(locale);

      buttonView.set({
        label: editor.t('Insert Velxio Circuit'),
        icon: velxioIcon,
        tooltip: true,
      });

      buttonView.bind('isOn', 'isEnabled').to(command, 'value', 'isEnabled');

      this.listenTo(buttonView, 'execute', () => {
        editor.execute('insertVelxioEmbed');
      });

      return buttonView;
    });
  }
}
