<?php
/**
 * Provider registry.
 *
 * Holds the canonical catalog of known AI provider definitions. This is the
 * "what providers exist" layer, separate from "what the admin has configured"
 * (which lives in Provider_Store).
 *
 * Third-party plugins can add custom provider definitions via the
 * 'acl_switchboard_providers_registered' filter.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Provider_Registry {

	/**
	 * Registered provider definitions, keyed by slug.
	 *
	 * @var array<string, Provider_Definition>
	 */
	private array $providers = array();

	/**
	 * Whether the registry has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Get all registered provider definitions.
	 *
	 * @return array<string, Provider_Definition>
	 */
	public function get_all(): array {
		$this->maybe_initialize();
		return $this->providers;
	}

	/**
	 * Get a single provider definition by slug.
	 *
	 * @param string $slug Provider slug.
	 * @return Provider_Definition|null
	 */
	public function get( string $slug ): ?Provider_Definition {
		$this->maybe_initialize();
		return $this->providers[ $slug ] ?? null;
	}

	/**
	 * Check if a provider slug is registered.
	 *
	 * @param string $slug Provider slug.
	 * @return bool
	 */
	public function has( string $slug ): bool {
		$this->maybe_initialize();
		return isset( $this->providers[ $slug ] );
	}

	/**
	 * Get all provider slugs.
	 *
	 * @return array<string>
	 */
	public function get_slugs(): array {
		$this->maybe_initialize();
		return array_keys( $this->providers );
	}

	/**
	 * Lazy-initialize the built-in provider catalog.
	 *
	 * @return void
	 */
	private function maybe_initialize(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;
		$this->register_builtins();

		/**
		 * Filter the registered provider definitions.
		 *
		 * Allows other plugins to add or modify provider definitions.
		 *
		 * @param array<string, Provider_Definition> $providers Registered providers.
		 */
		$this->providers = apply_filters( 'acl_switchboard_providers_registered', $this->providers );
	}

	/**
	 * Register all built-in provider definitions.
	 *
	 * @return void
	 */
	private function register_builtins(): void {

		$this->register( new Provider_Definition(
			slug: 'openai',
			label: 'OpenAI',
			default_base_url: 'https://api.openai.com/v1',
			supported_services: array( 'chat', 'image', 'embeddings', 'moderation', 'text_to_speech', 'speech_to_text' ),
			extra_fields: array(
				array(
					'key'         => 'org_id',
					'label'       => 'Organization ID',
					'type'        => 'text',
					'placeholder' => 'org-xxxxxxxxxxxxxxxx',
				),
				array(
					'key'         => 'project_id',
					'label'       => 'Project ID',
					'type'        => 'text',
					'placeholder' => 'proj-xxxxxxxxxxxxxxxx',
				),
			),
		) );

		$this->register( new Provider_Definition(
			slug: 'anthropic',
			label: 'Anthropic',
			default_base_url: 'https://api.anthropic.com/v1',
			supported_services: array( 'chat' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'google_ai',
			label: 'Google AI (Gemini)',
			default_base_url: 'https://generativelanguage.googleapis.com/v1beta',
			supported_services: array( 'chat', 'embeddings', 'image' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'elevenlabs',
			label: 'ElevenLabs',
			default_base_url: 'https://api.elevenlabs.io/v1',
			supported_services: array( 'text_to_speech', 'audio_generation', 'speech_to_text' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'stability',
			label: 'Stability AI',
			default_base_url: 'https://api.stability.ai/v1',
			supported_services: array( 'image' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'replicate',
			label: 'Replicate',
			default_base_url: 'https://api.replicate.com/v1',
			supported_services: array( 'image', 'video', 'audio_generation', 'chat' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'fal',
			label: 'Fal',
			default_base_url: 'https://fal.run',
			supported_services: array( 'image', 'video' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'deepgram',
			label: 'Deepgram',
			default_base_url: 'https://api.deepgram.com/v1',
			supported_services: array( 'speech_to_text', 'transcription', 'text_to_speech' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'assemblyai',
			label: 'AssemblyAI',
			default_base_url: 'https://api.assemblyai.com/v2',
			supported_services: array( 'transcription', 'speech_to_text' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'groq',
			label: 'Groq',
			default_base_url: 'https://api.groq.com/openai/v1',
			supported_services: array( 'chat', 'speech_to_text' ),
		) );

		$this->register( new Provider_Definition(
			slug: 'custom',
			label: 'Custom Provider',
			default_base_url: '',
			supported_services: array(),
			extra_fields: array(
				array(
					'key'         => 'custom_header_name',
					'label'       => 'Auth Header Name',
					'type'        => 'text',
					'placeholder' => 'Authorization',
				),
				array(
					'key'         => 'custom_header_prefix',
					'label'       => 'Auth Header Prefix',
					'type'        => 'text',
					'placeholder' => 'Bearer',
				),
			),
			is_custom: true,
		) );
	}

	/**
	 * Register a single provider definition.
	 *
	 * @param Provider_Definition $definition The provider definition.
	 * @return void
	 */
	private function register( Provider_Definition $definition ): void {
		$this->providers[ $definition->slug ] = $definition;
	}
}
