<?php
/**
 * Settings admin page view.
 *
 * Variables available (set by Admin_Controller::render_settings):
 *  - $settings array
 *
 * @package ACL_Switchboard
 */

use ACL_Switchboard\Admin\Admin_Controller;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Display any flash notices (user-scoped).
$notice_key = Admin_Controller::get_notice_transient_key();
$notice     = get_transient( $notice_key );
if ( $notice ) {
	delete_transient( $notice_key );
}

$delete_on_uninstall = ! empty( $settings['delete_data_on_uninstall'] );
?>
<div class="wrap acl-switchboard-wrap">
	<h1><?php esc_html_e( 'Settings', 'acl-switchboard' ); ?></h1>

	<?php if ( $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
		<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="acl_switchboard_save_settings">
		<?php wp_nonce_field( 'acl_switchboard_save_settings' ); ?>

		<table class="form-table" role="presentation">

			<!-- Uninstall behavior -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Data Removal', 'acl-switchboard' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1"
							<?php checked( $delete_on_uninstall ); ?>>
						<?php esc_html_e( 'Delete all ACL Switchboard data when the plugin is uninstalled', 'acl-switchboard' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'When checked, all provider configurations, service mappings, and settings will be permanently removed from the database if you delete this plugin. Leave unchecked to preserve data across reinstalls.', 'acl-switchboard' ); ?>
					</p>
				</td>
			</tr>

		</table>

		<?php submit_button( __( 'Save Settings', 'acl-switchboard' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Plugin Information', 'acl-switchboard' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Version', 'acl-switchboard' ); ?></th>
			<td><?php echo esc_html( ACL_SWITCHBOARD_VERSION ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'PHP Version', 'acl-switchboard' ); ?></th>
			<td><?php echo esc_html( PHP_VERSION ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WordPress Version', 'acl-switchboard' ); ?></th>
			<td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Configured Providers', 'acl-switchboard' ); ?></th>
			<td>
				<?php
				$providers = get_option( 'acl_switchboard_providers', array() );
				echo esc_html( is_array( $providers ) ? count( $providers ) : 0 );
				?>
			</td>
		</tr>
	</table>

	<hr>

	<h2><?php esc_html_e( 'Security Notes', 'acl-switchboard' ); ?></h2>
	<div class="acl-switchboard-info-box">
		<p>
			<?php esc_html_e( 'API keys are stored in the WordPress database (wp_options table). They are masked in the admin UI and never exposed to the frontend.', 'acl-switchboard' ); ?>
		</p>
		<p>
			<?php esc_html_e( 'For additional security, you can implement encryption at rest by adding filters to your theme or a custom plugin:', 'acl-switchboard' ); ?>
		</p>
		<pre><code>// In wp-config.php:
define( 'ACL_SWITCHBOARD_ENCRYPTION_KEY', 'your-secret-key-here' );

// In a custom plugin or functions.php:
add_filter( 'acl_switchboard_encrypt_key', function( $key, $slug ) {
    // Your encryption logic here using ACL_SWITCHBOARD_ENCRYPTION_KEY
    return $encrypted_key;
}, 10, 2 );

add_filter( 'acl_switchboard_decrypt_key', function( $key, $slug ) {
    // Your decryption logic here using ACL_SWITCHBOARD_ENCRYPTION_KEY
    return $decrypted_key;
}, 10, 2 );</code></pre>
	</div>
</div>
