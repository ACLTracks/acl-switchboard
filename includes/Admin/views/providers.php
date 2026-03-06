<?php
/**
 * Providers list admin page view.
 *
 * Variables available (set by Admin_Controller::render_providers):
 *  - $registry      Provider_Registry
 *  - $store         Provider_Store
 *  - $router        Service_Router
 *  - $service_types array<string, string>
 *  - $providers     array<string, array>  (saved configs)
 *  - $all_providers array<string, Provider_Definition> (registry catalog)
 *
 * @package ACL_Switchboard
 */

use ACL_Switchboard\Admin\Admin_Controller;
use ACL_Switchboard\Providers\Provider_Store;

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

$add_url = admin_url( 'admin.php?page=acl-switchboard-providers&action=add' );
?>
<div class="wrap acl-switchboard-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Providers', 'acl-switchboard' ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action">
		<?php esc_html_e( 'Add Provider', 'acl-switchboard' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ( $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
		<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( empty( $providers ) ) : ?>
		<div class="acl-switchboard-empty-state">
			<p><?php esc_html_e( 'No providers configured yet. Add your first AI provider to get started.', 'acl-switchboard' ); ?></p>
			<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary">
				<?php esc_html_e( 'Add Provider', 'acl-switchboard' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="widefat fixed striped acl-switchboard-providers-table">
			<thead>
				<tr>
					<th class="column-name"><?php esc_html_e( 'Provider', 'acl-switchboard' ); ?></th>
					<th class="column-status"><?php esc_html_e( 'Status', 'acl-switchboard' ); ?></th>
					<th class="column-services"><?php esc_html_e( 'Services', 'acl-switchboard' ); ?></th>
					<th class="column-apikey"><?php esc_html_e( 'API Key', 'acl-switchboard' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'acl-switchboard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $providers as $slug => $config ) : ?>
					<?php
					$edit_url = admin_url( 'admin.php?page=acl-switchboard-providers&action=edit&provider=' . urlencode( $slug ) );

					// Delete URL goes to admin.php (not admin-post.php) because the
					// handler is registered on admin_action_, which fires from admin.php
					// for the 'action' query parameter on GET requests.
					$delete_url = wp_nonce_url(
						admin_url( 'admin.php?action=acl_switchboard_delete_provider&provider=' . urlencode( $slug ) ),
						'acl_switchboard_delete_provider_' . $slug
					);

					$is_enabled = ! empty( $config['enabled'] );
					$label      = $config['label'] ?? $slug;
					$api_key    = $config['api_key'] ?? '';
					$services   = $config['services'] ?? array();
					?>
					<tr>
						<td class="column-name">
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>">
									<?php echo esc_html( $label ); ?>
								</a>
							</strong>
							<br>
							<span class="description"><?php echo esc_html( $slug ); ?></span>
						</td>
						<td class="column-status">
							<?php if ( $is_enabled ) : ?>
								<span class="acl-status-badge acl-status--active">
									<?php esc_html_e( 'Enabled', 'acl-switchboard' ); ?>
								</span>
							<?php else : ?>
								<span class="acl-status-badge acl-status--disabled">
									<?php esc_html_e( 'Disabled', 'acl-switchboard' ); ?>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-services">
							<?php if ( ! empty( $services ) ) : ?>
								<?php
								$labels = array();
								foreach ( $services as $svc ) {
									$labels[] = $service_types[ $svc ] ?? $svc;
								}
								echo esc_html( implode( ', ', $labels ) );
								?>
							<?php else : ?>
								<em><?php esc_html_e( 'None', 'acl-switchboard' ); ?></em>
							<?php endif; ?>
						</td>
						<td class="column-apikey">
							<?php if ( ! empty( $api_key ) ) : ?>
								<code class="acl-masked-key"><?php echo esc_html( Provider_Store::mask_key( $api_key ) ); ?></code>
							<?php else : ?>
								<em><?php esc_html_e( 'Not set', 'acl-switchboard' ); ?></em>
							<?php endif; ?>
						</td>
						<td class="column-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small">
								<?php esc_html_e( 'Edit', 'acl-switchboard' ); ?>
							</a>
							<button type="button"
								class="button button-small acl-test-connection-btn"
								data-provider="<?php echo esc_attr( $slug ); ?>">
								<?php esc_html_e( 'Test', 'acl-switchboard' ); ?>
							</button>
							<a href="<?php echo esc_url( $delete_url ); ?>"
								class="button button-small button-link-delete"
								onclick="return confirm('<?php echo esc_js( __( 'Delete this provider and its credentials?', 'acl-switchboard' ) ); ?>');">
								<?php esc_html_e( 'Delete', 'acl-switchboard' ); ?>
							</a>
							<span class="acl-test-result" data-provider="<?php echo esc_attr( $slug ); ?>"></span>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
