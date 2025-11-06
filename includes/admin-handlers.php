<?php
/**
 * Registers admin-post handlers for the CloudSync dashboard.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieves the CloudSync manager instance from the main plugin.
 *
 * @since 4.1.2
 *
 * @return CloudSync_Manager|null
 */
function cloudsync_get_manager() {
    if ( ! class_exists( 'SecurePDFViewer' ) ) {
        return null;
    }

    $plugin = SecurePDFViewer::get_instance();

    if ( ! $plugin || ! method_exists( $plugin, 'get_cloudsync_manager' ) ) {
        return null;
    }

    return $plugin->get_cloudsync_manager();
}

/**
 * Proxies execution to a CloudSync manager method.
 *
 * @since 4.1.2
 *
 * @param string $method Method name to execute.
 *
 * @return void
 */
function cloudsync_call_manager_method( $method ) {
    $manager = cloudsync_get_manager();

    if ( ! $manager || ! method_exists( $manager, $method ) ) {
        wp_die( esc_html__( 'CloudSync manager is not available.', 'secure-pdf-viewer' ) );
    }

    $manager->{$method}();
}

function cloudsync_handle_manual_sync_proxy() {
    cloudsync_call_manager_method( 'handle_manual_sync' );
}
add_action( 'admin_post_cloudsync_manual_sync', 'cloudsync_handle_manual_sync_proxy' );

function cloudsync_handle_cleanup_meta_proxy() {
    cloudsync_call_manager_method( 'handle_cleanup_meta' );
}
add_action( 'admin_post_cloudsync_cleanup_meta', 'cloudsync_handle_cleanup_meta_proxy' );

function cloudsync_handle_reset_tokens_proxy() {
    cloudsync_call_manager_method( 'handle_reset_tokens' );
}
add_action( 'admin_post_cloudsync_reset_tokens', 'cloudsync_handle_reset_tokens_proxy' );

function cloudsync_handle_rebuild_structure_proxy() {
    cloudsync_call_manager_method( 'handle_rebuild_structure' );
}
add_action( 'admin_post_cloudsync_rebuild_structure', 'cloudsync_handle_rebuild_structure_proxy' );

function cloudsync_handle_toggle_dev_mode_proxy() {
    cloudsync_call_manager_method( 'handle_toggle_dev_mode' );
}
add_action( 'admin_post_cloudsync_toggle_dev_mode', 'cloudsync_handle_toggle_dev_mode_proxy' );

function cloudsync_handle_download_logs_proxy() {
    cloudsync_call_manager_method( 'handle_download_logs' );
}
add_action( 'admin_post_cloudsync_download_logs', 'cloudsync_handle_download_logs_proxy' );

function cloudsync_handle_save_credentials_proxy() {
    cloudsync_call_manager_method( 'handle_save_credentials' );
}
add_action( 'admin_post_cloudsync_save_credentials', 'cloudsync_handle_save_credentials_proxy' );

function cloudsync_handle_oauth_connect_proxy() {
    cloudsync_call_manager_method( 'handle_oauth_connect' );
}
add_action( 'admin_post_cloudsync_oauth_connect', 'cloudsync_handle_oauth_connect_proxy' );

function cloudsync_handle_oauth_callback_proxy() {
    cloudsync_call_manager_method( 'handle_oauth_callback' );
}
add_action( 'admin_post_cloudsync_oauth_callback', 'cloudsync_handle_oauth_callback_proxy' );

function cloudsync_handle_revoke_access_proxy() {
    cloudsync_call_manager_method( 'handle_revoke_access' );
}
add_action( 'admin_post_cloudsync_revoke_access', 'cloudsync_handle_revoke_access_proxy' );

function cloudsync_handle_force_sync_proxy() {
    cloudsync_call_manager_method( 'handle_force_sync' );
}
add_action( 'admin_post_cloudsync_force_sync', 'cloudsync_handle_force_sync_proxy' );
