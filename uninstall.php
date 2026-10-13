<?php
/**
 * UNINSTALL
 *
 * Fires only on full plugin deletion via the WordPress Plugins screen.
 * Clears all plugin data only if the user opted in via the Advanced settings.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

if ( ! get_option( 'rolevisibility_clear_data_on_uninstall' ) ) {
    return;
}

global $wpdb;

$rolevisibility_options = [
    'rolevisibility_no_access_page',
    'rolevisibility_login_url',
    'rolevisibility_register_url',
    'rolevisibility_admin_bypass',
    'rolevisibility_post_types',
    'rolevisibility_roles',
    'rolevisibility_hide',
    'rolevisibility_clear_data_on_uninstall',
    'rolevisibility_restricted_index',
    'rolevisibility_template_index',
    'rolevisibility_db_version',
    'rolevisibility_migration_lock',
    'rolevisibility_migration_error',
    'rolevisibility_legacy_checked',
    'rolevisibility_activated_time',
];

foreach ( $rolevisibility_options as $rolevisibility_option ) {
    delete_option( $rolevisibility_option );
}

delete_transient( 'rolevisibility_migration_notice' );
delete_transient( 'rolevisibility_logo_info' );
delete_metadata( 'user', 0, 'rolevisibility_per_page', '', true );

$rolevisibility_template_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'rolevisibility_tpl' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time cleanup on uninstall; the post type is not registered here.

foreach ( $rolevisibility_template_ids as $rolevisibility_template_id ) {
    wp_delete_post( (int) $rolevisibility_template_id, true );
}

delete_post_meta_by_key( '_rolevisibility_access' );
delete_post_meta_by_key( '_rolevisibility_menu_access' );
