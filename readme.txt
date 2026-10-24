=== Front Office Pilot Chat ===
Contributors: frontofficepilot
Tags: chat, live chat, chat widget, ai assistant, customer service
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds your Front Office Pilot chat assistant to your WordPress site.

== Description ==

Front Office Pilot Chat adds the Front Office Pilot chat widget to every page of your site. Paste your site key and the plugin loads the hosted widget script. There is nothing else to configure in WordPress.

* One setting: your site key.
* Loads one script from chat.frontofficepilot.com, asynchronously, in the footer.
* Shows whether chat is live on your site, and links to your dashboard if your site address still needs adding.
* No tracking, no extra database tables, no AJAX or REST endpoints.

= External service =

This plugin connects to Front Office Pilot (https://frontofficepilot.com/), a hosted service.

* On every front-end page, visitors' browsers load the widget script from https://chat.frontofficepilot.com/widget/v1/fop-chat.js. The script reads your site key and, when a visitor chats, sends their messages to the chat assistant along with the current page path, the first page path of the visit and Google Analytics cookie values (`_ga`, `_ga_<stream>`) when present.
* When an administrator opens Settings, Front Office Pilot Chat, WordPress makes one request from your server to https://chat.frontofficepilot.com/widget/v1/config with your site key and your site address, to show whether chat is live.

Terms: https://frontofficepilot.com/terms-of-service/ . Privacy: https://frontofficepilot.com/privacy-policy/ .

== Installation ==

1. Upload the plugin zip under Plugins, Add New, Upload Plugin, and activate it.
2. Go to Settings, Front Office Pilot Chat.
3. Paste your site key (it starts with `fop_pk_`) and save.
4. If the page says to add your site, open your Front Office Pilot dashboard under Chat widget and add your site address (for example `https://www.example.com`).

== Frequently Asked Questions ==

= Where do I find my site key? =

In your Front Office Pilot dashboard under Chat widget.

= I saved my key but the widget does not show =

Your site address must be added in your Front Office Pilot dashboard under Chat widget. Addresses are exact: `https://example.com` and `https://www.example.com` are added separately, and sites on `http://` or with a port number are not supported.

= Is the site key secret? =

No. It is a publishable key and appears in your page HTML. It cannot change your settings.

== Changelog ==

= 1.0.0 =
* First release.
