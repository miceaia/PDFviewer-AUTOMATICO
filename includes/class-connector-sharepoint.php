<?php
/**
 * SharePoint connector stub.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/interface-cloudsync-connector.php';

/**
 * Handles communication with Microsoft Graph (SharePoint/OneDrive).
 *
 * This class intentionally contains only stubs so developers can extend it following
 * the Google Drive and Dropbox connectors as examples.
 *
 * Implementation steps:
 * 1. Register an Azure AD app and configure redirect URI matching your WordPress site.
 * 2. Exchange the authorization code for a refresh token and store it via the settings page.
 * 3. Replace the methods in this class using wp_remote_* helpers to call Microsoft Graph
 *    endpoints such as `/sites/{site-id}/drive/items`.
 * 4. Ensure the responses return the folder ID so it can be persisted in `_sp_folder_id`.
 */
class Connector_SharePoint implements CloudSync_Connector_Interface {

    /**
     * Creates a folder in SharePoint/OneDrive.
     *
     * @since 4.0.0
     *
     * @param string      $name      Folder name.
     * @param string|null $parent_id Optional parent identifier.
     *
     * @return string|null Remote folder ID or null.
     */
    public function create_folder( $name, $parent_id = null ) {
        cloudsync_add_log( __( 'SharePoint connector not yet implemented', 'secure-pdf-viewer' ) );
        return null;
    }

    /**
     * Lists remote changes.
     *
     * @since 4.0.0
     *
     * @param string $since_token Delta token for Microsoft Graph.
     *
     * @return array<string, mixed>|null Result payload.
     */
    public function list_changes( $since_token ) {
        unset( $since_token );
        cloudsync_add_log( __( 'SharePoint list_changes not yet implemented', 'secure-pdf-viewer' ) );
        return null;
    }

    /**
     * Renames a SharePoint folder.
     *
     * @since 4.0.0
     *
     * @param string $id       Folder identifier.
     * @param string $new_name New name.
     *
     * @return bool False until implemented.
     */
    public function rename_folder( $id, $new_name ) {
        unset( $id, $new_name );
        cloudsync_add_log( __( 'SharePoint rename not yet implemented', 'secure-pdf-viewer' ) );
        return false;
    }

    /**
     * Deletes a remote folder.
     *
     * @since 4.0.0
     *
     * @param string $id Folder identifier.
     *
     * @return bool False until implemented.
     */
    public function delete_folder( $id ) {
        unset( $id );
        cloudsync_add_log( __( 'SharePoint delete not yet implemented', 'secure-pdf-viewer' ) );
        return false;
    }

    /**
     * Lists SharePoint folder items.
     *
     * @since 4.2.0
     *
     * @param string|null $parent_id Parent identifier (unused until implemented).
     *
     * @return \WP_Error Always returns a stub error until implemented.
     */
    public function list_folder_items( $parent_id = null ) {
        unset( $parent_id );

        return new WP_Error(
            'cloudsync_sharepoint_unimplemented',
            __( 'El explorador de SharePoint aún no está disponible.', 'secure-pdf-viewer' )
        );
    }

    /**
     * Retrieves a Microsoft Graph access token, refreshing as needed.
     *
     * @since 4.3.0
     *
     * @return string Access token or empty string when unavailable.
     */
    public function get_access_token() {
        $credentials = cloudsync_get_service_credentials( 'sharepoint' );

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
     * Refreshes the Graph access token using the stored refresh token.
     *
     * @since 4.3.0
     *
     * @param array<string, mixed> $credentials Stored credential set.
     *
     * @return string Access token or empty string on failure.
     */
    protected function refresh_access_token( array $credentials ) {
        $tenant = ! empty( $credentials['tenant_id'] ) ? $credentials['tenant_id'] : 'common';

        $response = wp_remote_post(
            sprintf( 'https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode( $tenant ) ),
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $credentials['refresh_token'],
                    'scope'         => 'offline_access https://graph.microsoft.com/.default',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'SharePoint token refresh failed', 'secure-pdf-viewer' ), array( 'error' => $response->get_error_message() ) );

            return '';
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['access_token'] ) ) {
            cloudsync_add_log( __( 'SharePoint returned an empty access token.', 'secure-pdf-viewer' ), array( 'response' => $data ) );
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

        cloudsync_store_service_credentials( 'sharepoint', $updates );

        return $data['access_token'];
    }

    /**
     * Exchanges an OAuth code for SharePoint tokens.
     *
     * @since 4.3.0
     *
     * @param string $code Authorization code returned by Microsoft.
     *
     * @return array<string, mixed>|\WP_Error
     */
    public function exchange_code_for_tokens( $code ) {
        $credentials = cloudsync_get_service_credentials( 'sharepoint' );

        if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
            return new WP_Error( 'cloudsync_sharepoint_missing_keys', __( 'Configura el Client ID y Secret antes de autorizar SharePoint.', 'secure-pdf-viewer' ) );
        }

        $tenant = ! empty( $credentials['tenant_id'] ) ? $credentials['tenant_id'] : 'common';

        $response = wp_remote_post(
            sprintf( 'https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode( $tenant ) ),
            array(
                'timeout' => 20,
                'body'    => array(
                    'client_id'     => $credentials['client_id'],
                    'client_secret' => $credentials['client_secret'],
                    'code'          => $code,
                    'grant_type'    => 'authorization_code',
                    'redirect_uri'  => cloudsync_get_oauth_redirect_uri( 'sharepoint' ),
                    'scope'         => 'offline_access https://graph.microsoft.com/.default',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $body ) || ! is_array( $body ) ) {
            return new WP_Error( 'cloudsync_sharepoint_empty_response', __( 'SharePoint devolvió una respuesta vacía durante el intercambio de tokens.', 'secure-pdf-viewer' ) );
        }

        return $body;
    }

    /**
     * Stores SharePoint tokens returned by the OAuth callback.
     *
     * @since 4.3.0
     *
     * @param array<string, mixed> $tokens Token payload.
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

        cloudsync_store_service_credentials( 'sharepoint', $updates );

        cloudsync_add_log( __( 'SharePoint tokens stored from OAuth callback.', 'secure-pdf-viewer' ), array( 'service' => 'sharepoint' ) );
    }
}

if ( ! class_exists( 'CloudSync_SharePoint', false ) ) {
    class_alias( 'Connector_SharePoint', 'CloudSync_SharePoint' );
}
