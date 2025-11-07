<?php
/**
 * Admin-post handlers for the CloudSync LMS dashboard.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieves the CloudSync manager instance.
 *
 * @since 4.1.5
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
 * Executes a CloudSync manager method while handling missing dependencies gracefully.
 *
 * @since 4.1.5
 *
 * @param string $method Method name to execute on the manager.
 *
 * @throws Exception When the manager or method is unavailable.
 *
 * @return void
 */
function cloudsync_call_manager_method( string $method ): void {
    $manager = cloudsync_get_manager();

    if ( ! $manager ) {
        throw new Exception( 'CloudSync manager is not available.' );
    }

    if ( ! method_exists( $manager, $method ) ) {
        throw new Exception( sprintf( 'Manager method %s is undefined.', $method ) );
    }

    $manager->{$method}();
}

/**
 * Redirects back to the referring admin screen.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_redirect_back(): void {
    $referrer = wp_get_referer();

    if ( ! $referrer ) {
        $referrer = admin_url( 'admin.php?page=cloudsync-dashboard' );
    }

    wp_safe_redirect( $referrer );
    exit;
}

/**
 * Saves OAuth credentials for a cloud service.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_save_credentials(): void {
    error_log( '[CloudSync] save_credentials POST received.' );

    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_credentials_nonce' );

        $service = isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '';

        $service_map = array(
            'drive'      => array(
                'prefix'   => 'cloudsync_drive',
                'settings' => array(
                    'client_id'     => 'google_client_id',
                    'client_secret' => 'google_client_secret',
                    'refresh_token' => 'google_refresh_token',
                ),
            ),
            'dropbox'    => array(
                'prefix'   => 'cloudsync_dropbox',
                'settings' => array(
                    'client_id'     => 'dropbox_app_key',
                    'client_secret' => 'dropbox_app_secret',
                    'refresh_token' => 'dropbox_refresh_token',
                ),
            ),
            'sharepoint' => array(
                'prefix'   => 'cloudsync_sharepoint',
                'settings' => array(
                    'client_id'     => 'sharepoint_client_id',
                    'client_secret' => 'sharepoint_secret',
                    'refresh_token' => 'sharepoint_refresh_token',
                ),
            ),
        );

        if ( ! isset( $service_map[ $service ] ) ) {
            throw new Exception( 'Servicio inválido.' );
        }

        $client_id     = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
        $client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';
        $refresh_token = isset( $_POST['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) ) : '';
        $tenant_id     = isset( $_POST['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ) : '';

        if ( '' === $client_id || '' === $client_secret ) {
            throw new Exception( 'Client ID y Client Secret son obligatorios.' );
        }

        $settings = cloudsync_get_settings();

        $settings[ $service_map[ $service ]['settings']['client_id'] ]     = $client_id;
        $settings[ $service_map[ $service ]['settings']['client_secret'] ] = $client_secret;

        if ( 'sharepoint' === $service ) {
            $settings['sharepoint_tenant_id'] = $tenant_id;
            cloudsync_opt_set( 'cloudsync_sharepoint_tenant_id', $tenant_id );
        }

        if ( '' !== $refresh_token ) {
            $settings[ $service_map[ $service ]['settings']['refresh_token'] ] = $refresh_token;
        }

        cloudsync_save_settings( $settings );

        cloudsync_opt_set( $service_map[ $service ]['prefix'] . '_client_id', $client_id );
        cloudsync_opt_set( $service_map[ $service ]['prefix'] . '_client_secret', cloudsync_encrypt( $client_secret ) );

        if ( '' !== $refresh_token ) {
            cloudsync_opt_set( $service_map[ $service ]['prefix'] . '_refresh_token', cloudsync_encrypt( $refresh_token ) );
        }

        cloudsync_notice_success( '✅ Credenciales guardadas correctamente.' );
        error_log( sprintf( '[CloudSync] Credentials saved for %s.', $service ) );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] save_credentials error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al guardar: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Initiates the OAuth consent flow.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_oauth_connect(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_credentials_nonce' );

        $service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';

        if ( 'drive' !== $service ) {
            throw new Exception( 'Solo Google Drive está habilitado para esta acción.' );
        }

        $client_id = cloudsync_opt_get( 'cloudsync_drive_client_id', '' );

        if ( '' === $client_id ) {
            $settings  = cloudsync_get_settings();
            $client_id = isset( $settings['google_client_id'] ) ? $settings['google_client_id'] : '';
        }

        if ( '' === $client_id ) {
            throw new Exception( 'Falta Client ID de Google Drive.' );
        }

        $redirect_uri = admin_url( 'admin-post.php?action=cloudsync_oauth_callback&service=drive' );
        $scope        = rawurlencode( 'https://www.googleapis.com/auth/drive.file' );
        $auth_url     = 'https://accounts.google.com/o/oauth2/v2/auth'
            . '?response_type=code'
            . '&access_type=offline&prompt=consent'
            . '&client_id=' . rawurlencode( $client_id )
            . '&redirect_uri=' . rawurlencode( $redirect_uri )
            . '&scope=' . $scope
            . '&cloudsync_popup=1';

        error_log( '[CloudSync] Redirecting to Google OAuth consent.' );
        wp_safe_redirect( $auth_url );
        exit;
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] oauth_connect error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al iniciar OAuth: ' . $e->getMessage() );
        cloudsync_redirect_back();
    }
}

/**
 * Handles the OAuth callback for Google Drive.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_oauth_callback(): void {
    try {
        $service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';

        if ( 'drive' !== $service ) {
            throw new Exception( 'Servicio no soportado en callback.' );
        }

        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

        if ( '' === $code ) {
            throw new Exception( 'Falta "code" en el callback.' );
        }

        $settings      = cloudsync_get_settings();
        $client_id     = isset( $settings['google_client_id'] ) ? $settings['google_client_id'] : '';
        $client_secret = isset( $settings['google_client_secret'] ) ? $settings['google_client_secret'] : '';
        $redirect_uri  = admin_url( 'admin-post.php?action=cloudsync_oauth_callback&service=drive' );

        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'    => array(
                    'code'          => $code,
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                    'access_type'   => 'offline',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $body ) ) {
            throw new Exception( 'Respuesta inválida de Google.' );
        }

        if ( ! empty( $body['refresh_token'] ) ) {
            $settings['google_refresh_token'] = $body['refresh_token'];
            cloudsync_save_settings( $settings );
            cloudsync_opt_set( 'cloudsync_drive_refresh_token', cloudsync_encrypt( $body['refresh_token'] ) );
            cloudsync_notice_success( '✅ Google Drive conectado correctamente.' );
            error_log( '[CloudSync] OAuth callback stored refresh token.' );
        } elseif ( ! empty( $body['access_token'] ) ) {
            cloudsync_notice_success( 'Conectado (sin refresh_token nuevo).' );
            error_log( '[CloudSync] OAuth callback without new refresh token.' );
        } else {
            throw new Exception( 'No se recibió token.' );
        }

        echo '<script>if (window.opener) { window.opener.location.reload(); } window.close();</script>';
        exit;
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] oauth_callback error: ' . $e->getMessage() );
        echo '<p>OAuth error: ' . esc_html( $e->getMessage() ) . '</p>';
        echo '<script>setTimeout(function(){ window.close(); }, 2000);</script>';
        exit;
    }
}

/**
 * Revokes stored refresh tokens for a service.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_revoke_access(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_credentials_nonce' );

        $service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';

        $service_tokens = array(
            'drive'      => array( 'option' => 'cloudsync_drive_refresh_token', 'settings_key' => 'google_refresh_token' ),
            'dropbox'    => array( 'option' => 'cloudsync_dropbox_refresh_token', 'settings_key' => 'dropbox_refresh_token' ),
            'sharepoint' => array( 'option' => 'cloudsync_sharepoint_refresh_token', 'settings_key' => 'sharepoint_refresh_token' ),
        );

        if ( ! isset( $service_tokens[ $service ] ) ) {
            throw new Exception( 'Servicio inválido.' );
        }

        $settings = cloudsync_get_settings();
        $settings[ $service_tokens[ $service ]['settings_key'] ] = '';
        cloudsync_save_settings( $settings );

        delete_option( $service_tokens[ $service ]['option'] );

        cloudsync_notice_success( '🔒 Acceso revocado.' );
        error_log( sprintf( '[CloudSync] Refresh token revoked for %s.', $service ) );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] revoke_access error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al revocar: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Saves general configuration settings.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_save_config(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_config_nonce' );

        $raw_settings = isset( $_POST['cloudsync_general_settings'] ) ? (array) wp_unslash( $_POST['cloudsync_general_settings'] ) : array();

        $manager = cloudsync_get_manager();

        if ( $manager && method_exists( $manager, 'sanitize_general_settings' ) ) {
            $manager->sanitize_general_settings( $raw_settings );
        } else {
            $existing = cloudsync_get_general_settings();

            $clean = array(
                'sync_interval'       => isset( $raw_settings['sync_interval'] ) ? sanitize_text_field( $raw_settings['sync_interval'] ) : $existing['sync_interval'],
                'auto_sync'           => ! empty( $raw_settings['auto_sync'] ) ? 1 : 0,
                'priority_mode'       => isset( $raw_settings['priority_mode'] ) ? sanitize_text_field( $raw_settings['priority_mode'] ) : $existing['priority_mode'],
                'root_google'         => isset( $raw_settings['root_google'] ) ? sanitize_text_field( $raw_settings['root_google'] ) : $existing['root_google'],
                'root_dropbox'        => isset( $raw_settings['root_dropbox'] ) ? sanitize_text_field( $raw_settings['root_dropbox'] ) : $existing['root_dropbox'],
                'root_sharepoint'     => isset( $raw_settings['root_sharepoint'] ) ? sanitize_text_field( $raw_settings['root_sharepoint'] ) : $existing['root_sharepoint'],
                'email_notifications' => ! empty( $raw_settings['email_notifications'] ) ? 1 : 0,
                'developer_mode'      => isset( $raw_settings['developer_mode'] ) ? (int) $raw_settings['developer_mode'] : (int) $existing['developer_mode'],
            );

            $valid_intervals = array( '5', '10', '30', 'manual' );
            if ( ! in_array( $clean['sync_interval'], $valid_intervals, true ) ) {
                $clean['sync_interval'] = $existing['sync_interval'];
            }

            $valid_priority = array( 'wp', 'cloud', 'bidirectional' );
            if ( ! in_array( $clean['priority_mode'], $valid_priority, true ) ) {
                $clean['priority_mode'] = $existing['priority_mode'];
            }

            cloudsync_save_general_settings( $clean );

            if ( $manager && method_exists( $manager, 'ensure_cron_schedule' ) ) {
                $manager->ensure_cron_schedule();
            }
        }

        cloudsync_notice_success( '✅ Configuración guardada.' );
        error_log( '[CloudSync] General configuration updated.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] save_config error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al guardar configuración: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Processes advanced actions submitted via checkbox form.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_save_advanced(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_advanced_nonce' );

        if ( ! empty( $_POST['cleanup_orphans'] ) ) {
            cloudsync_call_manager_method( 'handle_cleanup_meta' );
        }

        if ( ! empty( $_POST['reset_tokens'] ) ) {
            cloudsync_call_manager_method( 'handle_reset_tokens' );
        }

        $general = cloudsync_get_general_settings();
        $general['developer_mode'] = ! empty( $_POST['developer_mode'] ) ? 1 : 0;
        cloudsync_save_general_settings( $general );

        cloudsync_notice_success( '✅ Acciones avanzadas completadas.' );
        error_log( '[CloudSync] Advanced actions executed.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] save_advanced error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error en avanzado: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Executes a manual synchronization request.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_manual_sync(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_manual_sync' );

        cloudsync_call_manager_method( 'handle_manual_sync' );
        cloudsync_notice_success( '✅ Sincronización manual iniciada.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] manual_sync error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error durante la sincronización manual: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Executes a forced synchronization request.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_force_sync(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_force_sync' );

        cloudsync_call_manager_method( 'handle_force_sync' );
        cloudsync_notice_success( '✅ Sincronización forzada ejecutada.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] force_sync error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al forzar sincronización: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Cleans orphan metadata entries.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_cleanup_orphans(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_cleanup_meta' );

        cloudsync_call_manager_method( 'handle_cleanup_meta' );
        cloudsync_notice_success( '✅ Metadatos huérfanos limpiados.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] cleanup_orphans error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al limpiar metadatos: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Resets stored OAuth tokens for every service.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_reset_tokens(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_reset_tokens' );

        cloudsync_call_manager_method( 'handle_reset_tokens' );
        cloudsync_notice_success( '✅ Tokens OAuth reiniciados.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] reset_tokens error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al reiniciar tokens: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

/**
 * Rebuilds the folder structure for all configured services.
 *
 * @since 4.1.5
 *
 * @return void
 */
