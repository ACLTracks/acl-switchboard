<?php
/**
 * Admin controller.
 *
 * Registers the admin menu pages and renders them by including view templates.
 * All form processing is handled separately by Admin_Handler.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Admin;

use ACL_Switchboard\Providers\Provider_Registry;
use ACL_Switchboard\Providers\Provider_Store;
use ACL_Switchboard\Services\Service_Router;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Controller {

	/**
	 * Menu slug prefix.
	 *
	 * @var string
	 */
	public const MENU_SLUG = 'acl-switchboard';

	/**
	 * Required capability for accessing the admin pages.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

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
	 * Hook suffixes returned by add_menu_page / add_submenu_page.
	 *
	 * Captured at menu registration time for use by enqueue_assets().
	 * Note: WordPress derives these from the menu slug constant, not
	 * from translated titles, so hardcoded strings would also work.
	 *
	 * @var array<string>
	 */
	private array $hook_suffixes = array();

	/**
	 * Constructor.
	 *
	 * @param Provider_Registry $registry Provider registry instance.
	 * @param Provider_Store    $store    Provider store instance.
	 * @param Service_Router    $router   Service router instance.
	 */
	public function __construct(
		Provider_Registry $registry,
		Provider_Store $store,
		Service_Router $router,
	) {
		$this->registry = $registry;
		$this->store    = $store;
		$this->router   = $router;
	}

	/**
	 * Register the admin menu and subpages.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		// Top-level menu.
		$this->hook_suffixes[] = (string) add_menu_page(
			__( 'ACL Switchboard', 'acl-switchboard' ),
			__( 'ACL Switchboard', 'acl-switchboard' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-networking',
			80
		);

		// Dashboard (same as top-level — overwrites the duplicate submenu label).
		$this->hook_suffixes[] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard — ACL Switchboard', 'acl-switchboard' ),
			__( 'Dashboard', 'acl-switchboard' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		// Providers.
		$this->hook_suffixes[] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Providers — ACL Switchboard', 'acl-switchboard' ),
			__( 'Providers', 'acl-switchboard' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-providers',
			array( $this, 'render_providers' )
		);

		// Service Routing.
		$this->hook_suffixes[] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Service Routing — ACL Switchboard', 'acl-switchboard' ),
			__( 'Service Routing', 'acl-switchboard' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-services',
			array( $this, 'render_service_routing' )
		);

		// Settings.
		$this->hook_suffixes[] = (string) add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings — ACL Switchboard', 'acl-switchboard' ),
			__( 'Settings', 'acl-switchboard' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Enqueue admin CSS and JS on our plugin pages only.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only load on our pages.
		if ( ! in_array( $hook_suffix, $this->hook_suffixes, true ) ) {
			return;
		}

		wp_enqueue_style(
			'acl-switchboard-admin',
			ACL_SWITCHBOARD_URL . 'assets/css/admin.css',
			array(),
			ACL_SWITCHBOARD_VERSION
		);

		wp_enqueue_script(
			'acl-switchboard-admin',
			ACL_SWITCHBOARD_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ACL_SWITCHBOARD_VERSION,
			true
		);

		wp_localize_script( 'acl-switchboard-admin', 'aclSwitchboard', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'acl_switchboard_test_connection' ),
			'i18n'    => array(
				'testing'     => __( 'Testing…', 'acl-switchboard' ),
				'testSuccess' => __( 'Connection successful!', 'acl-switchboard' ),
				'testFailed'  => __( 'Connection failed.', 'acl-switchboard' ),
			),
		) );
	}

	/**
	 * Render the Dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acl-switchboard' ) );
		}

		$registry      = $this->registry;
		$store         = $this->store;
		$router        = $this->router;
		$service_types = $router->get_service_types();
		$service_map   = $router->get_map();
		$providers     = $store->get_all();
		$enabled_count = count( $store->get_enabled() );

		include ACL_SWITCHBOARD_DIR . 'includes/Admin/views/dashboard.php';
	}

	/**
	 * Render the Providers page (list or edit).
	 *
	 * @return void
	 */
	public function render_providers(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acl-switchboard' ) );
		}

		$registry      = $this->registry;
		$store         = $this->store;
		$router        = $this->router;
		$service_types = $router->get_service_types();

		// Sanitize action and provider from query string.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display, no state change.
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug   = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';

		if ( 'edit' === $action || 'add' === $action ) {
			// Editing or adding a provider.
			$definition    = ! empty( $slug ) ? $registry->get( $slug ) : null;
			$saved         = ! empty( $slug ) ? $store->get( $slug ) : null;
			$is_new        = ( 'add' === $action );
			$all_providers = $registry->get_all();

			include ACL_SWITCHBOARD_DIR . 'includes/Admin/views/provider-edit.php';
		} else {
			// Provider list.
			$providers     = $store->get_all();
			$all_providers = $registry->get_all();

			include ACL_SWITCHBOARD_DIR . 'includes/Admin/views/providers.php';
		}
	}

	/**
	 * Render the Service Routing page.
	 *
	 * @return void
	 */
	public function render_service_routing(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acl-switchboard' ) );
		}

		$store         = $this->store;
		$router        = $this->router;
		$service_types = $router->get_service_types();
		$service_map   = $router->get_map();
		$providers     = $store->get_all();

		include ACL_SWITCHBOARD_DIR . 'includes/Admin/views/service-routing.php';
	}

	/**
	 * Render the Settings page.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'acl-switchboard' ) );
		}

		$settings = get_option( 'acl_switchboard_settings', array() );

		include ACL_SWITCHBOARD_DIR . 'includes/Admin/views/settings.php';
	}

	/**
	 * Get the user-scoped transient key for flash notices.
	 *
	 * Includes the current user ID to prevent cross-user notice leakage
	 * when multiple admins are using the plugin simultaneously.
	 *
	 * @return string
	 */
	public static function get_notice_transient_key(): string {
		return 'acl_switchboard_notice_' . get_current_user_id();
	}
}
