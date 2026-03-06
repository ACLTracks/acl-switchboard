<?php
/**
 * ACL Switchboard uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Removes all plugin data from the database if the admin opted in.
 *
 * @package ACL_Switchboard
 */

// Prevent direct execution.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'acl_switchboard_settings', array() );

// Only delete data if the admin explicitly opted in.
$delete_on_uninstall = isset( $settings['delete_data_on_uninstall'] ) && $settings['delete_data_on_uninstall'];

if ( $delete_on_uninstall ) {
	delete_option( 'acl_switchboard_providers' );
	delete_option( 'acl_switchboard_service_map' );
	delete_option( 'acl_switchboard_settings' );
	delete_option( 'acl_switchboard_db_version' );
}
