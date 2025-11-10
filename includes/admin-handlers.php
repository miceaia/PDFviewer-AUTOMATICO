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

        $allowed_services = array( 'google', 'dropbox', 'sharepoint' );

        if ( ! in_array( $service, $allowed_services, true ) ) {
            throw new Exception( 'Servicio inválido.' );
        }

        $updates = array(
            'client_id'     => isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
            'client_secret' => isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '',
            'refresh_token' => isset( $_POST['refresh_token'] ) ? sanitize_text_field( wp_unslash( $_POST['refresh_token'] ) ) : '',
        );

        if ( 'sharepoint' === $service ) {
            $updates['tenant_id'] = isset( $_POST['tenant_id'] ) ? sanitize_text_field( wp_unslash( $_POST['tenant_id'] ) ) : '';
        }

        $existing = cloudsync_get_service_credentials( $service );

        if ( empty( $existing['client_id'] ) && '' === $updates['client_id'] ) {
            throw new Exception( 'Client ID es obligatorio.' );
        }

        if ( empty( $existing['client_secret'] ) && '' === $updates['client_secret'] ) {
            throw new Exception( 'Client Secret es obligatorio.' );
        }

        cloudsync_store_service_credentials( $service, $updates );

        error_log( sprintf( '[CloudSync] Credentials stored for %s (client:%s, secret:%s, token:%s)', $service, '' === $updates['client_id'] ? 'kept' : 'updated', '' === $updates['client_secret'] ? 'kept' : 'updated', '' === $updates['refresh_token'] ? ( empty( $existing['refresh_token'] ) ? 'empty' : 'kept' ) : 'updated' ) );

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

        $allowed_services = array( 'google', 'dropbox', 'sharepoint' );

        if ( ! in_array( $service, $allowed_services, true ) ) {
            throw new Exception( 'Servicio inválido.' );
        }

        $credentials = cloudsync_get_service_credentials( $service );
        $state       = wp_create_nonce( 'cloudsync_oauth_state_' . $service );
        $redirect    = cloudsync_get_oauth_redirect_uri( $service );
        $auth_url    = '';

        switch ( $service ) {
            case 'google':
                if ( empty( $credentials['client_id'] ) ) {
                    throw new Exception( 'Falta Client ID de Google Drive.' );
                }

                $auth_url = add_query_arg(
                    array(
                        'response_type'         => 'code',
                        'client_id'             => $credentials['client_id'],
                        'redirect_uri'          => $redirect,
                        'scope'                 => 'https://www.googleapis.com/auth/drive',
                        'access_type'           => 'offline',
                        'prompt'                => 'consent',
                        'include_granted_scopes'=> 'true',
                        'state'                 => $state,
                    ),
                    'https://accounts.google.com/o/oauth2/v2/auth'
                );
                break;

            case 'dropbox':
                if ( empty( $credentials['client_id'] ) ) {
                    throw new Exception( 'Falta App Key de Dropbox.' );
                }

                $auth_url = add_query_arg(
                    array(
                        'response_type'    => 'code',
                        'client_id'        => $credentials['client_id'],
                        'redirect_uri'     => $redirect,
                        'scope'            => 'files.metadata.read files.metadata.write',
                        'token_access_type'=> 'offline',
                        'state'            => $state,
                    ),
                    'https://www.dropbox.com/oauth2/authorize'
                );
                break;

            case 'sharepoint':
                if ( empty( $credentials['client_id'] ) ) {
                    throw new Exception( 'Falta Client ID de SharePoint.' );
                }

                $tenant  = ! empty( $credentials['tenant_id'] ) ? $credentials['tenant_id'] : 'common';
                $auth_url = add_query_arg(
                    array(
                        'client_id'     => $credentials['client_id'],
                        'response_type' => 'code',
                        'redirect_uri'  => $redirect,
                        'response_mode' => 'query',
                        'scope'         => 'offline_access https://graph.microsoft.com/.default',
                        'state'         => $state,
                    ),
                    sprintf( 'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize', rawurlencode( $tenant ) )
                );
                break;
        }

        if ( empty( $auth_url ) ) {
            throw new Exception( 'No se pudo construir la URL de autorización.' );
        }

        error_log( sprintf( '[CloudSync] Redirecting to OAuth consent for %s.', $service ) );
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
        $state   = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

        $allowed_services = array( 'google', 'dropbox', 'sharepoint' );

        if ( ! in_array( $service, $allowed_services, true ) ) {
            throw new Exception( 'Servicio no soportado en callback.' );
        }

        if ( empty( $state ) || ! wp_verify_nonce( $state, 'cloudsync_oauth_state_' . $service ) ) {
            throw new Exception( 'Estado OAuth inválido. Intenta nuevamente.' );
        }

        if ( isset( $_GET['error'] ) ) {
            $error_description = isset( $_GET['error_description'] ) ? sanitize_text_field( wp_unslash( $_GET['error_description'] ) ) : sanitize_text_field( wp_unslash( $_GET['error'] ) );
            throw new Exception( $error_description );
        }

        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

        if ( '' === $code ) {
            throw new Exception( 'Falta "code" en el callback.' );
        }

        $connector = null;

        switch ( $service ) {
            case 'google':
                $connector = new Connector_GoogleDrive();
                break;
            case 'dropbox':
                $connector = new Connector_Dropbox();
                break;
            case 'sharepoint':
                $connector = new Connector_SharePoint();
                break;
        }

        if ( ! $connector || ! method_exists( $connector, 'exchange_code_for_tokens' ) ) {
            throw new Exception( 'Conector OAuth inválido.' );
        }

        $tokens = $connector->exchange_code_for_tokens( $code );

        if ( is_wp_error( $tokens ) ) {
            throw new Exception( $tokens->get_error_message() );
        }

        if ( method_exists( $connector, 'oauth_callback' ) ) {
            $connector->oauth_callback( $tokens );
        }

        $messages = array(
            'google'     => __( '✅ Google Drive conectado correctamente.', 'secure-pdf-viewer' ),
            'dropbox'    => __( '✅ Dropbox conectado correctamente.', 'secure-pdf-viewer' ),
            'sharepoint' => __( '✅ SharePoint conectado correctamente.', 'secure-pdf-viewer' ),
        );

        if ( isset( $messages[ $service ] ) ) {
            cloudsync_notice_success( $messages[ $service ] );
        }

        cloudsync_add_log( __( 'OAuth tokens almacenados correctamente.', 'secure-pdf-viewer' ), array( 'service' => $service ) );

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
function cloudsync_handle_revoke_credentials(): void {
    try {
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Permisos insuficientes.' );
        }

        check_admin_referer( 'cloudsync_credentials_nonce' );

        $service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';

        $allowed_services = array( 'google', 'dropbox', 'sharepoint' );

        if ( ! in_array( $service, $allowed_services, true ) ) {
            throw new Exception( 'Servicio inválido.' );
        }

        $credentials = cloudsync_get_service_credentials( $service );

        switch ( $service ) {
            case 'google':
                if ( ! empty( $credentials['refresh_token'] ) ) {
                    wp_remote_post(
                        'https://oauth2.googleapis.com/revoke',
                        array(
                            'body' => array( 'token' => $credentials['refresh_token'] ),
                        )
                    );
                }
                break;
            case 'dropbox':
                $token_to_revoke = ! empty( $credentials['access_token'] ) ? $credentials['access_token'] : $credentials['refresh_token'];

                if ( $token_to_revoke && ! empty( $credentials['client_id'] ) && ! empty( $credentials['client_secret'] ) ) {
                    wp_remote_post(
                        'https://api.dropboxapi.com/2/oauth2/token/revoke',
                        array(
                            'headers' => array(
                                'Authorization' => 'Basic ' . base64_encode( $credentials['client_id'] . ':' . $credentials['client_secret'] ),
                                'Content-Type'  => 'application/x-www-form-urlencoded',
                            ),
                            'body'    => array( 'token' => $token_to_revoke ),
                        )
                    );
                }
                break;
            case 'sharepoint':
            default:
                // Microsoft Graph tokens expire naturally; no revoke endpoint is required.
                break;
        }

        cloudsync_store_service_credentials(
            $service,
            array(
                'refresh_token' => '',
                'access_token'  => '',
                'token_expires' => 0,
            ),
            false
        );

        cloudsync_notice_success( '🔒 Acceso revocado.' );
        error_log( sprintf( '[CloudSync] Credentials revoked for %s.', $service ) );
    } catch ( Throwable $e ) {
        error_log( '[CloudSync] revoke_credentials error: ' . $e->getMessage() );
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
add_action( 'admin_post_cloudsync_revoke_credentials', 'cloudsync_handle_revoke_credentials' );
add_action( 'admin_post_cloudsync_save_config', 'cloudsync_handle_save_config' );
add_action( 'admin_post_cloudsync_save_advanced', 'cloudsync_handle_save_advanced' );
add_action( 'admin_post_cloudsync_manual_sync', 'cloudsync_handle_manual_sync' );
add_action( 'admin_post_cloudsync_force_sync', 'cloudsync_handle_force_sync' );
add_action( 'admin_post_cloudsync_cleanup_orphans', 'cloudsync_handle_cleanup_orphans' );
add_action( 'admin_post_cloudsync_reset_tokens', 'cloudsync_handle_reset_tokens' );
add_action( 'admin_post_cloudsync_reinitialize_folders', 'cloudsync_handle_reinitialize_folders' );
