<?php
/**
 * REST explorer for CloudSync services.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/helpers.php';

/**
 * Provides a REST endpoint used by the dashboard file explorer.
 */
class CloudSync_Explorer {

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
     * CloudSync_Explorer constructor.
     *
     * @param Connector_GoogleDrive  $google     Google connector.
     * @param Connector_Dropbox      $dropbox    Dropbox connector.
     * @param Connector_SharePoint   $sharepoint SharePoint connector.
     */
    public function __construct( Connector_GoogleDrive $google, Connector_Dropbox $dropbox, Connector_SharePoint $sharepoint ) {
        $this->google     = $google;
        $this->dropbox    = $dropbox;
        $this->sharepoint = $sharepoint;
    }

    /**
     * Boots the REST routes required for the explorer.
     *
     * @return void
     */
    public function init() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Registers the explorer REST route.
     *
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            'cloudsync/v1',
            '/explorer',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_list_request' ),
                'permission_callback' => array( $this, 'permissions_check' ),
                'args'                => array(
                    'service' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'validate_callback' => array( $this, 'validate_service' ),
                        'sanitize_callback' => 'sanitize_key',
                    ),
                    'parent'  => array(
                        'type'     => 'string',
                        'required' => false,
                    ),
                ),
            )
        );
    }

    /**
     * Validates that the current user can access the explorer data.
     *
     * @return bool
     */
    public function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Ensures the requested service is known.
     *
     * @param string $service Service slug.
     *
     * @return bool
     */
    public function validate_service( $service ) {
        $allowed = array( 'google', 'dropbox', 'sharepoint' );

        return in_array( $service, $allowed, true );
    }

    /**
     * Handles explorer requests.
     *
     * @param \WP_REST_Request $request Current request.
     *
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_list_request( \WP_REST_Request $request ) {
        $service   = $request->get_param( 'service' );
        $parent_id = $request->get_param( 'parent' );

        $connector = $this->get_connector( $service );

        if ( ! $connector ) {
            return new \WP_Error( 'cloudsync_unknown_service', __( 'Servicio de nube desconocido.', 'secure-pdf-viewer' ), array( 'status' => 400 ) );
        }

        if ( empty( $parent_id ) ) {
            $parent_id = $this->get_root_for_service( $service );
        }

        $items = $connector->list_folder_items( $parent_id );

        if ( is_wp_error( $items ) ) {
            $items->add_data( array( 'status' => 400 ) );

            return $items;
        }

        $response = array();

        foreach ( $items as $item ) {
            $response[] = array(
                'id'             => isset( $item['id'] ) ? $item['id'] : '',
                'name'           => isset( $item['name'] ) ? $item['name'] : '',
                'type'           => isset( $item['type'] ) ? $item['type'] : 'file',
                'service'        => isset( $item['service'] ) ? $item['service'] : $service,
                'has_children'   => ! empty( $item['has_children'] ),
                'size'           => isset( $item['size'] ) ? (int) $item['size'] : 0,
                'size_human'     => isset( $item['size'] ) && $item['size'] ? size_format( (int) $item['size'] ) : '',
                'modified'       => isset( $item['modified'] ) ? $item['modified'] : '',
                'modified_human' => $this->format_modified( isset( $item['modified'] ) ? $item['modified'] : '' ),
                'web_url'        => isset( $item['web_url'] ) ? $item['web_url'] : '',
                'icon'           => isset( $item['icon'] ) ? $item['icon'] : '',
            );
        }

        return rest_ensure_response( $response );
    }

    /**
     * Returns the connector object for the requested service.
     *
     * @param string $service Service slug.
     *
     * @return CloudSync_Connector_Interface|null
     */
    protected function get_connector( $service ) {
        switch ( $service ) {
            case 'google':
                return $this->google;
            case 'dropbox':
                return $this->dropbox;
            case 'sharepoint':
                return $this->sharepoint;
            default:
                return null;
        }
    }

    /**
     * Resolves the configured root folder for a service.
     *
     * @param string $service Service slug.
     *
     * @return string|null
     */
    protected function get_root_for_service( $service ) {
        $settings = cloudsync_get_general_settings();

        switch ( $service ) {
            case 'google':
                return ! empty( $settings['root_google'] ) ? $settings['root_google'] : null;
            case 'dropbox':
                return ! empty( $settings['root_dropbox'] ) ? $settings['root_dropbox'] : '';
            case 'sharepoint':
                return ! empty( $settings['root_sharepoint'] ) ? $settings['root_sharepoint'] : null;
            default:
                return null;
        }
    }

    /**
     * Formats modified timestamps using site preferences.
     *
     * @param string $raw Raw ISO date.
     *
     * @return string
     */
    protected function format_modified( $raw ) {
        if ( empty( $raw ) ) {
            return '';
        }

        $timestamp = strtotime( $raw );

        if ( ! $timestamp ) {
            return '';
        }

        return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }
}
