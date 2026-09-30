<?php
/**
 * Security regression check: Ceros zip uploads.
 *
 * Run: ddev wp eval-file wp-content/plugins/ceros-animations/tests/security/zip-upload.php
 *
 * Uploads crafted zips through the real edit screen (HTTP login, multipart
 * POST to post.php with the form's nonces) as a temporary contributor (no
 * upload_files) and a temporary author (upload_files), then checks what landed
 * on disk. It also checks the size/count limits in-process with lowered filters.
 * The plugin is activated for the run if it is not active, and everything the
 * run creates (users, posts, files under uploads/ceros) is removed afterwards.
 *
 * @package CerosEmbed
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.WP.EnqueuedResources -- Test fixtures (files on disk, HTML inside zips).

// WP-CLI includes this file inside a method; bind the shared state to real globals.
global $ceros_embed_t, $ceros_embed_pass;

$ceros_embed_t                  = array(
	'failures'  => 0,
	'activated' => false,
	'users'     => array(),
	'posts'     => array(),
	'tmp'       => trailingslashit( get_temp_dir() ) . 'ceros-embed-sec-' . wp_generate_password( 6, false ),
	'ceros_dir' => trailingslashit( wp_upload_dir()['basedir'] ) . 'ceros',
);
$ceros_embed_t['ceros_existed'] = is_dir( $ceros_embed_t['ceros_dir'] );

/**
 * Record one expectation.
 *
 * @param bool   $ok    Whether the expectation holds.
 * @param string $label Description.
 */
function ceros_embed_sec_expect( $ok, $label ) {
	global $ceros_embed_t;
	if ( ! $ok ) {
		++$ceros_embed_t['failures'];
	}
	WP_CLI::line( ( $ok ? '  PASS ' : '  FAIL ' ) . $label );
}

/**
 * Build a zip. Each entry is [ path, contents, 'dir'|'file'|'symlink' ].
 *
 * @param string $name    File name inside the temp dir.
 * @param array  $entries Entries.
 * @return string Path.
 */
function ceros_embed_sec_zip( $name, array $entries ) {
	global $ceros_embed_t;
	$path = $ceros_embed_t['tmp'] . '/' . $name;
	$zip  = new ZipArchive();
	$zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	foreach ( $entries as $entry ) {
		list( $entry_path, $contents, $type ) = array_pad( $entry, 3, 'file' );
		if ( 'dir' === $type ) {
			$zip->addEmptyDir( rtrim( $entry_path, '/' ) );
			continue;
		}
		$zip->addFromString( $entry_path, $contents, ZipArchive::FL_ENC_UTF_8 );
		$mode = 'symlink' === $type ? 0120777 : 0100644;
		$zip->setExternalAttributesName( $entry_path, ZipArchive::OPSYS_UNIX, $mode << 16 );
	}
	$zip->close();
	return $path;
}

/**
 * List every path under a directory (relative), or [] if it does not exist.
 *
 * @param string $dir Directory.
 * @return string[]
 */
function ceros_embed_sec_ls( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return array();
	}
	$out = array();
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $file ) {
		$out[] = substr( $file->getPathname(), strlen( $dir ) + 1 ) . ( $file->isLink() ? ' (symlink)' : '' );
	}
	sort( $out );
	return $out;
}

/**
 * Remove a directory tree.
 *
 * @param string $dir Directory.
 */
function ceros_embed_sec_rm( $dir ) {
	if ( is_link( $dir ) || is_file( $dir ) ) {
		unlink( $dir );
		return;
	}
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $item ) {
		if ( '.' !== $item && '..' !== $item ) {
			ceros_embed_sec_rm( $dir . '/' . $item );
		}
	}
	rmdir( $dir );
}

/**
 * Log in over HTTP and return the cookies.
 *
 * @param string $login    User login.
 * @param string $password Password.
 * @return WP_Http_Cookie[]
 */
function ceros_embed_sec_login( $login, $password ) {
	$res = wp_remote_post(
		wp_login_url(),
		array(
			'sslverify'   => false,
			'redirection' => 0,
			'cookies'     => array(
				new WP_Http_Cookie(
					array(
						'name'  => 'wordpress_test_cookie',
						'value' => 'WP Cookie check',
					)
				),
			),
			'body'        => array(
				'log'         => $login,
				'pwd'         => $password,
				'wp-submit'   => 'Log In',
				'testcookie'  => '1',
				'redirect_to' => admin_url(),
			),
		)
	);
	return wp_remote_retrieve_cookies( $res );
}

