<?php
class SPV_PDF_Viewer {

    public function init() {
        // Inicializar hooks si son necesarios
    }

    public function render_viewer($pdf_url, $width = '100%', $height = '600px', $title = '', $class = '', $pdf_id = '') {
        // ID único para el canvas
        $canvas_id = 'spv-pdf-canvas-' . uniqid();

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
                    <button type="button" id="hl-yellow" class="spv-btn spv-color-btn" data-color="#ffff00"
                            style="background: #ffff00; color: #333;"
                            title="<?php _e('Subrayar en amarillo', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en amarillo', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="hl-green" class="spv-btn spv-color-btn" data-color="#00ff00"
                            style="background: #00ff00; color: #333;"
                            title="<?php _e('Subrayar en verde', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en verde', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="hl-blue" class="spv-btn spv-color-btn" data-color="#00bfff"
                            style="background: #00bfff; color: #fff;"
                            title="<?php _e('Subrayar en azul', 'secure-pdf-viewer'); ?>"
                            aria-label="<?php _e('Subrayar en azul', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit" aria-hidden="true"></span>
                    </button>

                    <button type="button" id="hl-pink" class="spv-btn spv-color-btn" data-color="#ff69b4"
                            style="background: #ff69b4; color: #fff;"
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
                    <span id="zoom-label" class="spv-zoom-level" role="status" aria-live="polite">150%</span>
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
}
