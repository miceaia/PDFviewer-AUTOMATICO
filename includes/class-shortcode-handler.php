<?php
class SPV_Shortcode_Handler {
    
    public function init() {
        add_shortcode('secure_pdf', array($this, 'secure_pdf_shortcode'));
        add_shortcode('pdf_viewer', array($this, 'pdf_viewer_shortcode'));
    }
    
    public function secure_pdf_shortcode($atts) {
        $atts = shortcode_atts(array(
            'url' => '',
            'width' => get_option('spv_default_width', '100%'),
            'height' => get_option('spv_default_height', '600px'),
            'title' => '',
            'class' => ''
        ), $atts, 'secure_pdf');
        
        if (empty($atts['url'])) {
            return '<p class="spv-error">Error: Debe proporcionar una URL del PDF</p>';
        }
        
        // Sanitizar atributos
        $pdf_url = esc_url($atts['url']);
        $width = sanitize_text_field($atts['width']);
        $height = sanitize_text_field($atts['height']);
        $title = sanitize_text_field($atts['title']);
        $class = sanitize_text_field($atts['class']);
        
        $pdf_viewer = new SPV_PDF_Viewer();
        return $pdf_viewer->render_viewer($pdf_url, $width, $height, $title, $class);
    }
    
    public function pdf_viewer_shortcode($atts) {
        return $this->secure_pdf_shortcode($atts);
    }
}
