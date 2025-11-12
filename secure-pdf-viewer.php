<?php
/**
 * Plugin Name: Secure PDF Viewer
 * Plugin URI: https://miceanou.com
 * Description: Visualizador seguro de PDFs con selector de medios integrado
 * Version: 3.2.0
 * Author: miceanou
 * License: GPL v2 or later
 * Text Domain: secure-pdf-viewer
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('SPV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SPV_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SPV_PLUGIN_VERSION', '3.2.0');

// Cargar clases necesarias
require_once SPV_PLUGIN_PATH . 'includes/class-pdf-viewer.php';
require_once SPV_PLUGIN_PATH . 'includes/class-shortcode-handler.php';
require_once SPV_PLUGIN_PATH . 'includes/class-gutenberg-block.php';
require_once SPV_PLUGIN_PATH . 'includes/helpers.php';
require_once SPV_PLUGIN_PATH . 'includes/class-sync-manager.php';
require_once SPV_PLUGIN_PATH . 'includes/admin-handlers.php';
require_once SPV_PLUGIN_PATH . 'includes/class-annotations-handler.php';
require_once SPV_PLUGIN_PATH . 'includes/class-learndash-integration.php';

class SecurePDFViewer {
    
    private static $instance = null;
    private $pdf_viewer;
    private $shortcode_handler;
    private $gutenberg_block;

    /**
     * Administrador de sincronización en la nube.
     *
     * @var CloudSync_Manager
     */
    private $cloudsync_manager;

    /**
     * Integración con LearnDash.
     *
     * @var CloudSync_LearnDash_Integration
     */
    private $learndash_integration;

    /**
     * Returns the CloudSync manager instance.
     *
     * @since 4.1.2
     *
     * @return CloudSync_Manager
     */
    public function get_cloudsync_manager() {
        return $this->cloudsync_manager;
    }

    /**
     * Returns the LearnDash integration instance.
     *
     * @since 4.4.0
     *
     * @return CloudSync_LearnDash_Integration
     */
    public function get_learndash_integration() {
        return $this->learndash_integration;
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Inicializar en diferentes momentos para asegurar compatibilidad
        add_action('plugins_loaded', array($this, 'init_classes'));
        add_action('init', array($this, 'register_assets'));
        
        // Cargar scripts - MÚLTIPLES HOOKS para asegurar carga
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'), 999);
        add_action('wp_head', array($this, 'ensure_assets_loaded'), 1);
        add_action('wp_footer', array($this, 'ensure_assets_loaded'), 999);
        
        // Para bloques
        add_action('enqueue_block_assets', array($this, 'enqueue_frontend_assets'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
        
        // Hooks de activación
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init_classes() {
        $this->pdf_viewer        = new SPV_PDF_Viewer();
        $this->shortcode_handler = new SPV_Shortcode_Handler();
        $this->gutenberg_block   = new SPV_Gutenberg_Block();
        $this->cloudsync_manager = new CloudSync_Manager();

        // Inicializar integración con LearnDash
        $this->learndash_integration = new CloudSync_LearnDash_Integration($this->cloudsync_manager);

        $this->pdf_viewer->init();
        $this->shortcode_handler->init();
        $this->gutenberg_block->init();
        $this->cloudsync_manager->init();
        $this->learndash_integration->init();

        // Log de inicialización
        error_log('[CloudSync] Plugin initialized - LearnDash Integration: ' .
                  ($this->learndash_integration->is_learndash_active() ? 'Active' : 'Inactive'));
    }
    
    public function register_assets() {
        // Registrar (no encolar todavía)
        wp_register_script('pdf-js', 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js', array(), '3.4.120', true);
        wp_register_script('spv-viewer', SPV_PLUGIN_URL . 'assets/js/pdf-viewer.js', array('pdf-js', 'jquery'), SPV_PLUGIN_VERSION, true);
        wp_register_style('spv-style', SPV_PLUGIN_URL . 'assets/css/pdf-viewer.css', array(), SPV_PLUGIN_VERSION);
        wp_register_style('dashicons', includes_url('css/dashicons.min.css'), array(), SPV_PLUGIN_VERSION);
    }
    
    public function enqueue_frontend_assets() {
        // Solo en frontend (no en admin)
        if (is_admin()) {
            return;
        }

        // Encolar siempre
        wp_enqueue_script('jquery');
        wp_enqueue_script('pdf-js');
        wp_enqueue_script('spv-viewer');
        wp_enqueue_style('spv-style');
        wp_enqueue_style('dashicons');

        // Pasar datos AJAX al JavaScript
        wp_localize_script('spv-viewer', 'spvAjax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('spv_ajax_nonce')
        ));

        // Log para debug (visible en consola)
        wp_add_inline_script('spv-viewer', 'console.log("SPV: Assets loaded v3.1.0");', 'before');
    }
    
    public function ensure_assets_loaded() {
        // Verificación adicional - forzar carga si no están
        if (!is_admin() && !wp_script_is('spv-viewer', 'enqueued')) {
            $this->enqueue_frontend_assets();
        }
    }
    
    public function enqueue_editor_assets() {
        // Assets del editor de bloques
        wp_enqueue_script(
            'spv-block-editor',
            SPV_PLUGIN_URL . 'blocks/pdf-viewer-block.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'),
            SPV_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'spv-block-editor-style',
            SPV_PLUGIN_URL . 'blocks/editor.css',
            array('wp-edit-blocks'),
            SPV_PLUGIN_VERSION
        );
    }
    
    public function activate() {
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/secure-pdfs';
        
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }
        
        add_option('spv_default_width', '100%');
        add_option('spv_default_height', '600px');
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        $timestamp = wp_next_scheduled( CloudSync_Manager::CRON_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, CloudSync_Manager::CRON_HOOK );
        }

        flush_rewrite_rules();
    }
}

// Inicializar el plugin
function spv_init() {
    return SecurePDFViewer::get_instance();
}

// Arrancar
spv_init();
