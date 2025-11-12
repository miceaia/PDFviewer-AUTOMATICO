<?php
/**
 * LearnDash Integration Module
 *
 * Detects and synchronizes LearnDash courses with cloud services
 *
 * @package SecurePDFViewer\CloudSync
 * @since 4.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class CloudSync_LearnDash_Integration {

    /**
     * CloudSync Manager instance
     *
     * @var CloudSync_Manager
     */
    protected $sync_manager;

    /**
     * Whether LearnDash is active
     *
     * @var bool
     */
    protected $learndash_active = false;

    /**
     * LearnDash course post type
     *
     * @var string
     */
    const LD_COURSE_POST_TYPE = 'sfwd-courses';

    /**
     * LearnDash lesson post type
     *
     * @var string
     */
    const LD_LESSON_POST_TYPE = 'sfwd-lessons';

    /**
     * LearnDash topic post type
     *
     * @var string
     */
    const LD_TOPIC_POST_TYPE = 'sfwd-topic';

    /**
     * LearnDash quiz post type
     *
     * @var string
     */
    const LD_QUIZ_POST_TYPE = 'sfwd-quiz';

    /**
     * Meta key for cloud folder mapping
     *
     * @var string
     */
    const META_CLOUD_FOLDER = '_cloudsync_folder_id';

    /**
     * Meta key for last sync timestamp
     *
     * @var string
     */
    const META_LAST_SYNC = '_cloudsync_last_sync';

    /**
     * Constructor
     *
     * @param CloudSync_Manager $sync_manager CloudSync manager instance
     */
    public function __construct($sync_manager = null) {
        $this->sync_manager = $sync_manager;
        $this->detect_learndash();
    }

    /**
     * Initialize hooks and actions
     */
    public function init() {
        if (!$this->learndash_active) {
            add_action('admin_notices', array($this, 'show_learndash_notice'));
            return;
        }

        // Hook into LearnDash course creation/update
        add_action('save_post_' . self::LD_COURSE_POST_TYPE, array($this, 'handle_course_save'), 20, 3);
        add_action('save_post_' . self::LD_LESSON_POST_TYPE, array($this, 'handle_lesson_save'), 20, 3);
        add_action('save_post_' . self::LD_TOPIC_POST_TYPE, array($this, 'handle_topic_save'), 20, 3);

        // Hook into LearnDash deletions
        add_action('before_delete_post', array($this, 'handle_ld_delete'));

        // Add meta boxes to LearnDash courses
        add_action('add_meta_boxes', array($this, 'add_cloud_sync_metabox'));

        // AJAX actions for manual sync
        add_action('wp_ajax_ld_sync_course_to_cloud', array($this, 'ajax_sync_course'));
        add_action('wp_ajax_ld_bulk_sync_courses', array($this, 'ajax_bulk_sync'));

        // Admin menu for course management
        add_action('admin_menu', array($this, 'add_admin_menu'), 100);

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        error_log('[CloudSync] LearnDash integration initialized successfully');
    }

    /**
     * Detect if LearnDash is active
     */
    protected function detect_learndash() {
        // Check if LearnDash is active
        if (defined('LEARNDASH_VERSION')) {
            $this->learndash_active = true;
            error_log('[CloudSync] LearnDash detected - Version: ' . LEARNDASH_VERSION);
        } else if (class_exists('SFWD_LMS')) {
            $this->learndash_active = true;
            error_log('[CloudSync] LearnDash detected via SFWD_LMS class');
        } else if (function_exists('learndash_is_active')) {
            $this->learndash_active = learndash_is_active();
            error_log('[CloudSync] LearnDash detected via function');
        } else {
            error_log('[CloudSync] LearnDash NOT detected');
        }
    }

    /**
     * Show admin notice if LearnDash is not active
     */
    public function show_learndash_notice() {
        $screen = get_current_screen();
        if ($screen && strpos($screen->id, 'cloudsync') !== false) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <strong><?php _e('CloudSync - LearnDash Integration', 'secure-pdf-viewer'); ?></strong><br>
                    <?php _e('LearnDash no está instalado o activado. La integración automática de cursos está deshabilitada.', 'secure-pdf-viewer'); ?>
                </p>
                <p>
                    <a href="https://www.learndash.com/" target="_blank" class="button">
                        <?php _e('Obtener LearnDash', 'secure-pdf-viewer'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Get all LearnDash courses
     *
     * @return WP_Post[] Array of course posts
     */
    public function get_all_courses() {
        if (!$this->learndash_active) {
            return array();
        }

        $args = array(
            'post_type'      => self::LD_COURSE_POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => array('publish', 'draft'),
            'orderby'        => 'title',
            'order'          => 'ASC'
        );

        return get_posts($args);
    }

    /**
     * Get course lessons
     *
     * @param int $course_id Course post ID
     * @return WP_Post[] Array of lesson posts
     */
    public function get_course_lessons($course_id) {
        if (!$this->learndash_active) {
            return array();
        }

        // Use LearnDash function if available
        if (function_exists('learndash_get_course_lessons_list')) {
            $lessons = learndash_get_course_lessons_list($course_id);
            if (is_array($lessons) && !empty($lessons)) {
                return array_map('get_post', array_keys($lessons));
            }
        }

        // Fallback: Get lessons by parent relationship
        $args = array(
            'post_type'      => self::LD_LESSON_POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'course_id',
                    'value'   => $course_id,
                    'compare' => '='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Get lesson topics
     *
     * @param int $lesson_id Lesson post ID
     * @return WP_Post[] Array of topic posts
     */
    public function get_lesson_topics($lesson_id) {
        if (!$this->learndash_active) {
            return array();
        }

        // Use LearnDash function if available
        if (function_exists('learndash_get_topic_list')) {
            return learndash_get_topic_list($lesson_id);
        }

        // Fallback
        $args = array(
            'post_type'      => self::LD_TOPIC_POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'lesson_id',
                    'value'   => $lesson_id,
                    'compare' => '='
                )
            )
        );

        return get_posts($args);
    }

    /**
     * Handle course save
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function handle_course_save($post_id, $post, $update) {
        // Avoid autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Only for published courses
        if ($post->post_status !== 'publish') {
            return;
        }

        error_log(sprintf('[CloudSync] LearnDash course saved: %s (ID: %d)', $post->post_title, $post_id));

        // Schedule async sync (avoid slowing down the save)
        wp_schedule_single_event(time() + 10, 'cloudsync_sync_ld_course', array($post_id));
    }

    /**
     * Handle lesson save
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function handle_lesson_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $course_id = get_post_meta($post_id, 'course_id', true);
        if ($course_id) {
            error_log(sprintf('[CloudSync] LearnDash lesson saved: %s (ID: %d, Course: %d)', $post->post_title, $post_id, $course_id));
            wp_schedule_single_event(time() + 10, 'cloudsync_sync_ld_course', array($course_id));
        }
    }

    /**
     * Handle topic save
     *
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     * @param bool    $update  Whether this is an update
     */
    public function handle_topic_save($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $course_id = get_post_meta($post_id, 'course_id', true);
        if ($course_id) {
            error_log(sprintf('[CloudSync] LearnDash topic saved: %s (ID: %d, Course: %d)', $post->post_title, $post_id, $course_id));
            wp_schedule_single_event(time() + 10, 'cloudsync_sync_ld_course', array($course_id));
        }
    }

    /**
     * Handle LearnDash post deletion
     *
     * @param int $post_id Post ID being deleted
     */
    public function handle_ld_delete($post_id) {
        $post_type = get_post_type($post_id);

        $ld_types = array(
            self::LD_COURSE_POST_TYPE,
            self::LD_LESSON_POST_TYPE,
            self::LD_TOPIC_POST_TYPE
        );

        if (in_array($post_type, $ld_types)) {
            $folder_ids = get_post_meta($post_id, self::META_CLOUD_FOLDER, true);
            if ($folder_ids && is_array($folder_ids)) {
                error_log(sprintf('[CloudSync] Scheduling cloud folder deletion for post ID: %d', $post_id));
                // Schedule deletion (this would need to be implemented in sync manager)
                do_action('cloudsync_delete_cloud_folders', $folder_ids);
            }
        }
    }

    /**
     * Add cloud sync metabox to LearnDash courses
     */
    public function add_cloud_sync_metabox() {
        add_meta_box(
            'cloudsync_ld_metabox',
            __('CloudSync - Sincronización en la Nube', 'secure-pdf-viewer'),
            array($this, 'render_cloud_sync_metabox'),
            self::LD_COURSE_POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render cloud sync metabox
     *
     * @param WP_Post $post Current post object
     */
    public function render_cloud_sync_metabox($post) {
        $folder_ids = get_post_meta($post->ID, self::META_CLOUD_FOLDER, true);
        $last_sync = get_post_meta($post->ID, self::META_LAST_SYNC, true);

        wp_nonce_field('cloudsync_ld_metabox', 'cloudsync_ld_nonce');
        ?>
        <div class="cloudsync-metabox">
            <p><strong><?php _e('Estado de Sincronización:', 'secure-pdf-viewer'); ?></strong></p>

            <?php if ($folder_ids && is_array($folder_ids)): ?>
                <p style="color: #46b450;">
                    ✓ <?php _e('Sincronizado con la nube', 'secure-pdf-viewer'); ?>
                </p>

                <?php foreach ($folder_ids as $service => $folder_id): ?>
                    <p style="font-size: 11px;">
                        <strong><?php echo esc_html(ucfirst($service)); ?>:</strong>
                        <code><?php echo esc_html($folder_id); ?></code>
                    </p>
                <?php endforeach; ?>

                <?php if ($last_sync): ?>
                    <p style="font-size: 11px; color: #666;">
                        <?php printf(
                            __('Última sincronización: %s', 'secure-pdf-viewer'),
                            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_sync)
                        ); ?>
                    </p>
                <?php endif; ?>

            <?php else: ?>
                <p style="color: #dc3232;">
                    ✗ <?php _e('No sincronizado', 'secure-pdf-viewer'); ?>
                </p>
            <?php endif; ?>

            <p>
                <button type="button" class="button button-primary cloudsync-manual-sync" data-course-id="<?php echo esc_attr($post->ID); ?>">
                    <?php _e('Sincronizar Ahora', 'secure-pdf-viewer'); ?>
                </button>
            </p>

            <p style="font-size: 11px; color: #666;">
                <?php _e('La sincronización creará carpetas en tus servicios de nube configurados y subirá los PDFs del curso.', 'secure-pdf-viewer'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Add admin menu for LearnDash course management
     */
    public function add_admin_menu() {
        add_submenu_page(
            'cloudsync-dashboard',
            __('Cursos LearnDash', 'secure-pdf-viewer'),
            __('Cursos LearnDash', 'secure-pdf-viewer'),
            'manage_options',
            'cloudsync-learndash',
            array($this, 'render_learndash_page')
        );
    }

    /**
     * Render LearnDash courses management page
     */
    public function render_learndash_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $courses = $this->get_all_courses();
        ?>
        <div class="wrap">
            <h1><?php _e('Sincronización de Cursos LearnDash', 'secure-pdf-viewer'); ?></h1>

            <?php if (!$this->learndash_active): ?>
                <div class="notice notice-warning">
                    <p><?php _e('LearnDash no está activo. Por favor, instala y activa LearnDash para usar esta funcionalidad.', 'secure-pdf-viewer'); ?></p>
                </div>
                <?php return; ?>
            <?php endif; ?>

            <div class="cloudsync-learndash-header" style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                <p><?php printf(__('Total de cursos: %d', 'secure-pdf-viewer'), count($courses)); ?></p>

                <button type="button" class="button button-primary" id="cloudsync-bulk-sync-all">
                    <?php _e('Sincronizar Todos los Cursos', 'secure-pdf-viewer'); ?>
                </button>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 50px;">
                            <input type="checkbox" id="cloudsync-select-all">
                        </th>
                        <th><?php _e('Curso', 'secure-pdf-viewer'); ?></th>
                        <th><?php _e('Lecciones', 'secure-pdf-viewer'); ?></th>
                        <th><?php _e('Estado', 'secure-pdf-viewer'); ?></th>
                        <th><?php _e('Última Sincronización', 'secure-pdf-viewer'); ?></th>
                        <th><?php _e('Acciones', 'secure-pdf-viewer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $course): ?>
                        <?php
                        $folder_ids = get_post_meta($course->ID, self::META_CLOUD_FOLDER, true);
                        $last_sync = get_post_meta($course->ID, self::META_LAST_SYNC, true);
                        $lessons = $this->get_course_lessons($course->ID);
                        $is_synced = !empty($folder_ids) && is_array($folder_ids);
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="cloudsync-course-checkbox" value="<?php echo esc_attr($course->ID); ?>">
                            </td>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url(get_edit_post_link($course->ID)); ?>">
                                        <?php echo esc_html($course->post_title); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php echo count($lessons); ?> lecciones
                            </td>
                            <td>
                                <?php if ($is_synced): ?>
                                    <span style="color: #46b450;">● <?php _e('Sincronizado', 'secure-pdf-viewer'); ?></span>
                                <?php else: ?>
                                    <span style="color: #dc3232;">● <?php _e('No sincronizado', 'secure-pdf-viewer'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($last_sync): ?>
                                    <?php echo human_time_diff($last_sync, current_time('timestamp')) . ' ' . __('ago', 'secure-pdf-viewer'); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button cloudsync-sync-course" data-course-id="<?php echo esc_attr($course->ID); ?>">
                                    <?php _e('Sincronizar', 'secure-pdf-viewer'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($courses)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 20px;">
                                <?php _e('No se encontraron cursos de LearnDash', 'secure-pdf-viewer'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="cloudsync-sync-progress" style="display: none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3><?php _e('Sincronizando...', 'secure-pdf-viewer'); ?></h3>
                <div class="progress-bar" style="width: 100%; height: 30px; background: #fff; border-radius: 4px; overflow: hidden;">
                    <div class="progress-fill" style="width: 0%; height: 100%; background: #2271b1; transition: width 0.3s;"></div>
                </div>
                <p class="progress-text" style="margin-top: 10px;">0 / 0</p>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts for LearnDash pages
     */
    public function enqueue_admin_scripts($hook) {
        // Only on LearnDash edit screens and our custom page
        if ($hook !== 'post.php' && $hook !== 'post-new.php' && strpos($hook, 'cloudsync-learndash') === false) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || ($screen->post_type !== self::LD_COURSE_POST_TYPE && strpos($hook, 'cloudsync-learndash') === false)) {
            return;
        }

        wp_enqueue_script(
            'cloudsync-learndash',
            SPV_PLUGIN_URL . 'assets/js/learndash-sync.js',
            array('jquery'),
            SPV_PLUGIN_VERSION,
            true
        );

        wp_localize_script('cloudsync-learndash', 'cloudSyncLD', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cloudsync_ld_nonce'),
            'strings' => array(
                'syncing' => __('Sincronizando...', 'secure-pdf-viewer'),
                'success' => __('Sincronización completada', 'secure-pdf-viewer'),
                'error' => __('Error en la sincronización', 'secure-pdf-viewer'),
            )
        ));
    }

    /**
     * AJAX handler for single course sync
     */
    public function ajax_sync_course() {
        check_ajax_referer('cloudsync_ld_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'secure-pdf-viewer')));
        }

        $course_id = intval($_POST['course_id']);
        if (!$course_id) {
            wp_send_json_error(array('message' => __('ID de curso inválido', 'secure-pdf-viewer')));
        }

        // Here you would implement the actual sync logic
        // This is a placeholder for the sync operation
        $result = $this->sync_course_to_cloud($course_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Curso sincronizado correctamente', 'secure-pdf-viewer')));
        } else {
            wp_send_json_error(array('message' => __('Error al sincronizar el curso', 'secure-pdf-viewer')));
        }
    }

    /**
     * AJAX handler for bulk course sync
     */
    public function ajax_bulk_sync() {
        check_ajax_referer('cloudsync_ld_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permisos insuficientes', 'secure-pdf-viewer')));
        }

        $course_ids = isset($_POST['course_ids']) ? array_map('intval', $_POST['course_ids']) : array();

        if (empty($course_ids)) {
            wp_send_json_error(array('message' => __('No se seleccionaron cursos', 'secure-pdf-viewer')));
        }

        $results = array(
            'success' => 0,
            'failed' => 0,
            'total' => count($course_ids)
        );

        foreach ($course_ids as $course_id) {
            if ($this->sync_course_to_cloud($course_id)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        wp_send_json_success($results);
    }

    /**
     * Sync a course to cloud services
     *
     * @param int $course_id Course post ID
     * @return bool Success status
     */
    protected function sync_course_to_cloud($course_id) {
        if (!$this->sync_manager) {
            error_log('[CloudSync] No sync manager available');
            return false;
        }

        $course = get_post($course_id);
        if (!$course) {
            return false;
        }

        try {
            // Create folders in enabled cloud services
            $folder_ids = array();
            $settings = cloudsync_get_general_settings();

            // Sync to Google Drive if configured
            if (!empty($settings['root_google'])) {
                // TODO: Implement actual Google Drive sync
                error_log(sprintf('[CloudSync] Would sync course "%s" to Google Drive', $course->post_title));
            }

            // Sync to Dropbox if configured
            if (!empty($settings['root_dropbox'])) {
                // TODO: Implement actual Dropbox sync
                error_log(sprintf('[CloudSync] Would sync course "%s" to Dropbox', $course->post_title));
            }

            // Sync to SharePoint if configured
            if (!empty($settings['root_sharepoint'])) {
                // TODO: Implement actual SharePoint sync
                error_log(sprintf('[CloudSync] Would sync course "%s" to SharePoint', $course->post_title));
            }

            // Update meta
            update_post_meta($course_id, self::META_CLOUD_FOLDER, $folder_ids);
            update_post_meta($course_id, self::META_LAST_SYNC, current_time('timestamp'));

            error_log(sprintf('[CloudSync] Course "%s" synced successfully', $course->post_title));
            return true;

        } catch (Exception $e) {
            error_log(sprintf('[CloudSync] Error syncing course %d: %s', $course_id, $e->getMessage()));
            return false;
        }
    }

    /**
     * Check if LearnDash is active
     *
     * @return bool
     */
    public function is_learndash_active() {
        return $this->learndash_active;
    }
}
