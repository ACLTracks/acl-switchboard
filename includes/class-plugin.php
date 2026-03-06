<?php
/**
 * Core plugin orchestrator.
 *
 * Registers hooks, initializes subsystems, and acts as the singleton entry
 * point for the plugin lifecycle.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard;

use ACL_Switchboard\Admin\Admin_Controller;
use ACL_Switchboard\Admin\Admin_Handler;
use ACL_Switchboard\API\Switchboard_API;
use ACL_Switchboard\Providers\Provider_Registry;
use ACL_Switchboard\Providers\Provider_Store;
use ACL_Switchboard\Services\Service_Router;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * @var Provider_Registry
	 */
	private Provider_Registry $registry;

	/**
	 * @var Provider_Store
	 */
	private Provider_Store $store;

	/**
	 * @var Service_Router
	 */
	private Service_Router $router;

	/**
	 * Get or create the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor. Sets up subsystems and hooks.
	 */
	private function __construct() {
		$this->registry = new Provider_Registry();
		$this->store    = new Provider_Store();
		$this->router   = new Service_Router();

		// Initialize the public API singleton with our instances.
		Switchboard_API::init( $this->registry, $this->store, $this->router );

		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Activation hook.
		register_activation_hook( ACL_SWITCHBOARD_FILE, array( $this, 'activate' ) );

		// Admin-only initialization.
		if ( is_admin() ) {
			$admin_controller = new Admin_Controller( $this->registry, $this->store, $this->router );
			$admin_handler    = new Admin_Handler( $this->registry, $this->store, $this->router );

			add_action( 'admin_menu', array( $admin_controller, 'register_menus' ) );
			add_action( 'admin_enqueue_scripts', array( $admin_controller, 'enqueue_assets' ) );

			// Form handlers via admin_post (POST requests to admin-post.php).
			add_action( 'admin_post_acl_switchboard_save_provider', array( $admin_handler, 'handle_save_provider' ) );
			add_action( 'admin_post_acl_switchboard_save_service_map', array( $admin_handler, 'handle_save_service_map' ) );
			add_action( 'admin_post_acl_switchboard_save_settings', array( $admin_handler, 'handle_save_settings' ) );

			// Delete handler uses admin_action_ because it is triggered via a
			// GET link to admin.php?action=..., not a POST to admin-post.php.
			// The admin_action_{action} hook fires from admin.php for both GET
			// and POST requests when the 'action' query parameter is present.
			add_action( 'admin_action_acl_switchboard_delete_provider', array( $admin_handler, 'handle_delete_provider' ) );

			// AJAX handler for connection test.
			add_action( 'wp_ajax_acl_switchboard_test_connection', array( $admin_handler, 'ajax_test_connection' ) );
		}

		// Load text domain.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Plugin activation callback.
	 *
	 * Sets default option values if they don't already exist.
	 *
	 * @return void
	 */
	public function activate(): void {
		// Only initialize options if they don't exist yet (fresh install).
		if ( false === get_option( 'acl_switchboard_providers' ) ) {
			update_option( 'acl_switchboard_providers', array(), true );
		}

		if ( false === get_option( 'acl_switchboard_service_map' ) ) {
			// Initialize with all known service types set to null (unmapped).
			$service_types = Service_Router::get_default_service_types();
			$default_map   = array();
			foreach ( $service_types as $slug => $label ) {
				$default_map[ $slug ] = null;
			}
			update_option( 'acl_switchboard_service_map', $default_map, true );
		}

		if ( false === get_option( 'acl_switchboard_settings' ) ) {
			update_option( 'acl_switchboard_settings', array(
				'delete_data_on_uninstall' => false,
			), true );
		}

		update_option( 'acl_switchboard_db_version', ACL_SWITCHBOARD_VERSION, true );
	}

	/**
	 * Load the plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'acl-switchboard',
			false,
			dirname( ACL_SWITCHBOARD_BASENAME ) . '/languages'
		);
	}

	/**
	 * Accessor for the provider registry.
	 *
	 * @return Provider_Registry
	 */
	public function registry(): Provider_Registry {
		return $this->registry;
	}

	/**
	 * Accessor for the provider store.
	 *
	 * @return Provider_Store
	 */
	public function store(): Provider_Store {
		return $this->store;
	}

	/**
	 * Accessor for the service router.
	 *
	 * @return Service_Router
	 */
	public function router(): Service_Router {
		return $this->router;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent deserialization.
	 *
	 * @throws \RuntimeException Always.
	 */
	public function __wakeup(): void {
		throw new \RuntimeException( 'Cannot deserialize ACL Switchboard plugin singleton.' );
	}
}