/**
 * Upload a zip through the Ceros edit screen as the logged-in user.
 *
 * @param WP_Http_Cookie[] $cookies Session cookies.
 * @param int              $post_id Ceros post to save.
 * @param string           $zip     Zip path.
 * @return string The edit screen HTML after the save (shows admin notices).
 */
function ceros_embed_sec_upload( $cookies, $post_id, $zip ) {
	$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	$html     = wp_remote_retrieve_body(
		wp_remote_get(
			$edit_url,
			array(
				'sslverify' => false,
				'cookies'   => $cookies,
			)
		)
	);
	$fields   = array(
		'action'                  => 'editpost',
		'originalaction'          => 'editpost',
		'post_ID'                 => (string) $post_id,
		'post_type'               => 'ceros',
		'original_post_status'    => 'draft',
		'post_title'              => get_post_field( 'post_title', $post_id ),
		'save'                    => 'Save Draft',
		'_wpnonce'                => '',
		'ceros_file_upload_nonce' => '',
		'user_ID'                 => '',
	);
	foreach ( array( '_wpnonce', 'ceros_file_upload_nonce', 'user_ID' ) as $name ) {
		if ( preg_match( '/name=[\'"]' . $name . '[\'"][^>]*value=[\'"]([^\'"]*)/', $html, $m ) ) {
			$fields[ $name ] = $m[1];
		}
	}
	$boundary = 'ceros' . wp_generate_password( 16, false );
	$body     = '';
	foreach ( $fields as $name => $value ) {
		$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
	}
	$body .= "--$boundary\r\nContent-Disposition: form-data; name=\"ceros_file_upload\"; filename=\"" . basename( $zip ) . "\"\r\nContent-Type: application/zip\r\n\r\n" . file_get_contents( $zip ) . "\r\n--$boundary--\r\n";
	wp_remote_post(
		admin_url( 'post.php' ),
		array(
			'sslverify'   => false,
			'redirection' => 0,
			'timeout'     => 60,
			'cookies'     => $cookies,
			'headers'     => array( 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ),
			'body'        => $body,
		)
	);
	return wp_remote_retrieve_body(
		wp_remote_get(
			$edit_url,
			array(
				'sslverify' => false,
				'cookies'   => $cookies,
			)
		)
	);
}

/**
 * Stored file URL for a Ceros post, bypassing the object cache.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function ceros_embed_sec_meta( $post_id ) {
	wp_cache_delete( $post_id, 'post_meta' );
	return (string) get_post_meta( $post_id, '_ceros_file_upload', true );
}

wp_mkdir_p( $ceros_embed_t['tmp'] );
if ( ! is_plugin_active( 'ceros-animations/ceros-embed.php' ) ) {
	activate_plugin( 'ceros-animations/ceros-embed.php' );
	$ceros_embed_t['activated'] = true;
}
if ( ! class_exists( \CerosEmbed\CerosShortcode::class ) ) {
	require_once WP_PLUGIN_DIR . '/ceros-animations/ceros-embed.php';
}

$ceros_embed_legit = array(
	array( 's3demo/', '', 'dir' ),
	array( 's3demo/index.html', '<!doctype html><html><head><link rel="stylesheet" href="assets/app.css"><script src="assets/app.js"></script></head><body><h1 id="t">Ceros S3 demo</h1></body></html>' ),
	array( 's3demo/assets/', '', 'dir' ),
	array( 's3demo/assets/app.css', 'h1{color:#c00}' ),
	array( 's3demo/assets/app.js', 'document.getElementById("t");' ),
);
$ceros_embed_zips  = array(
	'a-php'         => ceros_embed_sec_zip(
		's3-a-php.zip',
		array_merge(
			$ceros_embed_legit,
			array(
				array( 's3demo/shell.php', '<?php echo "CEROS-PHP-EXEC-" . ( 6 * 7 );' ),
				array( 's3demo/.htaccess', 'AddType application/x-httpd-php .png' ),
			)
		)
	),
	'b-traversal'   => ceros_embed_sec_zip(
		's3-b-traversal.zip',
		array_merge(
			$ceros_embed_legit,
			array(
				array( 's3demo/../../../s3-zipslip-proof.txt', 'ZIPSLIP' ),
				array( '../s3-zipslip-proof2.html', '<script>alert(1)</script>' ),
			)
		)
	),
	'e-double-ext'  => ceros_embed_sec_zip( 's3-e-double-ext.zip', array_merge( $ceros_embed_legit, array( array( 's3demo/img.php.png', '<?php echo 1;' ) ) ) ),
	'f-dotfile'     => ceros_embed_sec_zip( 's3-f-dotfile.zip', array_merge( $ceros_embed_legit, array( array( 's3demo/.user.ini', 'auto_prepend_file=x' ) ) ) ),
	'c-legit'       => ceros_embed_sec_zip( 's3-c-legit.zip', $ceros_embed_legit ),
	// Shaped like a real Ceros export: p/N -> .. symlinks, HLS video, macOS junk.
	'd-real-export' => ceros_embed_sec_zip(
		's3-d-real-export.zip',
		array_merge(
			$ceros_embed_legit,
			array(
				array( 's3demo/p/', '', 'dir' ),
				array( 's3demo/p/1', '..', 'symlink' ),
				array( 's3demo/assets/.DS_Store', 'junk' ),
				array( '__MACOSX/s3demo/._index.html', 'junk' ),
				array( 's3demo/media/hls/file-00001.ts', 'TS' ),
				array( 's3demo/media/hls/file.m3u8', '#EXTM3U' ),
			)
		)
	),
);

// Temporary users.
$ceros_embed_pass = wp_generate_password( 20, false );
foreach ( array( 'contributor', 'author' ) as $ceros_embed_role ) {
	$ceros_embed_t['users'][ $ceros_embed_role ] = wp_insert_user(
		array(
			'user_login' => 'ceros-sec-' . $ceros_embed_role . '-' . wp_generate_password( 4, false, false ),
			'user_pass'  => $ceros_embed_pass,
			'user_email' => 'ceros-sec-' . $ceros_embed_role . wp_generate_password( 4, false, false ) . '@example.invalid',
			'role'       => $ceros_embed_role,
		)
	);
}

/**
 * Run one upload scenario and return [ new paths under uploads/ceros, meta, page html ].
 *
 * @param string $role Role key in $ceros_embed_t['users'].
 * @param string $zip  Zip path.
 * @return array
 */
