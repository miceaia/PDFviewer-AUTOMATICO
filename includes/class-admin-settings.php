<?php
/**
 * Clase para gestionar la página de ajustes del plugin en el admin
 *
 * @package SecurePDFViewer
 * @since 3.2.0
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

class SPV_Admin_Settings {

    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Agregar menú en el panel de administración
     */
    public function add_admin_menu() {
        add_options_page(
            __('Configuración PDF Viewer', 'secure-pdf-viewer'),
            __('PDF Viewer Micea', 'secure-pdf-viewer'),
            'manage_options',
            'spv-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Encolar assets para la página de admin
     */
    public function enqueue_admin_assets($hook) {
        // Solo cargar en nuestra página de settings
        if ('settings_page_spv-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_style(
            'spv-admin-style',
            SPV_PLUGIN_URL . 'assets/css/admin-settings.css',
            array('wp-color-picker'),
            SPV_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'spv-admin-script',
            SPV_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery', 'wp-color-picker'),
            SPV_PLUGIN_VERSION,
            true
        );
    }

    /**
     * Registrar todas las configuraciones
     */
    public function register_settings() {
        // Grupo de opciones
        register_setting('spv_settings_group', 'spv_settings', array($this, 'sanitize_settings'));

        // Sección: Dimensiones predeterminadas
        add_settings_section(
            'spv_dimensions_section',
            __('Dimensiones Predeterminadas', 'secure-pdf-viewer'),
            array($this, 'render_dimensions_section'),
            'spv-settings'
        );

        add_settings_field(
            'default_width',
            __('Ancho predeterminado', 'secure-pdf-viewer'),
            array($this, 'render_width_field'),
            'spv-settings',
            'spv_dimensions_section'
        );

        add_settings_field(
            'default_height',
            __('Altura predeterminada', 'secure-pdf-viewer'),
            array($this, 'render_height_field'),
            'spv-settings',
            'spv_dimensions_section'
        );

        // Sección: Marca de agua
        add_settings_section(
            'spv_watermark_section',
            __('Configuración de Marca de Agua', 'secure-pdf-viewer'),
            array($this, 'render_watermark_section'),
            'spv-settings'
        );

        add_settings_field(
            'watermark_show_user',
            __('Mostrar nombre de usuario', 'secure-pdf-viewer'),
            array($this, 'render_show_user_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_show_email',
            __('Mostrar email de usuario', 'secure-pdf-viewer'),
            array($this, 'render_show_email_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_show_date',
            __('Mostrar fecha', 'secure-pdf-viewer'),
            array($this, 'render_show_date_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_custom_text',
            __('Texto personalizado adicional', 'secure-pdf-viewer'),
            array($this, 'render_custom_text_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_position',
            __('Posición de la marca de agua', 'secure-pdf-viewer'),
            array($this, 'render_position_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_font_size',
            __('Tamaño de fuente', 'secure-pdf-viewer'),
            array($this, 'render_font_size_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_opacity',
            __('Opacidad', 'secure-pdf-viewer'),
            array($this, 'render_opacity_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        add_settings_field(
            'watermark_color',
            __('Color del texto', 'secure-pdf-viewer'),
            array($this, 'render_color_field'),
            'spv-settings',
            'spv_watermark_section'
        );

        // Sección: Configuración del visor
        add_settings_section(
            'spv_viewer_section',
            __('Configuración del Visor', 'secure-pdf-viewer'),
            array($this, 'render_viewer_section'),
            'spv-settings'
        );

        add_settings_field(
            'default_zoom',
            __('Zoom inicial', 'secure-pdf-viewer'),
            array($this, 'render_zoom_field'),
            'spv-settings',
            'spv_viewer_section'
        );

        add_settings_field(
            'autosave_delay',
            __('Tiempo de autosave (segundos)', 'secure-pdf-viewer'),
            array($this, 'render_autosave_field'),
            'spv-settings',
            'spv_viewer_section'
        );

        add_settings_field(
            'toolbar_color',
            __('Color de la barra de herramientas', 'secure-pdf-viewer'),
            array($this, 'render_toolbar_color_field'),
            'spv-settings',
            'spv_viewer_section'
        );
    }

    /**
     * Sanitizar y validar los datos del formulario
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        // Dimensiones
        $sanitized['default_width'] = sanitize_text_field($input['default_width']);
        $sanitized['default_height'] = sanitize_text_field($input['default_height']);

        // Marca de agua
        $sanitized['watermark_show_user'] = isset($input['watermark_show_user']) ? 1 : 0;
        $sanitized['watermark_show_email'] = isset($input['watermark_show_email']) ? 1 : 0;
        $sanitized['watermark_show_date'] = isset($input['watermark_show_date']) ? 1 : 0;
        $sanitized['watermark_custom_text'] = sanitize_text_field($input['watermark_custom_text']);
        $sanitized['watermark_position'] = sanitize_text_field($input['watermark_position']);
        $sanitized['watermark_font_size'] = absint($input['watermark_font_size']);
        $sanitized['watermark_opacity'] = floatval($input['watermark_opacity']);
        $sanitized['watermark_color'] = sanitize_hex_color($input['watermark_color']);

        // Visor
        $sanitized['default_zoom'] = floatval($input['default_zoom']);
        $sanitized['autosave_delay'] = absint($input['autosave_delay']);
        $sanitized['toolbar_color'] = sanitize_hex_color($input['toolbar_color']);

        return $sanitized;
    }

    /**
     * Renderizar la página de configuración
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->options = get_option('spv_settings', $this->get_default_options());
        ?>
        <div class="wrap spv-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="spv-settings-header">
                <p class="description">
                    <?php _e('Configure las opciones del visor PDF Micea. Estos ajustes se aplicarán a todos los visores PDF del sitio.', 'secure-pdf-viewer'); ?>
                </p>
            </div>

            <?php settings_errors('spv_settings'); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields('spv_settings_group');
                do_settings_sections('spv-settings');
                submit_button(__('Guardar Configuración', 'secure-pdf-viewer'));
                ?>
            </form>

            <div class="spv-settings-footer">
                <h3><?php _e('Vista previa de marca de agua', 'secure-pdf-viewer'); ?></h3>
                <div class="spv-watermark-preview" id="watermark-preview">
                    <?php echo $this->generate_preview_watermark(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Generar vista previa de la marca de agua
     */
    private function generate_preview_watermark() {
        $current_user = wp_get_current_user();
        $parts = array();

        if ($this->options['watermark_show_user']) {
            $parts[] = 'Usuario: ' . $current_user->display_name;
        }

        if ($this->options['watermark_show_email']) {
            $parts[] = $current_user->user_email;
        }

        if ($this->options['watermark_show_date']) {
            $parts[] = 'Fecha: ' . date_i18n(get_option('date_format'));
        }

        if (!empty($this->options['watermark_custom_text'])) {
            $parts[] = $this->options['watermark_custom_text'];
        }

        $text = implode(' · ', $parts);

        $style = sprintf(
            'font-size: %dpx; opacity: %s; color: %s;',
            $this->options['watermark_font_size'],
            $this->options['watermark_opacity'],
            $this->options['watermark_color']
        );

        return '<span style="' . $style . '">' . esc_html($text) . '</span>';
    }

    /**
     * Secciones
     */
    public function render_dimensions_section() {
        echo '<p>' . __('Configure las dimensiones predeterminadas para el visor PDF.', 'secure-pdf-viewer') . '</p>';
    }

    public function render_watermark_section() {
        echo '<p>' . __('Configure la marca de agua que aparece en cada página del PDF.', 'secure-pdf-viewer') . '</p>';
    }

    public function render_viewer_section() {
        echo '<p>' . __('Configure el comportamiento y apariencia del visor.', 'secure-pdf-viewer') . '</p>';
    }

    /**
     * Campos de configuración
     */
    public function render_width_field() {
        $value = isset($this->options['default_width']) ? $this->options['default_width'] : '100%';
        ?>
        <input type="text"
               name="spv_settings[default_width]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="100%">
        <p class="description">
            <?php _e('Ejemplo: 100%, 800px, 80vw', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    public function render_height_field() {
        $value = isset($this->options['default_height']) ? $this->options['default_height'] : '600px';
        ?>
        <input type="text"
               name="spv_settings[default_height]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="600px">
        <p class="description">
            <?php _e('Ejemplo: 600px, 80vh, 500px', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    public function render_show_user_field() {
        $checked = isset($this->options['watermark_show_user']) && $this->options['watermark_show_user'];
        ?>
        <label>
            <input type="checkbox"
                   name="spv_settings[watermark_show_user]"
                   value="1"
                   <?php checked($checked); ?>>
            <?php _e('Mostrar el nombre del usuario actual', 'secure-pdf-viewer'); ?>
        </label>
        <?php
    }

    public function render_show_email_field() {
        $checked = isset($this->options['watermark_show_email']) && $this->options['watermark_show_email'];
        ?>
        <label>
            <input type="checkbox"
                   name="spv_settings[watermark_show_email]"
                   value="1"
                   <?php checked($checked); ?>>
            <?php _e('Mostrar el email del usuario', 'secure-pdf-viewer'); ?>
        </label>
        <?php
    }

    public function render_show_date_field() {
        $checked = isset($this->options['watermark_show_date']) && $this->options['watermark_show_date'];
        ?>
        <label>
            <input type="checkbox"
                   name="spv_settings[watermark_show_date]"
                   value="1"
                   <?php checked($checked); ?>>
            <?php _e('Mostrar la fecha actual', 'secure-pdf-viewer'); ?>
        </label>
        <?php
    }

    public function render_custom_text_field() {
        $value = isset($this->options['watermark_custom_text']) ? $this->options['watermark_custom_text'] : '';
        ?>
        <input type="text"
               name="spv_settings[watermark_custom_text]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="<?php _e('Curso 2024-2025', 'secure-pdf-viewer'); ?>">
        <p class="description">
            <?php _e('Texto adicional que se mostrará en la marca de agua', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    public function render_position_field() {
        $value = isset($this->options['watermark_position']) ? $this->options['watermark_position'] : 'bottom-right';
        $positions = array(
            'top-left' => __('Superior izquierda', 'secure-pdf-viewer'),
            'top-center' => __('Superior centro', 'secure-pdf-viewer'),
            'top-right' => __('Superior derecha', 'secure-pdf-viewer'),
            'center' => __('Centro', 'secure-pdf-viewer'),
            'bottom-left' => __('Inferior izquierda', 'secure-pdf-viewer'),
            'bottom-center' => __('Inferior centro', 'secure-pdf-viewer'),
            'bottom-right' => __('Inferior derecha', 'secure-pdf-viewer'),
        );
        ?>
        <select name="spv_settings[watermark_position]">
            <?php foreach ($positions as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function render_font_size_field() {
        $value = isset($this->options['watermark_font_size']) ? $this->options['watermark_font_size'] : 10;
        ?>
        <input type="number"
               name="spv_settings[watermark_font_size]"
               value="<?php echo esc_attr($value); ?>"
               min="6"
               max="24"
               step="1"
               class="small-text">
        <span class="description">px (6-24)</span>
        <?php
    }

    public function render_opacity_field() {
        $value = isset($this->options['watermark_opacity']) ? $this->options['watermark_opacity'] : 0.15;
        ?>
        <input type="number"
               name="spv_settings[watermark_opacity]"
               value="<?php echo esc_attr($value); ?>"
               min="0.05"
               max="1"
               step="0.05"
               class="small-text">
        <span class="description">(0.05 - 1.0)</span>
        <p class="description">
            <?php _e('0.15 es discreto, 0.5 es visible', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    public function render_color_field() {
        $value = isset($this->options['watermark_color']) ? $this->options['watermark_color'] : '#000000';
        ?>
        <input type="text"
               name="spv_settings[watermark_color]"
               value="<?php echo esc_attr($value); ?>"
               class="spv-color-picker">
        <?php
    }

    public function render_zoom_field() {
        $value = isset($this->options['default_zoom']) ? $this->options['default_zoom'] : 1.5;
        ?>
        <input type="number"
               name="spv_settings[default_zoom]"
               value="<?php echo esc_attr($value); ?>"
               min="0.5"
               max="3"
               step="0.1"
               class="small-text">
        <span class="description">(0.5 - 3.0)</span>
        <p class="description">
            <?php _e('1.5 = 150%', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    public function render_autosave_field() {
        $value = isset($this->options['autosave_delay']) ? $this->options['autosave_delay'] : 3;
        ?>
        <input type="number"
               name="spv_settings[autosave_delay]"
               value="<?php echo esc_attr($value); ?>"
               min="1"
               max="30"
               step="1"
               class="small-text">
        <span class="description"><?php _e('segundos', 'secure-pdf-viewer'); ?></span>
        <p class="description">
            <?php _e('Tiempo de espera antes de guardar automáticamente', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    public function render_toolbar_color_field() {
        $value = isset($this->options['toolbar_color']) ? $this->options['toolbar_color'] : '#24333F';
        ?>
        <input type="text"
               name="spv_settings[toolbar_color]"
               value="<?php echo esc_attr($value); ?>"
               class="spv-color-picker">
        <p class="description">
            <?php _e('Color de fondo de la barra de herramientas', 'secure-pdf-viewer'); ?>
        </p>
        <?php
    }

    /**
     * Obtener opciones predeterminadas
     */
    public function get_default_options() {
        return array(
            'default_width' => '100%',
            'default_height' => '600px',
            'watermark_show_user' => 1,
            'watermark_show_email' => 0,
            'watermark_show_date' => 1,
            'watermark_custom_text' => 'Curso 2024-2025',
            'watermark_position' => 'bottom-right',
            'watermark_font_size' => 10,
            'watermark_opacity' => 0.15,
            'watermark_color' => '#000000',
            'default_zoom' => 1.5,
            'autosave_delay' => 3,
            'toolbar_color' => '#24333F',
        );
    }

    /**
     * Obtener una opción específica
     */
    public static function get_option($key, $default = null) {
        $options = get_option('spv_settings');

        if ($options && isset($options[$key])) {
            return $options[$key];
        }

        // Si no existe, devolver el valor predeterminado
        $instance = new self();
        $defaults = $instance->get_default_options();

        return isset($defaults[$key]) ? $defaults[$key] : $default;
    }
}
