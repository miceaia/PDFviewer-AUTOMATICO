<?php
/**
 * Common interface for cloud sync connectors.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Defines the contract required by all cloud storage connectors.
 */
interface CloudSync_Connector_Interface {
    /**
     * Creates a folder within the remote service.
     *
     * @param string      $name      Folder name.
     * @param string|null $parent_id Optional parent identifier supported by the provider.
     *
     * @return string|null Remote folder identifier or null on failure.
     */
    public function create_folder( $name, $parent_id = null );

    /**
     * Lists remote changes since a cursor/token.
     *
     * @param string $since_token Token returned by previous calls.
     *
     * @return array<string, mixed>|null Parsed response payload or null when unavailable.
     */
    public function list_changes( $since_token );

    /**
     * Renames a remote folder.
     *
     * @param string $id       Remote folder identifier.
     * @param string $new_name New folder name.
     *
     * @return bool True on success, false otherwise.
     */
    public function rename_folder( $id, $new_name );

    /**
     * Deletes a remote folder.
     *
     * @param string $id Remote folder identifier.
     *
     * @return bool True on success, false otherwise.
     */
    public function delete_folder( $id );

    /**
     * Lists the child items for a given folder.
     *
     * @since 4.2.0
     *
     * @param string|null $parent_id Optional folder identifier; null for the service root.
     *
     * @return array<int, array<string, mixed>>|\WP_Error Normalized list of child items or error on failure.
     */
    public function list_folder_items( $parent_id = null );
}
