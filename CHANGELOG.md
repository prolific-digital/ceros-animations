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

### Security
- `[ceros-animation]` escapes its `title` attribute and the site URL. A contributor could previously break out of the iframe's `title` attribute and inject script into any page that renders the shortcode (stored XSS).
- The Ceros meta box escapes the stored file URL and the post title it prints (a contributor-written title could break out of the shortcode field). The uploaded file URL is sanitized with `esc_url_raw()` before it is saved, the nonce is unslashed and sanitized before verification, and a save without an upload field no longer reads an undefined `$_FILES` entry.

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
