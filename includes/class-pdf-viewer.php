<?php
class SPV_PDF_Viewer {
    
    public function init() {
        // Inicializar hooks si son necesarios
    }
    
    public function render_viewer($pdf_url, $width = '100%', $height = '600px', $title = '', $class = '') {
        // ID único para el canvas
        $canvas_id = 'spv-pdf-canvas-' . uniqid();
        
        ob_start();
        ?>
        <div class="spv-viewer-container <?php echo esc_attr($class); ?>" 
             style="width: <?php echo esc_attr($width); ?>; height: <?php echo esc_attr($height); ?>;"
             data-pdf-url="<?php echo esc_url($pdf_url); ?>">
            
            <?php if ($title): ?>
                <h3 class="spv-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            
            <div class="spv-controls">
                <button class="spv-btn spv-prev" disabled>
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <?php _e('Anterior', 'secure-pdf-viewer'); ?>
                </button>
                
                <span class="spv-page-info">
                    <?php _e('Página', 'secure-pdf-viewer'); ?>: 
                    <span class="spv-current-page">1</span> / 
                    <span class="spv-total-pages">0</span>
                </span>
                
                <button class="spv-btn spv-next" disabled>
                    <?php _e('Siguiente', 'secure-pdf-viewer'); ?>
                    <span class="dashicons dashicons-arrow-right-alt2"></span>
                </button>
                
                <div class="spv-zoom-controls">
                    <button class="spv-btn spv-zoom-out">
                        <span class="dashicons dashicons-minus"></span>
                    </button>
                    <span class="spv-zoom-level">100%</span>
                    <button class="spv-btn spv-zoom-in">
                        <span class="dashicons dashicons-plus"></span>
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
