<?php
/**
 * Cloud sync manager for courses and lessons.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/class-connector-googledrive.php';
require_once __DIR__ . '/class-connector-dropbox.php';
require_once __DIR__ . '/class-connector-sharepoint.php';

/**
 * Coordinates synchronization between WordPress and cloud services.
 *
 * Usage example:
 *
 * <code>
 * $manager = new CloudSync_Manager();
 * $manager->init();
 * </code>
 *
 * Developers can hook into {@see 'cloudsync_after_create_course'} to obtain the
 * remote folder ID once a course is mirrored. To customize remote folder names,
 * use the {@see 'cloudsync_course_folder_name'} filter.
 */
class CloudSync_Manager {

    /**
     * Option name for the Google Drive delta token.
     */
    const OPTION_GDRIVE_START_PAGE = 'cloudsync_gdrive_start_page';

    /**
     * Option name for the Dropbox cursor.
     */
    const OPTION_DROPBOX_CURSOR = 'cloudsync_dropbox_cursor';

    /**
     * WordPress cron hook used to poll cloud services.
     */
    const CRON_HOOK = 'cloudsync_check_remote_changes';

    /**
     * Google Drive connector instance.
     *
     * @var Connector_GoogleDrive
     */
    protected $google;

    /**
     * Dropbox connector instance.
     *
     * @var Connector_Dropbox
     */
    protected $dropbox;

    /**
     * SharePoint connector instance.
     *
     * @var Connector_SharePoint
     */
    protected $sharepoint;

