<?php
/**
 * Google Drive connector implementation.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'CloudSync_GoogleDrive', false ) ) {
    class_alias( 'Connector_GoogleDrive', 'CloudSync_GoogleDrive' );
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/interface-cloudsync-connector.php';

/**
 * Handles communication with the Google Drive API.
 */
class Connector_GoogleDrive implements CloudSync_Connector_Interface {

    /**
     * Google Drive API base URL.
     */
    const API_BASE = 'https://www.googleapis.com/drive/v3';

    /**
     * Creates a folder in Google Drive.
     *
     * @since 4.0.0
     *
     * @param string      $name      Folder name.
     * @param string|null $parent_id Optional parent identifier.
     *
     * @return string|null Google Drive folder ID on success or null on failure.
     *
     * @example \Connector_GoogleDrive::create_folder( 'Curso Algebra', 'abc123' );
     */
    public function create_folder( $name, $parent_id = null ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            cloudsync_add_log( __( 'Google Drive token missing', 'secure-pdf-viewer' ) );
            return null;
        }

        $body = array(
            'name'     => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        );

        if ( ! empty( $parent_id ) ) {
            $body['parents'] = array( $parent_id );
        }

        $response = wp_remote_post(
            'https://www.googleapis.com/drive/v3/files',
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
            cloudsync_add_log( __( 'Google Drive folder creation failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $data['id'] ) ? $data['id'] : null;
    }

    /**
     * Retrieves the list of changes since a token.
     *
     * @since 4.0.0
     *
     * @param string $since_token Page token from the previous request.
     *
     * @return array<string, mixed>|null Response payload or null on failure.
     *
     * @example $connector->list_changes( '1234' );
     */
    public function list_changes( $since_token ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return null;
        }

        if ( empty( $since_token ) ) {
            $since_token = $this->get_start_page_token( $token );

            if ( empty( $since_token ) ) {
                return null;
            }
        }

        $url = add_query_arg(
            array(
                'pageToken' => $since_token,
                'spaces'    => 'drive',
                'pageSize'  => 50,
            ),
            'https://www.googleapis.com/drive/v3/changes'
        );

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'Google Drive list_changes failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );
            return null;
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Renames a remote folder.
     *
     * @since 4.0.0
     *
     * @param string $id       Folder ID.
     * @param string $new_name Desired name.
     *
     * @return bool Whether the rename succeeded.
     *
     * @example $connector->rename_folder( 'abc123', 'Curso Renombrado' );
     */
    public function rename_folder( $id, $new_name ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return false;
        }

        $response = wp_remote_request(
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ),
            array(
                'method'  => 'PATCH',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array( 'name' => $new_name ) ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'Google Drive rename failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );
            return false;
        }

        return 204 === wp_remote_retrieve_response_code( $response );
    }

    /**
     * Deletes a folder in Google Drive.
     *
     * @since 4.0.0
     *
     * @param string $id Folder identifier.
     *
     * @return bool Whether the deletion succeeded.
     */
    public function delete_folder( $id ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return false;
        }

        $response = wp_remote_request(
            'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $id ),
            array(
                'method'  => 'DELETE',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'Google Drive delete failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );
            return false;
        }

        return in_array( wp_remote_retrieve_response_code( $response ), array( 204, 200 ), true );
    }

    /**
     * Lists child items for a Google Drive folder.
     *
     * @since 4.2.0
     *
     * @param string|null $parent_id Parent folder identifier or null for root.
     *
     * @return array<int, array<string, mixed>>|\WP_Error
     */
    public function list_folder_items( $parent_id = null ) {
        $token = $this->get_access_token();

        if ( empty( $token ) ) {
            return new WP_Error( 'cloudsync_google_missing_token', __( 'Conecta Google Drive para explorar archivos.', 'secure-pdf-viewer' ) );
        }

        $folder_id = $parent_id ? $parent_id : 'root';

        $params = array(
            'q'        => sprintf( "'%s' in parents and trashed=false", $folder_id ),
            'spaces'   => 'drive',
            'pageSize' => 100,
            'orderBy'  => 'folder, name',
            'fields'   => 'files(id,name,mimeType,modifiedTime,size,webViewLink,iconLink)',
        );

        $response = wp_remote_get(
            add_query_arg( $params, self::API_BASE . '/files' ),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
                'timeout' => 20,
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'Google Drive list_folder_items failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );

            return new WP_Error( 'cloudsync_google_request_failed', __( 'No se pudo recuperar la carpeta de Google Drive.', 'secure-pdf-viewer' ) );
        }

        $data  = json_decode( wp_remote_retrieve_body( $response ), true );
        $files = isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : array();
        $items = array();

        foreach ( $files as $file ) {
            $is_folder = isset( $file['mimeType'] ) && 'application/vnd.google-apps.folder' === $file['mimeType'];

            $items[] = array(
                'id'           => isset( $file['id'] ) ? $file['id'] : '',
                'name'         => isset( $file['name'] ) ? $file['name'] : '',
                'type'         => $is_folder ? 'folder' : 'file',
                'modified'     => isset( $file['modifiedTime'] ) ? $file['modifiedTime'] : '',
                'size'         => isset( $file['size'] ) ? (int) $file['size'] : 0,
                'service'      => 'google',
                'web_url'      => isset( $file['webViewLink'] ) ? $file['webViewLink'] : '',
                'icon'         => isset( $file['iconLink'] ) ? $file['iconLink'] : '',
                'parent_id'    => $folder_id,
                'has_children' => $is_folder,
            );
        }

        return $items;
    }

    /**
     * Retrieves a valid Google Drive access token, refreshing when required.
     *
     * @since 4.3.0
     *
     * @return string OAuth access token.
     */
    public function get_access_token() {
        $credentials = cloudsync_get_service_credentials( 'google' );

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
     * Executes the refresh token flow.
     *
     * @since 4.3.0
     *
     * @param array<string, mixed> $credentials Stored credential set.
     *
     * @return string Access token or empty string when the refresh fails.
     */
    protected function refresh_access_token( array $credentials ) {
        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'refresh_token' => $credentials['refresh_token'],
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log(
                __( 'Google Drive token refresh failed', 'secure-pdf-viewer' ),
                array( 'error' => $response->get_error_message() )
            );

            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['access_token'] ) ) {
            cloudsync_add_log( __( 'Google Drive returned an empty access token.', 'secure-pdf-viewer' ), array( 'response' => $data ) );
            return '';
        }

        $expires = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;

        $updates = array(
            'access_token'  => $data['access_token'],
            'token_expires' => time() + $expires,
        );

        if ( ! empty( $data['refresh_token'] ) ) {
            $updates['refresh_token'] = $data['refresh_token'];
        }

        cloudsync_store_service_credentials( 'google', $updates );

        return $data['access_token'];
    }

