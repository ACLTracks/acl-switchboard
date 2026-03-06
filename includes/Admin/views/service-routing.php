<?php
/**
 * Service routing admin page view.
 *
 * Variables available (set by Admin_Controller::render_service_routing):
 *  - $store         Provider_Store
 *  - $router        Service_Router
 *  - $service_types array<string, string>
 *  - $service_map   array<string, string|null>
 *  - $providers     array<string, array> (saved configs)
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
?>
<div class="wrap acl-switchboard-wrap">
	<h1><?php esc_html_e( 'Service Routing', 'acl-switchboard' ); ?></h1>
	<p>
		<?php esc_html_e( 'Assign a default AI provider for each service type. When a downstream plugin requests a provider for a service, this mapping determines which provider is returned.', 'acl-switchboard' ); ?>
	</p>

	<?php if ( $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
		<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $providers ) ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: link to providers page */
					esc_html__( 'No providers are configured yet. %s first, then return here to map them to services.', 'acl-switchboard' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=acl-switchboard-providers&action=add' ) ) . '">'
					. esc_html__( 'Add a provider', 'acl-switchboard' )
					. '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="acl_switchboard_save_service_map">
		<?php wp_nonce_field( 'acl_switchboard_save_service_map' ); ?>

		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 30%;"><?php esc_html_e( 'Service Type', 'acl-switchboard' ); ?></th>
					<th style="width: 40%;"><?php esc_html_e( 'Default Provider', 'acl-switchboard' ); ?></th>
					<th style="width: 30%;"><?php esc_html_e( 'Available Providers', 'acl-switchboard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $service_types as $svc_slug => $svc_label ) : ?>
					<?php
					$current_provider = $service_map[ $svc_slug ] ?? '';

					// Find providers that support this service type.
					$compatible = array();
					foreach ( $providers as $p_slug => $p_config ) {
						$p_services = $p_config['services'] ?? array();
						if ( in_array( $svc_slug, $p_services, true ) ) {
							$compatible[ $p_slug ] = $p_config;
						}
					}
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $svc_label ); ?></strong>
							<br>
							<code><?php echo esc_html( $svc_slug ); ?></code>
						</td>
						<td>
							<select name="service_map[<?php echo esc_attr( $svc_slug ); ?>]" class="regular-text">
								<option value=""><?php esc_html_e( '— Not assigned —', 'acl-switchboard' ); ?></option>
								<?php foreach ( $compatible as $p_slug => $p_config ) : ?>
									<?php
									$p_label    = $p_config['label'] ?? $p_slug;
									$p_enabled  = ! empty( $p_config['enabled'] );
									$label_text = $p_label;
									if ( ! $p_enabled ) {
										$label_text .= ' ' . __( '(disabled)', 'acl-switchboard' );
									}
									?>
									<option value="<?php echo esc_attr( $p_slug ); ?>"
										<?php selected( $current_provider, $p_slug ); ?>>
										<?php echo esc_html( $label_text ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<?php if ( empty( $compatible ) ) : ?>
								<em><?php esc_html_e( 'No providers support this service', 'acl-switchboard' ); ?></em>
							<?php else : ?>
								<?php
								$names = array();
								foreach ( $compatible as $p_config ) {
									$names[] = $p_config['label'] ?? '?';
								}
								echo esc_html( implode( ', ', $names ) );
								?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save Routing', 'acl-switchboard' ) ); ?>
	</form>
</div>
