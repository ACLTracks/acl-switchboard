<?php
/**
 * Service router.
 *
 * Manages the mapping of service types (chat, image, tts, etc.) to default
 * providers. Reads and writes the acl_switchboard_service_map option.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Services;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Service_Router {

	/**
	 * Option key for the service map.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'acl_switchboard_service_map';

	/**
	 * In-memory cache.
	 *
	 * @var array|null
	 */
	private ?array $cache = null;

	/**
	 * Get the built-in service type definitions.
	 *
	 * Returns an associative array of slug => label.
	 *
	 * @return array<string, string>
	 */
	public static function get_default_service_types(): array {
		$types = array(
			'chat'             => __( 'Chat / Text Generation', 'acl-switchboard' ),
			'image'            => __( 'Image Generation', 'acl-switchboard' ),
			'audio_generation' => __( 'Audio Generation', 'acl-switchboard' ),
			'text_to_speech'   => __( 'Text to Speech', 'acl-switchboard' ),
			'speech_to_text'   => __( 'Speech to Text', 'acl-switchboard' ),
			'transcription'    => __( 'Transcription', 'acl-switchboard' ),
			'embeddings'       => __( 'Embeddings', 'acl-switchboard' ),
			'moderation'       => __( 'Moderation', 'acl-switchboard' ),
			'video'            => __( 'Video Generation', 'acl-switchboard' ),
		);

		/**
		 * Filter the recognized service types.
		 *
		 * Allows downstream plugins to add custom service types.
		 *
		 * @param array<string, string> $types Service type slug => label.
		 */
		return apply_filters( 'acl_switchboard_service_types', $types );
	}

	/**
	 * Get all service types with labels.
	 *
	 * @return array<string, string>
	 */
	public function get_service_types(): array {
		return self::get_default_service_types();
	}

	/**
	 * Get the full service map (service_slug => provider_slug|null).
	 *
	 * @return array<string, string|null>
	 */
	public function get_map(): array {
		if ( null === $this->cache ) {
			$data = get_option( self::OPTION_KEY, array() );
			$this->cache = is_array( $data ) ? $data : array();
		}

		// Ensure all known service types are represented in the map.
		$types = $this->get_service_types();
		foreach ( $types as $slug => $label ) {
			if ( ! array_key_exists( $slug, $this->cache ) ) {
				$this->cache[ $slug ] = null;
			}
		}

		return $this->cache;
	}

	/**
	 * Get the default provider slug for a service type.
	 *
	 * @param string $service_slug Service type slug.
	 * @return string|null Provider slug, or null if not mapped.
	 */
	public function get_default_provider( string $service_slug ): ?string {
		$map = $this->get_map();

		if ( ! isset( $map[ $service_slug ] ) || empty( $map[ $service_slug ] ) ) {
			return null;
		}

		return $map[ $service_slug ];
	}

	/**
	 * Save the full service map.
	 *
	 * @param array<string, string|null> $map The service map to save.
	 * @return bool Whether the save was successful.
	 */
	public function save_map( array $map ): bool {
		// Sanitize: only keep known service type keys, and ensure values are
		// either a non-empty string (provider slug) or null.
		$types     = $this->get_service_types();
		$sanitized = array();

		foreach ( $types as $slug => $label ) {
			if ( isset( $map[ $slug ] ) && '' !== $map[ $slug ] ) {
				$sanitized[ $slug ] = sanitize_key( $map[ $slug ] );
			} else {
				$sanitized[ $slug ] = null;
			}
		}

		$success = update_option( self::OPTION_KEY, $sanitized, true );

		// Bust cache.
		$this->cache = $success ? $sanitized : null;

		/**
		 * Fires after the service map is updated.
		 *
		 * @param array $sanitized The saved service map.
		 */
		do_action( 'acl_switchboard_service_map_updated', $sanitized );

		return $success;
	}
}