    /**
     * Bootstraps hooks.
     *
     * @since 4.0.0
     */
    public function init() {
        $this->google     = new Connector_GoogleDrive();
        $this->dropbox    = new Connector_Dropbox();
        $this->sharepoint = new Connector_SharePoint();

        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'save_post', array( $this, 'maybe_sync_post' ), 20, 2 );
        add_action( 'before_delete_post', array( $this, 'handle_post_delete' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );

        add_action( self::CRON_HOOK, array( $this, 'pull_remote_changes' ) );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
        }
    }

    /**
     * Registers custom post types for courses and lessons.
     *
     * @since 4.0.0
     *
     * @return void
     */
    public function register_post_types() {
        register_post_type(
            'curso',
            array(
                'labels' => array(
                    'name'          => __( 'Cursos', 'secure-pdf-viewer' ),
                    'singular_name' => __( 'Curso', 'secure-pdf-viewer' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'supports'     => array( 'title', 'editor' ),
                'show_in_menu' => true,
            )
        );

        register_post_type(
            'leccion',
            array(
                'labels' => array(
                    'name'          => __( 'Lecciones', 'secure-pdf-viewer' ),
                    'singular_name' => __( 'Lección', 'secure-pdf-viewer' ),
                ),
                'public'       => false,
                'show_ui'      => true,
                'supports'     => array( 'title', 'editor', 'page-attributes' ),
                'show_in_menu' => 'edit.php?post_type=curso',
            )
        );
    }

    /**
     * Registers plugin settings.
     *
     * @since 4.0.0
     *
     * @return void
     */
    public function register_settings() {
        register_setting( 'cloudsync_settings_group', 'cloudsync_settings', array( $this, 'sanitize_settings' ) );
    }

    /**
     * Validates and sanitizes incoming settings.
     *
     * @since 4.0.0
     *
     * @param array<string, mixed> $settings Raw settings.
     *
     * @return array<string, mixed> Sanitized settings.
     */
    public function sanitize_settings( $settings ) {
        $clean = array();

        $fields = array(
            'google_client_id',
            'google_client_secret',
            'google_refresh_token',
            'dropbox_app_key',
            'dropbox_app_secret',
            'dropbox_refresh_token',
            'sharepoint_client_id',
            'sharepoint_secret',
            'sharepoint_refresh_token',
        );

        foreach ( $fields as $field ) {
            $clean[ $field ] = isset( $settings[ $field ] ) ? sanitize_text_field( wp_unslash( $settings[ $field ] ) ) : '';
        }

        cloudsync_save_settings( $clean );

        return get_option( 'cloudsync_settings', array() );
    }

    /**
     * Registers settings and log admin pages.
     *
     * @since 4.0.0
     */
    public function register_admin_pages() {
        add_submenu_page(
            'options-general.php',
            __( 'Cloud Sync', 'secure-pdf-viewer' ),
            __( 'Cloud Sync', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-settings',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            null,
            __( 'Cloud Sync Logs', 'secure-pdf-viewer' ),
            __( 'Cloud Sync Logs', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-logs',
            array( $this, 'render_logs_page' )
        );
    }

    /**
     * Renders the settings page wrapper.
     *
     * @since 4.0.0
     */
    public function render_settings_page() {
        require_once SPV_PLUGIN_PATH . 'admin/settings-page.php';
    }

    /**
     * Renders the logs page wrapper.
     *
     * @since 4.0.0
     */
    public function render_logs_page() {
        require_once SPV_PLUGIN_PATH . 'admin/logs.php';
    }

    /**
     * Handles post creation/update to sync with cloud services.
     *
     * @since 4.0.0
     *
     * @param int      $post_id Post identifier.
     * @param \WP_Post $post    Post instance.
     */
    public function maybe_sync_post( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! in_array( $post->post_type, array( 'curso', 'leccion' ), true ) ) {
            return;
        }

        $name = cloudsync_prepare_name( $post->post_title, $post_id );

        if ( empty( $name ) ) {
            return;
        }

        $meta_keys = array(
            'curso'   => array( '_gd_folder_id', '_dbx_folder_id', '_sp_folder_id' ),
            'leccion' => array( '_gd_folder_id', '_dbx_folder_id', '_sp_folder_id' ),
        );

        foreach ( $meta_keys[ $post->post_type ] as $meta_key ) {
            $remote_id = get_post_meta( $post_id, $meta_key, true );

            if ( empty( $remote_id ) ) {
                $this->create_remote_folder( $post, $meta_key, $name );
            } else {
                $this->rename_remote_folder( $meta_key, $remote_id, $name );
            }
        }
    }

    /**
     * Processes a post deletion and mirrors it in the remote services.
     *
     * @since 4.0.0
     *
     * @param int $post_id Post identifier.
     */
    public function handle_post_delete( $post_id ) {
        $post = get_post( $post_id );

        if ( ! $post || ! in_array( $post->post_type, array( 'curso', 'leccion' ), true ) ) {
            return;
        }

        foreach ( array( '_gd_folder_id', '_dbx_folder_id', '_sp_folder_id' ) as $meta_key ) {
            $remote_id = get_post_meta( $post_id, $meta_key, true );

            if ( empty( $remote_id ) ) {
                continue;
            }

            $this->delete_remote_folder( $meta_key, $remote_id );
        }
    }

    /**
     * Iterates over remote connectors to create folders.
     *
     * @since 4.0.0
     *
     * @param \WP_Post $post     Post instance.
     * @param string   $meta_key Meta key used to store the identifier.
     * @param string   $name     Folder name.
     */
    protected function create_remote_folder( $post, $meta_key, $name ) {
        switch ( $meta_key ) {
            case '_gd_folder_id':
                $id = $this->google->create_folder( $name );
                break;
            case '_dbx_folder_id':
                $id = $this->dropbox->create_folder( $name );
                break;
            case '_sp_folder_id':
                $id = $this->sharepoint->create_folder( $name );
                break;
            default:
                $id = null;
        }

        if ( ! empty( $id ) ) {
            update_post_meta( $post->ID, $meta_key, $id );
            do_action( 'cloudsync_after_create_course', $post->ID, $id );
        }
    }

    /**
     * Renames remote folders when a post title changes.
     *
     * @since 4.0.0
     *
     * @param string $meta_key  Meta key storing the remote ID.
     * @param string $remote_id Remote identifier.
     * @param string $name      New name.
     */
    protected function rename_remote_folder( $meta_key, $remote_id, $name ) {
        switch ( $meta_key ) {
            case '_gd_folder_id':
                $this->google->rename_folder( $remote_id, $name );
                break;
            case '_dbx_folder_id':
                $this->dropbox->rename_folder( $remote_id, $name );
                break;
            case '_sp_folder_id':
                $this->sharepoint->rename_folder( $remote_id, $name );
                break;
        }
    }

    /**
     * Deletes a remote folder when the post is removed.
     *
     * @since 4.0.0
     *
     * @param string $meta_key  Meta key storing the remote ID.
     * @param string $remote_id Remote identifier.
     */
    protected function delete_remote_folder( $meta_key, $remote_id ) {
        switch ( $meta_key ) {
            case '_gd_folder_id':
                $this->google->delete_folder( $remote_id );
                break;
            case '_dbx_folder_id':
                $this->dropbox->delete_folder( $remote_id );
                break;
            case '_sp_folder_id':
                $this->sharepoint->delete_folder( $remote_id );
                break;
        }
    }

    /**
     * Pulls remote changes and reflects them in WordPress.
     *
     * @since 4.0.0
     *
     * @return void
     */
    public function pull_remote_changes() {
        $this->sync_google_drive_changes();
        $this->sync_dropbox_changes();
    }

    /**
     * Processes Google Drive changes feed and creates courses or lessons when needed.
     *
     * @since 4.0.0
     */
    protected function sync_google_drive_changes() {
        $token = get_option( self::OPTION_GDRIVE_START_PAGE, '' );

        $changes = $this->google->list_changes( $token );

        if ( empty( $changes['changes'] ) ) {
            return;
        }

        foreach ( $changes['changes'] as $change ) {
            if ( empty( $change['fileId'] ) || empty( $change['file']['name'] ) ) {
                continue;
            }

            $this->maybe_create_post_from_remote( $change['file']['name'], $change['fileId'], '_gd_folder_id' );
        }

        if ( isset( $changes['newStartPageToken'] ) ) {
            update_option( self::OPTION_GDRIVE_START_PAGE, $changes['newStartPageToken'] );
        }
    }

    /**
     * Processes Dropbox changes.
     *
     * @since 4.0.0
     */
    protected function sync_dropbox_changes() {
        $cursor = get_option( self::OPTION_DROPBOX_CURSOR, '' );

        $changes = $this->dropbox->list_changes( $cursor );

        if ( empty( $changes['entries'] ) ) {
            return;
        }

        foreach ( $changes['entries'] as $entry ) {
            if ( 'folder' !== $entry['.tag'] ) {
                continue;
            }

            $this->maybe_create_post_from_remote( basename( $entry['path_lower'] ), $entry['path_lower'], '_dbx_folder_id' );
        }

        if ( isset( $changes['cursor'] ) ) {
            update_option( self::OPTION_DROPBOX_CURSOR, $changes['cursor'] );
        }
    }

    /**
     * Creates a WordPress post if it does not exist yet for a remote folder.
     *
     * @since 4.0.0
     *
     * @param string $name      Remote folder name.
     * @param string $remote_id Remote identifier.
     * @param string $meta_key  Meta key to store the remote ID.
     */
    protected function maybe_create_post_from_remote( $name, $remote_id, $meta_key ) {
        $existing = get_posts(
            array(
                'post_type'      => array( 'curso', 'leccion' ),
                'posts_per_page' => 1,
                'meta_key'       => $meta_key,
                'meta_value'     => $remote_id,
                'fields'         => 'ids',
            )
        );

        if ( ! empty( $existing ) ) {
            return;
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'curso',
                'post_title'  => $name,
                'post_status' => 'publish',
            )
        );

        if ( $post_id && ! is_wp_error( $post_id ) ) {
            update_post_meta( $post_id, $meta_key, $remote_id );
            cloudsync_add_log( __( 'Course created from remote folder', 'secure-pdf-viewer' ), array( 'post_id' => $post_id ) );
        }
    }
}
