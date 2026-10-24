# Front Office Pilot Chat (WordPress plugin)

Adds the Front Office Pilot chat widget to a WordPress site. The site owner pastes a site key; the plugin prints

```html
<script src="https://chat.frontofficepilot.com/widget/v1/fop-chat.js" data-site-key="fop_pk_..." async></script>
```

in the footer of every front-end page. That is the whole plugin: one setting, one script tag, and a status check on
its own settings page.

The plugin slug and text domain are `front-office-pilot-chat` (main file `front-office-pilot-chat.php`); WordPress.org
does not accept "wordpress" in a slug. The GitHub repository keeps the name `fop-wordpress-chat`.

## Install

1. Download `front-office-pilot-chat-<version>.zip` (or build it, see below).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, **Install Now**, then **Activate**.
3. Go to **Settings → Front Office Pilot Chat**.
4. Paste your site key (from your Front Office Pilot dashboard under Chat widget; it looks like `fop_pk_` plus 32
   letters and numbers) and click **Save Changes**.
5. The page now shows one of two notices:
   - **"Chat is live on https://your-site."** You are done.
   - **"Add https://your-site in your Front Office Pilot dashboard under Chat widget."** Open
     <https://app.frontofficepilot.com/chat-widget>, add that exact address, save, and reload the settings page.

To remove the widget, clear the field and save, or deactivate the plugin. Deleting the plugin removes its option
(on multisite, from every site).

## What it sends

Two external services are involved. Nothing is sent until a site key is saved.

**Front Office Pilot (`chat.frontofficepilot.com`)**

- From visitors' browsers, on every front-end page: the widget script loads from
  `https://chat.frontofficepilot.com/widget/v1/fop-chat.js`, then requests `/widget/v1/config` with the **site key** and
  the **page's origin** (the browser's `Origin` header).
- From your WordPress server, only when an administrator opens Settings → Front Office Pilot Chat with a key saved:

  ```
  GET https://chat.frontofficepilot.com/widget/v1/config?k=<site key>
  Origin: <your site origin, from home_url()>
  ```

  It has a 3 second timeout. The answer only decides which notice is shown.
- Terms: <https://frontofficepilot.com/terms-of-service/>. Privacy: <https://frontofficepilot.com/privacy-policy/>.

**Vapi (`api.vapi.ai`)**

- From visitors' browsers only, when a visitor chats: the **chat messages** they type, the **page path** (current path
  and first path of the visit, at most 100 characters each and blanked if they look like an email address or phone
  number) and the Google Analytics cookie IDs **`_ga` and `_ga_<id>`** when they look like GA cookies. Your server
  sends nothing to Vapi.
- Terms: <https://vapi.ai/terms-of-service>. Privacy: <https://vapi.ai/privacy>.

The plugin itself has no tracking or telemetry, stores no other options, and registers no AJAX or REST endpoints.

## Troubleshooting

- **"That is not a valid site key."** The key must be exactly `fop_pk_` followed by 32 letters and numbers
  (case-sensitive). Your previous key is kept.
- **"Add https://... in your Front Office Pilot dashboard"** after you already added it: addresses are exact.
  `https://example.com` and `https://www.example.com` are separate entries; there is no path, no port and no wildcard.
  Check **Settings → General → Site Address (URL)** matches what you added.
- **"The chat widget needs your site on https without a custom port"**: the site address is `http://` or has a port
  (for example `http://localhost:8888`). Such addresses cannot be added in the dashboard, so the widget stays hidden
  there and the plugin skips the status check.
- **The status check failed** (timeout, firewall blocking outbound requests): the page shows the "Add ..." notice.
  The widget on the front end does not depend on this check; open your site in a browser to confirm.
- **No widget and no script tag in the page source:** check the key is saved, and that a caching or optimisation
  plugin is not stripping or delaying the `fop-chat` script. Clear page caches after saving.
- **Widget appears twice?** It cannot: the widget mounts once per page even if the tag is duplicated.

## Testing against a run.app deployment

Before `chat.frontofficepilot.com` is mapped, point the plugin at the Cloud Run URL by adding this to
`wp-config.php` (above "That's all, stop editing!"):

```php
define( 'FOP_CHAT_SCRIPT_URL', 'https://<service>-<hash>.a.run.app/widget/v1/fop-chat.js' );
```

The status check derives its config URL from the script's origin, so this one line moves both. Remove it to go back
to the production URL.

## Development

Requires PHP 7.4+ and Composer. No WordPress install is needed: `tests/bootstrap.php` stubs the WordPress functions the
plugin uses.

```sh
composer install
vendor/bin/phpunit
```

CI (`.github/workflows/test.yml`) runs the suite on PHP 7.4 and 8.3.

Build the release zip (version read from the `front-office-pilot-chat.php` header):

```sh
bin/build-zip.sh   # -> dist/front-office-pilot-chat-<version>.zip
```

The zip has one top-level `front-office-pilot-chat/` folder with `front-office-pilot-chat.php`, `uninstall.php`, `readme.txt` and
`includes/` only.

## Screenshots

Settings page, no key saved:

![Settings, empty](docs/screenshots/settings-empty.png)

Settings page, key saved and the site is live:

![Settings, live](docs/screenshots/settings-live.png)

Settings page, key saved but the site address still needs adding in the dashboard:

![Settings, needs origin](docs/screenshots/settings-needs-origin.png)

The widget on the front end:

![Widget on the front end](docs/screenshots/widget-front-end.png)
