<?php
/**
 * Dropbox connector implementation.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'CloudSync_Dropbox', false ) ) {
    class_alias( 'Connector_Dropbox', 'CloudSync_Dropbox' );
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/interface-cloudsync-connector.php';

/**
 * Handles communication with the Dropbox API.
 */
class Connector_Dropbox implements CloudSync_Connector_Interface {

    /**
     * Creates a folder in Dropbox.
     *
     * @since 4.0.0
     *
     * @param string      $name      Folder name.
     * @param string|null $parent_id Optional parent identifier.
     *
     * @return string|null Dropbox folder path (lowercase) or null on failure.
     *
     * @example \Connector_Dropbox::create_folder( 'Curso Algebra', '/cursos' );
     */
    public function create_folder( $name, $parent_id = null ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            cloudsync_add_log( __( 'Dropbox token missing', 'secure-pdf-viewer' ) );
            return null;
        }

        $path = '/' . ltrim( $name, '/' );

        if ( ! empty( $parent_id ) ) {
            $path = rtrim( $parent_id, '/' ) . '/' . ltrim( $name, '/' );
        }

        $response = wp_remote_post(
            'https://api.dropboxapi.com/2/files/create_folder_v2',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'path'       => $path,
                        'autorename' => true,
                    )
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox folder creation failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $data['metadata']['path_lower'] ) ? $data['metadata']['path_lower'] : null;
    }

    /**
     * Lists changes performed on Dropbox.
     *
     * @since 4.0.0
     *
     * @param string $since_token Cursor representing the last checkpoint.
     *
     * @return array<string, mixed>|null Response data.
     */
    public function list_changes( $since_token ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return null;
        }

        $endpoint = empty( $since_token )
            ? 'https://api.dropboxapi.com/2/files/list_folder'
            : 'https://api.dropboxapi.com/2/files/list_folder/continue';

        $body = empty( $since_token )
            ? array(
                'path'      => '',
                'recursive' => true,
            )
            : array(
                'cursor' => $since_token,
            );

        $response = wp_remote_post(
            $endpoint,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox list_changes failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );
            return null;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Renames an existing Dropbox folder.
     *
     * @since 4.0.0
     *
     * @param string $id       Folder identifier.
     * @param string $new_name Desired folder name.
     *
     * @return bool True on success.
     */
    public function rename_folder( $id, $new_name ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return false;
        }

        $metadata = $this->get_metadata( $id, $token );

        if ( empty( $metadata['path_lower'] ) ) {
            return false;
        }

        $to_path = trailingslashit( dirname( $metadata['path_lower'] ) );

        if ( '.' === trim( $to_path, './' ) ) {
            $to_path = '';
        }

        $response = wp_remote_post(
            'https://api.dropboxapi.com/2/files/move_v2',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'from_path' => $metadata['path_lower'],
                        'to_path'   => rtrim( $to_path, '/' ) . '/' . ltrim( $new_name, '/' ),
                    )
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox rename failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );
            return false;
        }

        return 200 === wp_remote_retrieve_response_code( $response );
    }

    /**
     * Deletes a Dropbox folder.
     *
     * @since 4.0.0
     *
     * @param string $id Folder identifier.
     *
     * @return bool True when the deletion request succeeds.
     */
    public function delete_folder( $id ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return false;
        }

        $metadata = $this->get_metadata( $id, $token );

        if ( empty( $metadata['path_lower'] ) ) {
            return false;
        }

        $response = wp_remote_post(
            'https://api.dropboxapi.com/2/files/delete_v2',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array( 'path' => $metadata['path_lower'] ) ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox delete failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );
            return false;
        }

        return 200 === wp_remote_retrieve_response_code( $response );
    }

    /**
     * Lists child items for a Dropbox folder.
     *
     * @since 4.2.0
     *
     * @param string|null $parent_id Folder path (lowercase) or null for root.
     *
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function list_folder_items( $parent_id = null ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return new WP_Error( 'cloudsync_dropbox_missing_token', __( 'Conecta Dropbox para explorar archivos.', 'secure-pdf-viewer' ) );
        }

        $path = $parent_id ? $parent_id : '';

        $response = wp_remote_post(
            'https://api.dropboxapi.com/2/files/list_folder',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'path'                 => $path,
                        'recursive'            => false,
                        'include_media_info'   => false,
                        'limit'                => 2000,
                        'include_deleted'      => false,
                        'include_non_downloadable_files' => true,
                    )
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox list_folder_items failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );

            return new WP_Error( 'cloudsync_dropbox_request_failed', __( 'No se pudo recuperar la carpeta de Dropbox.', 'secure-pdf-viewer' ) );
        }

        $data    = json_decode( wp_remote_retrieve_body( $response ), true );
        $entries = isset( $data['entries'] ) && is_array( $data['entries'] ) ? $data['entries'] : array();
        $items   = array();

        foreach ( $entries as $entry ) {
            $tag       = isset( $entry['.tag'] ) ? $entry['.tag'] : 'file';
            $is_folder = 'folder' === $tag;
            $path_lower = isset( $entry['path_lower'] ) ? $entry['path_lower'] : '';
            $path_display = isset( $entry['path_display'] ) ? $entry['path_display'] : $path_lower;
            $encoded_path = '';

            if ( ! empty( $path_display ) ) {
                $segments     = array_map( 'rawurlencode', array_filter( explode( '/', ltrim( $path_display, '/' ) ), 'strlen' ) );
                $encoded_path = implode( '/', $segments );
            }

            $items[] = array(
                'id'           => $path_lower,
                'name'         => isset( $entry['name'] ) ? $entry['name'] : basename( $path_display ),
                'type'         => $is_folder ? 'folder' : 'file',
                'modified'     => isset( $entry['server_modified'] ) ? $entry['server_modified'] : '',
                'size'         => isset( $entry['size'] ) ? (int) $entry['size'] : 0,
                'service'      => 'dropbox',
                'web_url'      => $is_folder ? 'https://www.dropbox.com/home/' . $encoded_path : 'https://www.dropbox.com/preview/' . $encoded_path,
                'icon'         => 'dropbox',
                'parent_id'    => $path,
                'has_children' => $is_folder,
            );
        }

        return $items;
    }

    /**
     * Retrieves a valid Dropbox access token, refreshing when required.
     *
     * @since 4.3.0
     *
     * @return string OAuth access token.
     */
    public function get_access_token() {
        $credentials = cloudsync_get_service_credentials( 'dropbox' );

        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            return '';
        }

        $access_token  = isset( $credentials['access_token'] ) ? $credentials['access_token'] : '';
        $token_expires = isset( $credentials['token_expires'] ) ? (int) $credentials['token_expires'] : 0;

        if ( $access_token && $token_expires > ( time() + 60 ) ) {
            return $access_token;
        }

        if ( empty( $credentials['refresh_token'] ) ) {
            return '';
        }

        return $this->refresh_access_token( $credentials );
    }

    /**
     * Refreshes the Dropbox access token using the stored refresh token.
     *
     * @since 4.3.0
     *
     * @param array<string, mixed> $credentials Stored credential set.
     *
     * @return string Access token or empty string when refresh fails.
     */
    protected function refresh_access_token( array $credentials ) {
        $response = wp_remote_post(
            'https://api.dropboxapi.com/oauth2/token',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $credentials['client_id'] . ':' . $credentials['client_secret'] ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => array(
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $credentials['refresh_token'],
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox token refresh failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );

            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['access_token'] ) ) {
            cloudsync_add_log( __( 'Dropbox returned an empty access token.', 'secure-pdf-viewer' ), array( 'response' => $data ) );
            return '';
        }

        $expires = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 14400;

        $updates = array(
            'access_token'  => $data['access_token'],
            'token_expires' => time() + $expires,
        );

        if ( ! empty( $data['refresh_token'] ) ) {
            $updates['refresh_token'] = $data['refresh_token'];
        }

        cloudsync_store_service_credentials( 'dropbox', $updates );

        return $data['access_token'];
    }

    /**
     * Exchanges an OAuth code for Dropbox tokens.
     *
     * @since 4.3.0
     *
     * @param string $code Authorisation code returned by Dropbox.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function exchange_code_for_tokens( $code ) {
        $credentials = cloudsync_get_service_credentials( 'dropbox' );

        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            return new WP_Error( 'cloudsync_dropbox_missing_keys', __( 'Configura el App Key y Secret antes de autorizar Dropbox.', 'secure-pdf-viewer' ) );
        }

        $response = wp_remote_post(
            'https://api.dropboxapi.com/oauth2/token',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode( $credentials['client_id'] . ':' . $credentials['client_secret'] ),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ),
                'body'    => array(
                    'code'              => $code,
                    'grant_type'        => 'authorization_code',
                    'redirect_uri'      => cloudsync_get_oauth_redirect_uri( 'dropbox' ),
                    'token_access_type' => 'offline',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error( 'cloudsync_dropbox_empty_response', __( 'Dropbox devolvió una respuesta vacía durante el intercambio de tokens.', 'secure-pdf-viewer' ) );
        }

        return $body;
    }

    /**
     * Stores Dropbox tokens from the OAuth callback payload.
     *
     * @since 4.3.0
     *
     * @param array<string, mixed> $tokens Token payload returned by Dropbox.
     *
     * @return void
     */
    public function oauth_callback( array $tokens ) {
        $updates = array();

        if ( ! empty( $tokens['access_token'] ) ) {
            $updates['access_token'] = $tokens['access_token'];
        }

        if ( ! empty( $tokens['refresh_token'] ) ) {
            $updates['refresh_token'] = $tokens['refresh_token'];
        }

        if ( ! empty( $tokens['expires_in'] ) ) {
            $updates['token_expires'] = time() + (int) $tokens['expires_in'];
        }

        cloudsync_store_service_credentials( 'dropbox', $updates );

        cloudsync_add_log( __( 'Dropbox tokens stored from OAuth callback.', 'secure-pdf-viewer' ), array( 'service' => 'dropbox' ) );
    }

    /**
     * Retrieves metadata for a Dropbox object.
     *
     * @since 4.0.0
     *
     * @param string $id    The Dropbox file ID.
     * @param string $token Access token for Dropbox API.
     *
     * @return array<string, mixed>|null Metadata response or null on failure.
     */
    protected function get_metadata( $id, $token ) {
        $response = wp_remote_post(
            'https://api.dropboxapi.com/2/files/get_metadata',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode(
                    array(
                        'path' => $id,
                    )
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Dropbox metadata request failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );

            return null;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
}
