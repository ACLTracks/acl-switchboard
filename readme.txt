=== ACL Switchboard ===
Contributors: aclplugins
Tags: ai, api, providers, openai, anthropic, switchboard, credentials
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Central AI provider registry and routing hub for WordPress. Store API credentials, map services to providers, and expose a PHP API for the ACL plugin ecosystem.

== Description ==

ACL Switchboard is infrastructure for WordPress sites that use AI services. Instead of configuring API keys in every individual plugin, you configure them once in the Switchboard and let downstream plugins query for credentials and routing.

**What it does:**

* Stores API keys and configuration for multiple AI providers (OpenAI, Anthropic, Google AI, ElevenLabs, Stability AI, Replicate, Fal, Deepgram, AssemblyAI, Groq, and custom providers)
* Maps service types (chat, image generation, TTS, transcription, etc.) to default providers
* Exposes a clean PHP API that other plugins can call
* Provides connection testing for configured providers
* Keeps all credential management in one admin screen

**What it does NOT do:**

* It is not a chatbot, image generator, or AI tool itself
* It does not make API calls on behalf of other plugins
* It does not expose any frontend functionality

**For Plugin Developers:**

Other plugins can query the Switchboard like this:

    if ( function_exists( 'acl_switchboard' ) ) {
        $slug  = acl_switchboard()->get_default_provider_for_service( 'chat' );
        $creds = acl_switchboard()->get_provider_credentials( $slug );
    }

== Installation ==

1. Upload the `acl-switchboard` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to ACL Switchboard in the admin menu
4. Add and configure your AI providers
5. Map service types to default providers

== Frequently Asked Questions ==

= Are API keys encrypted? =

API keys are stored in the WordPress database using the same pattern as other credential-storing plugins (WooCommerce, Mailchimp, etc.). The plugin provides filter hooks (`acl_switchboard_encrypt_key` and `acl_switchboard_decrypt_key`) so you can implement encryption at rest using a key stored in wp-config.php.

= Can I add custom providers? =

Yes. Use the "Custom Provider" type when adding a provider, or use the `acl_switchboard_providers_registered` filter to register custom provider definitions programmatically.

= Does this plugin make any external API calls? =

Only when you explicitly click "Test Connection" for a provider. No background calls are made.

== Changelog ==

= 1.0.0 =
* Initial release
* Provider registry with 10 built-in providers plus custom provider support
* Service routing for 9 service types
* Connection testing
* PHP API facade for downstream plugins
