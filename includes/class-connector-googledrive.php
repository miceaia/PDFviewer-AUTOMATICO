<?php
/**
 * Google Drive connector implementation.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/helpers.php';

/**
 * Handles communication with the Google Drive API.
 */
class Connector_GoogleDrive {

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

        if ( isset( $data['id'] ) ) {
            do_action( 'cloudsync_after_create_course', 0, $data['id'] );
            return $data['id'];
        }

        return null;
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
     * Exchanges the refresh token for an access token.
     *
     * @since 4.0.0
     *
     * @return string Access token or empty string when unavailable.
     */
    protected function get_access_token() {
        $settings = cloudsync_get_settings();

        if ( empty( $settings['google_refresh_token'] ) || empty( $settings['google_client_id'] ) || empty( $settings['google_client_secret'] ) ) {
            return '';
        }

        $response = wp_remote_post(
            'https://oauth2.googleapis.com/token',
            array(
                'body' => array(
                    'client_id'     => $settings['google_client_id'],
                    'client_secret' => $settings['google_client_secret'],
                    'refresh_token' => $settings['google_refresh_token'],
                    'grant_type'    => 'refresh_token',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'Google Drive token refresh failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );
            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $data['access_token'] ) ? $data['access_token'] : '';
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
