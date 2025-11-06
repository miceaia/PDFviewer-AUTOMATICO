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
        'google_client_id'         => '',
        'google_client_secret'     => '',
        'google_refresh_token'     => '',
        'dropbox_app_key'          => '',
        'dropbox_app_secret'       => '',
        'dropbox_refresh_token'    => '',
        'sharepoint_client_id'     => '',
        'sharepoint_secret'        => '',
        'sharepoint_tenant_id'     => '',
        'sharepoint_refresh_token' => '',
    );

    $settings = get_option( 'cloudsync_settings', array() );

    if ( empty( $settings ) || ! is_array( $settings ) ) {
        return $defaults;
    }

    $settings = array_merge( $defaults, $settings );

    $sensitive_fields = array(
        'google_client_secret',
        'google_refresh_token',
        'dropbox_app_secret',
        'dropbox_refresh_token',
        'sharepoint_secret',
        'sharepoint_refresh_token',
    );

    foreach ( $sensitive_fields as $key ) {
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
    $sensitive_fields = array(
        'google_client_secret',
        'google_refresh_token',
        'dropbox_app_secret',
        'dropbox_refresh_token',
        'sharepoint_secret',
        'sharepoint_refresh_token',
    );

    foreach ( $sensitive_fields as $key ) {
        if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {
            $settings[ $key ] = cloudsync_encrypt( $settings[ $key ] );
        }
    }

    update_option( 'cloudsync_settings', $settings );
}

/**
 * Returns metadata for configured OAuth services.
 *
 * @since 4.1.2
 *
 * @return array<string, array<string, mixed>>
 */
function cloudsync_get_service_definitions() {
    return array(
        'google'     => array(
            'label'       => __( 'Google Drive', 'secure-pdf-viewer' ),
            'token_field' => 'google_refresh_token',
            'required_fields' => array( 'google_client_id', 'google_client_secret' ),
            'fields'      => array(
                'google_client_id'     => array(
                    'label'     => __( 'Client ID', 'secure-pdf-viewer' ),
                    'sensitive' => false,
                ),
                'google_client_secret' => array(
                    'label'     => __( 'Client Secret', 'secure-pdf-viewer' ),
                    'sensitive' => true,
                ),
                'google_refresh_token' => array(
                    'label'     => __( 'Refresh Token', 'secure-pdf-viewer' ),
                    'sensitive' => true,
                    'is_token'  => true,
                ),
            ),
            'guide'       => array(
                'type' => 'internal',
                'slug' => 'google',
            ),
        ),
        'dropbox'    => array(
            'label'       => __( 'Dropbox', 'secure-pdf-viewer' ),
            'token_field' => 'dropbox_refresh_token',
            'required_fields' => array( 'dropbox_app_key', 'dropbox_app_secret' ),
            'fields'      => array(
                'dropbox_app_key'       => array(
                    'label'     => __( 'App Key', 'secure-pdf-viewer' ),
                    'sensitive' => false,
                ),
                'dropbox_app_secret'    => array(
                    'label'     => __( 'App Secret', 'secure-pdf-viewer' ),
                    'sensitive' => true,
                ),
                'dropbox_refresh_token' => array(
                    'label'     => __( 'Refresh Token', 'secure-pdf-viewer' ),
                    'sensitive' => true,
                    'is_token'  => true,
                ),
            ),
            'guide'       => array(
                'type' => 'internal',
                'slug' => 'dropbox',
            ),
        ),
        'sharepoint' => array(
            'label'       => __( 'SharePoint / OneDrive', 'secure-pdf-viewer' ),
            'token_field' => 'sharepoint_refresh_token',
            'required_fields' => array( 'sharepoint_client_id', 'sharepoint_secret' ),
            'fields'      => array(
                'sharepoint_client_id'     => array(
                    'label'     => __( 'Client ID', 'secure-pdf-viewer' ),
                    'sensitive' => false,
                ),
                'sharepoint_secret'        => array(
                    'label'     => __( 'Client Secret', 'secure-pdf-viewer' ),
                    'sensitive' => true,
                ),
                'sharepoint_tenant_id'     => array(
                    'label'     => __( 'Tenant ID', 'secure-pdf-viewer' ),
                    'sensitive' => false,
                ),
                'sharepoint_refresh_token' => array(
                    'label'     => __( 'Refresh Token', 'secure-pdf-viewer' ),
                    'sensitive' => true,
                    'is_token'  => true,
                ),
            ),
            'guide'       => array(
                'type' => 'internal',
                'slug' => 'sharepoint',
            ),
        ),
    );
}

/**
 * Builds the OAuth redirect URI for a service.
 *
 * @since 4.2.1
 *
 * @param string $service Service slug.
 *
 * @return string Redirect URI.
 */
function cloudsync_get_oauth_redirect_uri( $service ) {
    return add_query_arg(
        array(
            'action'  => 'cloudsync_oauth_callback',
            'service' => $service,
        ),
        admin_url( 'admin-post.php' )
    );
}

