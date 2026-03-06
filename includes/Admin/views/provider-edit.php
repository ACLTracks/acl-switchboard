<?php
/**
 * Provider add/edit admin page view.
 *
 * Variables available (set by Admin_Controller::render_providers):
 *  - $registry      Provider_Registry
 *  - $store         Provider_Store
 *  - $router        Service_Router
 *  - $service_types array<string, string>
 *  - $definition    Provider_Definition|null (registry entry)
 *  - $saved         array|null (saved config)
 *  - $is_new        bool
 *  - $slug          string (empty for new)
 *  - $all_providers array<string, Provider_Definition> (registry catalog)
 *
 * @package ACL_Switchboard
 */

use ACL_Switchboard\Providers\Provider_Store;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Merge definition defaults with saved config for form values.
$form_label    = $saved['label'] ?? ( $definition ? $definition->label : '' );
$form_enabled  = $saved ? ! empty( $saved['enabled'] ) : true;
$form_api_key  = $saved['api_key'] ?? '';
$form_base_url = $saved['base_url'] ?? ( $definition ? $definition->default_base_url : '' );
$form_org_id   = $saved['org_id'] ?? '';
$form_notes    = $saved['notes'] ?? '';
$form_services = $saved['services'] ?? ( $definition ? $definition->supported_services : array() );
$form_extra    = $saved['extra'] ?? array();

// Determine extra fields from definition.
$extra_fields = array();
if ( $definition ) {
	$extra_fields = $definition->extra_fields;
}

$page_title = $is_new
	? __( 'Add Provider', 'acl-switchboard' )
	: sprintf(
		/* translators: %s: provider name */
		__( 'Edit Provider: %s', 'acl-switchboard' ),
		$form_label
	);