function ceros_embed_sec_run( $role, $zip ) {
	global $ceros_embed_t;
	$user    = get_userdata( $ceros_embed_t['users'][ $role ] );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'ceros',
			'post_title'  => 'Ceros zip test ' . basename( $zip ),
			'post_status' => 'draft',
			'post_author' => $user->ID,
		)
	);

	$ceros_embed_t['posts'][] = $post_id;
	$before                   = ceros_embed_sec_ls( $ceros_embed_t['ceros_dir'] );
	$before_root              = ceros_embed_sec_ls( dirname( $ceros_embed_t['ceros_dir'] ) );
	$html                     = ceros_embed_sec_upload( ceros_embed_sec_login( $user->user_login, $GLOBALS['ceros_embed_pass'] ), $post_id, $zip );
	$new                      = array_values( array_diff( ceros_embed_sec_ls( $ceros_embed_t['ceros_dir'] ), $before ) );
	$new_outside              = array_values( array_filter( array_diff( ceros_embed_sec_ls( dirname( $ceros_embed_t['ceros_dir'] ) ), $before_root ), static fn( $p ) => ! str_starts_with( $p, 'ceros' ) ) );
	$notice                   = preg_match( '/<div class="notice notice-error[^"]*"><p>(.*?)<\/p>/s', $html, $m ) ? wp_strip_all_tags( $m[1] ) : '(none)';
	WP_CLI::line( '  ' . $role . ' uploads ' . basename( $zip ) . ':' );
	WP_CLI::line( '    new under uploads/ceros: ' . ( $new ? implode( ', ', $new ) : '(nothing)' ) );
	WP_CLI::line( '    new elsewhere in uploads: ' . ( $new_outside ? implode( ', ', $new_outside ) : '(nothing)' ) );
	WP_CLI::line( '    _ceros_file_upload: ' . ( '' !== ceros_embed_sec_meta( $post_id ) ? ceros_embed_sec_meta( $post_id ) : '(empty)' ) );
	WP_CLI::line( '    admin notice: ' . $notice );
	return array( $new, ceros_embed_sec_meta( $post_id ), $post_id, $new_outside, $notice );
}

/**
 * Whether any of the paths is an extracted file (not the uploaded zip or the dir guard files).
 *
 * @param string[] $paths Paths relative to uploads/ceros.
 * @return bool
 */
function ceros_embed_sec_extracted( $paths ) {
	return (bool) array_filter( $paths, static fn( $p ) => ! preg_match( '/^(\.htaccess|index\.php|[^\/]+\.zip)$/', $p ) );
}

WP_CLI::line( '1. Contributor (no upload_files) uploads a legitimate export' );
list( $ceros_embed_new, $ceros_embed_meta ) = ceros_embed_sec_run( 'contributor', $ceros_embed_zips['c-legit'] );
ceros_embed_sec_expect( ! $ceros_embed_new && '' === $ceros_embed_meta, 'nothing is written and no URL is stored' );

