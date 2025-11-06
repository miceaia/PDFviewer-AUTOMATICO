<?php
class SPV_Gutenberg_Block {
    
    public function init() {
        add_action('init', array($this, 'register_block'));
    }
    
    public function register_block() {
        if (!function_exists('register_block_type')) {
            return;
        }
        
        register_block_type('secure-pdf-viewer/pdf-viewer', array(
            'api_version' => 2,
            'editor_script' => 'spv-block-editor',
            'editor_style' => 'spv-block-editor-style',
            'render_callback' => array($this, 'render_block'),
            'attributes' => array(
                'pdfUrl' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'pdfId' => array(
                    'type' => 'number',
                    'default' => 0
                ),
                'title' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'width' => array(
                    'type' => 'string',
                    'default' => '100%'
                ),
                'height' => array(
                    'type' => 'string',
                    'default' => '600px'
                )
            )
        ));
    }
    
    public function render_block($attributes, $content = '') {
        // Asegurar que los assets estén cargados
        wp_enqueue_script('jquery');
        wp_enqueue_script('pdf-js');
        wp_enqueue_script('spv-viewer');
        wp_enqueue_style('spv-style');
        wp_enqueue_style('dashicons');
        
        $pdf_url = isset($attributes['pdfUrl']) ? $attributes['pdfUrl'] : '';
        $title = isset($attributes['title']) ? $attributes['title'] : '';
        $width = isset($attributes['width']) ? $attributes['width'] : '100%';
        $height = isset($attributes['height']) ? $attributes['height'] : '600px';
        
        if (empty($pdf_url)) {
            return '<div class="spv-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0; border: 1px solid #f5c6cb;">
                <strong>⚠️ Error:</strong> No se ha seleccionado ningún archivo PDF. Por favor, edita esta página y selecciona un PDF desde el bloque.
            </div>';
        }
        
        $pdf_viewer = new SPV_PDF_Viewer();
        return $pdf_viewer->render_viewer($pdf_url, $width, $height, $title, 'spv-from-block');
    }
}
