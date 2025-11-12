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
                    <button class="spv-btn spv-prev" disabled title="<?php _e('Página anterior', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </button>

                    <span class="spv-page-info">
                        <span class="spv-current-page">1</span> /
                        <span class="spv-total-pages">0</span>
                    </span>

                    <button class="spv-btn spv-next" disabled title="<?php _e('Página siguiente', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </button>
                </div>

                <!-- Herramientas de anotación -->
                <div class="spv-control-group spv-annotation-tools">
                    <button class="spv-btn spv-select-tool active" title="<?php _e('Seleccionar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-move"></span>
                    </button>

                    <button class="spv-btn spv-highlight-tool" title="<?php _e('Resaltador', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-edit"></span>
                    </button>

                    <button class="spv-btn spv-eraser-tool" title="<?php _e('Borrador', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-editor-removeformatting"></span>
                    </button>

                    <input type="color" class="spv-color-picker" value="#ffff00"
                           title="<?php _e('Color del resaltador', 'secure-pdf-viewer'); ?>">

                    <div class="spv-color-presets">
                        <button class="spv-color-preset" data-color="#ffff00" style="background: #ffff00;" title="Amarillo"></button>
                        <button class="spv-color-preset" data-color="#00ff00" style="background: #00ff00;" title="Verde"></button>
                        <button class="spv-color-preset" data-color="#ff00ff" style="background: #ff00ff;" title="Rosa"></button>
                        <button class="spv-color-preset" data-color="#00ffff" style="background: #00ffff;" title="Cyan"></button>
                    </div>
                </div>

                <!-- Deshacer/Rehacer -->
                <div class="spv-control-group">
                    <button class="spv-btn spv-undo" disabled title="<?php _e('Deshacer', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-undo"></span>
                    </button>

                    <button class="spv-btn spv-redo" disabled title="<?php _e('Rehacer', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-redo"></span>
                    </button>
                </div>

                <!-- Zoom -->
                <div class="spv-control-group spv-zoom-controls">
                    <button class="spv-btn spv-zoom-out" title="<?php _e('Alejar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-minus"></span>
                    </button>
                    <span class="spv-zoom-level">100%</span>
                    <button class="spv-btn spv-zoom-in" title="<?php _e('Acercar', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-plus"></span>
                    </button>
                </div>

                <!-- Pantalla completa y Guardar -->
                <div class="spv-control-group">
                    <button class="spv-btn spv-fullscreen" title="<?php _e('Pantalla completa', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-fullscreen-alt"></span>
                    </button>

                    <button class="spv-btn spv-save-annotations" title="<?php _e('Guardar anotaciones', 'secure-pdf-viewer'); ?>">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Guardar', 'secure-pdf-viewer'); ?>
                    </button>
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