foreach ( array( 'a-php', 'b-traversal', 'e-double-ext', 'f-dotfile' ) as $ceros_embed_key ) {
	WP_CLI::line( '2. Author uploads ' . $ceros_embed_key );
	list( $ceros_embed_new, $ceros_embed_meta, , $ceros_embed_outside ) = ceros_embed_sec_run( 'author', $ceros_embed_zips[ $ceros_embed_key ] );
	ceros_embed_sec_expect( ! ceros_embed_sec_extracted( $ceros_embed_new ) && ! $ceros_embed_outside && '' === $ceros_embed_meta, 'the zip is rejected and nothing is extracted' );
}
$ceros_embed_slip = array_filter( array( ABSPATH . 's3-zipslip-proof.txt', WP_CONTENT_DIR . '/s3-zipslip-proof.txt', dirname( $ceros_embed_t['ceros_dir'] ) . '/s3-zipslip-proof.txt', dirname( $ceros_embed_t['ceros_dir'] ) . '/s3-zipslip-proof2.html', $ceros_embed_t['ceros_dir'] . '/s3-zipslip-proof2.html' ), 'file_exists' );
ceros_embed_sec_expect( ! $ceros_embed_slip, 'no path-traversal file exists anywhere' . ( $ceros_embed_slip ? ': ' . implode( ', ', $ceros_embed_slip ) : '' ) );

foreach ( array( 'c-legit', 'd-real-export' ) as $ceros_embed_key ) {
	WP_CLI::line( '3. Author uploads ' . $ceros_embed_key );
	list( $ceros_embed_new, $ceros_embed_meta, $ceros_embed_pid ) = ceros_embed_sec_run( 'author', $ceros_embed_zips[ $ceros_embed_key ] );
	ceros_embed_sec_expect( '' !== $ceros_embed_meta, 'the export is extracted and its URL stored' );
	$ceros_embed_codes = array();
	foreach ( array( '/index.html', '/assets/app.js', '/assets/app.css' ) as $ceros_embed_file ) {
		$ceros_embed_codes[] = wp_remote_retrieve_response_code( wp_remote_get( $ceros_embed_meta . $ceros_embed_file, array( 'sslverify' => false ) ) );
	}
	ceros_embed_sec_expect( array( 200, 200, 200 ) === $ceros_embed_codes, 'index.html, app.js and app.css are served (' . implode( ', ', $ceros_embed_codes ) . ')' );
	$ceros_embed_embed = do_shortcode( '[ceros-animation id="' . $ceros_embed_pid . '" title="Demo"]' );
	ceros_embed_sec_expect( str_contains( $ceros_embed_embed, 'src="' . esc_url( $ceros_embed_meta ) . '/index.html"' ), 'the shortcode embeds it' );
	if ( 'd-real-export' === $ceros_embed_key ) {
		ceros_embed_sec_expect( ! array_filter( $ceros_embed_new, static fn( $p ) => str_contains( $p, 'symlink' ) || str_contains( $p, '.DS_Store' ) || str_contains( $p, '__MACOSX' ) || str_ends_with( $p, '/p/1' ) ), 'symlinks, .DS_Store and __MACOSX entries are skipped' );
		ceros_embed_sec_expect( (bool) preg_grep( '/file-00001\.ts$/', $ceros_embed_new ) && (bool) preg_grep( '/file\.m3u8$/', $ceros_embed_new ), 'HLS video (.ts, .m3u8) is extracted' );
	}
}

WP_CLI::line( '4. Directory guard files' );
ceros_embed_sec_expect( is_file( $ceros_embed_t['ceros_dir'] . '/.htaccess' ) && is_file( $ceros_embed_t['ceros_dir'] . '/index.php' ), 'uploads/ceros has .htaccess and index.php' );

