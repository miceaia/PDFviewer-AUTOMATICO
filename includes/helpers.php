<?php
/**
 * Helper functions for the Cloud Sync integration.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieves the cloud sync settings option.
 *
 * @since 4.0.0
 *
 * @return array<string, mixed> Associative array with credentials and tokens.
 */
function cloudsync_get_settings() {
    $defaults = array(
        'google_client_id'     => '',
        'google_client_secret' => '',
        'google_refresh_token' => '',
        'dropbox_app_key'      => '',
        'dropbox_app_secret'   => '',
        'dropbox_refresh_token'=> '',
        'sharepoint_client_id' => '',
        'sharepoint_secret'    => '',
        'sharepoint_refresh_token' => '',
    );

    $settings = get_option( 'cloudsync_settings', array() );

    if ( empty( $settings ) || ! is_array( $settings ) ) {
        return $defaults;
    }

    $settings = array_merge( $defaults, $settings );

    // Decrypt the tokens before returning them.
    foreach ( array( 'google_refresh_token', 'dropbox_refresh_token', 'sharepoint_refresh_token' ) as $key ) {
        if ( ! empty( $settings[ $key ] ) ) {
            $settings[ $key ] = cloudsync_decrypt( $settings[ $key ] );
        }
    }

    return $settings;
}

/**
 * Retrieves general dashboard settings.
 *
 * @since 4.1.0
 *
 * @return array<string, mixed> Option values for the dashboard behaviour.
 */
function cloudsync_get_general_settings() {
    $defaults = array(
        'sync_interval'      => '10',
        'auto_sync'          => 1,
        'priority_mode'      => 'bidirectional',
        'root_google'        => '',
        'root_dropbox'       => '',
        'root_sharepoint'    => '',
        'email_notifications'=> 0,
        'developer_mode'     => 0,
    );

    $settings = get_option( 'cloudsync_general_settings', array() );

    if ( empty( $settings ) || ! is_array( $settings ) ) {
        return $defaults;
    }

    return array_merge( $defaults, $settings );
}

/**
 * Persists general dashboard settings.
 *
 * @since 4.1.0
 *
 * @param array<string, mixed> $settings Settings to save.
 *
 * @return void
 */
function cloudsync_save_general_settings( $settings ) {
    update_option( 'cloudsync_general_settings', $settings );
}

/**
 * Stores cloud sync settings in the options table.
 *
 * @since 4.0.0
 *
 * @param array<string, mixed> $settings Settings array to save.
 *
 * @return void
 */
function cloudsync_save_settings( $settings ) {
    foreach ( array( 'google_refresh_token', 'dropbox_refresh_token', 'sharepoint_refresh_token' ) as $key ) {
        if ( ! empty( $settings[ $key ] ) ) {
            $settings[ $key ] = cloudsync_encrypt( $settings[ $key ] );
        }
    }

    update_option( 'cloudsync_settings', $settings );
}

/**
 * Encrypts a string before persisting it to the database.
 *
 * @since 4.0.0
 *
 * @param string $data Raw token or secret.
 *
 * @return string Encrypted value.
 */
function cloudsync_encrypt( $data ) {
    if ( empty( $data ) ) {
        return '';
    }

    $key = wp_salt( 'secure_auth' );
    $iv  = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_SALT ), 0, 16 );

    $encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );

    return base64_encode( $encrypted );
}

/**
 * Decrypts a string retrieved from the database.
 *
 * @since 4.0.0
 *
 * @param string $data Encrypted payload.
 *
 * @return string Decrypted value.
 */
function cloudsync_decrypt( $data ) {
    if ( empty( $data ) ) {
        return '';
    }

    $key = wp_salt( 'secure_auth' );
    $iv  = substr( hash( 'sha256', AUTH_KEY . SECURE_AUTH_SALT ), 0, 16 );

    $decoded = base64_decode( $data );

    if ( false === $decoded ) {
        return '';
    }

    $decrypted = openssl_decrypt( $decoded, 'AES-256-CBC', $key, 0, $iv );

    return false === $decrypted ? '' : $decrypted;
}

/**
 * Adds an entry to the cloud sync log.
 *
 * @since 4.0.0
 *
 * @param string $message Message to store in the log.
 * @param array<string, mixed> $context Optional contextual data for developers.
 *
 * @return void
 */
function cloudsync_add_log( $message, $context = array() ) {
    $logs = get_option( 'cloudsync_logs', array() );

    if ( ! is_array( $logs ) ) {
        $logs = array();
    }

    $logs[] = array(
        'time'    => current_time( 'mysql' ),
        'message' => $message,
        'context' => $context,
    );

    update_option( 'cloudsync_logs', array_slice( $logs, -200 ) );
}

/**
 * Retrieves stored log entries.
 *
 * @since 4.0.0
 *
 * @return array<int, array<string, mixed>> List of logs.
 */
function cloudsync_get_logs() {
    $logs = get_option( 'cloudsync_logs', array() );

    if ( empty( $logs ) ) {
        return array();
    }

    return $logs;
}

/**
 * Helper to transform a course or lesson name before syncing.
 *
 * @since 4.0.0
 *
 * @param string $name     Original name.
 * @param int    $post_id  Post identifier.
 *
 * @return string Filtered name ready for the remote service.
 */
function cloudsync_prepare_name( $name, $post_id ) {
    $name = apply_filters( 'cloudsync_course_folder_name', $name, $post_id );

    return wp_strip_all_tags( $name );
}
