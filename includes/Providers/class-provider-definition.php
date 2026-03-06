<?php
/**
 * Provider definition value object.
 *
 * Represents the static metadata about a known AI provider: its slug, label,
 * default base URL, supported services, and any extra configuration fields
 * the provider expects. This is the "catalog entry", not the user's saved
 * configuration.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Provider_Definition {

	/**
	 * Unique slug identifier (e.g. 'openai', 'anthropic').
	 *
	 * @var string
	 */
	public readonly string $slug;

	/**
	 * Human-readable label.
	 *
	 * @var string
	 */
	public readonly string $label;

	/**
	 * Default API base URL for this provider.
	 *
	 * @var string
	 */
	public readonly string $default_base_url;

	/**
	 * Service slugs this provider supports (e.g. ['chat', 'image']).
	 *
	 * @var array<string>
	 */
	public readonly array $supported_services;

	/**
	 * Definitions for extra config fields beyond api_key and base_url.
	 *
	 * Each entry is an associative array with keys:
	 *   'key'         => string  — field key stored in the extra config
	 *   'label'       => string  — human-readable label
	 *   'type'        => string  — 'text' | 'password' | 'textarea'
	 *   'placeholder' => string  — placeholder text
	 *
	 * @var array<array<string, string>>
	 */
	public readonly array $extra_fields;

	/**
	 * Whether this is the generic "custom" provider type.
	 *
	 * @var bool
	 */
	public readonly bool $is_custom;

	/**
	 * Constructor.
	 *
	 * @param string $slug               Provider slug.
	 * @param string $label              Display name.
	 * @param string $default_base_url   Default API base URL.
	 * @param array  $supported_services List of service slugs.
	 * @param array  $extra_fields       Extra field definitions.
	 * @param bool   $is_custom          Whether this is a custom provider.
	 */
	public function __construct(
		string $slug,
		string $label,
		string $default_base_url = '',
		array $supported_services = array(),
		array $extra_fields = array(),
		bool $is_custom = false,
	) {
		$this->slug               = $slug;
		$this->label              = $label;
		$this->default_base_url   = $default_base_url;
		$this->supported_services = $supported_services;
		$this->extra_fields       = $extra_fields;
		$this->is_custom          = $is_custom;
	}

	/**
	 * Convert to associative array for serialization or merging.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'slug'               => $this->slug,
			'label'              => $this->label,
			'default_base_url'   => $this->default_base_url,
			'supported_services' => $this->supported_services,
			'extra_fields'       => $this->extra_fields,
			'is_custom'          => $this->is_custom,
		);
	}
}