WP_CLI::line( '5. Size and count limits (in-process, lowered filters)' );
if ( class_exists( \CerosEmbed\CerosZipExtractor::class ) ) {
	$ceros_embed_bomb = ceros_embed_sec_zip( 's3-g-bomb.zip', array( array( 's3demo/index.html', 'ok' ), array( 's3demo/assets/big.js', str_repeat( "\0", 3 * MB_IN_BYTES ) ) ) );
	$ceros_embed_cap  = static fn() => MB_IN_BYTES;
	add_filter( 'ceros_embed_max_uncompressed_bytes', $ceros_embed_cap );
	$ceros_embed_res  = \CerosEmbed\CerosZipExtractor::inspect( $ceros_embed_bomb );
	remove_filter( 'ceros_embed_max_uncompressed_bytes', $ceros_embed_cap );
	ceros_embed_sec_expect( is_wp_error( $ceros_embed_res ), 'a 3 MB entry is rejected with a 1 MB limit: ' . ( is_wp_error( $ceros_embed_res ) ? $ceros_embed_res->get_error_message() : 'accepted' ) );
	$ceros_embed_cap = static fn() => 3;
	add_filter( 'ceros_embed_max_files', $ceros_embed_cap );
	$ceros_embed_res = \CerosEmbed\CerosZipExtractor::inspect( $ceros_embed_zips['c-legit'] );
	remove_filter( 'ceros_embed_max_files', $ceros_embed_cap );
	ceros_embed_sec_expect( is_wp_error( $ceros_embed_res ), 'a 5-entry zip is rejected with a 3-file limit: ' . ( is_wp_error( $ceros_embed_res ) ? $ceros_embed_res->get_error_message() : 'accepted' ) );

	// Extraction enforces the byte budget itself, in case the zip under-reports its sizes.
	$ceros_embed_entries = \CerosEmbed\CerosZipExtractor::inspect( $ceros_embed_bomb );
	$ceros_embed_dest    = $ceros_embed_t['tmp'] . '/extract-bomb';
	$ceros_embed_cap     = static fn() => MB_IN_BYTES;
	add_filter( 'ceros_embed_max_uncompressed_bytes', $ceros_embed_cap );
	$ceros_embed_res     = \CerosEmbed\CerosZipExtractor::extract( $ceros_embed_bomb, $ceros_embed_entries, $ceros_embed_dest );
	remove_filter( 'ceros_embed_max_uncompressed_bytes', $ceros_embed_cap );
	ceros_embed_sec_expect( is_wp_error( $ceros_embed_res ) && ! file_exists( $ceros_embed_dest ) && ! glob( $ceros_embed_t['tmp'] . '/temp_unzip_*' ), 'extraction stops at the byte budget and removes its partial output' );

	// A zip with the export's files at its root (no top-level folder) extracts into the destination.
	$ceros_embed_flat = ceros_embed_sec_zip( 's3-h-flat.zip', array( array( 'index.html', 'ok' ), array( 'assets/app.js', '1' ) ) );
	$ceros_embed_dest = $ceros_embed_t['tmp'] . '/extract-flat';
	$ceros_embed_res  = \CerosEmbed\CerosZipExtractor::extract( $ceros_embed_flat, \CerosEmbed\CerosZipExtractor::inspect( $ceros_embed_flat ), $ceros_embed_dest );
	ceros_embed_sec_expect( true === $ceros_embed_res && is_file( $ceros_embed_dest . '/index.html' ) && is_file( $ceros_embed_dest . '/assets/app.js' ), 'a zip without a top-level folder extracts into the destination' );
} else {
	ceros_embed_sec_expect( false, 'CerosEmbed\\CerosZipExtractor exists (no size or count limits)' );
}

// Cleanup.
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $ceros_embed_t['posts'] as $ceros_embed_pid ) {
	$ceros_embed_url = ceros_embed_sec_meta( $ceros_embed_pid );
	if ( $ceros_embed_url && $ceros_embed_t['ceros_existed'] ) {
		ceros_embed_sec_rm( $ceros_embed_t['ceros_dir'] . '/' . basename( $ceros_embed_url ) );
		ceros_embed_sec_rm( $ceros_embed_t['ceros_dir'] . '/' . basename( $ceros_embed_url ) . '.zip' );
	}
	wp_delete_post( $ceros_embed_pid, true );
}
foreach ( $ceros_embed_t['users'] as $ceros_embed_uid ) {
	wp_delete_user( $ceros_embed_uid );
}
if ( ! $ceros_embed_t['ceros_existed'] ) {
	ceros_embed_sec_rm( $ceros_embed_t['ceros_dir'] );
}
foreach ( $ceros_embed_slip as $ceros_embed_file ) {
	unlink( $ceros_embed_file );
}
ceros_embed_sec_rm( $ceros_embed_t['tmp'] );
if ( $ceros_embed_t['activated'] ) {
	deactivate_plugins( 'ceros-animations/ceros-embed.php' );
}
WP_CLI::line( 'Cleaned up ' . count( $ceros_embed_t['users'] ) . ' users, ' . count( $ceros_embed_t['posts'] ) . ' posts and ' . ( $ceros_embed_t['ceros_existed'] ? 'the test folders in' : 'all of' ) . ' uploads/ceros.' );

if ( $ceros_embed_t['failures'] ) {
	WP_CLI::error( $ceros_embed_t['failures'] . ' check(s) failed.' );
}
WP_CLI::success( 'All zip upload checks passed.' );
