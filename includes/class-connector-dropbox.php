<?php
/**
 * Dropbox connector implementation.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/helpers.php';

/**
 * Handles communication with the Dropbox API.
 */
class Connector_Dropbox {

    /**
     * Creates a folder in Dropbox.
     *
     * @since 4.0.0
     *
     * @param string      $name      Folder name.
     * @param string|null $parent_id Optional parent identifier.
     *
     * @return string|null Dropbox folder ID or null on failure.
     *
     * @example \Connector_Dropbox::create_folder( 'Curso Algebra', 'id:parent' );
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

        if ( isset( $data['metadata']['id'] ) ) {
            do_action( 'cloudsync_after_create_course', 0, $data['metadata']['id'] );
            return $data['metadata']['id'];
        }

        return null;
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
     * Exchanges the refresh token for an access token.
     *
     * @since 4.0.0
     *
     * @return string Access token or empty string.
     */
    protected function get_access_token() {
        $settings = cloudsync_get_settings();

        if ( empty( $settings['dropbox_refresh_token'] ) || empty( $settings['dropbox_app_key'] ) || empty( $settings['dropbox_app_secret'] ) ) {
            return '';
        }

        $response = wp_remote_post(
            'https://api.dropboxapi.com/oauth2/token',
            array(
                'body' => array(
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $settings['dropbox_refresh_token'],
                    'client_id'     => $settings['dropbox_app_key'],
                    'client_secret' => $settings['dropbox_app_secret'],
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

        return isset( $data['access_token'] ) ? $data['access_token'] : '';
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
