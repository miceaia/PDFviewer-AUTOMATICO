<?php
/**
 * Helper functions for the Cloud Sync integration.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'cloudsync_encrypt' ) ) {
    /**
     * Encrypts plain text using AES-256-CBC with WordPress salts.
     *
     * Falls back to returning the original value when OpenSSL is missing.
     * FIX: Added error handling to prevent fatal errors during encryption.
     *
     * @since 4.1.5
     *
     * @param string $plain Plain text string to encrypt.
     *
     * @return string Encrypted string or the original plain text when encryption fails.
     */
    function cloudsync_encrypt( string $plain ): string {
        if ( '' === $plain ) {
            return '';
        }

        if ( ! function_exists( 'openssl_encrypt' ) ) {
            static $logged = false;

            if ( ! $logged ) {
                error_log( '[CloudSync] OpenSSL extension unavailable. Storing credential without encryption.' );
                $logged = true;
            }

            return $plain;
        }

        try {
            $key = hash( 'sha256', wp_salt( 'auth' ), true );
            $iv  = substr( hash( 'sha256', wp_salt( 'secure-auth' ) ), 0, 16 );

            $encrypted = @openssl_encrypt( $plain, 'AES-256-CBC', $key, 0, $iv );

            if ( false === $encrypted || '' === $encrypted ) {
                error_log( '[CloudSync] Encryption failed, storing plain text as fallback.' );
                return $plain;
            }

            return $encrypted;
        } catch ( Exception $e ) {
            error_log( '[CloudSync] Encryption exception: ' . $e->getMessage() );
            return $plain;
        } catch ( Throwable $e ) {
            error_log( '[CloudSync] Encryption error: ' . $e->getMessage() );
            return $plain;
        }
    }
}

if ( ! function_exists( 'cloudsync_decrypt' ) ) {
    /**
     * Decrypts a string previously encrypted with {@see cloudsync_encrypt()}.
     *
     * FIX: Added comprehensive error handling for corrupted/double-encrypted data.
     *
     * @since 4.1.5
     *
     * @param string $cipher Encrypted cipher text.
     *
     * @return string Decrypted string, or empty string if decryption fails completely.
     */
    function cloudsync_decrypt( string $cipher ): string {
        if ( '' === $cipher ) {
            return '';
        }

        if ( ! function_exists( 'openssl_decrypt' ) ) {
            static $logged = false;

            if ( ! $logged ) {
                error_log( '[CloudSync] OpenSSL extension unavailable. Returning stored credential verbatim.' );
                $logged = true;
            }

            return $cipher;
        }

        try {
            $key = hash( 'sha256', wp_salt( 'auth' ), true );
            $iv  = substr( hash( 'sha256', wp_salt( 'secure-auth' ) ), 0, 16 );

            // Suppress OpenSSL warnings for corrupted data
            $decrypted = @openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );

            if ( false !== $decrypted && '' !== $decrypted ) {
                return $decrypted;
            }

            // Try base64 decoding first (legacy format)
            $decoded = @base64_decode( $cipher, true );

            if ( false !== $decoded && '' !== $decoded ) {
                $fallback = @openssl_decrypt( $decoded, 'AES-256-CBC', $key, 0, $iv );

                if ( false !== $fallback && '' !== $fallback ) {
                    return $fallback;
                }
            }

            // If all decryption attempts fail, log the issue and return empty
            // This prevents fatal errors from corrupted/double-encrypted data
            static $error_logged = array();
            $hash = substr( md5( $cipher ), 0, 8 );

            if ( ! isset( $error_logged[ $hash ] ) ) {
                error_log( sprintf(
                    '[CloudSync] WARNING: Failed to decrypt credential (hash: %s). Data may be corrupted. Use clear-credentials.php to reset.',
                    $hash
                ) );
                $error_logged[ $hash ] = true;
            }

            return ''; // Return empty string instead of corrupted data
        } catch ( Exception $e ) {
            error_log( '[CloudSync] Decryption exception: ' . $e->getMessage() );
            return '';
        } catch ( Throwable $e ) {
            error_log( '[CloudSync] Decryption error: ' . $e->getMessage() );
            return '';
        }
    }
}

if ( ! function_exists( 'cloudsync_opt_set' ) ) {
    /**
     * Persists a CloudSync option with autoload disabled.
     *
     * @since 4.1.5
     *
     * @param string $name  Option name.
     * @param mixed  $value Value to persist.
     *
     * @return void
     */
    function cloudsync_opt_set( string $name, $value ): void {
        update_option( $name, $value, false );
    }
}

