<?php
/**
 * Provider store.
 *
 * Handles reading and writing admin-configured provider data (credentials,
 * enabled state, notes, etc.) from the acl_switchboard_providers option.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Provider_Store {

	/**
	 * Option key for provider configurations.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'acl_switchboard_providers';

	/**
	 * In-memory cache of provider data to avoid redundant get_option calls
	 * within the same request.
	 *
	 * @var array|null
	 */
	private ?array $cache = null;

	/**
	 * Get all saved provider configurations.
	 *
	 * @return array<string, array> Keyed by provider slug.
	 */
	public function get_all(): array {
		if ( null === $this->cache ) {
			$data = get_option( self::OPTION_KEY, array() );
			$this->cache = is_array( $data ) ? $data : array();
		}
		return $this->cache;
	}

	/**
	 * Get a single saved provider configuration.
	 *
	 * @param string $slug Provider slug.
	 * @return array|null The provider config, or null if not saved.
	 */
	public function get( string $slug ): ?array {
		$all = $this->get_all();
		return $all[ $slug ] ?? null;
	}

	/**
	 * Check if a provider has been configured (saved at least once).
	 *
	 * @param string $slug Provider slug.
	 * @return bool
	 */
	public function exists( string $slug ): bool {
		return null !== $this->get( $slug );
	}

	/**
	 * Save or update a provider configuration.
	 *
	 * @param string $slug         Provider slug.
	 * @param array  $data         Provider configuration data.
	 * @param bool   $skip_encrypt If true, skip the encrypt filter on the API key.
	 *                             Use this when the key value was read back from the
	 *                             database and is being carried forward unchanged, to
	 *                             prevent double-encryption.
	 * @return bool Whether the save was successful.
	 */
	public function save( string $slug, array $data, bool $skip_encrypt = false ): bool {
		$all_before = $this->get_all();
		$all        = $all_before;

		// Ensure required keys exist with defaults.
		$data = wp_parse_args( $data, array(
			'label'    => $slug,
			'enabled'  => false,
			'api_key'  => '',
			'base_url' => '',
			'org_id'   => '',
			'extra'    => array(),
			'notes'    => '',
			'services' => array(),
		) );

		// Normalize types.
		$data['enabled']  = (bool) $data['enabled'];
		$data['services'] = array_values( array_filter( (array) $data['services'] ) );

		// Apply encryption filter to the API key before storage,
		// unless the caller explicitly says to skip it (key unchanged).
		if ( ! $skip_encrypt && ! empty( $data['api_key'] ) ) {
			/**
			 * Filter the API key before storing it.
			 *
			 * Site operators can hook into this to encrypt the key using
			 * sodium or another method, with a decryption key stored in
			 * wp-config.php.
			 *
			 * @param string $api_key The raw API key.
			 * @param string $slug    The provider slug.
			 */
			$data['api_key'] = apply_filters( 'acl_switchboard_encrypt_key', $data['api_key'], $slug );
		}

		$all[ $slug ] = $data;
		$unchanged    = ( $all === $all_before );

		$result = update_option( self::OPTION_KEY, $all, true );

		if ( $result || $unchanged ) {
			// update_option() also returns false when no DB write is needed.
			// In that unchanged case the DB already contains $all.
			$this->cache = $all;
		} else {
			// Real write failure: invalidate cache so callers re-read DB state.
			$this->cache = null;
			return false;
		}

		/**
		 * Fires after a provider configuration is saved.
		 *
		 * @param string $slug The provider slug.
		 * @param array  $data The saved provider data.
		 */
		do_action( 'acl_switchboard_provider_saved', $slug, $data );

		return $result;
	}

	/**
	 * Delete a saved provider configuration.
	 *
	 * @param string $slug Provider slug.
	 * @return bool Whether the deletion was successful.
	 */
	public function delete( string $slug ): bool {
		$all_before = $this->get_all();
		$all        = $all_before;

		if ( ! isset( $all[ $slug ] ) ) {
			return false;
		}

		unset( $all[ $slug ] );
		$unchanged = ( $all === $all_before );

		$result = update_option( self::OPTION_KEY, $all, true );

		if ( $result || $unchanged ) {
			$this->cache = $all;
		} else {
			$this->cache = null;
		}

		return $result;
	}

	/**
	 * Get only enabled provider configurations.
	 *
	 * @return array<string, array>
	 */
	public function get_enabled(): array {
		return array_filter( $this->get_all(), function ( array $provider ): bool {
			return ! empty( $provider['enabled'] );
		} );
	}

	/**
	 * Check if a provider is enabled.
	 *
	 * @param string $slug Provider slug.
	 * @return bool
	 */
	public function is_enabled( string $slug ): bool {
		$provider = $this->get( $slug );
		return null !== $provider && ! empty( $provider['enabled'] );
	}

	/**
	 * Retrieve the decrypted API key for a provider.
	 *
	 * @param string $slug Provider slug.
	 * @return string The API key, or empty string if not configured.
	 */
	public function get_api_key( string $slug ): string {
		$provider = $this->get( $slug );

		if ( null === $provider || empty( $provider['api_key'] ) ) {
			return '';
		}

		/**
		 * Filter the API key after reading it from storage.
		 *
		 * Site operators can hook into this to decrypt the key.
		 *
		 * @param string $api_key The stored (possibly encrypted) API key.
		 * @param string $slug    The provider slug.
		 */
		return apply_filters( 'acl_switchboard_decrypt_key', $provider['api_key'], $slug );
	}

	/**
	 * Get credentials array for a provider.
	 *
	 * This is the primary method downstream plugins use to get what they need
	 * to make API calls.
	 *
	 * @param string $slug Provider slug.
	 * @return array|null Array with 'api_key', 'base_url', 'org_id', 'extra', or null.
	 */
	public function get_credentials( string $slug ): ?array {
		$provider = $this->get( $slug );

		if ( null === $provider ) {
			return null;
		}

		return array(
			'api_key'  => $this->get_api_key( $slug ),
			'base_url' => $provider['base_url'] ?? '',
			'org_id'   => $provider['org_id'] ?? '',
			'extra'    => $provider['extra'] ?? array(),
		);
	}

	/**
	 * Mask an API key for display, showing only the last 4 characters.
	 *
	 * @param string $key The full API key.
	 * @return string The masked key (e.g., '••••••••sk4f').
	 */
	public static function mask_key( string $key ): string {
		if ( empty( $key ) ) {
			return '';
		}

		$length  = strlen( $key );
		$visible = substr( $key, -4 );
		return str_repeat( '•', max( 8, $length - 4 ) ) . $visible;
	}

	/**
	 * Sanitize an API key input.
	 *
	 * Unlike sanitize_text_field(), this preserves all printable ASCII characters
	 * that commonly appear in API keys (base64 chars like +, /, =, and others).
	 * Only strips leading/trailing whitespace and control characters.
	 *
	 * @param string $key Raw API key input.
	 * @return string Sanitized API key.
	 */
	public static function sanitize_api_key( string $key ): string {
		// Strip null bytes and control characters (0x00-0x1F, 0x7F)
		// but preserve all printable characters.
		$key = preg_replace( '/[\x00-\x1F\x7F]/', '', $key );

		return trim( $key );
	}
}
