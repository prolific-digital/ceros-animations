# Changelog

All notable changes to the Ceros Embed plugin (formerly "Ceros Animations") are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-09-29

### Added
- Automatic updates from the Prolific Digital license server (`api.prolificdigital.io`). Set `PROLIFIC_LICENSE_KEY_CEROS_EMBED` in `wp-config.php` (or use the `prolific_license_key` filter). plugin-update-checker ^5.7 is a Composer dependency.
- `CEROS_EMBED_VERSION` constant; translations load from `languages/`.
- `readme.txt`, coding-standards config and CI/release workflows.

### Changed
- Main plugin file renamed from `index.php` to `ceros-embed.php`. Re-activate the plugin after updating.
- `vendor/` (Composer's autoloader for the plugin's own `CerosEmbed\` classes) is no longer committed; release zips build it with `composer install --no-dev`.
- The Ceros post type labels use the `ceros-embed` text domain (they used the placeholder `your-plugin-textdomain`).
- Every PHP file exits when loaded outside WordPress (ABSPATH guard).
- License changed from GPL-3.0 to GPL-2.0-or-later (`LICENSE` now holds the GPL-2.0 text).
- Requires WordPress 6.5+ and PHP 8.4+.

### Fixed
- A zip whose files sit at its root (no top-level folder) now extracts into the export folder; previously the stored URL pointed to a folder that was never created. Each upload uses its own temporary folder, which is always removed.

### Security
- `[ceros-animation]` escapes its `title` attribute and the site URL. A contributor could previously break out of the iframe's `title` attribute and inject script into any page that renders the shortcode (stored XSS).
- The Ceros meta box escapes the stored file URL and the post title it prints (a contributor-written title could break out of the shortcode field). The uploaded file URL is sanitized with `esc_url_raw()` before it is saved, the nonce is unslashed and sanitized before verification, and a save without an upload field no longer reads an undefined `$_FILES` entry.
- Ceros zip uploads are validated before anything is written. Uploading now requires the `upload_files` capability as well as `edit_post` (contributors could previously extract a zip into the public uploads folder); the user gets an admin notice instead. Each entry is checked with `ZipArchive`: the whole zip is rejected if any file type is outside the allowlist (html, htm, js, css, json, map, txt, xml, png, jpg, jpeg, gif, webp, svg, ico, woff, woff2, ttf, otf, eot, mp4, webm, mp3, wav, ts, m3u8; filter `ceros_embed_allowed_extensions`), has a script-like inner extension (`x.php.png`), is a hidden file or folder (`.htaccess`, `.user.ini`), or has an absolute or `..` path. Symlinks, extensionless files and `__MACOSX/`, `.DS_Store` and `Thumbs.db` entries are skipped. Zips over 10,000 entries or 512 MB uncompressed are rejected (filters `ceros_embed_max_files`, `ceros_embed_max_uncompressed_bytes`), and extraction enforces the byte limit while streaming. `uploads/ceros/` gets an `.htaccess` that disables PHP and denies script files on Apache, plus an `index.php` silence file. The uploaded file must have a `.zip` extension. The PHP zip extension is now required.

## [1.0.0] - 2023-08-07

### Added
- Initial release of the Ceros Animation Plugin for WordPress.
- Two display types: "scrolling" and "full" for flexible content presentation.
- Intuitive meta box for Ceros Custom Post Type management.
- Upload .zip files containing Ceros project assets for easy integration.
- Comprehensive documentation to guide users in setting up and using the plugin.
- Support for screen readers with customizable title attributes for enhanced accessibility.
- Seamless integration with WordPress, ensuring smooth installation and activation.
- Embed Ceros projects into posts, pages, or custom post types using the provided shortcode.

[Unreleased]: https://github.com/prolific-digital/ceros-animations/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/prolific-digital/ceros-animations/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/prolific-digital/ceros-animations/releases/tag/v1.0.0