if ( ! function_exists( 'cloudsync_opt_get' ) ) {
    /**
     * Retrieves a CloudSync option.
     *
     * @since 4.1.5
     *
     * @param string $name    Option name.
     * @param mixed  $default Optional default value.
     *
     * @return mixed
     */
    function cloudsync_opt_get( string $name, $default = '' ) {
        $value = get_option( $name, null );

        return null === $value ? $default : $value;
    }
}

if ( ! function_exists( 'cloudsync_notice_success' ) ) {
    /**
     * Stores a success admin notice in a transient.
     *
     * @since 4.1.5
     *
     * @param string $message Message to display.
     *
     * @return void
     */
    function cloudsync_notice_success( string $message ): void {
        set_transient( 'cloudsync_admin_notice', array(
            'type' => 'success',
            'msg'  => $message,
        ), 60 );
    }
}

if ( ! function_exists( 'cloudsync_notice_error' ) ) {
    /**
     * Stores an error admin notice in a transient.
     *
     * @since 4.1.5
     *
     * @param string $message Message to display.
     *
     * @return void
     */
    function cloudsync_notice_error( string $message ): void {
        set_transient( 'cloudsync_admin_notice', array(
            'type' => 'error',
            'msg'  => $message,
        ), 60 );
    }
}

/**
 * Returns the internal service → option mapping used for credential storage.
 *
 * @since 4.3.0
 *
 * @return array<string, array<string, mixed>>
 */
function cloudsync_get_service_option_map() {
    return array(
        'google'     => array(
            'prefix'            => 'cloudsync_drive',
            'fields'            => array(
                'client_id'     => 'client_id',
                'client_secret' => 'client_secret',
                'refresh_token' => 'refresh_token',
                'access_token'  => 'access_token',
                'token_expires' => 'token_expires',
            ),
            'sensitive_fields' => array( 'client_secret', 'refresh_token', 'access_token' ),
            'preserve_on_empty'=> array( 'client_id', 'client_secret', 'refresh_token', 'access_token' ),
        ),
        'dropbox'    => array(
            'prefix'            => 'cloudsync_dropbox',
            'fields'            => array(
                'client_id'     => 'client_id',
                'client_secret' => 'client_secret',
                'refresh_token' => 'refresh_token',
                'access_token'  => 'access_token',
                'token_expires' => 'token_expires',
            ),
            'sensitive_fields' => array( 'client_secret', 'refresh_token', 'access_token' ),
            'preserve_on_empty'=> array( 'client_id', 'client_secret', 'refresh_token', 'access_token' ),
        ),
        'sharepoint' => array(
            'prefix'            => 'cloudsync_sharepoint',
            'fields'            => array(
                'client_id'     => 'client_id',
                'client_secret' => 'client_secret',
                'tenant_id'     => 'tenant_id',
                'refresh_token' => 'refresh_token',
                'access_token'  => 'access_token',
                'token_expires' => 'token_expires',
            ),
            'sensitive_fields' => array( 'client_secret', 'refresh_token', 'access_token' ),
            'preserve_on_empty'=> array( 'client_id', 'client_secret', 'tenant_id', 'refresh_token', 'access_token' ),
        ),
    );
}

/**
 * Retrieves decrypted credentials for a given service.
 *
 * @since 4.3.0
 *
 * @param string $service              Service slug (google, dropbox, sharepoint).
 * @param bool   $use_legacy_fallback  Whether to hydrate empty values from legacy settings.
 *
 * @return array<string, mixed>
 */
