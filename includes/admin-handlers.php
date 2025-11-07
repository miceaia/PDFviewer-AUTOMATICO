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

/**
 * Handles saving the general configuration form.
 *
 * @since 4.1.3
 *
 * @return void
 */
function cloudsync_handle_save_config() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to update settings.', 'secure-pdf-viewer' ) );
    }

    check_admin_referer( 'cloudsync_save_config', 'cloudsync_nonce' );

    $raw_settings = isset( $_POST['cloudsync_general_settings'] ) ? (array) wp_unslash( $_POST['cloudsync_general_settings'] ) : array();
    $existing     = cloudsync_get_general_settings();

    if ( ! isset( $raw_settings['developer_mode'] ) ) {
        $raw_settings['developer_mode'] = isset( $existing['developer_mode'] ) ? (int) $existing['developer_mode'] : 0;
    }

    $manager = cloudsync_get_manager();

    if ( $manager && method_exists( $manager, 'sanitize_general_settings' ) ) {
        $manager->sanitize_general_settings( $raw_settings );
    } else {
        $defaults = $existing;
        $clean    = array(
            'sync_interval'       => isset( $raw_settings['sync_interval'] ) ? sanitize_text_field( $raw_settings['sync_interval'] ) : $defaults['sync_interval'],
            'auto_sync'           => isset( $raw_settings['auto_sync'] ) ? 1 : 0,
            'priority_mode'       => isset( $raw_settings['priority_mode'] ) ? sanitize_text_field( $raw_settings['priority_mode'] ) : $defaults['priority_mode'],
            'root_google'         => isset( $raw_settings['root_google'] ) ? sanitize_text_field( $raw_settings['root_google'] ) : '',
            'root_dropbox'        => isset( $raw_settings['root_dropbox'] ) ? sanitize_text_field( $raw_settings['root_dropbox'] ) : '',
            'root_sharepoint'     => isset( $raw_settings['root_sharepoint'] ) ? sanitize_text_field( $raw_settings['root_sharepoint'] ) : '',
            'email_notifications' => isset( $raw_settings['email_notifications'] ) ? 1 : 0,
            'developer_mode'      => isset( $raw_settings['developer_mode'] ) ? (int) $raw_settings['developer_mode'] : 0,
        );

        $valid_intervals = array( '5', '10', '30', 'manual' );
        if ( ! in_array( $clean['sync_interval'], $valid_intervals, true ) ) {
            $clean['sync_interval'] = $defaults['sync_interval'];
        }

        $valid_priority = array( 'wp', 'cloud', 'bidirectional' );
        if ( ! in_array( $clean['priority_mode'], $valid_priority, true ) ) {
            $clean['priority_mode'] = $defaults['priority_mode'];
        }

        cloudsync_save_general_settings( $clean );

        if ( $manager && method_exists( $manager, 'ensure_cron_schedule' ) ) {
            $manager->ensure_cron_schedule();
        }
    }

    $redirect = wp_get_referer();

    if ( ! $redirect ) {
        $redirect = admin_url( 'admin.php?page=cloudsync-dashboard' );
    }

    $redirect = add_query_arg(
        array(
            'tab'               => 'general',
            'settings-updated'  => 'true',
        ),
        remove_query_arg( array( 'settings-updated', 'cloudsync_notice' ), $redirect )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_cloudsync_save_config', 'cloudsync_handle_save_config' );

/**
 * Handles saving advanced preferences such as developer mode.
 *
 * @since 4.1.3
 *
 * @return void
 */
function cloudsync_handle_save_advanced() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to update advanced settings.', 'secure-pdf-viewer' ) );
    }

    check_admin_referer( 'cloudsync_save_advanced', 'cloudsync_advanced_nonce' );

    $existing                  = cloudsync_get_general_settings();
    $existing['developer_mode'] = isset( $_POST['developer_mode'] ) ? 1 : 0;

    $manager = cloudsync_get_manager();

    if ( $manager && method_exists( $manager, 'sanitize_general_settings' ) ) {
        $manager->sanitize_general_settings( $existing );
    } else {
        cloudsync_save_general_settings( $existing );
    }

    cloudsync_add_log( __( 'Developer mode preference updated from dashboard.', 'secure-pdf-viewer' ) );

    $redirect = wp_get_referer();

    if ( ! $redirect ) {
        $redirect = admin_url( 'admin.php?page=cloudsync-dashboard-advanced' );
    }

    $redirect = add_query_arg(
        array(
            'tab'               => 'advanced',
            'cloudsync_notice'  => 'developer-mode',
            'settings-updated'  => 'true',
        ),
        remove_query_arg( array( 'settings-updated', 'cloudsync_notice' ), $redirect )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_cloudsync_save_advanced', 'cloudsync_handle_save_advanced' );

/**
 * Handles saving OAuth credentials securely.
 *
 * @since 4.1.3
 *
 * @return void
 */
function cloudsync_handle_save_credentials() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to update credentials.', 'secure-pdf-viewer' ) );
    }

    check_admin_referer( 'cloudsync_oauth_action', 'cloudsync_oauth_nonce' );

    $service     = isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '';
    $definitions = cloudsync_get_service_definitions();

    error_log( '[CloudSync] Received credential save request for service: ' . ( $service ? $service : 'unknown' ) );

    if ( empty( $service ) || ! isset( $definitions[ $service ] ) ) {
        error_log( '[CloudSync] Aborting credential save: invalid service identifier.' );

        $redirect = add_query_arg(
            array(
                'tab'              => 'oauth',
                'cloudsync_notice' => 'invalid-service',
            ),
            admin_url( 'admin.php?page=cloudsync-dashboard' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    $settings      = cloudsync_get_settings();
    $service_fields = isset( $definitions[ $service ]['fields'] ) ? $definitions[ $service ]['fields'] : array();
    $log_snapshot  = array();

    foreach ( $service_fields as $field_key => $field_config ) {
        $incoming_raw   = isset( $_POST[ $field_key ] ) ? wp_unslash( $_POST[ $field_key ] ) : '';
        $incoming_value = is_scalar( $incoming_raw ) ? (string) $incoming_raw : '';
        $keep_existing  = isset( $_POST[ $field_key . '_keep' ] ) && '1' === (string) wp_unslash( $_POST[ $field_key . '_keep' ] );

        if ( '' === trim( $incoming_value ) ) {
            if ( $keep_existing && isset( $settings[ $field_key ] ) && '' !== $settings[ $field_key ] ) {
                $log_snapshot[ $field_key ] = 'kept';
                continue;
            }

            $settings[ $field_key ] = '';
            $log_snapshot[ $field_key ] = 'cleared';
        } else {
            $sanitized_value          = sanitize_text_field( $incoming_value );
            $settings[ $field_key ]   = $sanitized_value;
            $log_snapshot[ $field_key ] = 'length:' . strlen( $sanitized_value );
        }
    }

    if ( function_exists( 'wp_json_encode' ) ) {
        error_log( '[CloudSync] Sanitized payload for ' . $service . ': ' . wp_json_encode( $log_snapshot ) );
    }

    if ( ! function_exists( 'openssl_encrypt' ) ) {
        error_log( '[CloudSync] Warning: OpenSSL extension is not available. Credentials will be stored without encryption.' );
    }

    try {
        cloudsync_save_settings( $settings );
    } catch ( Throwable $exception ) {
        error_log( '[CloudSync] Failed to save credentials for ' . $service . ': ' . $exception->getMessage() );

        $error_redirect = wp_get_referer();
        $fallback       = add_query_arg(
            array(
                'page'             => 'cloudsync-dashboard',
                'tab'              => 'oauth',
                'cloudsync_notice' => 'oauth-error',
            ),
            admin_url( 'admin.php' )
        );

        $error_redirect = $error_redirect ? wp_validate_redirect( $error_redirect, $fallback ) : $fallback;
        $error_redirect = remove_query_arg( array( 'settings-updated', 'cloudsync_notice' ), $error_redirect );
        $error_redirect = add_query_arg(
            array(
                'tab'              => 'oauth',
                'cloudsync_notice' => 'oauth-error',
            ),
            $error_redirect
        );

        wp_safe_redirect( $error_redirect );
        exit;
    }

    $service_label = isset( $definitions[ $service ]['label'] ) ? wp_strip_all_tags( $definitions[ $service ]['label'] ) : ucfirst( $service );

    cloudsync_add_log(
        sprintf( __( '%s credentials updated from dashboard.', 'secure-pdf-viewer' ), $service_label ),
        array( 'service' => $service )
    );

    $redirect    = wp_get_referer();
    $fallback    = add_query_arg(
        array(
            'page' => 'cloudsync-dashboard',
            'tab'  => 'oauth',
        ),
        admin_url( 'admin.php' )
    );
    $redirect = $redirect ? wp_validate_redirect( $redirect, $fallback ) : $fallback;
    $redirect = remove_query_arg( array( 'settings-updated', 'cloudsync_notice', 'service' ), $redirect );
    $redirect = add_query_arg(
        array(
            'tab'              => 'oauth',
            'service'          => $service,
            'cloudsync_notice' => 'credentials-saved',
            'settings-updated' => 'true',
        ),
        $redirect
    );

    error_log( '[CloudSync] Credentials saved successfully for ' . $service . '. Redirecting to: ' . $redirect );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_cloudsync_save_credentials', 'cloudsync_handle_save_credentials' );

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
