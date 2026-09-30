<?php
/**
 * Validates and extracts uploaded Ceros export zips.
 *
 * @package CerosEmbed
 */

namespace CerosEmbed;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use ZipArchive;

/**
 * Class CerosZipExtractor
 *
 * A zip is checked entry by entry before anything is written. The whole zip is
 * rejected if any entry has a file type outside the allowlist, a script-like
 * inner extension (`x.php.png`), a dot-file or dot-directory, an absolute or
 * `..` path, or if it exceeds the file count or uncompressed size limits.
 * Symlinks, extensionless files (Ceros exports contain `p/<n> -> ..` symlinks)
 * and macOS/Windows metadata (`__MACOSX/`, `.DS_Store`, `Thumbs.db`) are
 * skipped rather than extracted.
 */
class CerosZipExtractor {

	/**
	 * File extensions a Ceros export may contain. Filterable with
	 * `ceros_embed_allowed_extensions`.
	 */
	const ALLOWED_EXTENSIONS = array(
		'html',
		'htm',
		'js',
		'css',
		'json',
		'map',
		'txt',
		'xml',
		'png',
		'jpg',
		'jpeg',
		'gif',
		'webp',
		'svg',
		'ico',
		'woff',
		'woff2',
		'ttf',
		'otf',
		'eot',
		'mp4',
		'webm',
		'mp3',
		'wav',
		'ts',   // HLS video segments.
		'm3u8', // HLS playlists.
	);

	/**
	 * Inner extensions that a web server might execute (`shell.php.png`).
	 */
	const EXECUTABLE_EXTENSIONS = '/^(php\d*|phtml|phar|pht|phps|shtml?|cgi|pl|py|sh|asp|aspx|jsp|exe)$/';

	/**
	 * OS metadata that is skipped wherever it appears.
	 */
	const IGNORED_NAMES = array( '.ds_store', 'thumbs.db', 'desktop.ini' );

