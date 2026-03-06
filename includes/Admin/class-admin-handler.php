<?php
/**
 * Admin handler.
 *
 * Processes form submissions and AJAX requests from the admin UI.
 * All handlers verify nonces and capabilities before doing anything.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Admin;

use ACL_Switchboard\Providers\Connection_Tester;
use ACL_Switchboard\Providers\Provider_Registry;
use ACL_Switchboard\Providers\Provider_Store;
use ACL_Switchboard\Services\Service_Router;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin_Handler {

	/**
	 * Required capability.
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
	 * Constructor.
	 *
	 * @param Provider_Registry $registry Provider registry.
	 * @param Provider_Store    $store    Provider store.
	 * @param Service_Router    $router   Service router.
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
	 * Handle saving a provider configuration.
	 *
	 * @return void
	 */
	public function handle_save_provider(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'acl-switchboard' ) );
		}

		check_admin_referer( 'acl_switchboard_save_provider' );

		// Determine the provider slug.
		$is_new        = ! empty( $_POST['is_new'] );
		$provider_type = isset( $_POST['provider_type'] ) ? sanitize_key( wp_unslash( $_POST['provider_type'] ) ) : '';

		if ( $is_new ) {
			if ( 'custom' === $provider_type ) {
				// For custom providers, derive slug from the label.
				$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
				$slug  = sanitize_key( str_replace( ' ', '_', strtolower( $label ) ) );

				if ( empty( $slug ) ) {
					$slug = 'custom_' . wp_generate_password( 6, false );
				}

				// Ensure uniqueness by appending a counter.
				$base_slug = $slug;
				$counter   = 1;
				while ( $this->store->exists( $slug ) ) {
					$slug = $base_slug . '_' . $counter;
					$counter++;
				}
			} else {
				$slug = $provider_type;

				// Server-side overwrite guard: prevent re-adding a non-custom
				// provider that already exists. The UI disables these options
				// in the dropdown, but a crafted POST could bypass that.
				if ( $this->store->exists( $slug ) ) {
					$this->redirect_with_notice(
						'providers',
						'error',
						sprintf(
							/* translators: %s: provider slug */
							__( 'Provider "%s" already exists. Edit it instead of adding it again.', 'acl-switchboard' ),
							$slug
						)
					);
					return;
				}
			}
		} else {
			$slug = isset( $_POST['provider_slug'] ) ? sanitize_key( wp_unslash( $_POST['provider_slug'] ) ) : '';
		}

		if ( empty( $slug ) ) {
			$this->redirect_with_notice( 'providers', 'error', __( 'Invalid provider slug.', 'acl-switchboard' ) );
			return;
		}

		// Collect fields.
		$label    = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : $slug;
		$enabled  = ! empty( $_POST['enabled'] );
		$base_url = isset( $_POST['base_url'] ) ? esc_url_raw( wp_unslash( $_POST['base_url'] ) ) : '';
		$org_id   = isset( $_POST['org_id'] ) ? sanitize_text_field( wp_unslash( $_POST['org_id'] ) ) : '';
		$notes    = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		// Sanitize services array.
		$services = array();
		if ( isset( $_POST['services'] ) && is_array( $_POST['services'] ) ) {
			$raw_services = wp_unslash( $_POST['services'] );
			$services     = array_map( 'sanitize_key', $raw_services );
		}

		// Handle API key with a purpose-built sanitizer instead of
		// sanitize_text_field(), which can mangle base64 and special chars
		// commonly found in API keys.
		$api_key_input = isset( $_POST['api_key'] )
			? Provider_Store::sanitize_api_key( wp_unslash( $_POST['api_key'] ) )
			: '';

		$existing = $this->store->get( $slug );

		// If the submitted key is empty or only contains mask characters (•),
		// keep the existing stored key and flag that encryption should be
		// skipped (the stored value is already encrypted if filters are active).
		$skip_encrypt = false;
		if ( empty( $api_key_input ) || preg_match( '/^[•]+$/', $api_key_input ) ) {
			$api_key      = ( null !== $existing && ! empty( $existing['api_key'] ) ) ? $existing['api_key'] : '';
			$skip_encrypt = true;
		} else {
			$api_key = $api_key_input;
		}

		// Collect extra config fields.
		$extra = array();
		if ( isset( $_POST['extra'] ) && is_array( $_POST['extra'] ) ) {
			$raw_extra = wp_unslash( $_POST['extra'] );
			foreach ( $raw_extra as $key => $value ) {
				$extra[ sanitize_key( $key ) ] = sanitize_text_field( $value );
			}
		}

		// If the base URL is empty, try the registry default.
		if ( empty( $base_url ) ) {
			$definition = $this->registry->get( $slug ) ?? $this->registry->get( $provider_type );
			if ( null !== $definition ) {
				$base_url = $definition->default_base_url;
			}
		}

		$data = array(
			'label'    => $label,
			'enabled'  => $enabled,
			'api_key'  => $api_key,
			'base_url' => $base_url,
			'org_id'   => $org_id,
			'extra'    => $extra,
			'notes'    => $notes,
			'services' => $services,
		);

		// Delegate entirely to Provider_Store::save(), using the skip_encrypt
		// flag to prevent double-encryption when the key is unchanged. This
		// keeps the store's internal cache consistent and avoids bypassing
		// the store's encapsulation.
		$this->store->save( $slug, $data, $skip_encrypt );

		$this->redirect_with_notice(
			'providers',
			'success',
			sprintf(
				/* translators: %s: provider label */
				__( 'Provider "%s" saved successfully.', 'acl-switchboard' ),
				$label
			)
		);
	}

	/**
	 * Handle deleting a provider.
	 *
	 * This fires via the admin_action_ hook (GET request to admin.php),
	 * not via admin_post_ (which requires POST to admin-post.php).
	 *
	 * @return void
	 */
	public function handle_delete_provider(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'acl-switchboard' ) );
		}

		$slug = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';

		// Nonce is slug-specific to prevent token reuse across providers.
		check_admin_referer( 'acl_switchboard_delete_provider_' . $slug );

		if ( empty( $slug ) ) {
			$this->redirect_with_notice( 'providers', 'error', __( 'Invalid provider.', 'acl-switchboard' ) );
			return;
		}

		$this->store->delete( $slug );

		// Also remove this provider from the service map if it was a default.
		$map     = $this->router->get_map();
		$changed = false;
		foreach ( $map as $service => $provider_slug ) {
			if ( $provider_slug === $slug ) {
				$map[ $service ] = null;
				$changed         = true;
			}
		}
		if ( $changed ) {
			$this->router->save_map( $map );
		}

		$this->redirect_with_notice( 'providers', 'success', __( 'Provider deleted.', 'acl-switchboard' ) );
	}

	/**
	 * Handle saving the service map.
	 *
	 * @return void
	 */
	public function handle_save_service_map(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'acl-switchboard' ) );
		}

		check_admin_referer( 'acl_switchboard_save_service_map' );

		$map = array();

		if ( isset( $_POST['service_map'] ) && is_array( $_POST['service_map'] ) ) {
			$raw_map = wp_unslash( $_POST['service_map'] );
			foreach ( $raw_map as $service => $provider ) {
				$map[ sanitize_key( $service ) ] = ! empty( $provider ) ? sanitize_key( $provider ) : null;
			}
		}

		$this->router->save_map( $map );

		$this->redirect_with_notice(
			'services',
			'success',
			__( 'Service routing updated.', 'acl-switchboard' )
		);
	}

	/**
	 * Handle saving global settings.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'acl-switchboard' ) );
		}

		check_admin_referer( 'acl_switchboard_save_settings' );

		$settings = array(
			'delete_data_on_uninstall' => ! empty( $_POST['delete_data_on_uninstall'] ),
		);

		update_option( 'acl_switchboard_settings', $settings, true );

		$this->redirect_with_notice(
			'settings',
			'success',
			__( 'Settings saved.', 'acl-switchboard' )
		);
	}

	/**
	 * AJAX handler for testing a provider connection.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'acl-switchboard' ) ), 403 );
		}

		check_ajax_referer( 'acl_switchboard_test_connection' );

		$slug = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';

		if ( empty( $slug ) ) {
			wp_send_json_error( array( 'message' => __( 'No provider specified.', 'acl-switchboard' ) ) );
		}

		$result = Connection_Tester::test( $slug, $this->store );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Redirect back to an admin page with a flash notice.
	 *
	 * Uses a user-scoped transient to prevent cross-user notice leakage
	 * when multiple admins are using the plugin simultaneously.
	 *
	 * @param string $page    Page suffix ('providers', 'services', 'settings').
	 * @param string $type    Notice type ('success' or 'error').
	 * @param string $message Notice message.
	 * @return void
	 */
	private function redirect_with_notice( string $page, string $type, string $message ): void {
		$transient_key = Admin_Controller::get_notice_transient_key();

		set_transient( $transient_key, array(
			'type'    => $type,
			'message' => $message,
		), 30 );

		$url = admin_url( 'admin.php?page=acl-switchboard' );
		if ( 'providers' === $page ) {
			$url = admin_url( 'admin.php?page=acl-switchboard-providers' );
		} elseif ( 'services' === $page ) {
			$url = admin_url( 'admin.php?page=acl-switchboard-services' );
		} elseif ( 'settings' === $page ) {
			$url = admin_url( 'admin.php?page=acl-switchboard-settings' );
		}

		wp_safe_redirect( $url );
		exit;
	}
}
