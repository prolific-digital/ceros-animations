=== Ceros Embed ===
Contributors: prolificdigital
Tags: ceros, embed, shortcode, interactive
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-host Ceros interactive experiences: upload a Ceros export as a zip and embed it with a shortcode.

== Description ==

Adds a **Ceros** post type. Upload a zipped Ceros export on a Ceros post; the plugin unpacks it into `wp-content/uploads/ceros/` and shows a ready-made shortcode:

`[ceros-animation id="POST_ID" type="scrolling" title="Title for screen readers"]`

`type` is `scrolling` (16:9 frame) or `full` (full-height frame). The title is used as the iframe's accessible name.

See the [plugin documentation](https://prolificdigital.notion.site/Ceros-WordPress-Plugin-2165805d1e9f4fd5ad58ab64b5a5d473?pvs=4).

== Installation ==

1. Upload the release zip from **Plugins > Add New > Upload Plugin** and activate it.
2. Add a Ceros post, upload the export zip, and paste the shortcode where you want it.
   Uploading needs the "unfiltered HTML" capability: Administrators and Editors on single sites, super admins only on multisite. Change it with the `ceros_embed_upload_capability` filter.
3. To receive automatic updates, add your license key to `wp-config.php`:
   `define( 'PROLIFIC_LICENSE_KEY_CEROS_EMBED', 'your-key' );`

== Changelog ==

= 1.1.0 =
* Updates come from the Prolific Digital license server. Define `PROLIFIC_LICENSE_KEY_CEROS_EMBED` to receive them.
* Main file renamed from `index.php` to `ceros-embed.php`; re-activate the plugin after updating.
* Post type labels are translatable under the `ceros-embed` text domain.
* License is now GPL-2.0-or-later. Requires WordPress 6.5+ and PHP 8.4+.
* Security: the `[ceros-animation]` shortcode escapes its `title` attribute and the site URL (stored XSS by contributors).
* Security: the Ceros meta box escapes the stored file URL and post title, and the file URL is sanitized on save.
* Security: uploading a Ceros zip requires the `upload_files` capability, and zips are checked entry by entry: only allowlisted static file types are extracted, and zips with scripts, hidden files, `..` paths or excessive size are rejected. `uploads/ceros/` gets an `.htaccess` that blocks PHP on Apache. Requires the PHP zip extension.
* Security: uploading a Ceros zip requires the `unfiltered_html` capability (Administrators and Editors on single sites; super admins only on multisite). Change it with the `ceros_embed_upload_capability` filter.

= 1.0.0 =
* Initial release.
