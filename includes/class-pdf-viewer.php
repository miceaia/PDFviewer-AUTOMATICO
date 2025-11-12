<?php
class SPV_PDF_Viewer {

    public function init() {
        // Inicializar hooks si son necesarios
    }

    public function render_viewer($pdf_url, $width = '', $height = '', $title = '', $class = '', $pdf_id = '') {
        // ID único para el canvas
        $canvas_id = 'spv-pdf-canvas-' . uniqid();

        // Obtener configuraciones
        $settings = get_option('spv_settings', array());
        $default_width = isset($settings['default_width']) ? $settings['default_width'] : '100%';
        $default_height = isset($settings['default_height']) ? $settings['default_height'] : '600px';
        $toolbar_color = isset($settings['toolbar_color']) ? $settings['toolbar_color'] : '#24333F';

        // Usar valores por defecto si no se especifican
        if (empty($width)) {
            $width = $default_width;
        }
        if (empty($height)) {
            $height = $default_height;
        }

        // Obtener datos del usuario actual
        $current_user = wp_get_current_user();
        $user_data = array(
            'name' => $current_user->display_name,
            'email' => $current_user->user_email,
            'id' => $current_user->ID
        );

        ob_start();
        ?>
        <div class="spv-viewer-container <?php echo esc_attr($class); ?>"
             style="width: <?php echo esc_attr($width); ?>; height: <?php echo esc_attr($height); ?>;"
             data-pdf-url="<?php echo esc_url($pdf_url); ?>"
             data-pdf-id="<?php echo esc_attr($pdf_id); ?>"
             data-user-info='<?php echo json_encode($user_data); ?>'>

            <?php if ($title): ?>
                <h3 class="spv-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <!-- Barra superior compacta minimalista -->
            <div class="spv-toolbar" style="background-color: <?php echo esc_attr($toolbar_color); ?>">
                <!-- Navegación de páginas -->
                <div class="spv-toolbar-group">
                    <button type="button" id="btn-prev" class="spv-toolbar-btn" disabled
                            title="<?php _e('Página anterior (←)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Página anterior', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="btn-next" class="spv-toolbar-btn" disabled
                            title="<?php _e('Página siguiente (→)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Página siguiente', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="spv-toolbar-divider"></div>

                <!-- Contador de páginas -->
                <span id="page-counter" class="spv-page-counter" role="status" aria-live="polite">
                    <span class="spv-current-page">1</span> / <span class="spv-total-pages">0</span>
                </span>

                <div class="spv-toolbar-divider"></div>

                <!-- Subrayado con dropdown -->
                <div class="spv-highlight-container">
                    <button type="button" id="btn-highlight" class="spv-toolbar-btn spv-highlight-btn"
                            title="<?php _e('Subrayar texto', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar texto', 'secure-pdf-viewer'); ?>"
                            aria-expanded="false"
                            aria-haspopup="true">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                        <span class="spv-btn-text"><?php _e('Subrayar', 'secure-pdf-viewer'); ?></span>
                        <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                    </button>

                    <!-- Dropdown de colores -->
                    <div id="highlight-dropdown" class="spv-highlight-dropdown" role="menu" aria-hidden="true">
                        <button type="button" class="spv-color-option" data-color="#ffff00" role="menuitem"
                                aria-label="<?php _e('Amarillo', 'secure-pdf-viewer'); ?>">
                            <span class="spv-color-swatch" style="background: #ffff00;"></span>
                            <span class="spv-color-name"><?php _e('Amarillo', 'secure-pdf-viewer'); ?></span>
                        </button>
                        <button type="button" class="spv-color-option" data-color="#00ff00" role="menuitem"
                                aria-label="<?php _e('Verde', 'secure-pdf-viewer'); ?>">
                            <span class="spv-color-swatch" style="background: #00ff00;"></span>
                            <span class="spv-color-name"><?php _e('Verde', 'secure-pdf-viewer'); ?></span>
                        </button>
                        <button type="button" class="spv-color-option" data-color="#00bfff" role="menuitem"
                                aria-label="<?php _e('Azul', 'secure-pdf-viewer'); ?>">
                            <span class="spv-color-swatch" style="background: #00bfff;"></span>
                            <span class="spv-color-name"><?php _e('Azul', 'secure-pdf-viewer'); ?></span>
                        </button>
                        <button type="button" class="spv-color-option" data-color="#ff69b4" role="menuitem"
                                aria-label="<?php _e('Rosa', 'secure-pdf-viewer'); ?>">
                            <span class="spv-color-swatch" style="background: #ff69b4;"></span>
                            <span class="spv-color-name"><?php _e('Rosa', 'secure-pdf-viewer'); ?></span>
                        </button>
                        <button type="button" class="spv-color-option" data-color="#ff8c00" role="menuitem"
                                aria-label="<?php _e('Naranja', 'secure-pdf-viewer'); ?>">
                            <span class="spv-color-swatch" style="background: #ff8c00;"></span>
                            <span class="spv-color-name"><?php _e('Naranja', 'secure-pdf-viewer'); ?></span>
                        </button>
                        <div class="spv-dropdown-divider"></div>
                        <button type="button" id="btn-erase" class="spv-color-option spv-erase-option" role="menuitem"
                                aria-label="<?php _e('Borrar subrayado', 'secure-pdf-viewer'); ?>">
                            <span class="dashicons dashicons-editor-removeformatting" aria-hidden="true"></span>
                            <span class="spv-color-name"><?php _e('Borrar', 'secure-pdf-viewer'); ?></span>
                        </button>
                    </div>
                </div>

                <div class="spv-toolbar-divider"></div>

                <!-- Deshacer/Rehacer -->
                <div class="spv-toolbar-group">
                    <button type="button" id="btn-undo" class="spv-toolbar-btn" disabled
                            title="<?php _e('Deshacer (Ctrl+Z)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Deshacer', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="btn-redo" class="spv-toolbar-btn" disabled
                            title="<?php _e('Rehacer (Ctrl+Y)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Rehacer', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-redo" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="spv-toolbar-divider"></div>

                <!-- Zoom -->
                <div class="spv-toolbar-group spv-zoom-group">
                    <button type="button" id="btn-zoom-out" class="spv-toolbar-btn"
                            title="<?php _e('Alejar (Ctrl+-)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Alejar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-minus" aria-hidden="true"></span>
                    </button>
                    <span id="zoom-label" class="spv-zoom-label" role="status" aria-live="polite">150%</span>
                    <button type="button" id="btn-zoom-in" class="spv-toolbar-btn"
                            title="<?php _e('Acercar (Ctrl++)', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Acercar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-plus" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="spv-toolbar-divider"></div>

                <!-- Guardar -->
                <button type="button" id="btn-save" class="spv-toolbar-btn spv-save-btn"
                        title="<?php _e('Guardar anotaciones', 'secure-pdf-viewer'); ?>"
                        aria-label="<?php _e('Guardar anotaciones', 'secure-pdf-viewer'); ?>">
                    <span class="dashicons dashicons-saved" aria-hidden="true"></span>
                    <span class="spv-btn-text"><?php _e('Guardar', 'secure-pdf-viewer'); ?></span>
                </button>

                <span id="save-status" class="spv-save-status" role="status" aria-live="polite"></span>

                <div class="spv-toolbar-divider"></div>

                <!-- Pantalla completa -->
                <button type="button" id="btn-fullscreen" class="spv-toolbar-btn"
                        title="<?php _e('Pantalla completa (ESC para salir)', 'secure-pdf-viewer'); ?>"
                        aria-label="<?php _e('Pantalla completa', 'secure-pdf-viewer'); ?>">
                    <span class="dashicons dashicons-fullscreen-alt" aria-hidden="true"></span>
                </button>
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
}