function cloudsync_get_service_credentials( $service, $use_legacy_fallback = true ) {
    $map = cloudsync_get_service_option_map();

    if ( ! isset( $map[ $service ] ) ) {
        return array();
    }

    $fields    = $map[ $service ]['fields'];
    $sensitive = $map[ $service ]['sensitive_fields'];
    $credentials = array();

    foreach ( $fields as $field => $suffix ) {
        $option_name = $map[ $service ]['prefix'] . '_' . $suffix;
        $stored      = cloudsync_opt_get( $option_name, '' );

        if ( in_array( $field, $sensitive, true ) && '' !== $stored ) {
            $credentials[ $field ] = cloudsync_decrypt( $stored );
        } elseif ( 'token_expires' === $field ) {
            $credentials[ $field ] = (int) $stored;
        } else {
            $credentials[ $field ] = is_string( $stored ) ? $stored : '';
        }
    }

    if ( $use_legacy_fallback ) {
        $legacy = cloudsync_opt_get( 'cloudsync_settings', array() );

        if ( is_array( $legacy ) && ! empty( $legacy ) ) {
            $legacy_sensitive = array(
                'google_client_secret',
                'google_refresh_token',
                'dropbox_app_secret',
                'dropbox_client_secret',
                'dropbox_refresh_token',
                'sharepoint_secret',
                'sharepoint_refresh_token',
            );

            foreach ( $fields as $field => $suffix ) {
                if ( ! empty( $credentials[ $field ] ) ) {
                    continue;
                }

                $legacy_key = '';

                switch ( $service ) {
                    case 'google':
                        $legacy_key = 'google_' . ( 'client_id' === $field ? 'client_id' : ( 'client_secret' === $field ? 'client_secret' : ( 'refresh_token' === $field ? 'refresh_token' : $field ) ) );
                        break;
                    case 'dropbox':
                        if ( 'client_id' === $field ) {
                            $legacy_key = isset( $legacy['dropbox_client_id'] ) ? 'dropbox_client_id' : 'dropbox_app_key';
                        } elseif ( 'client_secret' === $field ) {
                            $legacy_key = isset( $legacy['dropbox_client_secret'] ) ? 'dropbox_client_secret' : 'dropbox_app_secret';
                        } elseif ( 'refresh_token' === $field ) {
                            $legacy_key = 'dropbox_refresh_token';
                        }
                        break;
                    case 'sharepoint':
                        if ( 'tenant_id' === $field ) {
                            $legacy_key = 'sharepoint_tenant_id';
                        } elseif ( in_array( $field, array( 'client_id', 'client_secret', 'refresh_token' ), true ) ) {
                            $legacy_key = 'sharepoint_' . ( 'client_id' === $field ? 'client_id' : ( 'client_secret' === $field ? 'secret' : 'refresh_token' ) );
                        }
                        break;
                }

                if ( ! $legacy_key || ! isset( $legacy[ $legacy_key ] ) ) {
                    continue;
                }

                $value = $legacy[ $legacy_key ];

                if ( in_array( $legacy_key, $legacy_sensitive, true ) && '' !== $value ) {
                    $value = cloudsync_decrypt( $value );
                }

                if ( 'token_expires' === $field ) {
                    $value = (int) $value;
                }

                $credentials[ $field ] = $value;
            }
        }
    }

    return $credentials;
}

/**
 * Persists credential fields for a service.
 *
 * @since 4.3.0
 *
 * @param string               $service        Service slug.
 * @param array<string, mixed> $values         Values to store (plain text; encryption is handled internally).
 * @param bool                 $preserve_empty Whether to keep existing values when the provided field is empty.
 *
 * @return void
 */
function cloudsync_store_service_credentials( $service, array $values, $preserve_empty = true ) {
    $map = cloudsync_get_service_option_map();

    if ( ! isset( $map[ $service ] ) ) {
        return;
    }

    $fields            = $map[ $service ]['fields'];
    $sensitive_fields  = $map[ $service ]['sensitive_fields'];
    $preserve_on_empty = isset( $map[ $service ]['preserve_on_empty'] ) ? $map[ $service ]['preserve_on_empty'] : array();

    foreach ( $fields as $field => $suffix ) {
        $option_name = $map[ $service ]['prefix'] . '_' . $suffix;

        // FIX DOBLE ENCRIPTACIÓN: Solo procesar si el campo viene en $values
        if ( ! array_key_exists( $field, $values ) ) {
            // Si el campo no viene en $values, no hacer nada (mantener valor existente)
            continue;
        }

        $candidate = $values[ $field ];

        if ( is_string( $candidate ) ) {
            $candidate = trim( $candidate );
        }

        // Si viene vacío y preserve_empty está activo, no actualizar (mantener valor existente)
        if ( '' === $candidate && $preserve_empty && in_array( $field, $preserve_on_empty, true ) ) {
            continue; // No actualizar, mantener valor existente en DB
        }

        // Aquí $candidate es un valor NUEVO en texto plano que necesita encriptarse
        $value = $candidate;

        if ( in_array( $field, $sensitive_fields, true ) ) {
            $stored = '' === $value ? '' : cloudsync_encrypt( (string) $value );
            cloudsync_opt_set( $option_name, $stored );
        } elseif ( 'token_expires' === $field ) {
            cloudsync_opt_set( $option_name, (int) $value );
        } else {
            cloudsync_opt_set( $option_name, $value );
        }
    }

    cloudsync_refresh_legacy_settings_cache();
}