function cloudsync_handle_reinitialize_folders(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_rebuild_structure' );

        cloudsync_call_manager_method( 'handle_rebuild_structure' );
        cloudsync_notice_success( '✅ Estructura de carpetas re-inicializada.' );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] reinitialize_folders error: ' . $e->getMessage() );
        cloudsync_notice_error( '❌ Error al reinicializar carpetas: ' . $e->getMessage() );
    }

    cloudsync_redirect_back();
}

add_action( 'admin_post_cloudsync_save_credentials', 'cloudsync_handle_save_credentials' );
add_action( 'admin_post_cloudsync_oauth_connect', 'cloudsync_handle_oauth_connect' );
add_action( 'admin_post_cloudsync_oauth_callback', 'cloudsync_handle_oauth_callback' );
add_action( 'admin_post_cloudsync_revoke_access', 'cloudsync_handle_revoke_access' );
add_action( 'admin_post_cloudsync_save_config', 'cloudsync_handle_save_config' );
add_action( 'admin_post_cloudsync_save_advanced', 'cloudsync_handle_save_advanced' );
add_action( 'admin_post_cloudsync_manual_sync', 'cloudsync_handle_manual_sync' );
add_action( 'admin_post_cloudsync_force_sync', 'cloudsync_handle_force_sync' );
add_action( 'admin_post_cloudsync_cleanup_orphans', 'cloudsync_handle_cleanup_orphans' );
add_action( 'admin_post_cloudsync_reset_tokens', 'cloudsync_handle_reset_tokens' );
add_action( 'admin_post_cloudsync_reinitialize_folders', 'cloudsync_handle_reinitialize_folders' );
