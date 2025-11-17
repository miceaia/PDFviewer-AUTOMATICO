<?php
/**
 * Settings handler for the Secure PDF Viewer preferences.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SPV_PDF_Settings {

    const OPTION_NAME = 'spv_pdf_viewer_settings';

    /**
     * Boots hooks.
     */
    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

    /**
     * Registers the option with WordPress so it can be managed safely.
     */
    public static function register_settings() {
        register_setting(
            'spv_pdf_viewer',
            self::OPTION_NAME,
            array(
                'type'              => 'array',
                'default'           => self::get_default_settings(),
                'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
                'show_in_rest'      => false,
            )
        );
    }

    /**
     * Returns the default preferences applied to the PDF viewer.
     *
     * @return array
     */
    public static function get_default_settings() {
        return array(
            'default_zoom'      => 1.5,
            'min_zoom'          => 0.5,
            'max_zoom'          => 3.0,
            'highlight_opacity' => 0.4,
            'highlight_colors'  => array(
                'yellow' => '#ffff00',
                'green'  => '#00ff00',
                'blue'   => '#00bfff',
                'pink'   => '#ff69b4',
            ),
            'watermark_enabled'   => 1,
            'watermark_text'      => 'Usuario: {user_name} · Fecha: {date}',
            'watermark_color'     => '#000000',
            'watermark_opacity'   => 0.15,
            'watermark_font_size' => 14,
            'watermark_rotation'  => -30,
            'copy_protection'     => 1,
        );
    }

    /**
     * Fetches the persisted settings merged with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $stored = get_option( self::OPTION_NAME, array() );

        if ( empty( $stored ) || ! is_array( $stored ) ) {
            return self::get_default_settings();
        }

        return array_merge( self::get_default_settings(), $stored );
    }

    /**
     * Sanitizes and persists new settings.
     *
     * @param array $settings Raw settings.
     *
     * @return array
     */
    public static function update_settings( $settings ) {
        $sanitized = self::sanitize_settings( $settings );

        update_option( self::OPTION_NAME, $sanitized, false );

        return $sanitized;
    }

    /**
     * Sanitizes data before persisting.
     *
     * @param array $settings Raw input.
     *
     * @return array
     */
    public static function sanitize_settings( $settings ) {
        $defaults   = self::get_default_settings();
        $sanitized  = $defaults;
        $input      = is_array( $settings ) ? $settings : array();

        $sanitized['default_zoom'] = self::sanitize_number( $input, 'default_zoom', $defaults['default_zoom'], 0.1, 5 );
        $sanitized['min_zoom']     = self::sanitize_number( $input, 'min_zoom', $defaults['min_zoom'], 0.1, 5 );
        $sanitized['max_zoom']     = self::sanitize_number( $input, 'max_zoom', $defaults['max_zoom'], 0.5, 10 );

        if ( $sanitized['min_zoom'] > $sanitized['max_zoom'] ) {
            $sanitized['min_zoom'] = $defaults['min_zoom'];
            $sanitized['max_zoom'] = $defaults['max_zoom'];
        }

        $sanitized['highlight_opacity'] = self::sanitize_number( $input, 'highlight_opacity', $defaults['highlight_opacity'], 0, 1 );

        $colors = isset( $input['highlight_colors'] ) && is_array( $input['highlight_colors'] )
            ? $input['highlight_colors']
            : array();

        $sanitized_colors = array();
        foreach ( $defaults['highlight_colors'] as $slug => $default_color ) {
            $sanitized_colors[ $slug ] = self::sanitize_color( $colors[ $slug ] ?? $default_color, $default_color );
        }
        $sanitized['highlight_colors'] = $sanitized_colors;

        $sanitized['watermark_text']      = isset( $input['watermark_text'] ) ? sanitize_textarea_field( $input['watermark_text'] ) : $defaults['watermark_text'];
        $sanitized['watermark_enabled']   = empty( $input['watermark_enabled'] ) ? 0 : 1;
        $sanitized['watermark_color']     = self::sanitize_color( $input['watermark_color'] ?? $defaults['watermark_color'], $defaults['watermark_color'] );
        $sanitized['watermark_opacity']   = self::sanitize_number( $input, 'watermark_opacity', $defaults['watermark_opacity'], 0, 1 );
        $sanitized['watermark_font_size'] = self::sanitize_number( $input, 'watermark_font_size', $defaults['watermark_font_size'], 8, 72 );
        $sanitized['watermark_rotation']  = self::sanitize_number( $input, 'watermark_rotation', $defaults['watermark_rotation'], -90, 90 );
        $sanitized['copy_protection']     = empty( $input['copy_protection'] ) ? 0 : 1;

        return $sanitized;
    }

    /**
     * Registers REST routes.
     */
    public static function register_rest_routes() {
        register_rest_route(
            'secure-pdf-viewer/v1',
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'rest_get_settings' ),
                    'permission_callback' => array( __CLASS__, 'rest_permission_callback' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( __CLASS__, 'rest_update_settings' ),
                    'permission_callback' => array( __CLASS__, 'rest_permission_callback' ),
                    'args'                => self::get_rest_args(),
                ),
            )
        );
    }

    /**
     * Permission callback ensuring only admins can access the settings.
     *
     * @return bool
     */
    public static function rest_permission_callback() {
        return current_user_can( 'manage_options' );
    }

    /**
     * REST callback for GET requests.
     *
     * @return WP_REST_Response
     */
    public static function rest_get_settings() {
        return rest_ensure_response(
            array(
                'settings' => self::get_settings(),
            )
        );
    }

    /**
     * REST callback for POST/PUT requests.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return WP_REST_Response
     */
    public static function rest_update_settings( WP_REST_Request $request ) {
        $data = $request->get_json_params();

        if ( empty( $data ) ) {
            $data = $request->get_params();
        }

        $updated = self::update_settings( $data );

        return rest_ensure_response(
            array(
                'settings' => $updated,
            )
        );
    }

    /**
     * Settings schema used for REST args.
     *
     * @return array
     */
    protected static function get_rest_args() {
        return array(
            'default_zoom' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'min_zoom' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'max_zoom' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'highlight_opacity' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'highlight_colors' => array(
                'type'              => 'object',
                'required'          => false,
            ),
            'watermark_enabled' => array(
                'type'              => 'boolean',
                'required'          => false,
            ),
            'watermark_text' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_textarea_field',
            ),
            'watermark_color' => array(
                'type'              => 'string',
                'required'          => false,
            ),
            'watermark_opacity' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'watermark_font_size' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'watermark_rotation' => array(
                'type'              => 'number',
                'required'          => false,
                'sanitize_callback' => 'floatval',
            ),
            'copy_protection' => array(
                'type'              => 'boolean',
                'required'          => false,
            ),
        );
    }

    /**
     * Sanitizes a numeric setting.
     *
     * @param array  $input   Raw input array.
     * @param string $key     Setting key.
     * @param float  $default Default value.
     * @param float  $min     Minimum allowed.
     * @param float  $max     Maximum allowed.
     *
     * @return float
     */
    protected static function sanitize_number( $input, $key, $default, $min, $max ) {
        if ( ! isset( $input[ $key ] ) ) {
            return $default;
        }

        $value = floatval( $input[ $key ] );

        if ( $value < $min ) {
            return $min;
        }

        if ( $value > $max ) {
            return $max;
        }

        return $value;
    }

    /**
     * Sanitizes a hex color string.
     *
     * @param string $value    Raw value.
     * @param string $fallback Fallback color.
     *
     * @return string
     */
    protected static function sanitize_color( $value, $fallback ) {
        if ( is_string( $value ) ) {
            $value = trim( $value );
        }

        if ( is_string( $value ) && preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $value ) ) {
            return strtolower( $value );
        }

        return $fallback;
    }

    /**
     * Converts settings into a safe payload for frontend scripts.
     *
     * @return array
     */
    public static function prepare_frontend_settings() {
        $settings = self::get_settings();

        return array(
            'default_zoom'      => (float) $settings['default_zoom'],
            'min_zoom'          => (float) $settings['min_zoom'],
            'max_zoom'          => (float) $settings['max_zoom'],
            'highlight_opacity' => (float) $settings['highlight_opacity'],
            'highlight_colors'  => $settings['highlight_colors'],
            'watermark_enabled' => (int) $settings['watermark_enabled'],
            'watermark_text'    => $settings['watermark_text'],
            'watermark_color'   => $settings['watermark_color'],
            'watermark_opacity' => (float) $settings['watermark_opacity'],
            'watermark_font_size'=> (float) $settings['watermark_font_size'],
            'watermark_rotation'=> (float) $settings['watermark_rotation'],
            'copy_protection'   => (int) $settings['copy_protection'],
        );
    }
}
