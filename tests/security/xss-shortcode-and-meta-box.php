<?php
/**
 * Security regression check: stored XSS through the [ceros-animation]
 * shortcode and the Ceros meta box.
 *
 * Run: ddev wp eval-file wp-content/plugins/ceros-animations/tests/security/xss-shortcode-and-meta-box.php
 *
 * It creates a temporary contributor, saves the payloads through
 * wp_insert_post() as that user (so kses runs exactly as it does in the
 * editor), renders the shortcode and the meta box, then deletes everything
 * it created. It works whether or not the plugin is active.
 *
 * @package CerosEmbed
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( \CerosEmbed\CerosShortcode::class ) ) {
	require_once WP_PLUGIN_DIR . '/ceros-animations/ceros-embed.php';
	( new \CerosEmbed\CerosPostType() )->custom_post_type_ceros();
}

$ceros_embed_failures = 0;

/**
 * Print one result line and count failures.
 *
 * @param string $label    What was checked.
 * @param bool   $is_safe  Whether the output is safe.
 * @param string $evidence The relevant output fragment.
 */
$ceros_embed_report = static function ( string $label, bool $is_safe, string $evidence ) use ( &$ceros_embed_failures ): void {
	if ( ! $is_safe ) {
		++$ceros_embed_failures;
	}
	WP_CLI::line( ( $is_safe ? 'SAFE       ' : 'VULNERABLE ' ) . $label );
	WP_CLI::line( '    ' . trim( preg_replace( '/\s+/', ' ', $evidence ) ) );
};

// Temporary contributor. Contributors have edit_posts but not unfiltered_html.
$ceros_embed_user_id = wp_insert_user(
	array(
		'user_login' => 'ceros-sec-contrib-' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'ceros-sec-' . wp_generate_password( 6, false ) . '@example.invalid',
		'role'       => 'contributor',
	)
);
wp_set_current_user( $ceros_embed_user_id ); // Triggers kses_init() for this user.

// 1. Shortcode `title` attribute. Tags are stripped by kses, so the payload breaks out of the attribute instead.
$ceros_embed_title_payload = 'x" onload="alert(document.domain)" data-x="';
$ceros_embed_post_id       = wp_insert_post(
	array(
		'post_title'   => 'Ceros XSS check',
		'post_content' => "[ceros-animation id=\"1\" type=\"scrolling\" title='" . $ceros_embed_title_payload . "']\n[ceros-animation id=\"1\" type=\"full\" title='" . $ceros_embed_title_payload . "']",
		'post_status'  => 'pending',
	)
);
$ceros_embed_saved = get_post_field( 'post_content', $ceros_embed_post_id );
$ceros_embed_html  = do_shortcode( $ceros_embed_saved );
preg_match_all( '/<iframe[^>]*>/', $ceros_embed_html, $ceros_embed_iframes );
foreach ( $ceros_embed_iframes[0] as $ceros_embed_i => $ceros_embed_iframe ) {
	$ceros_embed_report(
		'shortcode title attribute (' . ( 0 === $ceros_embed_i ? 'scrolling' : 'full' ) . ')',
		! str_contains( $ceros_embed_iframe, ' onload="alert' ),
		(string) strstr( $ceros_embed_iframe, 'title=' )
	);
}

// 2. $site_url in data-ceros-origin-domains (site URL comes from an option / filter).
$ceros_embed_site_filter = static fn() => 'https://example.test/"><script>alert(1)</script>';
add_filter( 'site_url', $ceros_embed_site_filter );
$ceros_embed_html = do_shortcode( '[ceros-animation id="1" title="Ok"]' );
remove_filter( 'site_url', $ceros_embed_site_filter );
preg_match( '/data-ceros-origin-domains=.{0,60}/', $ceros_embed_html, $ceros_embed_m );
$ceros_embed_report( 'shortcode data-ceros-origin-domains ($site_url)', ! str_contains( $ceros_embed_html, '<script>alert(1)' ), $ceros_embed_m[0] ?? $ceros_embed_html );

// 3. Meta box: post title (written by the contributor) in the shortcode input's value attribute.
// wptexturize() curls bare quotes, but not quotes inside a kses-allowed tag, so the payload hides in one.
$ceros_embed_ceros_id = wp_insert_post(
	array(
		'post_type'   => 'ceros',
		'post_title'  => '<a title=" autofocus onfocus=alert(document.domain) x">Report</a>',
		'post_status' => 'draft',
	)
);
// 4. Meta box: stored _ceros_file_upload URL.
update_post_meta( $ceros_embed_ceros_id, '_ceros_file_upload', 'https://example.test/x"><img src=x onerror=alert(2)>' );

$GLOBALS['post'] = get_post( $ceros_embed_ceros_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
setup_postdata( $GLOBALS['post'] );
ob_start();
( new \CerosEmbed\CerosPostOptions() )->render_meta_box( $GLOBALS['post'] );
$ceros_embed_box = (string) ob_get_clean();
wp_reset_postdata();

preg_match( '/<input type="text".*?class="widefat">/s', $ceros_embed_box, $ceros_embed_m );
$ceros_embed_report( 'meta box shortcode value (post title)', ! str_contains( $ceros_embed_m[0] ?? '', '<a title=" autofocus' ), $ceros_embed_m[0] ?? '' );
$ceros_embed_report( 'meta box current file / preview ($file_url)', ! str_contains( $ceros_embed_box, '<img src=x' ), implode( ' | ', array_filter( array_map( 'trim', preg_grep( '/example\.test/', explode( "\n", $ceros_embed_box ) ) ) ) ) );

// Cleanup.
wp_set_current_user( 0 );
wp_delete_post( $ceros_embed_post_id, true );
wp_delete_post( $ceros_embed_ceros_id, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $ceros_embed_user_id );
WP_CLI::line( 'Cleaned up contributor #' . $ceros_embed_user_id . ' and posts #' . $ceros_embed_post_id . ', #' . $ceros_embed_ceros_id . '.' );

if ( $ceros_embed_failures ) {
	WP_CLI::error( $ceros_embed_failures . ' check(s) vulnerable.' );
}
WP_CLI::success( 'All checks safe.' );
