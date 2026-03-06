<?php
/**
 * Plugin Name: ACL Switchboard
 * Plugin URI:  https://github.com/acl-plugins/acl-switchboard
 * Description: Central AI provider registry and routing hub for WordPress. Stores API credentials, maps service types to providers, and exposes a PHP API for other ACL ecosystem plugins.
 * Version:     1.0.0
 * Author:      ACL Plugins
 * Author URI:  https://github.com/acl-plugins
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: acl-switchboard
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package ACL_Switchboard
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ACL_SWITCHBOARD_VERSION', '1.0.0' );
define( 'ACL_SWITCHBOARD_FILE', __FILE__ );
define( 'ACL_SWITCHBOARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACL_SWITCHBOARD_URL', plugin_dir_url( __FILE__ ) );
define( 'ACL_SWITCHBOARD_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'ACL_SWITCHBOARD_MIN_PHP', '8.0' );
define( 'ACL_SWITCHBOARD_MIN_WP', '6.0' );

/**
 * Check minimum PHP version before loading anything.
 */
if ( version_compare( PHP_VERSION, ACL_SWITCHBOARD_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: 1: Required PHP version, 2: Current PHP version */
					__( 'ACL Switchboard requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP to activate the plugin.', 'acl-switchboard' ),
					ACL_SWITCHBOARD_MIN_PHP,
					PHP_VERSION
				)
			)
		);
	} );
	return;
}

// Load the autoloader.
require_once ACL_SWITCHBOARD_DIR . 'includes/class-autoloader.php';
ACL_Switchboard\Autoloader::register();

// Boot the plugin.
require_once ACL_SWITCHBOARD_DIR . 'includes/class-plugin.php';
ACL_Switchboard\Plugin::instance();

/**
 * Global accessor for the public API.
 *
 * Downstream plugins should use this function to interact with the switchboard:
 *
 *     $creds = acl_switchboard()->get_provider_credentials( 'openai' );
 *
 * Always guard with: if ( function_exists( 'acl_switchboard' ) ) { ... }
 *
 * @return \ACL_Switchboard\API\Switchboard_API
 */
function acl_switchboard(): ACL_Switchboard\API\Switchboard_API {
	return ACL_Switchboard\API\Switchboard_API::instance();
}