	/**
	 * Check a zip without writing anything.
	 *
	 * @param string $zip_path Path to the zip.
	 * @return array[]|WP_Error Entries to extract (`index`, `path`, `dir`), or why the zip was rejected.
	 */
	public static function inspect( $zip_path ) {
		if ( ! class_exists( ZipArchive::class ) ) {
			return new WP_Error( 'ceros_embed_no_ziparchive', __( 'The PHP zip extension is required to upload Ceros exports.', 'ceros-embed' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CHECKCONS ) ) {
			return new WP_Error( 'ceros_embed_bad_zip', __( 'The uploaded file is not a valid zip archive.', 'ceros-embed' ) );
		}

		/**
		 * Filters the maximum number of entries in a Ceros zip.
		 *
		 * @param int $max_files Default 10000.
		 */
		$max_files = (int) apply_filters( 'ceros_embed_max_files', 10000 );

		/**
		 * Filters the maximum total uncompressed size of a Ceros zip, in bytes.
		 *
		 * @param int $max_bytes Default 512 MB.
		 */
		$max_bytes = (int) apply_filters( 'ceros_embed_max_uncompressed_bytes', 512 * MB_IN_BYTES );

		/**
		 * Filters the file extensions allowed in a Ceros zip (lowercase, without the dot).
		 *
		 * @param string[] $extensions Allowed extensions.
		 */
		$allowed = array_map( 'strtolower', (array) apply_filters( 'ceros_embed_allowed_extensions', self::ALLOWED_EXTENSIONS ) );

		if ( $zip->numFiles > $max_files ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$zip->close();
			/* translators: %d: maximum number of files. */
			return new WP_Error( 'ceros_embed_too_many_files', sprintf( __( 'The zip contains more than %d files.', 'ceros-embed' ), $max_files ) );
		}

		$entries = array();
		$total   = 0;

		for ( $i = 0; $i < $zip->numFiles; $i++ ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive property.
			$stat = $zip->statIndex( $i );
			if ( false === $stat ) {
				$zip->close();
				return new WP_Error( 'ceros_embed_bad_zip', __( 'The uploaded file is not a valid zip archive.', 'ceros-embed' ) );
			}

			$result = self::check_entry( $zip, $i, $stat, $allowed );
			if ( is_wp_error( $result ) ) {
				$zip->close();
				return $result;
			}
			if ( null === $result ) {
				continue;
			}

			if ( ! $result['dir'] ) {
				$total += (int) $stat['size'];
				if ( $total > $max_bytes ) {
					$zip->close();
					/* translators: %s: maximum size, e.g. "512 MB". */
					return new WP_Error( 'ceros_embed_too_large', sprintf( __( 'The zip expands to more than %s.', 'ceros-embed' ), size_format( $max_bytes ) ) );
				}
			}
			$entries[] = $result;
		}

		$zip->close();

		if ( ! array_filter( $entries, static fn( $entry ) => ! $entry['dir'] ) ) {
			return new WP_Error( 'ceros_embed_empty_zip', __( 'The zip contains no files to extract.', 'ceros-embed' ) );
		}

		return $entries;
	}

	/**
	 * Check one entry.
	 *
	 * @param ZipArchive $zip     Open archive.
	 * @param int        $index   Entry index.
	 * @param array      $stat    Result of statIndex().
	 * @param string[]   $allowed Allowed extensions.
	 * @return array|null|WP_Error The entry to extract, null to skip it, or an error that rejects the zip.
	 */
	private static function check_entry( ZipArchive $zip, $index, array $stat, array $allowed ) {
		$name = (string) $stat['name'];
		/* translators: %s: path inside the zip. */
		$reject = static fn( $message ) => new WP_Error( 'ceros_embed_rejected_entry', sprintf( $message, $name ) );

		$path = str_replace( '\\', '/', $name );
		if ( str_contains( $path, "\0" ) || str_starts_with( $path, '/' ) || preg_match( '#^[a-z]:#i', $path ) ) {
			/* translators: %s: path inside the zip. */
			return $reject( __( 'The zip was rejected: "%s" is an absolute path.', 'ceros-embed' ) );
		}

		$segments = array_values( array_filter( explode( '/', $path ), static fn( $segment ) => '' !== $segment && '.' !== $segment ) );
		if ( in_array( '..', $segments, true ) ) {
			/* translators: %s: path inside the zip. */
			return $reject( __( 'The zip was rejected: "%s" points outside the export folder.', 'ceros-embed' ) );
		}
		if ( ! $segments || '__MACOSX' === $segments[0] || in_array( strtolower( end( $segments ) ), self::IGNORED_NAMES, true ) ) {
			return null;
		}

		$is_dir = str_ends_with( $path, '/' );

		$opsys = 0;
		$attr  = 0;
		if ( $zip->getExternalAttributesIndex( $index, $opsys, $attr ) && ZipArchive::OPSYS_UNIX === $opsys && 0120000 === ( ( $attr >> 16 ) & 0170000 ) ) {
			return null; // Symlink: never recreated.
		}

		foreach ( $segments as $segment ) {
			if ( str_starts_with( $segment, '.' ) ) {
				/* translators: %s: path inside the zip. */
				return $reject( __( 'The zip was rejected: "%s" is a hidden file or folder.', 'ceros-embed' ) );
			}
		}

		if ( ! $is_dir ) {
			$parts = explode( '.', strtolower( end( $segments ) ) );
			if ( count( $parts ) < 2 ) {
				return null; // Extensionless file.
			}
			$extension = array_pop( $parts );
			array_shift( $parts );
			if ( ! in_array( $extension, $allowed, true ) ) {
				/* translators: %s: path inside the zip. */
				return $reject( __( 'The zip was rejected: "%s" is not an allowed file type.', 'ceros-embed' ) );
			}
			foreach ( $parts as $inner ) {
				if ( preg_match( self::EXECUTABLE_EXTENSIONS, $inner ) ) {
					/* translators: %s: path inside the zip. */
					return $reject( __( 'The zip was rejected: "%s" has a script extension.', 'ceros-embed' ) );
				}
			}
		}

		return array(
			'index' => $index,
			'path'  => implode( '/', $segments ),
			'dir'   => $is_dir,
		);
	}

	/**
	 * Extract checked entries into a new folder.
	 *
	 * The entries are written to a temporary sibling folder first. If the zip
	 * holds a single top-level folder (a zipped Ceros export folder), that folder
	 * becomes $destination; otherwise the extracted files do.
	 *
	 * @param string  $zip_path    Path to the zip.
	 * @param array[] $entries     Result of inspect().
	 * @param string  $destination Folder to create. Must not exist.
	 * @return true|WP_Error
	 */
	public static function extract( $zip_path, array $entries, $destination ) {
		if ( file_exists( $destination ) ) {
			return new WP_Error( 'ceros_embed_exists', __( 'A folder for this export already exists.', 'ceros-embed' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CHECKCONS ) ) {
			return new WP_Error( 'ceros_embed_bad_zip', __( 'The uploaded file is not a valid zip archive.', 'ceros-embed' ) );
		}

		$max_bytes = (int) apply_filters( 'ceros_embed_max_uncompressed_bytes', 512 * MB_IN_BYTES ); // Documented in inspect().
		$remaining = $max_bytes;
		$temp      = dirname( $destination ) . '/temp_unzip_' . wp_generate_password( 12, false, false );
		$error     = null;

		if ( ! wp_mkdir_p( $temp ) ) {
			$error = new WP_Error( 'ceros_embed_write_failed', __( 'Could not create the export folder.', 'ceros-embed' ) );
		}

		foreach ( $error ? array() : $entries as $entry ) {
			$target = $temp . '/' . $entry['path'];
			if ( $entry['dir'] ) {
				wp_mkdir_p( $target );
				continue;
			}
			wp_mkdir_p( dirname( $target ) );

			// Stream each entry with a byte budget, so a zip that under-reports its sizes can't fill the disk.
			$in  = $zip->getStreamIndex( $entry['index'] );
			$out = $in ? fopen( $target, 'xb' ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streamed, size-limited write.
			if ( ! $in || ! $out ) {
				$error = new WP_Error( 'ceros_embed_write_failed', __( 'Could not extract the zip.', 'ceros-embed' ) );
			} else {
				$remaining -= (int) stream_copy_to_stream( $in, $out, $remaining + 1 );
				if ( $remaining < 0 ) {
					/* translators: %s: maximum size, e.g. "512 MB". */
					$error = new WP_Error( 'ceros_embed_too_large', sprintf( __( 'The zip expands to more than %s.', 'ceros-embed' ), size_format( $max_bytes ) ) );
				}
			}
			if ( $in ) {
				fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
			if ( $out ) {
				fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
			if ( $error ) {
				break;
			}
		}
		$zip->close();

		if ( ! $error ) {
			$items  = array_values( array_diff( (array) scandir( $temp ), array( '.', '..' ) ) );
			$source = ( 1 === count( $items ) && is_dir( $temp . '/' . $items[0] ) ) ? $temp . '/' . $items[0] : $temp;
			if ( ! rename( $source, $destination ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
				$error = new WP_Error( 'ceros_embed_write_failed', __( 'Could not move the extracted export into place.', 'ceros-embed' ) );
			}
		}

		self::delete_tree( $temp );
		return $error ? $error : true;
	}

	/**
	 * Stop the web server from running scripts in the Ceros uploads folder.
	 *
	 * Writes `.htaccess` (Apache; nginx ignores it) and an `index.php` silence
	 * file, unless a file with that name already exists.
	 *
	 * @param string $dir The uploads/ceros folder.
	 */
	public static function protect_directory( $dir ) {
		$files = array(
			'.htaccess' => implode(
				"\n",
				array(
					'# BEGIN Ceros Embed',
					'# Ceros exports are static files. Never run scripts from this folder.',
					'<IfModule mod_php.c>',
					'	php_flag engine off',
					'</IfModule>',
					'<IfModule mod_php7.c>',
					'	php_flag engine off',
					'</IfModule>',
					'<IfModule mod_php5.c>',
					'	php_flag engine off',
					'</IfModule>',
					'<FilesMatch "(?i)\.(php\d*|phtml|phar|pht|phps|shtml?|cgi|pl|py|sh)(\.|$)">',
					'	<IfModule mod_authz_core.c>',
					'		Require all denied',
					'	</IfModule>',
					'	<IfModule !mod_authz_core.c>',
					'		Order allow,deny',
					'		Deny from all',
					'	</IfModule>',
					'</FilesMatch>',
					'# END Ceros Embed',
					'',
				)
			),
			'index.php' => "<?php\n// Silence is golden.\n",
		);
		foreach ( $files as $name => $contents ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Direct write in the uploads folder, as the extraction does.
			}
		}
	}

	/**
	 * Remove a folder tree created by extract().
	 *
	 * @param string $path File or folder.
	 */
	private static function delete_tree( $path ) {
		if ( is_link( $path ) || is_file( $path ) ) {
			wp_delete_file( $path );
			return;
		}
		if ( ! is_dir( $path ) ) {
			return;
		}
		foreach ( array_diff( (array) scandir( $path ), array( '.', '..' ) ) as $item ) {
			self::delete_tree( $path . '/' . $item );
		}
		rmdir( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}