/**
 * Provides step-by-step instructions for OAuth connection guides.
 *
 * @since 4.2.1
 *
 * @return array<string, array<string, mixed>>
 */
function cloudsync_get_connection_guides() {
    $google_redirect     = cloudsync_get_oauth_redirect_uri( 'google' );
    $dropbox_redirect    = cloudsync_get_oauth_redirect_uri( 'dropbox' );
    $sharepoint_redirect = cloudsync_get_oauth_redirect_uri( 'sharepoint' );

    return array(
        'google'     => array(
            'title'    => __( 'Paso a paso para conectar Google Drive', 'secure-pdf-viewer' ),
            'intro'    => __( 'Sigue estas instrucciones para autorizar tu cuenta de Google Drive y sincronizar carpetas con WordPress.', 'secure-pdf-viewer' ),
            'redirect' => $google_redirect,
            'steps'    => array(
                __( 'Obtén el Client ID y Client Secret desde Google Cloud Console (Credenciales → Crear credenciales → ID de cliente OAuth 2.0 → Aplicación web).', 'secure-pdf-viewer' ),
                sprintf(
                    __( 'Agrega la URI de redirección autorizada <code>%s</code> en Google Cloud (sección “URIs de redirección autorizados”).', 'secure-pdf-viewer' ),
                    esc_html( $google_redirect )
                ),
                __( 'Completa el formulario del plugin con el Client ID y Client Secret y guarda los cambios.', 'secure-pdf-viewer' ),
                __( 'Haz clic en “Dar acceso” para abrir la ventana emergente de autorización, inicia sesión y acepta los permisos. El plugin guardará el refresh token automáticamente.', 'secure-pdf-viewer' ),
                sprintf(
                    __( 'Si el refresh token no se genera, construye manualmente la URL de autorización con el scope <code>https://www.googleapis.com/auth/drive.file</code> y la URI <code>%1$s</code>, autoriza el acceso y envía el código devuelto a <code>https://oauth2.googleapis.com/token</code> mediante cURL o Postman para obtener el token.', 'secure-pdf-viewer' ),
                    esc_html( $google_redirect )
                ),
            ),
        ),
        'dropbox'    => array(
            'title'    => __( 'Configurar la conexión con Dropbox', 'secure-pdf-viewer' ),
            'intro'    => __( 'Crea una aplicación en Dropbox y vincúlala al plugin para habilitar la sincronización.', 'secure-pdf-viewer' ),
            'redirect' => $dropbox_redirect,
            'steps'    => array(
                __( 'Accede a Dropbox App Console y crea una nueva aplicación con permisos Scoped access (Files.content.write y Files.content.read).', 'secure-pdf-viewer' ),
                sprintf(
                    __( 'Incluye la URI de redirección <code>%s</code> en la sección OAuth 2 → Redirect URIs.', 'secure-pdf-viewer' ),
                    esc_html( $dropbox_redirect )
                ),
                __( 'Introduce el App Key y App Secret en el formulario del plugin y guarda las credenciales.', 'secure-pdf-viewer' ),
                __( 'Presiona “Dar acceso” para autorizar la aplicación y generar el refresh token.', 'secure-pdf-viewer' ),
            ),
        ),
        'sharepoint' => array(
            'title'    => __( 'Conectar SharePoint / OneDrive', 'secure-pdf-viewer' ),
            'intro'    => __( 'Prepara una aplicación de Azure AD para habilitar el acceso de Microsoft Graph.', 'secure-pdf-viewer' ),
            'redirect' => $sharepoint_redirect,
            'steps'    => array(
                __( 'Registra una aplicación en Azure Active Directory (Portal de Azure → App registrations) y toma nota del Tenant ID y Client ID.', 'secure-pdf-viewer' ),
                __( 'Concede permisos delegados Files.ReadWrite.All y Sites.ReadWrite.All y habilita el acceso sin conexión.', 'secure-pdf-viewer' ),
                sprintf(
                    __( 'Configura la URI de redirección <code>%s</code> en Authentication → Web redirect URIs.', 'secure-pdf-viewer' ),
                    esc_html( $sharepoint_redirect )
                ),
                __( 'Introduce el Tenant ID, Client ID y Client Secret en el plugin y guarda las credenciales.', 'secure-pdf-viewer' ),
                __( 'Ejecuta “Dar acceso” para completar el flujo OAuth y almacenar el refresh token.', 'secure-pdf-viewer' ),
            ),
            'extra'    => __( 'La implementación completa del conector de SharePoint requiere definir los endpoints de Microsoft Graph dentro de Connector_SharePoint.', 'secure-pdf-viewer' ),
        ),
    );
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

    $decoded = base64_decode( $data, true );

    if ( false === $decoded ) {
        // Backwards compatibility: return raw value when it was stored as plain text previously.
        return $data;
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
