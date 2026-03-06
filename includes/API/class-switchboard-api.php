<?php
/**
 * Switchboard API — public facade for downstream plugins.
 *
 * This is the primary interface that other ACL ecosystem plugins use to
 * interact with the switchboard. Access it via the global acl_switchboard()
 * function.
 *
 * Example usage from a downstream plugin:
 *
 *     if ( function_exists( 'acl_switchboard' ) ) {
 *         $slug  = acl_switchboard()->get_default_provider_for_service( 'chat' );
 *         $creds = acl_switchboard()->get_provider_credentials( $slug );
 *         // Use $creds['api_key'], $creds['base_url'], etc.
 *     }
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\API;

use ACL_Switchboard\Providers\Connection_Tester;
use ACL_Switchboard\Providers\Provider_Registry;
use ACL_Switchboard\Providers\Provider_Store;
use ACL_Switchboard\Services\Service_Router;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Switchboard_API {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether init() has been called with dependencies.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Nullable to avoid fatal errors if accessed before init().
	 *
	 * @var Provider_Registry|null
	 */
	private ?Provider_Registry $registry = null;

	/**
	 * @var Provider_Store|null
	 */
	private ?Provider_Store $store = null;

	/**
	 * @var Service_Router|null
	 */
	private ?Service_Router $router = null;

	/**
	 * Initialize the API with its dependencies.
	 *
	 * Called once during plugin boot by the Plugin class.
	 *
	 * @param Provider_Registry $registry The provider registry.
	 * @param Provider_Store    $store    The provider store.
	 * @param Service_Router    $router   The service router.
	 * @return void
	 */
	public static function init(
		Provider_Registry $registry,
		Provider_Store $store,
		Service_Router $router,
	): void {
		$api              = self::instance();
		$api->registry    = $registry;
		$api->store       = $store;
		$api->router      = $router;
		$api->initialized = true;
	}

	/**
	 * Get the singleton instance.
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
	 * Check if the API has been initialized.
	 *
	 * Downstream plugins can use this to verify the switchboard is ready
	 * before calling methods.
	 *
	 * @return bool
	 */
	public function is_ready(): bool {
		return $this->initialized;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

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
		throw new \RuntimeException( 'Cannot deserialize ACL Switchboard API singleton.' );
	}

	/**
	 * Guard that verifies the API has been initialized.
	 *
	 * Called at the top of every public method to provide a clear error
	 * message if a downstream plugin calls the API too early.
	 *
	 * @throws \RuntimeException If called before init().
	 * @return void
	 */
	private function require_init(): void {
		if ( ! $this->initialized ) {
			throw new \RuntimeException(
				'ACL Switchboard API accessed before initialization. '
				. 'Ensure the acl-switchboard plugin is active and call '
				. 'acl_switchboard() no earlier than the "plugins_loaded" hook.'
			);
		}
	}

	// -------------------------------------------------------------------------
	// Provider methods
	// -------------------------------------------------------------------------

	/**
	 * Get all registered provider definitions (the catalog).
	 *
	 * Returns an array of Provider_Definition objects keyed by slug.
	 *
	 * @return array<string, \ACL_Switchboard\Providers\Provider_Definition>
	 */
	public function get_registered_providers(): array {
		$this->require_init();
		return $this->registry->get_all();
	}

	/**
	 * Get all enabled (configured and active) providers.
	 *
	 * Returns saved configuration arrays, not definitions.
	 *
	 * @return array<string, array>
	 */
	public function get_enabled_providers(): array {
		$this->require_init();
		return $this->store->get_enabled();
	}

	/**
	 * Get a single provider's merged definition and saved configuration.
	 *
	 * Returns null if the provider slug is unknown AND not configured.
	 *
	 * @param string $slug Provider slug.
	 * @return array|null Merged data array.
	 */
	public function get_provider( string $slug ): ?array {
		$this->require_init();

		$definition = $this->registry->get( $slug );
		$saved      = $this->store->get( $slug );

		if ( null === $definition && null === $saved ) {
			return null;
		}

		$result = array();

		if ( null !== $definition ) {
			$result = $definition->to_array();
		}

		if ( null !== $saved ) {
			// Saved config takes precedence for overlapping keys like label.
			$result = array_merge( $result, $saved );
			$result['configured'] = true;
		} else {
			$result['configured'] = false;
		}

		return $result;
	}

	/**
	 * Get the credentials for a provider.
	 *
	 * Returns an array with: api_key, base_url, org_id, extra.
	 * Returns null if the provider is not configured.
	 *
	 * @param string $slug Provider slug.
	 * @return array|null
	 */
	public function get_provider_credentials( string $slug ): ?array {
		$this->require_init();
		return $this->store->get_credentials( $slug );
	}

	/**
	 * Check if a provider is currently enabled.
	 *
	 * @param string $slug Provider slug.
	 * @return bool
	 */
	public function is_provider_enabled( string $slug ): bool {
		$this->require_init();
		return $this->store->is_enabled( $slug );
	}

	/**
	 * Check if a provider supports a specific service type.
	 *
	 * Checks the saved configuration first (admin may have customized services),
	 * then falls back to the registry definition.
	 *
	 * @param string $provider_slug Provider slug.
	 * @param string $service_slug  Service type slug.
	 * @return bool
	 */
	public function provider_supports_service( string $provider_slug, string $service_slug ): bool {
		$this->require_init();

		// Check saved config first (admin may have customized services).
		$saved = $this->store->get( $provider_slug );
		if ( null !== $saved && isset( $saved['services'] ) ) {
			return in_array( $service_slug, (array) $saved['services'], true );
		}

		// Fall back to registry definition.
		$definition = $this->registry->get( $provider_slug );
		if ( null !== $definition ) {
			return in_array( $service_slug, $definition->supported_services, true );
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Service routing methods
	// -------------------------------------------------------------------------

	/**
	 * Get all recognized service types.
	 *
	 * @return array<string, string> slug => label.
	 */
	public function get_service_types(): array {
		$this->require_init();
		return $this->router->get_service_types();
	}

	/**
	 * Get the full service map.
	 *
	 * @return array<string, string|null>
	 */
	public function get_service_map(): array {
		$this->require_init();
		return $this->router->get_map();
	}

	/**
	 * Get the default provider slug for a given service type.
	 *
	 * @param string $service_slug The service type (e.g. 'chat', 'image').
	 * @return string|null The provider slug, or null if not mapped.
	 */
	public function get_default_provider_for_service( string $service_slug ): ?string {
		$this->require_init();
		return $this->router->get_default_provider( $service_slug );
	}

	// -------------------------------------------------------------------------
	// Connection testing
	// -------------------------------------------------------------------------

	/**
	 * Test the connection for a provider.
	 *
	 * @param string $slug Provider slug.
	 * @return array{success: bool, message: string}
	 */
	public function test_provider_connection( string $slug ): array {
		$this->require_init();
		return Connection_Tester::test( $slug, $this->store );
	}

	// -------------------------------------------------------------------------
	// Dependency checking helpers
	// -------------------------------------------------------------------------

	/**
	 * Get the plugin version.
	 *
	 * Useful for downstream plugins to check compatibility.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return ACL_SWITCHBOARD_VERSION;
	}
}
