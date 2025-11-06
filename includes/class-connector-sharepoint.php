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
}
