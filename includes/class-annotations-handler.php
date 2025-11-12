<?php
/**
 * Gestor de Anotaciones de PDF
 *
 * Maneja el guardado y carga de anotaciones de usuario en PDFs
 *
 * @package SecurePDFViewer
 */

if (!defined('ABSPATH')) {
    exit;
}

class SPV_Annotations_Handler {

    /**
     * Nombre de la tabla de anotaciones
     */
    const TABLE_NAME = 'spv_pdf_annotations';

    public function __construct() {
        // Hooks AJAX para usuarios logueados
        add_action('wp_ajax_spv_save_annotations', array($this, 'save_annotations'));
        add_action('wp_ajax_spv_load_annotations', array($this, 'load_annotations'));

        // Hook de activación para crear tabla
        add_action('plugins_loaded', array($this, 'maybe_create_table'));
    }

    /**
     * Crear tabla de anotaciones si no existe
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                pdf_id varchar(255) NOT NULL,
                pdf_url text NOT NULL,
                annotations longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY pdf_id (pdf_id)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Guardar anotaciones vía AJAX
     */
    public function save_annotations() {
        // Verificar nonce
        check_ajax_referer('spv_ajax_nonce', 'nonce');

        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Usuario no autenticado'));
            return;
        }

        // Obtener datos
        $user_id = get_current_user_id();
        $pdf_id = sanitize_text_field($_POST['pdf_id']);
        $annotations = wp_unslash($_POST['annotations']); // JSON string

        // Validar datos
        if (empty($pdf_id) || empty($annotations)) {
            wp_send_json_error(array('message' => 'Datos incompletos'));
            return;
        }

        // Validar JSON
        $decoded = json_decode($annotations, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => 'JSON inválido'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Verificar si ya existe una anotación para este PDF y usuario
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_id = %d AND pdf_id = %s",
            $user_id,
            $pdf_id
        ));

        if ($existing) {
            // Actualizar
            $result = $wpdb->update(
                $table_name,
                array(
                    'annotations' => $annotations,
                    'updated_at' => current_time('mysql')
                ),
                array(
                    'user_id' => $user_id,
                    'pdf_id' => $pdf_id
                ),
                array('%s', '%s'),
                array('%d', '%s')
            );
        } else {
            // Insertar nuevo
            $result = $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'pdf_id' => $pdf_id,
                    'pdf_url' => '', // Opcional, se puede agregar si se necesita
                    'annotations' => $annotations,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }

        if ($result !== false) {
            wp_send_json_success(array('message' => 'Anotaciones guardadas correctamente'));
        } else {
            wp_send_json_error(array('message' => 'Error al guardar anotaciones'));
        }
    }

    /**
     * Cargar anotaciones vía AJAX
     */
    public function load_annotations() {
        // Verificar nonce
        check_ajax_referer('spv_ajax_nonce', 'nonce');

        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Usuario no autenticado'));
            return;
        }

        // Obtener datos
        $user_id = get_current_user_id();
        $pdf_id = sanitize_text_field($_POST['pdf_id']);

        if (empty($pdf_id)) {
            wp_send_json_error(array('message' => 'PDF ID requerido'));
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Obtener anotaciones
        $annotations = $wpdb->get_var($wpdb->prepare(
            "SELECT annotations FROM $table_name WHERE user_id = %d AND pdf_id = %s",
            $user_id,
            $pdf_id
        ));

        if ($annotations) {
            wp_send_json_success(array('annotations' => $annotations));
        } else {
            wp_send_json_success(array('annotations' => null));
        }
    }
}

// Inicializar el handler
new SPV_Annotations_Handler();