    /**
     * Exchanges an authorization code for tokens during OAuth callback.
     *
     * @since 4.3.0
     *
     * @param string $code Authorization code provided by Google.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function exchange_code_for_tokens( $code ) {
        $credentials = cloudsync_get_service_credentials( 'google' );

        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            return new WP_Error( 'cloudsync_google_missing_keys', __( 'Configura el Client ID y Secret antes de autorizar Google Drive.', 'secure-pdf-viewer' ) );
        }

        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'timeout' => 20,
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ),
                'body'    => array(
                    'code'          => $code,
                    'client_id'     => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'redirect_uri'  => cloudsync_get_oauth_redirect_uri( 'google' ),
                    'grant_type'    => 'authorization_code',
                    'access_type'   => 'offline',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error( 'cloudsync_google_empty_response', __( 'Google Drive devolvió una respuesta vacía durante el intercambio de tokens.', 'secure-pdf-viewer' ) );
        }

        return $body;
    }

    /**
     * Persists tokens received from the OAuth callback.
     *
     * @since 4.3.0
     *
     * @param array<string, mixed> $tokens Token payload returned by Google.
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

        cloudsync_store_service_credentials( 'google', $updates );

        cloudsync_add_log( __( 'Google Drive tokens stored from OAuth callback.', 'secure-pdf-viewer' ), array( 'service' => 'google' ) );
    }

    /**
     * Retrieves the start page token required to poll for changes.
     *
     * @since 4.0.0
     *
     * @param string $token OAuth access token.
     *
     * @return string|null Start page token or null on failure.
     */
    protected function get_start_page_token( $token ) {
        $response = wp_remote_get(
            'https://www.googleapis.com/drive/v3/changes/startPageToken',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'Google Drive startPageToken failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $data['startPageToken'] ) ? $data['startPageToken'] : null;
    }
}
