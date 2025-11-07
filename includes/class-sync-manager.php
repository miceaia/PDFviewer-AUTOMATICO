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
require_once __DIR__ . '/interface-cloudsync-connector.php';
require_once __DIR__ . '/class-connector-googledrive.php';
require_once __DIR__ . '/class-connector-dropbox.php';
require_once __DIR__ . '/class-connector-sharepoint.php';
require_once __DIR__ . '/class-explorer.php';

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
     * Explorer REST helper.
     *
     * @var CloudSync_Explorer
     */
    protected $explorer;

    /**
     * Bootstraps hooks.
     *
     * @since 4.0.0
     */
    public function init() {
        $this->google     = new Connector_GoogleDrive();
        $this->dropbox    = new Connector_Dropbox();
        $this->sharepoint = new Connector_SharePoint();
        $this->explorer   = new CloudSync_Explorer( $this->google, $this->dropbox, $this->sharepoint );

        add_action( 'init', array( $this, 'register_post_types' ) );
        add_action( 'save_post', array( $this, 'maybe_sync_post' ), 20, 2 );
        add_action( 'before_delete_post', array( $this, 'handle_post_delete' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        add_action( 'admin_menu', array( $this, 'register_admin_pages' ) );

        add_filter( 'cron_schedules', array( $this, 'register_custom_schedules' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        add_action( self::CRON_HOOK, array( $this, 'pull_remote_changes' ) );

        $this->ensure_cron_schedule();

        $this->explorer->init();
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
        register_setting( 'cloudsync_oauth', 'cloudsync_settings', array( $this, 'sanitize_settings' ) );
        register_setting( 'cloudsync_general', 'cloudsync_general_settings', array( $this, 'sanitize_general_settings' ) );
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
            'sharepoint_tenant_id',
            'sharepoint_refresh_token',
        );

        foreach ( $fields as $field ) {
            $clean[ $field ] = isset( $settings[ $field ] ) ? sanitize_text_field( wp_unslash( $settings[ $field ] ) ) : '';
        }

        cloudsync_save_settings( $clean );

        return get_option( 'cloudsync_settings', array() );
    }

    /**
     * Sanitizes the general dashboard settings.
     *
     * @since 4.1.0
     *
     * @param array<string, mixed> $settings Raw settings submitted by the admin.
     *
     * @return array<string, mixed> Clean settings ready to be persisted.
     */
    public function sanitize_general_settings( $settings ) {
        $defaults = cloudsync_get_general_settings();

        $clean = array(
            'sync_interval'       => isset( $settings['sync_interval'] ) ? sanitize_text_field( $settings['sync_interval'] ) : $defaults['sync_interval'],
            'auto_sync'           => isset( $settings['auto_sync'] ) ? 1 : 0,
            'priority_mode'       => isset( $settings['priority_mode'] ) ? sanitize_text_field( $settings['priority_mode'] ) : $defaults['priority_mode'],
            'root_google'         => isset( $settings['root_google'] ) ? sanitize_text_field( $settings['root_google'] ) : '',
            'root_dropbox'        => isset( $settings['root_dropbox'] ) ? sanitize_text_field( $settings['root_dropbox'] ) : '',
            'root_sharepoint'     => isset( $settings['root_sharepoint'] ) ? sanitize_text_field( $settings['root_sharepoint'] ) : '',
            'email_notifications' => isset( $settings['email_notifications'] ) ? 1 : 0,
            'developer_mode'      => isset( $settings['developer_mode'] ) ? 1 : 0,
        );

        $valid_intervals = array( '5', '10', '30', 'manual' );
        if ( ! in_array( $clean['sync_interval'], $valid_intervals, true ) ) {
            $clean['sync_interval'] = $defaults['sync_interval'];
        }

        $valid_priority = array( 'wp', 'cloud', 'bidirectional' );
        if ( ! in_array( $clean['priority_mode'], $valid_priority, true ) ) {
            $clean['priority_mode'] = $defaults['priority_mode'];
        }

        cloudsync_save_general_settings( $clean );

        $this->ensure_cron_schedule();

        return get_option( 'cloudsync_general_settings', $defaults );
    }

    /**
     * Registers custom cron schedules used by the dashboard settings.
     *
     * @since 4.1.0
     *
     * @param array<string, array<string, mixed>> $schedules Existing schedules.
     *
     * @return array<string, array<string, mixed>> Modified schedules list.
     */
    public function register_custom_schedules( $schedules ) {
        $schedules['five_minutes'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Cada 5 minutos', 'secure-pdf-viewer' ),
        );

        $schedules['ten_minutes'] = array(
            'interval' => 10 * MINUTE_IN_SECONDS,
            'display'  => __( 'Cada 10 minutos', 'secure-pdf-viewer' ),
        );

        $schedules['thirty_minutes'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display'  => __( 'Cada 30 minutos', 'secure-pdf-viewer' ),
        );

        return $schedules;
    }

    /**
     * Ensures the cron schedule matches the admin configuration.
     *
     * @since 4.1.0
     *
     * @return void
     */
    protected function ensure_cron_schedule() {
        $settings = cloudsync_get_general_settings();
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( empty( $settings['auto_sync'] ) || 'manual' === $settings['sync_interval'] ) {
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, self::CRON_HOOK );
            }

            update_option( 'cloudsync_current_schedule', 'manual' );
            return;
        }

        $map = array(
            '5'   => 'five_minutes',
            '10'  => 'ten_minutes',
            '30'  => 'thirty_minutes',
        );

        $schedule = isset( $map[ $settings['sync_interval'] ] ) ? $map[ $settings['sync_interval'] ] : 'hourly';
        $current  = get_option( 'cloudsync_current_schedule', '' );

        if ( $timestamp && $current === $schedule ) {
            return;
        }

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }

        wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, self::CRON_HOOK );
        update_option( 'cloudsync_current_schedule', $schedule );
    }

    /**
     * Registers settings and log admin pages.
     *
     * @since 4.0.0
     */
    public function register_admin_pages() {
        require_once SPV_PLUGIN_PATH . 'admin/dashboard.php';

        add_menu_page(
            __( 'CloudSync LMS', 'secure-pdf-viewer' ),
            __( 'CloudSync LMS', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-cloud',
            3
        );

        add_submenu_page(
            'cloudsync-dashboard',
            __( 'Configuración general', 'secure-pdf-viewer' ),
            __( 'Configuración general', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'cloudsync-dashboard',
            __( 'Credenciales OAuth', 'secure-pdf-viewer' ),
            __( 'Credenciales OAuth', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard-oauth',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'cloudsync-dashboard',
            __( 'Sincronización', 'secure-pdf-viewer' ),
            __( 'Sincronización', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard-sync',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'cloudsync-dashboard',
            __( 'Monitor / Logs', 'secure-pdf-viewer' ),
            __( 'Monitor / Logs', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard-logs',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'cloudsync-dashboard',
            __( 'Explorador de archivos', 'secure-pdf-viewer' ),
            __( 'Explorador de archivos', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard-explorer',
            array( $this, 'render_dashboard_page' )
        );

        add_submenu_page(
            'cloudsync-dashboard',
            __( 'Herramientas avanzadas', 'secure-pdf-viewer' ),
            __( 'Herramientas avanzadas', 'secure-pdf-viewer' ),
            'manage_options',
            'cloudsync-dashboard-advanced',
            array( $this, 'render_dashboard_page' )
        );
    }

    /**
     * Enqueues assets for the CloudSync dashboard experience.
     *
     * @since 4.1.2
     *
     * @param string $hook Current admin page hook suffix.
     *
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'cloudsync-dashboard' ) ) {
            return;
        }

        wp_enqueue_script(
            'cloudsync-oauth',
            SPV_PLUGIN_URL . 'assets/js/cloudsync-oauth.js',
            array(),
            SPV_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'cloudsync-oauth',
            'cloudsyncOAuthData',
            array(
                'popupName'   => 'cloudsync-oauth-popup',
                'popupWidth'  => 600,
                'popupHeight' => 700,
            )
        );

        wp_enqueue_style(
            'cloudsync-explorer',
            SPV_PLUGIN_URL . 'assets/css/cloudsync-explorer.css',
            array(),
            SPV_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'cloudsync-explorer',
            SPV_PLUGIN_URL . 'assets/js/cloudsync-explorer.js',
            array( 'jquery' ),
            SPV_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'cloudsync-explorer',
            'cloudsyncExplorerData',
            array(
                'restUrl' => rest_url( 'cloudsync/v1/explorer' ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
                'labels'  => array(
                    'loading' => __( 'Cargando...', 'secure-pdf-viewer' ),
                    'empty'   => __( 'Esta carpeta está vacía.', 'secure-pdf-viewer' ),
                    'error'   => __( 'No se pudo cargar la carpeta.', 'secure-pdf-viewer' ),
                    'copied'  => __( 'Enlace copiado al portapapeles.', 'secure-pdf-viewer' ),
                    'copy'    => __( 'Copiar enlace', 'secure-pdf-viewer' ),
                    'open'    => __( 'Abrir en la nube', 'secure-pdf-viewer' ),
                    'toggle'  => __( 'Alternar carpeta', 'secure-pdf-viewer' ),
                ),
            )
        );
    }

    /**
     * Renders the dashboard UI.
     *
     * @since 4.0.0
     */
    public function render_dashboard_page() {
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'cloudsync-dashboard';
        $tab_map      = array(
            'cloudsync-dashboard'           => 'general',
            'cloudsync-dashboard-oauth'     => 'oauth',
            'cloudsync-dashboard-sync'      => 'sync',
            'cloudsync-dashboard-logs'      => 'logs',
            'cloudsync-dashboard-explorer'  => 'explorer',
            'cloudsync-dashboard-advanced'  => 'advanced',
        );

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : ( isset( $tab_map[ $current_page ] ) ? $tab_map[ $current_page ] : 'general' );

        cloudsync_render_admin_page( $active_tab );
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
        $parent_id = $this->determine_parent_remote_id( $post, $meta_key );

        if ( 'leccion' === $post->post_type && null === $parent_id ) {
            return;
        }

        switch ( $meta_key ) {
            case '_gd_folder_id':
                $id = $this->google->create_folder( $name, $parent_id );
                break;
            case '_dbx_folder_id':
                $id = $this->dropbox->create_folder( $name, $parent_id );
                break;
            case '_sp_folder_id':
                $id = $this->sharepoint->create_folder( $name, $parent_id );
                break;
            default:
                $id = null;
        }

        if ( ! empty( $id ) ) {
            update_post_meta( $post->ID, $meta_key, $id );

            if ( 'curso' === $post->post_type ) {
                do_action( 'cloudsync_after_create_course', $post->ID, $id );
            }
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
     * Determines the parent remote identifier for lessons.
     *
     * @since 4.0.1
     *
     * @param \WP_Post $post     Post instance.
     * @param string   $meta_key Remote meta key.
     *
     * @return string|null Parent remote identifier when available.
     */
    protected function determine_parent_remote_id( $post, $meta_key ) {
        if ( 'leccion' !== $post->post_type ) {
            return null;
        }

        if ( empty( $post->post_parent ) ) {
            return null;
        }

        $parent_remote_id = get_post_meta( (int) $post->post_parent, $meta_key, true );

        if ( empty( $parent_remote_id ) ) {
            return null;
        }

        if ( '_dbx_folder_id' === $meta_key && false === strpos( $parent_remote_id, '/' ) ) {
            cloudsync_add_log(
                __( 'Dropbox parent folder path missing; skipping lesson folder creation.', 'secure-pdf-viewer' ),
                array(
                    'lesson_id' => $post->ID,
                    'parent_id' => (int) $post->post_parent,
                )
            );

            return null;
        }

        return $parent_remote_id;
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

    /**
     * Handles saving OAuth credentials from the dashboard.
     *
     * @since 4.1.1
     *
     * @return void
     */
    public function handle_save_credentials() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to update credentials.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_oauth_action', 'cloudsync_oauth_nonce' );

        $service     = isset( $_POST['service'] ) ? sanitize_key( wp_unslash( $_POST['service'] ) ) : '';
        $definitions = cloudsync_get_service_definitions();

        if ( ! $service || ! isset( $definitions[ $service ] ) ) {
            wp_safe_redirect( add_query_arg( array(
                'page'            => 'cloudsync-dashboard-oauth',
                'tab'             => 'oauth',
                'cloudsync_notice'=> 'invalid-service',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $settings = cloudsync_get_settings();

        foreach ( $definitions[ $service ]['fields'] as $field_key => $field_config ) {
            $value         = isset( $_POST[ $field_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) ) : '';
            $keep_existing = isset( $_POST[ $field_key . '_keep' ] ) && '1' === $_POST[ $field_key . '_keep' ];

            if ( '' === $value && $keep_existing && isset( $settings[ $field_key ] ) ) {
                continue;
            }

            $settings[ $field_key ] = $value;
        }

        cloudsync_save_settings( $settings );

        cloudsync_add_log(
            sprintf( __( '%s credentials updated from dashboard.', 'secure-pdf-viewer' ), $this->get_service_label( $service ) ),
            array( 'service' => $service )
        );

        wp_safe_redirect( add_query_arg( array(
            'page'             => 'cloudsync-dashboard-oauth',
            'tab'              => 'oauth',
            'cloudsync_notice' => 'credentials-saved',
            'service'          => $service,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Initiates the OAuth authorisation redirect.
     *
     * @since 4.1.1
     *
     * @return void
     */
    public function handle_oauth_connect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to connect services.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_oauth_action' );

        $service  = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';
        $settings = cloudsync_get_settings();

        add_filter( 'allowed_redirect_hosts', array( $this, 'allow_oauth_hosts' ) );

        $redirect_back = add_query_arg( array(
            'page'             => 'cloudsync-dashboard-oauth',
            'tab'              => 'oauth',
            'cloudsync_notice' => 'missing-credentials',
            'service'          => $service,
        ), admin_url( 'admin.php' ) );

        switch ( $service ) {
            case 'google':
                if ( empty( $settings['google_client_id'] ) || empty( $settings['google_client_secret'] ) ) {
                    wp_safe_redirect( $redirect_back );
                    exit;
                }

                $state     = wp_create_nonce( 'cloudsync_oauth_state_google' );
                $auth_url  = add_query_arg(
                    array(
                        'client_id'     => $settings['google_client_id'],
                        'redirect_uri'  => $this->get_oauth_redirect_uri( 'google' ),
                        'response_type' => 'code',
                        'scope'         => 'https://www.googleapis.com/auth/drive.file',
                        'access_type'   => 'offline',
                        'prompt'        => 'consent',
                        'state'         => $state,
                    ),
                    'https://accounts.google.com/o/oauth2/v2/auth'
                );

                wp_safe_redirect( $auth_url );
                exit;

            case 'dropbox':
                if ( empty( $settings['dropbox_app_key'] ) || empty( $settings['dropbox_app_secret'] ) ) {
                    wp_safe_redirect( $redirect_back );
                    exit;
                }

                $state    = wp_create_nonce( 'cloudsync_oauth_state_dropbox' );
                $auth_url = add_query_arg(
                    array(
                        'client_id'         => $settings['dropbox_app_key'],
                        'redirect_uri'      => $this->get_oauth_redirect_uri( 'dropbox' ),
                        'response_type'     => 'code',
                        'token_access_type' => 'offline',
                        'state'             => $state,
                    ),
                    'https://www.dropbox.com/oauth2/authorize'
                );

                wp_safe_redirect( $auth_url );
                exit;

            case 'sharepoint':
                if ( empty( $settings['sharepoint_client_id'] ) || empty( $settings['sharepoint_secret'] ) ) {
                    wp_safe_redirect( $redirect_back );
                    exit;
                }

                $tenant   = ! empty( $settings['sharepoint_tenant_id'] ) ? $settings['sharepoint_tenant_id'] : 'common';
                $state    = wp_create_nonce( 'cloudsync_oauth_state_sharepoint' );
                $auth_url = add_query_arg(
                    array(
                        'client_id'     => $settings['sharepoint_client_id'],
                        'response_type' => 'code',
                        'redirect_uri'  => $this->get_oauth_redirect_uri( 'sharepoint' ),
                        'response_mode' => 'query',
                        'scope'         => 'offline_access Files.ReadWrite.All Sites.ReadWrite.All',
                        'state'         => $state,
                    ),
                    sprintf( 'https://login.microsoftonline.com/%s/oauth2/v2.0/authorize', rawurlencode( $tenant ) )
                );

                wp_safe_redirect( $auth_url );
                exit;
        }

        wp_safe_redirect( add_query_arg( array(
            'page'             => 'cloudsync-dashboard-oauth',
            'tab'              => 'oauth',
            'cloudsync_notice' => 'invalid-service',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Processes the OAuth callback and stores refresh tokens.
     *
     * @since 4.1.1
     *
     * @return void
     */
    public function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to complete authorisation.', 'secure-pdf-viewer' ) );
        }

        $service = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';
        $code    = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
        $state   = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

        if ( ! $service || ! $code ) {
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'cloudsync-dashboard-oauth',
                'tab'              => 'oauth',
                'cloudsync_notice' => 'oauth-error',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        if ( ! $state || ! wp_verify_nonce( $state, 'cloudsync_oauth_state_' . $service ) ) {
            wp_die( esc_html__( 'Invalid OAuth state. Please try again.', 'secure-pdf-viewer' ) );
        }

        $settings     = cloudsync_get_settings();
        $definitions  = cloudsync_get_service_definitions();
        $redirect_uri = $this->get_oauth_redirect_uri( $service );
        $body         = array();
        $endpoint     = '';

        if ( ! isset( $definitions[ $service ] ) ) {
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'cloudsync-dashboard-oauth',
                'tab'              => 'oauth',
                'cloudsync_notice' => 'invalid-service',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        switch ( $service ) {
            case 'google':
                $endpoint = 'https://oauth2.googleapis.com/token';
                $body     = array(
                    'code'          => $code,
                    'client_id'     => $settings['google_client_id'],
                    'client_secret' => $settings['google_client_secret'],
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                );
                break;

            case 'dropbox':
                $endpoint = 'https://api.dropboxapi.com/oauth2/token';
                $body     = array(
                    'code'          => $code,
                    'client_id'     => $settings['dropbox_app_key'],
                    'client_secret' => $settings['dropbox_app_secret'],
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                );
                break;

            case 'sharepoint':
                $tenant   = ! empty( $settings['sharepoint_tenant_id'] ) ? $settings['sharepoint_tenant_id'] : 'common';
                $endpoint = sprintf( 'https://login.microsoftonline.com/%s/oauth2/v2.0/token', rawurlencode( $tenant ) );
                $body     = array(
                    'code'          => $code,
                    'client_id'     => $settings['sharepoint_client_id'],
                    'client_secret' => $settings['sharepoint_secret'],
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                    'scope'         => 'offline_access Files.ReadWrite.All Sites.ReadWrite.All',
                );
                break;

            default:
                wp_safe_redirect( add_query_arg( array(
                    'page'             => 'cloudsync-dashboard-oauth',
                    'tab'              => 'oauth',
                    'cloudsync_notice' => 'invalid-service',
                ), admin_url( 'admin.php' ) ) );
                exit;
        }

        $response = wp_remote_post( $endpoint, array( 'body' => $body ) );

        if ( is_wp_error( $response ) ) {
            cloudsync_add_log( __( 'OAuth token exchange failed.', 'secure-pdf-viewer' ), array( 'service' => $service, 'error' => $response->get_error_message() ) );
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'cloudsync-dashboard-oauth',
                'tab'              => 'oauth',
                'cloudsync_notice' => 'oauth-error',
                'service'          => $service,
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data ) || ! is_array( $data ) ) {
            cloudsync_add_log( __( 'OAuth token exchange returned an empty response.', 'secure-pdf-viewer' ), array( 'service' => $service ) );
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'cloudsync-dashboard-oauth',
                'tab'              => 'oauth',
                'cloudsync_notice' => 'oauth-error',
                'service'          => $service,
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $token_key = $definitions[ $service ]['token_field'];

        if ( ! empty( $data['refresh_token'] ) ) {
            $settings[ $token_key ] = sanitize_text_field( $data['refresh_token'] );
        }

        cloudsync_save_settings( $settings );

        cloudsync_add_log(
            sprintf( __( '%s connected via OAuth.', 'secure-pdf-viewer' ), $this->get_service_label( $service ) ),
            array( 'service' => $service )
        );

        wp_safe_redirect( add_query_arg( array(
            'page'             => 'cloudsync-dashboard-oauth',
            'tab'              => 'oauth',
            'cloudsync_notice' => 'connected',
            'service'          => $service,
            'cloudsync_popup'  => '1',
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Revokes stored credentials for a service.
     *
     * @since 4.1.1
     *
     * @return void
     */
    public function handle_revoke_access() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to revoke access.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_oauth_action' );

        $service  = isset( $_GET['service'] ) ? sanitize_key( wp_unslash( $_GET['service'] ) ) : '';
        $settings    = cloudsync_get_settings();
        $definitions = cloudsync_get_service_definitions();

        if ( ! isset( $definitions[ $service ] ) ) {
            wp_safe_redirect( add_query_arg( array(
                'page'             => 'cloudsync-dashboard-oauth',
                'tab'              => 'oauth',
                'cloudsync_notice' => 'invalid-service',
            ), admin_url( 'admin.php' ) ) );
            exit;
        }

        $token_field = $definitions[ $service ]['token_field'];
        $token       = isset( $settings[ $token_field ] ) ? $settings[ $token_field ] : '';

        switch ( $service ) {
            case 'google':
                if ( $token ) {
                    wp_remote_post( 'https://oauth2.googleapis.com/revoke', array( 'body' => array( 'token' => $token ) ) );
                }
                break;

            case 'dropbox':
                if ( $token && ! empty( $settings['dropbox_app_key'] ) && ! empty( $settings['dropbox_app_secret'] ) ) {
                    wp_remote_post(
                        'https://api.dropboxapi.com/2/oauth2/token/revoke',
                        array(
                            'body'    => array( 'token' => $token ),
                            'headers' => array(
                                'Authorization' => 'Basic ' . base64_encode( $settings['dropbox_app_key'] . ':' . $settings['dropbox_app_secret'] ),
                            ),
                        )
                    );
                }
                break;

            case 'sharepoint':
                // Microsoft Graph does not provide a direct refresh token revoke endpoint for app tokens.
                break;
        }

        $settings[ $token_field ] = '';

        cloudsync_save_settings( $settings );

        cloudsync_add_log(
            sprintf( __( '%s access revoked from dashboard.', 'secure-pdf-viewer' ), $this->get_service_label( $service ) ),
            array( 'service' => $service )
        );

        wp_safe_redirect( add_query_arg( array(
            'page'             => 'cloudsync-dashboard-oauth',
            'tab'              => 'oauth',
            'cloudsync_notice' => 'revoked',
            'service'          => $service,
        ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Builds the callback URL for a given service.
     *
     * @since 4.1.1
     *
     * @param string $service Service slug.
     *
     * @return string
     */
    protected function get_oauth_redirect_uri( $service ) {
        return cloudsync_get_oauth_redirect_uri( $service );
    }

    /**
     * Provides a human-friendly label for services.
     *
     * @since 4.1.1
     *
     * @param string $service Service slug.
     *
     * @return string
     */
    protected function get_service_label( $service ) {
        $labels = array(
            'google'     => __( 'Google Drive', 'secure-pdf-viewer' ),
            'dropbox'    => __( 'Dropbox', 'secure-pdf-viewer' ),
            'sharepoint' => __( 'SharePoint', 'secure-pdf-viewer' ),
        );

        return isset( $labels[ $service ] ) ? $labels[ $service ] : ucfirst( $service );
    }

    /**
     * Allows external OAuth hosts during wp_safe_redirect usage.
     *
     * @since 4.1.2
     *
     * @param array<int, string> $hosts Existing hosts.
     *
     * @return array<int, string>
     */
    public function allow_oauth_hosts( $hosts ) {
        $oauth_hosts = array(
            'accounts.google.com',
            'www.dropbox.com',
            'login.microsoftonline.com',
        );

        return array_unique( array_merge( $hosts, $oauth_hosts ) );
    }

    /**
     * Handles manual synchronization from the dashboard.
     *
     * @since 4.1.0
     */
    public function handle_manual_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to run synchronisation.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_manual_sync' );

        $this->pull_remote_changes();
        $this->sync_all_posts();

        update_option( 'cloudsync_last_sync', current_time( 'timestamp' ) );
        cloudsync_add_log( __( 'Manual synchronisation executed from dashboard.', 'secure-pdf-viewer' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'cloudsync-dashboard-sync', 'cloudsync_notice' => 'manual-sync' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Forces an immediate sync from the advanced tools tab.
     *
     * @since 4.1.2
     */
    public function handle_force_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to force synchronisation.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_force_sync' );

        $this->pull_remote_changes();
        $this->sync_all_posts();

        update_option( 'cloudsync_last_sync', current_time( 'timestamp' ) );
        cloudsync_add_log( __( 'Force synchronisation executed from advanced tools.', 'secure-pdf-viewer' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'cloudsync-dashboard-advanced', 'cloudsync_notice' => 'force-sync' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Purges orphaned metadata entries.
     *
     * @since 4.1.0
     */
    public function handle_cleanup_meta() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to clean metadata.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_cleanup_meta' );

        global $wpdb;

        $allowed_meta = array( '_gd_folder_id', '_dbx_folder_id', '_sp_folder_id' );
        $meta_placeholders = implode( ',', array_fill( 0, count( $allowed_meta ), '%s' ) );

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key IN ($meta_placeholders) AND (p.ID IS NULL OR (p.post_type NOT IN ('curso','leccion')))",
                $allowed_meta
            )
        );

        cloudsync_add_log( __( 'Orphaned metadata cleaned from dashboard tools.', 'secure-pdf-viewer' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'cloudsync-dashboard-advanced', 'cloudsync_notice' => 'cleanup' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Resets stored OAuth tokens.
     *
     * @since 4.1.0
     */
    public function handle_reset_tokens() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to reset tokens.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_reset_tokens' );

        $settings = cloudsync_get_settings();
        $service = isset( $_REQUEST['service'] ) ? sanitize_key( wp_unslash( $_REQUEST['service'] ) ) : '';

        $map = array(
            'google'     => 'google_refresh_token',
            'dropbox'    => 'dropbox_refresh_token',
            'sharepoint' => 'sharepoint_refresh_token',
        );

        if ( $service && isset( $map[ $service ] ) ) {
            $settings[ $map[ $service ] ] = '';
            $label = ucfirst( $service );
            cloudsync_add_log( sprintf( __( 'OAuth tokens cleared for %s.', 'secure-pdf-viewer' ), $label ), array( 'service' => $service ) );
        } else {
            foreach ( $map as $token_key ) {
                $settings[ $token_key ] = '';
            }
            cloudsync_add_log( __( 'OAuth tokens cleared from dashboard.', 'secure-pdf-viewer' ) );
        }

        cloudsync_save_settings( $settings );

        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = admin_url( 'admin.php?page=cloudsync-dashboard-advanced' );
        }

        $redirect = add_query_arg( 'cloudsync_notice', 'reset-tokens', $redirect );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Rebuilds the remote folder structure.
     *
     * @since 4.1.0
     */
    public function handle_rebuild_structure() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to rebuild folders.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_rebuild_structure' );

        $posts = get_posts(
            array(
                'post_type'      => array( 'curso', 'leccion' ),
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
            )
        );

        foreach ( $posts as $post ) {
            foreach ( array( '_gd_folder_id', '_dbx_folder_id', '_sp_folder_id' ) as $meta_key ) {
                delete_post_meta( $post->ID, $meta_key );
            }

            $this->maybe_sync_post( $post->ID, $post );
        }

        cloudsync_add_log( __( 'Folder structure reinitialised from dashboard.', 'secure-pdf-viewer' ) );

        wp_safe_redirect( add_query_arg( array( 'page' => 'cloudsync-dashboard-advanced', 'cloudsync_notice' => 'rebuild' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Toggles developer mode visualisations.
     *
     * @since 4.1.0
     */
    public function handle_toggle_dev_mode() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to change developer mode.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_toggle_dev_mode' );

        $settings = cloudsync_get_general_settings();
        $settings['developer_mode'] = isset( $_POST['developer_mode'] ) ? 1 : 0;

        cloudsync_save_general_settings( $settings );
        cloudsync_add_log( __( 'Developer mode preference updated.', 'secure-pdf-viewer' ) );

        $redirect = wp_get_referer();
        if ( ! $redirect ) {
            $redirect = admin_url( 'admin.php?page=cloudsync-dashboard-advanced' );
        }

        $redirect = add_query_arg( 'cloudsync_notice', 'developer-mode', $redirect );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Streams log entries as a JSON download.
     *
     * @since 4.1.0
     */
    public function handle_download_logs() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to download logs.', 'secure-pdf-viewer' ) );
        }

        check_admin_referer( 'cloudsync_download_logs' );

        $logs = cloudsync_get_logs();

        nocache_headers();
        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="cloudsync-logs-' . gmdate( 'Ymd-His' ) . '.json"' );

        echo wp_json_encode( $logs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /**
     * Ensures every existing course and lesson is synchronised.
     *
     * @since 4.1.0
     *
     * @return void
     */
    protected function sync_all_posts() {
        $posts = get_posts(
            array(
                'post_type'      => array( 'curso', 'leccion' ),
                'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
                'posts_per_page' => -1,
            )
        );

        foreach ( $posts as $post ) {
            $this->maybe_sync_post( $post->ID, $post );
        }
    }
}
