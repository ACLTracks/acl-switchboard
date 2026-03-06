<?php
/**
 * Class autoloader for ACL Switchboard.
 *
 * Maps the ACL_Switchboard namespace to the includes/ directory using a
 * WordPress-style filename convention (class-lowercase-name.php).
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Autoloader {

	/**
	 * The root namespace this autoloader handles.
	 *
	 * @var string
	 */
	private const NAMESPACE_ROOT = 'ACL_Switchboard\\';

	/**
	 * Register the autoloader with spl_autoload.
	 *
	 * @return void
	 */
	public static function register(): void {
		spl_autoload_register( array( static::class, 'autoload' ) );
	}

	/**
	 * Autoload a class file.
	 *
	 * Converts namespace segments into directory paths and class names into
	 * WordPress-style filenames: ACL_Switchboard\Admin\Admin_Controller
	 * becomes includes/Admin/class-admin-controller.php.
	 *
	 * @param string $class Fully qualified class name.
	 * @return void
	 */
	public static function autoload( string $class ): void {
		// Only handle our namespace.
		if ( ! str_starts_with( $class, self::NAMESPACE_ROOT ) ) {
			return;
		}

		// Strip the root namespace prefix.
		$relative = substr( $class, strlen( self::NAMESPACE_ROOT ) );

		// Split into segments.
		$parts = explode( '\\', $relative );

		// The last segment is the class name.
		$class_name = array_pop( $parts );

		// Convert class name to filename: Admin_Controller -> class-admin-controller.php
		$filename = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		// Build the directory path from remaining namespace segments.
		$directory = ACL_SWITCHBOARD_DIR . 'includes/';
		if ( ! empty( $parts ) ) {
			$directory .= implode( '/', $parts ) . '/';
		}

		$filepath = $directory . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}
}
