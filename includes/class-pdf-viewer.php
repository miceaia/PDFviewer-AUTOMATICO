<?php
class SPV_PDF_Viewer {

    public function init() {
        // Inicializar hooks si son necesarios
    }

    public function render_viewer($pdf_url, $width = '100%', $height = '600px', $title = '', $class = '', $pdf_id = '') {
        // ID único para el canvas
        $canvas_id = 'spv-pdf-canvas-' . uniqid();

        $viewer_settings  = SPV_PDF_Settings::prepare_frontend_settings();
        $highlight_colors = isset( $viewer_settings['highlight_colors'] ) && is_array( $viewer_settings['highlight_colors'] )
            ? $viewer_settings['highlight_colors']
            : array();
        $default_zoom_percent = isset( $viewer_settings['default_zoom'] )
            ? max( 10, min( 500, intval( round( $viewer_settings['default_zoom'] * 100 ) ) ) )
            : 150;

        // Obtener datos del usuario actual
        $current_user = wp_get_current_user();
        $user_roles   = is_array( $current_user->roles ) ? $current_user->roles : array();

        $user_data = array(
            'name'     => $current_user->display_name,
            'username' => $current_user->user_login,
            'email'    => $current_user->user_email,
            'id'       => $current_user->ID,
        );

        $user_data_attribute = esc_attr( wp_json_encode( $user_data ) );

        ob_start();
        ?>
        <div class="spv-viewer-container <?php echo esc_attr($class); ?>"
             style="width: <?php echo esc_attr($width); ?>; height: <?php echo esc_attr($height); ?>;"
             data-pdf-url="<?php echo esc_url($pdf_url); ?>"
             data-pdf-id="<?php echo esc_attr($pdf_id); ?>"
             data-user-info='<?php echo esc_attr( wp_json_encode( $user_data ) ); ?>'>

            <?php if ($title): ?>
                <h3 class="spv-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <div class="spv-controls">
                <!-- Navegación -->
                <div class="spv-control-group">
                    <button type="button" id="btn-prev" class="spv-btn spv-prev" disabled
                            title="<?php _e('Página anterior (←)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Página anterior', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                    </button>

                    <span id="page-counter" class="spv-page-info" role="status" aria-live="polite">
                        <span class="spv-current-page">1</span> /
                        <span class="spv-total-pages">0</span>
                    </span>

                    <button type="button" id="btn-next" class="spv-btn spv-next" disabled
                            title="<?php _e('Página siguiente (→)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Página siguiente', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </button>
                </div>

                <!-- Herramientas de anotación - Colores de subrayado -->
                <div class="spv-control-group spv-annotation-tools">
                    <?php
                    $yellow_color = $this->get_highlight_color( $highlight_colors, 'yellow', '#ffff00' );
                    $yellow_text  = $this->get_contrast_color( $yellow_color );
                    ?>
                    <button type="button" id="hl-yellow" class="spv-btn spv-color-btn"
                            data-color="<?php echo esc_attr( $yellow_color ); ?>"
                            style="background: <?php echo esc_attr( $yellow_color ); ?>; color: <?php echo esc_attr( $yellow_text ); ?>;"
                            title="<?php _e('Subrayar en amarillo', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en amarillo', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <?php
                    $green_color = $this->get_highlight_color( $highlight_colors, 'green', '#00ff00' );
                    $green_text  = $this->get_contrast_color( $green_color );
                    ?>
                    <button type="button" id="hl-green" class="spv-btn spv-color-btn"
                            data-color="<?php echo esc_attr( $green_color ); ?>"
                            style="background: <?php echo esc_attr( $green_color ); ?>; color: <?php echo esc_attr( $green_text ); ?>;"
                            title="<?php _e('Subrayar en verde', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en verde', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <?php
                    $blue_color = $this->get_highlight_color( $highlight_colors, 'blue', '#00bfff' );
                    $blue_text  = $this->get_contrast_color( $blue_color );
                    ?>
                    <button type="button" id="hl-blue" class="spv-btn spv-color-btn"
                            data-color="<?php echo esc_attr( $blue_color ); ?>"
                            style="background: <?php echo esc_attr( $blue_color ); ?>; color: <?php echo esc_attr( $blue_text ); ?>;"
                            title="<?php _e('Subrayar en azul', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en azul', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <?php
                    $pink_color = $this->get_highlight_color( $highlight_colors, 'pink', '#ff69b4' );
                    $pink_text  = $this->get_contrast_color( $pink_color );
                    ?>
                    <button type="button" id="hl-pink" class="spv-btn spv-color-btn"
                            data-color="<?php echo esc_attr( $pink_color ); ?>"
                            style="background: <?php echo esc_attr( $pink_color ); ?>; color: <?php echo esc_attr( $pink_text ); ?>;"
                            title="<?php _e('Subrayar en rosa', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en rosa', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="hl-erase" class="spv-btn spv-eraser-tool"
                            title="<?php _e('Borrar subrayado', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Borrar subrayado', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-editor-removeformatting" aria-hidden="true"></span>
                    </button>
                </div>

                <!-- Deshacer/Rehacer -->
                <div class="spv-control-group">
                    <button type="button" id="btn-undo" class="spv-btn spv-undo" disabled
                            title="<?php _e('Deshacer (Ctrl+Z)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Deshacer', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="btn-redo" class="spv-btn spv-redo" disabled
                            title="<?php _e('Rehacer (Ctrl+Y)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Rehacer', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-redo" aria-hidden="true"></span>
                    </button>
                </div>

                <!-- Zoom -->
                <div class="spv-control-group spv-zoom-controls">
                    <button type="button" id="btn-zoom-out" class="spv-btn spv-zoom-out"
                            title="<?php _e('Alejar', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Alejar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                    </button>
                    <span id="zoom-label" class="spv-zoom-level" role="status" aria-live="polite"><?php echo esc_html( $default_zoom_percent ); ?>%</span>
                    <button type="button" id="btn-zoom-in" class="spv-btn spv-zoom-in"
                            title="<?php _e('Acercar', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Acercar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                    </button>
                </div>

                <!-- Pantalla completa y Guardar -->
                <div class="spv-control-group">
                    <button type="button" id="btn-fullscreen" class="spv-btn spv-fullscreen"
                            title="<?php _e('Pantalla completa (ESC para salir)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Pantalla completa', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="btn-save" class="spv-btn spv-save-annotations"
                            title="<?php _e('Guardar anotaciones', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Guardar anotaciones', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                        <?php _e('Guardar', 'secure-pdf-viewer'); ?>
                    </button>

                    <span id="save-status" class="spv-save-status" role="status" aria-live="polite"></span>
                </div>
            </div>

            <div class="spv-canvas-container">
                <canvas id="<?php echo esc_attr($canvas_id); ?>" class="spv-pdf-canvas"></canvas>
                <div class="spv-loading">
                    <div class="spv-spinner"></div>
                    <p><?php _e('Cargando PDF...', 'secure-pdf-viewer'); ?></p>
                </div>
            </div>

            <div class="spv-protection-overlay"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    protected function get_highlight_color( $colors, $slug, $fallback ) {
        if ( isset( $colors[ $slug ] ) && is_string( $colors[ $slug ] ) && preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $colors[ $slug ] ) ) {
            return strtolower( $colors[ $slug ] );
        }

        return $fallback;
    }

    protected function get_contrast_color( $hex_color ) {
        if ( ! is_string( $hex_color ) ) {
            return '#ffffff';
        }

        $hex = ltrim( $hex_color, '#' );

        if ( 3 === strlen( $hex ) ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( 6 !== strlen( $hex ) ) {
            return '#ffffff';
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        $luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;

        return $luminance > 0.6 ? '#333333' : '#ffffff';
    }
}