/**
 * Synchronises the legacy aggregated option with per-service credentials.
 *
 * FIX: Obtiene valores YA ENCRIPTADOS directamente de las opciones para evitar doble encriptación.
 * No debe usar cloudsync_get_service_credentials() que desencripta los valores.
 *
 * @since 4.3.0
 *
 * @return void
 */
function cloudsync_refresh_legacy_settings_cache() {
    $map = cloudsync_get_service_option_map();

    // Obtener valores ENCRIPTADOS directamente de las opciones (sin desencriptar)
    $data = array();

    // Google Drive
    foreach ( $map['google']['fields'] as $field => $suffix ) {
        $option_name = $map['google']['prefix'] . '_' . $suffix;
        $stored      = cloudsync_opt_get( $option_name, '' );
        $legacy_key  = 'google_' . ( 'client_secret' === $field ? 'client_secret' : ( 'client_id' === $field ? 'client_id' : ( 'refresh_token' === $field ? 'refresh_token' : ( 'access_token' === $field ? 'access_token' : ( 'token_expires' === $field ? 'token_expires' : $field ) ) ) ) );

        if ( 'token_expires' === $field ) {
            $data[ $legacy_key ] = (int) $stored;
        } else {
            $data[ $legacy_key ] = $stored; // Ya está encriptado si es sensible
        }
    }

    // Dropbox
    foreach ( $map['dropbox']['fields'] as $field => $suffix ) {
        $option_name = $map['dropbox']['prefix'] . '_' . $suffix;
        $stored      = cloudsync_opt_get( $option_name, '' );
        $legacy_key  = 'dropbox_' . ( 'client_secret' === $field ? 'client_secret' : ( 'client_id' === $field ? 'client_id' : ( 'refresh_token' === $field ? 'refresh_token' : ( 'access_token' === $field ? 'access_token' : ( 'token_expires' === $field ? 'token_expires' : $field ) ) ) ) );

        if ( 'token_expires' === $field ) {
            $data[ $legacy_key ] = (int) $stored;
        } else {
            $data[ $legacy_key ] = $stored; // Ya está encriptado si es sensible
        }
    }

    // SharePoint
    foreach ( $map['sharepoint']['fields'] as $field => $suffix ) {
        $option_name = $map['sharepoint']['prefix'] . '_' . $suffix;
        $stored      = cloudsync_opt_get( $option_name, '' );

        if ( 'client_secret' === $field ) {
            $legacy_key = 'sharepoint_secret';
        } elseif ( 'tenant_id' === $field ) {
            $legacy_key = 'sharepoint_tenant_id';
        } else {
            $legacy_key = 'sharepoint_' . $field;
        }

        if ( 'token_expires' === $field ) {
            $data[ $legacy_key ] = (int) $stored;
        } else {
            $data[ $legacy_key ] = $stored; // Ya está encriptado si es sensible
        }
    }

    cloudsync_opt_set( 'cloudsync_settings', $data );
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
        'google_access_token'      => '',
        'google_token_expires'     => 0,
        'dropbox_client_id'        => '',
        'dropbox_client_secret'    => '',
        'dropbox_refresh_token'    => '',
        'dropbox_access_token'     => '',
        'dropbox_token_expires'    => 0,
        'sharepoint_client_id'     => '',
        'sharepoint_secret'        => '',
        'sharepoint_tenant_id'     => '',
        'sharepoint_refresh_token' => '',
        'sharepoint_access_token'  => '',
        'sharepoint_token_expires' => 0,
    );

    $settings = $defaults;

    $google = cloudsync_get_service_credentials( 'google' );

    $settings['google_client_id']     = $google['client_id'] ?? '';
    $settings['google_client_secret'] = $google['client_secret'] ?? '';
    $settings['google_refresh_token'] = $google['refresh_token'] ?? '';
    $settings['google_access_token']  = $google['access_token'] ?? '';
    $settings['google_token_expires'] = isset( $google['token_expires'] ) ? (int) $google['token_expires'] : 0;

    $dropbox = cloudsync_get_service_credentials( 'dropbox' );

    $settings['dropbox_client_id']     = $dropbox['client_id'] ?? '';
    $settings['dropbox_client_secret'] = $dropbox['client_secret'] ?? '';
    $settings['dropbox_refresh_token'] = $dropbox['refresh_token'] ?? '';
    $settings['dropbox_access_token']  = $dropbox['access_token'] ?? '';
    $settings['dropbox_token_expires'] = isset( $dropbox['token_expires'] ) ? (int) $dropbox['token_expires'] : 0;

    $sharepoint = cloudsync_get_service_credentials( 'sharepoint' );

    $settings['sharepoint_client_id']     = $sharepoint['client_id'] ?? '';
    $settings['sharepoint_secret']        = $sharepoint['client_secret'] ?? '';
    $settings['sharepoint_tenant_id']     = $sharepoint['tenant_id'] ?? '';
    $settings['sharepoint_refresh_token'] = $sharepoint['refresh_token'] ?? '';
    $settings['sharepoint_access_token']  = $sharepoint['access_token'] ?? '';
    $settings['sharepoint_token_expires'] = isset( $sharepoint['token_expires'] ) ? (int) $sharepoint['token_expires'] : 0;

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

    $settings = cloudsync_opt_get( 'cloudsync_general_settings', array() );

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
    cloudsync_opt_set( 'cloudsync_general_settings', $settings );
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
    if ( ! is_array( $settings ) ) {
        return;
    }

    $service_map = array(
        'google'     => array(
            'client_id'     => 'google_client_id',
            'client_secret' => 'google_client_secret',
            'refresh_token' => 'google_refresh_token',
        ),
        'dropbox'    => array(
            'client_id'     => 'dropbox_client_id',
            'client_secret' => 'dropbox_client_secret',
            'refresh_token' => 'dropbox_refresh_token',
        ),
        'sharepoint' => array(
            'client_id'     => 'sharepoint_client_id',
            'client_secret' => 'sharepoint_secret',
            'tenant_id'     => 'sharepoint_tenant_id',
            'refresh_token' => 'sharepoint_refresh_token',
        ),
    );

    foreach ( $service_map as $service => $fields ) {
        $values = array();

        foreach ( $fields as $field => $legacy_key ) {
            if ( array_key_exists( $legacy_key, $settings ) ) {
                $values[ $field ] = $settings[ $legacy_key ];
            }
        }

        if ( ! empty( $values ) ) {
            cloudsync_store_service_credentials( $service, $values );
        }
    }

    cloudsync_refresh_legacy_settings_cache();
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
            'required_fields' => array( 'dropbox_client_id', 'dropbox_client_secret' ),
            'fields'      => array(
                'dropbox_client_id'     => array(
                    'label'     => __( 'App Key / Client ID', 'secure-pdf-viewer' ),
                    'sensitive' => false,
                ),
                'dropbox_client_secret' => array(
                    'label'     => __( 'App Secret / Client Secret', 'secure-pdf-viewer' ),
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

/**
 * Clears all stored credentials for a specific service.
 *
 * Use this to remove corrupted credentials that may have been double-encrypted.
 *
 * @since 4.3.1
 *
 * @param string $service Service slug (google, dropbox, sharepoint).
 *
 * @return bool True if credentials were cleared, false if service is invalid.
 */
function cloudsync_clear_service_credentials( $service ) {
    $map = cloudsync_get_service_option_map();

    if ( ! isset( $map[ $service ] ) ) {
        return false;
    }

    $fields = $map[ $service ]['fields'];

    foreach ( $fields as $field => $suffix ) {
        $option_name = $map[ $service ]['prefix'] . '_' . $suffix;
        delete_option( $option_name );
    }

    cloudsync_refresh_legacy_settings_cache();

    error_log( sprintf( '[CloudSync] Cleared all credentials for service: %s', $service ) );

    return true;
}

/**
 * Clears all CloudSync credentials from the database.
 *
 * Use this to remove all corrupted credentials and start fresh.
 *
 * @since 4.3.1
 *
 * @return void
 */
function cloudsync_clear_all_credentials() {
    $services = array( 'google', 'dropbox', 'sharepoint' );

    foreach ( $services as $service ) {
        cloudsync_clear_service_credentials( $service );
    }

    delete_option( 'cloudsync_settings' );

    error_log( '[CloudSync] Cleared all credentials for all services.' );
}
