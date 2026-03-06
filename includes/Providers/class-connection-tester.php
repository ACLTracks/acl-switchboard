<?php
/**
 * Connection tester.
 *
 * Dispatches health-check / connection-test requests to providers.
 *
 * For v1, basic tests are implemented for providers with simple, well-known
 * endpoints (OpenAI models list, Anthropic messages with empty body to check
 * auth, etc.). Providers without a practical test endpoint return a
 * "not implemented" result.
 *
 * Future versions can introduce a Provider_Adapter interface with a
 * test_connection() method for each provider.
 *
 * @package ACL_Switchboard
 */

namespace ACL_Switchboard\Providers;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Connection_Tester {

	/**
	 * Test the connection for a given provider.
	 *
	 * @param string         $slug  Provider slug.
	 * @param Provider_Store $store The provider store instance.
	 * @return array{success: bool, message: string}
	 */
	public static function test( string $slug, Provider_Store $store ): array {
		$credentials = $store->get_credentials( $slug );

		if ( null === $credentials || empty( $credentials['api_key'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No API key configured for this provider.', 'acl-switchboard' ),
			);
		}

		$result = match ( $slug ) {
			'openai'    => self::test_openai( $credentials ),
			'anthropic' => self::test_anthropic( $credentials ),
			'google_ai' => self::test_google_ai( $credentials ),
			'groq'      => self::test_groq( $credentials ),
			'elevenlabs' => self::test_elevenlabs( $credentials ),
			'deepgram'  => self::test_deepgram( $credentials ),
			'assemblyai' => self::test_assemblyai( $credentials ),
			'stability' => self::test_stability( $credentials ),
			'replicate' => self::test_replicate( $credentials ),
			'fal'       => self::test_fal( $credentials ),
			default     => self::test_generic( $credentials ),
		};

		/**
		 * Fires after a connection test completes.
		 *
		 * @param string $slug   The provider slug.
		 * @param array  $result The test result array.
		 */
		do_action( 'acl_switchboard_connection_test_result', $slug, $result );

		return $result;
	}

	/**
	 * Test OpenAI connection by listing models.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_openai( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.openai.com/v1';
		$headers  = array(
			'Authorization' => 'Bearer ' . $credentials['api_key'],
			'Content-Type'  => 'application/json',
		);

		if ( ! empty( $credentials['org_id'] ) ) {
			$headers['OpenAI-Organization'] = $credentials['org_id'];
		}

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'models',
			array(
				'headers' => $headers,
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'OpenAI' );
	}

	/**
	 * Test Anthropic connection by hitting the messages endpoint.
	 *
	 * A minimal request that will return a 400 (bad request) if the key is valid,
	 * or 401 if the key is invalid. We treat 400 as success because it means
	 * authentication passed.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_anthropic( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.anthropic.com/v1';

		$response = wp_remote_post(
			trailingslashit( $base_url ) . 'messages',
			array(
				'headers' => array(
					'x-api-key'         => $credentials['api_key'],
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model'      => 'claude-sonnet-4-20250514',
					'max_tokens' => 1,
					'messages'   => array(),
				) ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Anthropic connection failed: %s', 'acl-switchboard' ),
					$response->get_error_message()
				),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 400 = auth passed but request is malformed (expected). 200 = also fine.
		if ( in_array( $code, array( 200, 400 ), true ) ) {
			return array(
				'success' => true,
				'message' => __( 'Anthropic connection successful. API key is valid.', 'acl-switchboard' ),
			);
		}

		if ( 401 === $code || 403 === $code ) {
			return array(
				'success' => false,
				'message' => __( 'Anthropic authentication failed. Check your API key.', 'acl-switchboard' ),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Anthropic returned unexpected status code: %d', 'acl-switchboard' ),
				$code
			),
		);
	}

	/**
	 * Test Google AI connection by listing models.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_google_ai( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://generativelanguage.googleapis.com/v1beta';
		$url      = trailingslashit( $base_url ) . 'models?key=' . $credentials['api_key'];

		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		return self::evaluate_response( $response, 'Google AI' );
	}

	/**
	 * Test Groq connection by listing models (OpenAI-compatible).
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_groq( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.groq.com/openai/v1';

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'models',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $credentials['api_key'],
				),
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'Groq' );
	}

	/**
	 * Test ElevenLabs connection by hitting the user info endpoint.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_elevenlabs( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.elevenlabs.io/v1';

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'user',
			array(
				'headers' => array(
					'xi-api-key' => $credentials['api_key'],
				),
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'ElevenLabs' );
	}

	/**
	 * Test Deepgram connection by checking project info.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_deepgram( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.deepgram.com/v1';

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'projects',
			array(
				'headers' => array(
					'Authorization' => 'Token ' . $credentials['api_key'],
				),
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'Deepgram' );
	}

	/**
	 * Test AssemblyAI connection.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_assemblyai( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.assemblyai.com/v2';

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'transcript?limit=1',
			array(
				'headers' => array(
					'Authorization' => $credentials['api_key'],
				),
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'AssemblyAI' );
	}

	/**
	 * Test Stability AI connection.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_stability( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.stability.ai/v1';

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'engines/list',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $credentials['api_key'],
				),
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'Stability AI' );
	}

	/**
	 * Test Replicate connection by listing models.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_replicate( array $credentials ): array {
		$base_url = $credentials['base_url'] ?: 'https://api.replicate.com/v1';

		$response = wp_remote_get(
			trailingslashit( $base_url ) . 'models?limit=1',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $credentials['api_key'],
				),
				'timeout' => 15,
			)
		);

		return self::evaluate_response( $response, 'Replicate' );
	}

	/**
	 * Test Fal connection.
	 *
	 * Fal doesn't have a dedicated "list" or "ping" endpoint, so this is a
	 * best-effort check. Returns a not-implemented notice.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_fal( array $credentials ): array {
		// Fal uses per-model endpoints with no generic auth check endpoint.
		return array(
			'success' => false,
			'message' => __( 'Fal does not provide a generic health-check endpoint. The API key will be validated when you make your first request.', 'acl-switchboard' ),
		);
	}

	/**
	 * Generic test for custom / unknown providers.
	 *
	 * @param array $credentials Provider credentials.
	 * @return array{success: bool, message: string}
	 */
	private static function test_generic( array $credentials ): array {
		if ( empty( $credentials['base_url'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'No base URL configured. Cannot test connection for a custom provider without a base URL.', 'acl-switchboard' ),
			);
		}

		// Try a simple HEAD request to the base URL.
		$response = wp_remote_head( $credentials['base_url'], array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Connection failed: %s', 'acl-switchboard' ),
					$response->get_error_message()
				),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return array(
			'success' => $code >= 200 && $code < 500,
			'message' => sprintf(
				/* translators: %d: HTTP status code */
				__( 'Base URL responded with HTTP %d. Note: API key validity was not checked for custom providers.', 'acl-switchboard' ),
				$code
			),
		);
	}

	/**
	 * Evaluate a standard wp_remote response for success/failure.
	 *
	 * @param array|\WP_Error $response     The wp_remote response.
	 * @param string          $provider_name Human-readable provider name.
	 * @return array{success: bool, message: string}
	 */
	private static function evaluate_response( $response, string $provider_name ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: 1: provider name, 2: error message */
					__( '%1$s connection failed: %2$s', 'acl-switchboard' ),
					$provider_name,
					$response->get_error_message()
				),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return array(
				'success' => true,
				'message' => sprintf(
					/* translators: %s: provider name */
					__( '%s connection successful. API key is valid.', 'acl-switchboard' ),
					$provider_name
				),
			);
		}

		if ( 401 === $code || 403 === $code ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: provider name */
					__( '%s authentication failed. Check your API key.', 'acl-switchboard' ),
					$provider_name
				),
			);
		}

		return array(
			'success' => false,
			'message' => sprintf(
				/* translators: 1: provider name, 2: HTTP status code */
				__( '%1$s returned unexpected status code: %2$d', 'acl-switchboard' ),
				$provider_name,
				$code
			),
		);
	}
}
