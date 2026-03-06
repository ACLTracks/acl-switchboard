<?php
/**
 * Dashboard admin page view.
 *
 * Variables available (set by Admin_Controller::render_dashboard):
 *  - $registry      Provider_Registry
 *  - $store         Provider_Store
 *  - $router        Service_Router
 *  - $service_types array<string, string>
 *  - $service_map   array<string, string|null>
 *  - $providers     array<string, array>
 *  - $enabled_count int
 *
 * @package ACL_Switchboard
 */

use ACL_Switchboard\Admin\Admin_Controller;
use ACL_Switchboard\Providers\Provider_Store;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Display any flash notices (user-scoped to prevent cross-user leakage).
$notice_key = Admin_Controller::get_notice_transient_key();
$notice     = get_transient( $notice_key );
if ( $notice ) {
	delete_transient( $notice_key );
}

$total_providers = count( $providers );
$unmapped        = 0;
foreach ( $service_map as $service => $provider_slug ) {
	if ( empty( $provider_slug ) ) {
		$unmapped++;
	}
}
?>
<div class="wrap acl-switchboard-wrap">
	<h1><?php esc_html_e( 'ACL Switchboard', 'acl-switchboard' ); ?></h1>
	<p class="acl-switchboard-tagline">
		<?php esc_html_e( 'Central AI provider registry and routing hub for WordPress.', 'acl-switchboard' ); ?>
	</p>

	<?php if ( $notice && is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
		<div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Status Cards -->
	<div class="acl-switchboard-cards">
		<div class="acl-switchboard-card">
			<h3><?php echo esc_html( $total_providers ); ?></h3>
			<p><?php esc_html_e( 'Configured Providers', 'acl-switchboard' ); ?></p>
		</div>
		<div class="acl-switchboard-card">
			<h3><?php echo esc_html( $enabled_count ); ?></h3>
			<p><?php esc_html_e( 'Enabled Providers', 'acl-switchboard' ); ?></p>
		</div>
		<div class="acl-switchboard-card <?php echo $unmapped > 0 ? 'acl-switchboard-card--warning' : ''; ?>">
			<h3><?php echo esc_html( $unmapped ); ?></h3>
			<p><?php esc_html_e( 'Unmapped Services', 'acl-switchboard' ); ?></p>
		</div>
		<div class="acl-switchboard-card">
			<h3><?php echo esc_html( count( $service_types ) ); ?></h3>
			<p><?php esc_html_e( 'Service Types', 'acl-switchboard' ); ?></p>
		</div>
	</div>

	<!-- Service Routing Overview -->
	<h2><?php esc_html_e( 'Service Routing Overview', 'acl-switchboard' ); ?></h2>
	<table class="widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Service Type', 'acl-switchboard' ); ?></th>
				<th><?php esc_html_e( 'Default Provider', 'acl-switchboard' ); ?></th>
				<th><?php esc_html_e( 'Status', 'acl-switchboard' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $service_types as $service_slug => $service_label ) : ?>
				<?php
				$mapped_slug  = $service_map[ $service_slug ] ?? null;
				$mapped_label = '';
				$status_class = 'acl-status--unmapped';
				$status_text  = __( 'Not assigned', 'acl-switchboard' );

				if ( $mapped_slug && isset( $providers[ $mapped_slug ] ) ) {
					$mapped_label = $providers[ $mapped_slug ]['label'] ?? $mapped_slug;
					if ( ! empty( $providers[ $mapped_slug ]['enabled'] ) ) {
						$status_class = 'acl-status--active';
						$status_text  = __( 'Active', 'acl-switchboard' );
					} else {
						$status_class = 'acl-status--disabled';
						$status_text  = __( 'Provider disabled', 'acl-switchboard' );
					}
				} elseif ( $mapped_slug ) {
					$mapped_label = $mapped_slug;
					$status_class = 'acl-status--missing';
					$status_text  = __( 'Provider not configured', 'acl-switchboard' );
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $service_label ); ?></strong></td>
					<td>
						<?php if ( $mapped_slug ) : ?>
							<?php echo esc_html( $mapped_label ); ?>
						<?php else : ?>
							<em><?php esc_html_e( '— Not assigned —', 'acl-switchboard' ); ?></em>
						<?php endif; ?>
					</td>
					<td>
						<span class="acl-status-badge <?php echo esc_attr( $status_class ); ?>">
							<?php echo esc_html( $status_text ); ?>
						</span>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p style="margin-top: 1em;">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=acl-switchboard-providers' ) ); ?>" class="button">
			<?php esc_html_e( 'Manage Providers', 'acl-switchboard' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=acl-switchboard-services' ) ); ?>" class="button">
			<?php esc_html_e( 'Configure Routing', 'acl-switchboard' ); ?>
		</a>
	</p>

	<!-- Quick Info -->
	<div class="acl-switchboard-info-box">
		<h3><?php esc_html_e( 'For Plugin Developers', 'acl-switchboard' ); ?></h3>
		<p><?php esc_html_e( 'Other ACL plugins can query the switchboard via the PHP API:', 'acl-switchboard' ); ?></p>
		<pre><code>if ( function_exists( 'acl_switchboard' ) ) {
    $slug  = acl_switchboard()-&gt;get_default_provider_for_service( 'chat' );
    $creds = acl_switchboard()-&gt;get_provider_credentials( $slug );
}</code></pre>
	</div>
</div>
