<?php
/**
 * Small WordPress filesystem adapter used by generated and staged plugin files.
 *
 * @package Brendigo_Sidrena_Cijena
 */

defined( 'ABSPATH' ) || exit;

/**
 * Centralize filesystem access so file handling is consistent and testable.
 */
final class Brendigo_Sidrena_Cijena_Filesystem {

	/**
	 * Return the configured WordPress filesystem implementation.
	 *
	 * @return WP_Filesystem_Base|null
	 */
	public static function get() {
		global $wp_filesystem;

		if ( is_object( $wp_filesystem ) ) {
			return $wp_filesystem;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! WP_Filesystem() || ! is_object( $wp_filesystem ) ) {
			return null;
		}

		return $wp_filesystem;
	}

	/**
	 * Whether a path exists.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function exists( $path ) {
		$filesystem = self::get();
		return $filesystem ? $filesystem->exists( $path ) : false;
	}

	/**
	 * Whether a path is a directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_dir( $path ) {
		$filesystem = self::get();
		return $filesystem ? $filesystem->is_dir( $path ) : false;
	}

	/**
	 * Whether a path is a file.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_file( $path ) {
		$filesystem = self::get();
		return $filesystem ? $filesystem->is_file( $path ) : false;
	}

	/**
	 * Read a file.
	 *
	 * @param string $path Absolute path.
	 * @return string|false
	 */
	public static function get_contents( $path ) {
		$filesystem = self::get();
		return $filesystem ? $filesystem->get_contents( $path ) : false;
	}

	/**
	 * Write a file.
	 *
	 * @param string $path     Absolute path.
	 * @param string $contents Contents.
	 * @return bool
	 */
	public static function put_contents( $path, $contents ) {
		$filesystem = self::get();
		if ( ! $filesystem ) {
			return false;
		}

		$mode = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		return $filesystem->put_contents( $path, $contents, $mode );
	}

	/**
	 * Return file size in bytes.
	 *
	 * @param string $path Absolute path.
	 * @return int
	 */
	public static function size( $path ) {
		$filesystem = self::get();
		return $filesystem ? max( 0, (int) $filesystem->size( $path ) ) : 0;
	}

	/**
	 * Return file modification time.
	 *
	 * @param string $path Absolute path.
	 * @return int
	 */
	public static function mtime( $path ) {
		$filesystem = self::get();
		return $filesystem ? max( 0, (int) $filesystem->mtime( $path ) ) : 0;
	}

	/**
	 * Directory listing.
	 *
	 * @param string $path Absolute path.
	 * @return array<string,array<string,mixed>>
	 */
	public static function dirlist( $path ) {
		$filesystem = self::get();
		if ( ! $filesystem ) {
			return array();
		}

		$list = $filesystem->dirlist( $path, false, false );
		return is_array( $list ) ? $list : array();
	}

	/**
	 * Delete a file or directory.
	 *
	 * @param string $path      Absolute path.
	 * @param bool   $recursive Recursively delete directory contents.
	 * @return bool
	 */
	public static function delete( $path, $recursive = false ) {
		$filesystem = self::get();
		return $filesystem ? $filesystem->delete( $path, $recursive ) : false;
	}

	/**
	 * Copy a file.
	 *
	 * @param string $source Source path.
	 * @param string $dest   Destination path.
	 * @return bool
	 */
	public static function copy( $source, $dest ) {
		$filesystem = self::get();
		if ( ! $filesystem ) {
			return false;
		}

		$mode = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		return $filesystem->copy( $source, $dest, true, $mode );
	}
}
