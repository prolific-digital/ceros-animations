# Ceros Animation Plugin for WordPress

Unleash interactive creativity on your website with Ceros Embed. Elevate user engagement and infuse arts, music, and stunning visuals into your brand's digital presence.

# Table of Contents

- [Installation](#installation)
- [Usage](#usage)
- [Documentation](#documentation)
- [Support](#support)
- [Contributing](#contributing)
- [License](#license)

---

## Requirements

| | |
|---|---|
| WordPress | 6.5+ |
| PHP | 8.4+ |
| Text domain | `ceros-embed` |
| License | GPL-2.0-or-later |

## Installation

1. Download `ceros-embed.zip` from a [GitHub release](https://github.com/prolific-digital/ceros-animations/releases).
2. Log in to your WordPress dashboard.
3. Navigate to **Plugins** > **Add New**.
4. Click on the **Upload Plugin** button.
5. Choose the downloaded zip file and click **Install Now**.
6. After installation, click **Activate** to activate the Ceros Embed plugin.

### Updates

Updates are served by the Prolific Digital license server. Add the site's license key to `wp-config.php`:

```php
define( 'PROLIFIC_LICENSE_KEY_CEROS_EMBED', 'your-key' );
```

You can also supply the key with the `prolific_license_key` filter, which receives `( $key, 'ceros-embed' )`.

## Usage

When adding your Ceros animation, upload a .zip file containing the Ceros project assets. Make sure that your files are at the root of the zipped folder. The plugin will handle the rest!

Please note that the plugin accepts .zip files. You can upload your project using and get the shortcode to embed it into any post, page, or custom post type.

Uploading requires the `upload_files` capability (Authors and above) and the PHP zip extension. Only static file types are extracted (HTML, JS, CSS, JSON, images, fonts, audio/video including HLS `.ts`/`.m3u8`). A zip containing anything else, such as PHP files, hidden files like `.htaccess`, or `..` paths, is rejected with an admin notice. Symlinks, extensionless files and macOS metadata are skipped. Adjust the limits with the `ceros_embed_allowed_extensions`, `ceros_embed_max_files` and `ceros_embed_max_uncompressed_bytes` filters.

To embed a Ceros project, use the following shortcode:

```
[ceros-animation id="THE_POST_ID" type="TYPE" title="YOUR_TITLE"]
```

Replace `THE_POST_ID` with the unique ID of your Ceros embed, `TYPE` with either "scrolling" or "full" (for different display types), and `YOUR_TITLE` with the title for screen readers.

## Documentation

For detailed information and guidelines, you can visit our [plugin documentation](https://prolificdigital.notion.site/Ceros-WordPress-Plugin-2165805d1e9f4fd5ad58ab64b5a5d473?pvs=4).

## Development

`vendor/` is not committed. The plugin's own classes load through Composer's autoloader, so a git checkout needs `composer install` before the plugin can be activated:

```sh
ddev exec -d /var/www/html/wp-content/plugins/ceros-animations composer install
ddev exec -d /var/www/html/wp-content/plugins/ceros-animations composer lint
ddev exec -d /var/www/html/wp-content/plugins/ceros-animations composer phpcs
```

To release: bump the version in the plugin header, `CEROS_EMBED_VERSION` and `readme.txt` `Stable tag`; add a `## [x.y.z] - YYYY-MM-DD` section to `CHANGELOG.md`; merge to `main` and push a `vx.y.z` tag. The shared release workflow runs `composer install --no-dev`, builds `ceros-embed.zip` and attaches it to a GitHub Release.

## Support

For support, feedback, or feature requests, please [submit an issue](https://github.com/prolific-digital/ceros-embed/issues) on GitHub.

Let your website come alive with the immersive power of Ceros Embed!

## Contributing

We welcome contributions to improve the Ceros Embed plugin. To contribute:

1. Fork the project.
2. Make your feature addition or bug fix.
3. Send us a pull request with a brief description of your changes.

> Before sending a pull request for a feature addition, please discuss it with us first. We want to ensure that changes align with the project's direction and goals.

## License

This project is licensed under GPL-2.0-or-later. See `LICENSE`.

---

_Proudly crafted by Prolific Digital_
