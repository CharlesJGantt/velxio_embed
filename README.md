# Velxio Embed

A Drupal 10/11 module that embeds live, interactive [Velxio](https://github.com/davidmonterocrespo24/velxio) circuit simulators (Arduino, ESP32, Raspberry Pi Pico and more) into your content.

- A **CKEditor 5 toolbar button** ("Velxio Circuit") to drop a simulator into any article.
- A **text filter** that turns the editor placeholder into a sandboxed `<iframe>` at render time.
- A **"Velxio Project" media type** for storing `.vlx` circuit files, so an article can embed a specific pre-built circuit.
- A **Save to Media Library endpoint** so you can build a circuit in the Velxio editor and push it straight into Drupal.
- An optional **full-page sandbox** (`/velxio-lab`) with the complete Velxio editor.

> **Important: you need a Velxio instance that supports embedding.** This module talks to a self-hosted Velxio server. The embed view (`/embed`) and the "Save to Media Library" menu item are not in upstream Velxio. They come from the **[TMWB-Velxio fork](https://github.com/CharlesJGantt/TMWB-Velxio)**, which is what this module is designed for. Upstream Velxio on its own will not work for embeds.

This module is a separate program from Velxio. It only talks to it over HTTP (an iframe and one JSON endpoint). Velxio itself is AGPLv3, and if you run a modified copy publicly you must offer its source (the fork above already does).

## Requirements

- Drupal 10.x or 11.x
- Core modules: Filter, CKEditor 5, File, Image, Media, Path (declared as dependencies, so they are enabled automatically)
- A running Velxio instance from the fork above, served over **HTTPS** if your Drupal site is HTTPS (browsers block mixed content)
- Optional: the [Entity Usage](https://www.drupal.org/project/entity_usage) module. If installed, the module tracks which content embeds which Velxio Project media.

## Installation

### With Composer

This module is not on Packagist or drupal.org yet. Add this repository as a VCS source, then require it:

```bash
composer config repositories.velxio_embed vcs https://github.com/CharlesJGantt/velxio_embed
composer require charlesjgantt/velxio_embed:dev-main
drush en velxio_embed -y
```

### Manually

1. Download or clone this repository.
2. Copy the folder into `web/modules/custom/` and name it `velxio_embed`.
3. Enable the module at **Extend** (`/admin/modules`) or with `drush en velxio_embed -y`.

The compiled CKEditor plugin (`js/build/velxioEmbed.js`) is committed, so you do **not** need Node.js unless you want to change the plugin.

## Configuration

### 1. Point the module at your Velxio instance

Go to `/admin/config/content/velxio` (Velxio Embed settings):

- **Velxio instance base URL**: where your Velxio fork is hosted, for example `https://velxio.example.com` (no trailing slash). The installed default is only a placeholder.
- **Default embed height**: pixel height used when an embed does not set its own (default 600).

### 2. Enable the editor button on a text format

1. Go to **Configuration > Content authoring > Text formats and editors** (`/admin/config/content/formats`).
2. Edit a format that uses CKEditor 5 (for example Full HTML).
3. Drag the **Velxio Circuit** button into the active toolbar.
4. Under **Enabled filters**, check **Velxio Circuit Embed**.
5. Save the format.

If the filter is not enabled, the placeholder is shown as an empty box instead of a simulator.

## Using it

### Embed a simulator in an article

1. Edit content and click the **Velxio Circuit** button in the toolbar.
2. Pick one of two options:
   - **Existing project**: choose a saved circuit (a "Velxio Project" media item). Readers get exactly that circuit.
   - **Blank project**: leave the project empty and pick a board. Readers get an empty canvas with that board to experiment on.
3. Click **Insert**. A placeholder card appears in the editor. The live simulator only runs on the rendered page, never inside CKEditor.

### Create Velxio Project media

There are two ways to get a circuit into the media library:

- **By hand:** build your circuit in Velxio, export a `.vlx` file, then go to **Content > Media > Add media > Velxio Project** and upload it.
- **Straight from the editor:** use the Save to Media Library feature described next.

### Save to Media Library (push from Velxio to Drupal)

1. Go to `/admin/config/content/velxio`, open **Save to Media Library**, and click **Generate new token**.
2. Copy the token right away. It is shown once and only its hash is stored. Generating a new token invalidates the old one.
3. In **Allowed origins (CORS)**, add the origin of your Velxio instance, one per line, scheme + host + port with no path (for example `https://velxio.example.com`). Leave it empty to refuse all browser calls. For local development you can add `http://localhost:5173`.
4. In the Velxio editor, open the **File** menu and choose **Save to Media Library**. The first time, it asks for your Drupal site URL and the token. These are kept in that browser's local storage only.

Each save creates a new Velxio Project media item and returns its edit link. The endpoint is `POST /api/velxio/save-project` with an `Authorization: Bearer <token>` header and a JSON body in the `.vlx` export format (`"format": "velxio-project"`).

### Full-page sandbox

`/velxio-lab` renders the complete Velxio editor full screen. Link to it from a menu if you want a free-play page. It is open to everyone, so restrict it with your own access rules if you need to.

## Security notes

- The embed iframe uses the `sandbox` attribute with a limited set of permissions.
- Placeholder attributes are validated: the project value must be a valid URL, and the board value is reduced to letters, digits and hyphens.
- The Save endpoint is protected by the bearer token and a CORS origin allowlist. It never falls back to `*`. Use HTTPS so the token is not sent in clear text.
- Saved projects are owned by user 1 (the site admin account).
- Project files are served through `/velxio-project/{media}/file` with `Access-Control-Allow-Origin: *` so the Velxio instance (a different origin) can fetch them. Access follows the media item's own view permission. Do not put anything secret in a circuit file.
- No secrets are shipped with this module. The token hash is stored in your site's active configuration, and the installed default is empty.

## Theming

- Embed template: `templates/velxio-embed.html.twig` (variables `iframe_src`, `height`).
- Sandbox template: `templates/velxio-sandbox-page.html.twig` (variable `iframe_src`).
- Styles: `css/velxio-embed.css`, attached through the `velxio_embed/embed` library. The sandbox page deliberately takes over the whole viewport with `!important`. Override it in your theme if you need a header to remain visible.

## Developing the CKEditor 5 plugin

The source is in `js/ckeditor5_plugins/velxioEmbed/src/`. After changing it, rebuild the committed bundle:

```bash
npm install
npm run build
```

| File | Purpose |
|---|---|
| `index.js` | Plugin entry point |
| `velxioembed.js` | Toolbar button and dialog |
| `velxioembedediting.js` | Schema and upcast/downcast converters |
| `insertvelxioembedcommand.js` | Inserts the placeholder |

## Module layout

```
velxio_embed/
├── config/install/        Default settings + the "Velxio Project" media type
├── config/schema/
├── css/
├── js/build/              Compiled CKEditor 5 plugin (committed)
├── js/ckeditor5_plugins/  Plugin source
├── src/
│   ├── Controller/        Sandbox page, project file serve, save endpoint
│   ├── EventSubscriber/   CORS preflight for the save endpoint
│   ├── Form/              Settings form, editor dialog
│   └── Plugin/            Text filter, Entity Usage tracker
├── templates/
└── velxio_embed.*.yml
```

## Troubleshooting

- **Empty box instead of a simulator:** the **Velxio Circuit Embed** filter is not enabled on that text format.
- **Embed shows a blank or error frame:** check the base URL setting, and that the Velxio instance is reachable from visitors' browsers (a `localhost` Velxio cannot be seen by the public).
- **An embedded project will not load:** the Velxio instance must be able to fetch your site's `/velxio-project/{id}/file` URL, so your Drupal site must be publicly reachable too.
- **Save to Media Library fails in the browser:** make sure the Velxio origin is in **Allowed origins** and the token is current. A 401 means a wrong or replaced token, a 503 means no token has been generated yet.
- **Changed the base URL but old pages still use the old one:** clear caches (`drush cr`).

## Credits and license

- [Velxio](https://github.com/davidmonterocrespo24/velxio) by David Montero Crespo (AGPLv3). This module is independent and not affiliated with or endorsed by the Velxio author.
- Module code is licensed **GPL-2.0-or-later** (see `LICENSE.txt`), the standard license for Drupal modules.