$back_url = admin_url( 'admin.php?page=acl-switchboard-providers' );
?>
<div class="wrap acl-switchboard-wrap">
	<h1>
		<?php echo esc_html( $page_title ); ?>
		<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action">
			<?php esc_html_e( '← Back to Providers', 'acl-switchboard' ); ?>
		</a>
	</h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="acl_switchboard_save_provider">
		<?php wp_nonce_field( 'acl_switchboard_save_provider' ); ?>

		<?php if ( $is_new ) : ?>
			<input type="hidden" name="is_new" value="1">
		<?php else : ?>
			<input type="hidden" name="provider_slug" value="<?php echo esc_attr( $slug ); ?>">
		<?php endif; ?>

		<table class="form-table" role="presentation">

			<?php if ( $is_new ) : ?>
				<!-- Provider type selector (new providers only) -->
				<tr>
					<th scope="row">
						<label for="provider_type"><?php esc_html_e( 'Provider Type', 'acl-switchboard' ); ?></label>
					</th>
					<td>
						<select name="provider_type" id="provider_type" class="regular-text acl-provider-type-select">
							<?php foreach ( $all_providers as $def ) : ?>
								<?php
								// Skip providers that are already configured (except custom).
								$already_exists = $store->exists( $def->slug ) && ! $def->is_custom;
								?>
								<option
									value="<?php echo esc_attr( $def->slug ); ?>"
									data-base-url="<?php echo esc_attr( $def->default_base_url ); ?>"
									data-services="<?php echo esc_attr( wp_json_encode( $def->supported_services ) ); ?>"
									data-extra-fields="<?php echo esc_attr( wp_json_encode( $def->extra_fields ) ); ?>"
									data-label="<?php echo esc_attr( $def->label ); ?>"
									<?php disabled( $already_exists ); ?>
								>
									<?php echo esc_html( $def->label ); ?>
									<?php if ( $already_exists ) : ?>
										<?php esc_html_e( '(already configured)', 'acl-switchboard' ); ?>
									<?php endif; ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Select a known provider or choose "Custom Provider" for any other API.', 'acl-switchboard' ); ?>
						</p>
					</td>
				</tr>
			<?php endif; ?>

			<!-- Label -->
			<tr>
				<th scope="row">
					<label for="label"><?php esc_html_e( 'Display Name', 'acl-switchboard' ); ?></label>
				</th>
				<td>
					<input type="text" name="label" id="label" class="regular-text"
						value="<?php echo esc_attr( $form_label ); ?>"
						placeholder="<?php esc_attr_e( 'My Provider', 'acl-switchboard' ); ?>">
				</td>
			</tr>

			<!-- Enabled -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'acl-switchboard' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="enabled" value="1"
							<?php checked( $form_enabled ); ?>>
						<?php esc_html_e( 'Enabled — make this provider available to other plugins', 'acl-switchboard' ); ?>
					</label>
				</td>
			</tr>

			<!-- API Key -->
			<tr>
				<th scope="row">
					<label for="api_key"><?php esc_html_e( 'API Key', 'acl-switchboard' ); ?></label>
				</th>
				<td>
					<?php if ( ! empty( $form_api_key ) ) : ?>
						<input type="password" name="api_key" id="api_key" class="regular-text"
							value=""
							placeholder="<?php echo esc_attr( Provider_Store::mask_key( $form_api_key ) ); ?>"
							autocomplete="off">
						<p class="description">
							<?php esc_html_e( 'A key is saved. Leave blank to keep the current key, or enter a new key to replace it.', 'acl-switchboard' ); ?>
						</p>
					<?php else : ?>
						<input type="password" name="api_key" id="api_key" class="regular-text"
							value=""
							placeholder="<?php esc_attr_e( 'Enter your API key', 'acl-switchboard' ); ?>"
							autocomplete="off">
					<?php endif; ?>
				</td>
			</tr>

			<!-- Base URL -->
			<tr>
				<th scope="row">
					<label for="base_url"><?php esc_html_e( 'Base URL', 'acl-switchboard' ); ?></label>
				</th>
				<td>
					<input type="url" name="base_url" id="base_url" class="regular-text"
						value="<?php echo esc_attr( $form_base_url ); ?>"
						placeholder="https://api.example.com/v1">
					<p class="description">
						<?php esc_html_e( 'Leave blank to use the provider\'s default endpoint.', 'acl-switchboard' ); ?>
					</p>
				</td>
			</tr>

			<!-- Org / Project ID -->
			<tr>
				<th scope="row">
					<label for="org_id"><?php esc_html_e( 'Organization / Project ID', 'acl-switchboard' ); ?></label>
				</th>
				<td>
					<input type="text" name="org_id" id="org_id" class="regular-text"
						value="<?php echo esc_attr( $form_org_id ); ?>"
						placeholder="<?php esc_attr_e( 'Optional', 'acl-switchboard' ); ?>">
				</td>
			</tr>

			<!-- Extra fields from provider definition -->
			<?php if ( ! empty( $extra_fields ) ) : ?>
				<?php foreach ( $extra_fields as $field ) : ?>
					<?php
					$field_key   = $field['key'] ?? '';
					$field_label = $field['label'] ?? $field_key;
					$field_type  = $field['type'] ?? 'text';
					$field_ph    = $field['placeholder'] ?? '';
					$field_value = $form_extra[ $field_key ] ?? '';

					// Skip org_id if it's already shown above.
					if ( 'org_id' === $field_key ) {
						continue;
					}
					?>
					<tr class="acl-extra-field" data-field-key="<?php echo esc_attr( $field_key ); ?>">
						<th scope="row">
							<label for="extra_<?php echo esc_attr( $field_key ); ?>">
								<?php echo esc_html( $field_label ); ?>
							</label>
						</th>
						<td>
							<?php if ( 'textarea' === $field_type ) : ?>
								<textarea name="extra[<?php echo esc_attr( $field_key ); ?>]"
									id="extra_<?php echo esc_attr( $field_key ); ?>"
									class="regular-text"
									rows="3"
									placeholder="<?php echo esc_attr( $field_ph ); ?>"
								><?php echo esc_textarea( $field_value ); ?></textarea>
							<?php elseif ( 'password' === $field_type ) : ?>
								<input type="password"
									name="extra[<?php echo esc_attr( $field_key ); ?>]"
									id="extra_<?php echo esc_attr( $field_key ); ?>"
									class="regular-text"
									value="<?php echo esc_attr( $field_value ); ?>"
									placeholder="<?php echo esc_attr( $field_ph ); ?>"
									autocomplete="off">
							<?php else : ?>
								<input type="text"
									name="extra[<?php echo esc_attr( $field_key ); ?>]"
									id="extra_<?php echo esc_attr( $field_key ); ?>"
									class="regular-text"
									value="<?php echo esc_attr( $field_value ); ?>"
									placeholder="<?php echo esc_attr( $field_ph ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- Supported Services -->
			<tr>
				<th scope="row"><?php esc_html_e( 'Supported Services', 'acl-switchboard' ); ?></th>
				<td>
					<fieldset>
						<?php foreach ( $service_types as $svc_slug => $svc_label ) : ?>
							<label style="display: block; margin-bottom: 4px;">
								<input type="checkbox" name="services[]"
									value="<?php echo esc_attr( $svc_slug ); ?>"
									<?php checked( in_array( $svc_slug, $form_services, true ) ); ?>>
								<?php echo esc_html( $svc_label ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description">
						<?php esc_html_e( 'Select which service types this provider can handle. This controls which providers appear in the Service Routing dropdowns.', 'acl-switchboard' ); ?>
					</p>
				</td>
			</tr>

			<!-- Notes -->
			<tr>
				<th scope="row">
					<label for="notes"><?php esc_html_e( 'Notes', 'acl-switchboard' ); ?></label>
				</th>
				<td>
					<textarea name="notes" id="notes" class="large-text" rows="3"
						placeholder="<?php esc_attr_e( 'Internal notes about this provider configuration…', 'acl-switchboard' ); ?>"
					><?php echo esc_textarea( $form_notes ); ?></textarea>
				</td>
			</tr>

		</table>

		<?php submit_button( $is_new ? __( 'Add Provider', 'acl-switchboard' ) : __( 'Save Provider', 'acl-switchboard' ) ); ?>
	</form>

	<?php if ( ! $is_new && ! empty( $slug ) ) : ?>
		<hr>
		<h3><?php esc_html_e( 'Connection Test', 'acl-switchboard' ); ?></h3>
		<p>
			<button type="button" class="button acl-test-connection-btn"
				data-provider="<?php echo esc_attr( $slug ); ?>">
				<?php esc_html_e( 'Test Connection', 'acl-switchboard' ); ?>
			</button>
			<span class="acl-test-result" data-provider="<?php echo esc_attr( $slug ); ?>"></span>
		</p>
	<?php endif; ?>
</div>
