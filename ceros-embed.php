<?php
/**
 * Plugin Name:       Ceros Embed
 * Plugin URI:        https://prolificdigital.com
 * Description:       Self-host Ceros interactive experiences: upload a Ceros export as a zip and embed it with a shortcode.
 * Version:           1.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Author:            Prolific Digital
 * Author URI:        https://prolificdigital.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceros-embed
 * Domain Path:       /languages
 * Update URI:        https://api.prolificdigital.io/api/update?plugin=ceros-embed
 *
 * @package CerosEmbed
 */

defined( 'ABSPATH' ) || exit;

define( 'CEROS_EMBED_VERSION', '1.1.0' );
define( 'CEROS_EMBED_PLUGIN_FILE', __FILE__ );
define( 'CEROS_EMBED_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Include Composer's autoloader (the plugin's own CerosEmbed\ classes and
// plugin-update-checker). Built into release zips; in a git checkout run
// `composer install` first.
require_once CEROS_EMBED_PLUGIN_DIR . 'vendor/autoload.php';

require_once CEROS_EMBED_PLUGIN_DIR . 'includes/update-checker.php';

/**
 * Load bundled translations from /languages (the header's Domain Path).
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'ceros-embed', false, dirname( plugin_basename( CEROS_EMBED_PLUGIN_FILE ) ) . '/languages' );
	}
);

use CerosEmbed\InitCeros;

$Ceros_Init = new InitCeros();
