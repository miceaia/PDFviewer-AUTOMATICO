<?php
/**
 * CloudSync LMS admin dashboard.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'cloudsync_render_admin_page' ) ) {
    /**
     * Renders the CloudSync dashboard with tabbed navigation.
     *
     * @since 4.1.0
     *
     * @param string $active_tab Active tab slug.
     *
     * @return void
     */
    function cloudsync_render_admin_page( $active_tab = 'general' ) {
        $tabs = array(
            'general'  => __( 'Configuración', 'secure-pdf-viewer' ),
            'oauth'    => __( 'Credenciales', 'secure-pdf-viewer' ),
            'sync'     => __( 'Sincronización', 'secure-pdf-viewer' ),
            'logs'     => __( 'Logs', 'secure-pdf-viewer' ),
            'explorer' => __( 'Explorador', 'secure-pdf-viewer' ),
            'advanced' => __( 'Avanzado', 'secure-pdf-viewer' ),
        );

        if ( ! isset( $tabs[ $active_tab ] ) ) {
            $active_tab = 'general';
        }

        $general_settings    = cloudsync_get_general_settings();
        $oauth_settings      = cloudsync_get_settings();
        $logs                = cloudsync_get_logs();
        $service_definitions  = cloudsync_get_service_definitions();
        $connection_guides    = cloudsync_get_connection_guides();
        $notice           = isset( $_GET['cloudsync_notice'] ) ? sanitize_key( wp_unslash( $_GET['cloudsync_notice'] ) ) : '';

        $last_sync = (int) get_option( 'cloudsync_last_sync', 0 );
        $courses   = wp_count_posts( 'curso' );
        $lessons   = wp_count_posts( 'leccion' );

        $course_total  = isset( $courses->publish ) ? (int) $courses->publish : 0;
        $lesson_total  = isset( $lessons->publish ) ? (int) $lessons->publish : 0;
        $recent_logs   = array_slice( array_reverse( $logs ), 0, 10 );

        $explorer_roots = array(
            'google'     => isset( $general_settings['root_google'] ) ? $general_settings['root_google'] : '',
            'dropbox'    => isset( $general_settings['root_dropbox'] ) ? $general_settings['root_dropbox'] : '',
            'sharepoint' => isset( $general_settings['root_sharepoint'] ) ? $general_settings['root_sharepoint'] : '',
        );

        $status_icons = array(
            true  => '<span class="cloudsync-status-badge is-connected"><span class="cloudsync-status-badge__dot" aria-hidden="true"></span>' . esc_html__( 'Conectado', 'secure-pdf-viewer' ) . '</span>',
            false => '<span class="cloudsync-status-badge is-disconnected"><span class="cloudsync-status-badge__dot" aria-hidden="true"></span>' . esc_html__( 'Desconectado', 'secure-pdf-viewer' ) . '</span>',
        );

        $explorer_services_data = array();

        foreach ( $service_definitions as $slug => $definition ) {
            $token_field = $definition['token_field'];
            $explorer_services_data[] = array(
                'slug'      => $slug,
                'label'     => wp_strip_all_tags( $definition['label'] ),
                'connected' => ! empty( $oauth_settings[ $token_field ] ),
                'root'      => isset( $explorer_roots[ $slug ] ) ? $explorer_roots[ $slug ] : '',
            );
        }

        ?>
        <div class="wrap cloudsync-dashboard">
            <h1>☁️ <?php esc_html_e( 'CloudSync LMS Dashboard', 'secure-pdf-viewer' ); ?></h1>

            <?php
            $settings_updated = isset( $_GET['settings-updated'] ) ? sanitize_text_field( wp_unslash( $_GET['settings-updated'] ) ) : '';
            if ( $settings_updated && empty( $notice ) ) :
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ <?php esc_html_e( 'Cambios guardados correctamente.', 'secure-pdf-viewer' ); ?></p>
                </div>
            <?php elseif ( ! empty( $notice ) ) : ?>
                <?php
                $error_notices = array( 'oauth-error', 'invalid-service', 'missing-credentials' );
                $notice_class  = in_array( $notice, $error_notices, true ) ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
                ?>
                <div class="<?php echo esc_attr( $notice_class ); ?>">
                    <p>
                        <?php
                        switch ( $notice ) {
                            case 'manual-sync':
                                esc_html_e( 'Sincronización manual completada correctamente.', 'secure-pdf-viewer' );
                                break;
                            case 'force-sync':
                                esc_html_e( 'Sincronización forzada ejecutada correctamente.', 'secure-pdf-viewer' );
                                break;
                            case 'cleanup':
                                esc_html_e( 'Metadatos huérfanos eliminados.', 'secure-pdf-viewer' );
                                break;
                            case 'reset-tokens':
                                esc_html_e( 'Tokens OAuth reiniciados.', 'secure-pdf-viewer' );
                                break;
                            case 'rebuild':
                                esc_html_e( 'Estructura de carpetas re-sincronizada.', 'secure-pdf-viewer' );
                                break;
                            case 'developer-mode':
                                esc_html_e( 'Preferencias de modo desarrollador actualizadas.', 'secure-pdf-viewer' );
                                break;
                            case 'credentials-saved':
                                esc_html_e( 'Credenciales guardadas correctamente.', 'secure-pdf-viewer' );
                                break;
                            case 'connected':
                                esc_html_e( 'Conexión OAuth completada con éxito.', 'secure-pdf-viewer' );
                                break;
                            case 'revoked':
                                esc_html_e( 'Acceso revocado correctamente.', 'secure-pdf-viewer' );
                                break;
                            case 'missing-credentials':
                                esc_html_e( 'Completa los campos obligatorios antes de iniciar sesión.', 'secure-pdf-viewer' );
                                break;
                            case 'oauth-error':
                                esc_html_e( 'No se pudo completar la autorización OAuth. Intenta nuevamente.', 'secure-pdf-viewer' );
                                break;
                            case 'invalid-service':
                                esc_html_e( 'Servicio desconocido. Actualiza la página e inténtalo otra vez.', 'secure-pdf-viewer' );
                                break;
                            default:
                                esc_html_e( 'Acción completada.', 'secure-pdf-viewer' );
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ( $tabs as $tab => $label ) : ?>
                    <?php
                    $url = add_query_arg(
                        array(
                            'page' => isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'cloudsync-dashboard',
                            'tab'  => $tab,
                        ),
                        admin_url( 'admin.php' )
                    );
                    ?>
                    <a href="<?php echo esc_url( $url ); ?>" class="nav-tab <?php echo $active_tab === $tab ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </h2>

            <?php if ( 'general' === $active_tab ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-card">
                    <?php wp_nonce_field( 'cloudsync_save_config', 'cloudsync_nonce' ); ?>
                    <input type="hidden" name="action" value="cloudsync_save_config" />
                    <input type="hidden" name="cloudsync_general_settings[developer_mode]" value="<?php echo (int) $general_settings['developer_mode']; ?>" />
                    <h2><?php esc_html_e( 'Configuración general', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Controla la frecuencia y comportamiento de la sincronización automática.', 'secure-pdf-viewer' ); ?></p>
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Intervalo de sincronización', 'secure-pdf-viewer' ); ?></th>
                                <td>
                                    <select name="cloudsync_general_settings[sync_interval]">
                                        <option value="5" <?php selected( $general_settings['sync_interval'], '5' ); ?>><?php esc_html_e( 'Cada 5 minutos', 'secure-pdf-viewer' ); ?></option>
                                        <option value="10" <?php selected( $general_settings['sync_interval'], '10' ); ?>><?php esc_html_e( 'Cada 10 minutos', 'secure-pdf-viewer' ); ?></option>
                                        <option value="30" <?php selected( $general_settings['sync_interval'], '30' ); ?>><?php esc_html_e( 'Cada 30 minutos', 'secure-pdf-viewer' ); ?></option>
                                        <option value="manual" <?php selected( $general_settings['sync_interval'], 'manual' ); ?>><?php esc_html_e( 'Manual', 'secure-pdf-viewer' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Sincronización automática', 'secure-pdf-viewer' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cloudsync_general_settings[auto_sync]" value="1" <?php checked( (int) $general_settings['auto_sync'], 1 ); ?> />
                                        <?php esc_html_e( 'Activar sincronización bidireccional automáticamente.', 'secure-pdf-viewer' ); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Modo de prioridad', 'secure-pdf-viewer' ); ?></th>
                                <td class="cloudsync-radio-group">
                                    <label><input type="radio" name="cloudsync_general_settings[priority_mode]" value="wp" <?php checked( $general_settings['priority_mode'], 'wp' ); ?> /> <?php esc_html_e( 'WordPress → Nube', 'secure-pdf-viewer' ); ?></label><br />
                                    <label><input type="radio" name="cloudsync_general_settings[priority_mode]" value="cloud" <?php checked( $general_settings['priority_mode'], 'cloud' ); ?> /> <?php esc_html_e( 'Nube → WordPress', 'secure-pdf-viewer' ); ?></label><br />
                                    <label><input type="radio" name="cloudsync_general_settings[priority_mode]" value="bidirectional" <?php checked( $general_settings['priority_mode'], 'bidirectional' ); ?> /> <?php esc_html_e( 'Bidireccional', 'secure-pdf-viewer' ); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Carpeta raíz Google Drive', 'secure-pdf-viewer' ); ?></th>
                                <td><input type="text" name="cloudsync_general_settings[root_google]" value="<?php echo esc_attr( $general_settings['root_google'] ); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Carpeta raíz Dropbox', 'secure-pdf-viewer' ); ?></th>
                                <td><input type="text" name="cloudsync_general_settings[root_dropbox]" value="<?php echo esc_attr( $general_settings['root_dropbox'] ); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Carpeta raíz SharePoint/OneDrive', 'secure-pdf-viewer' ); ?></th>
                                <td><input type="text" name="cloudsync_general_settings[root_sharepoint]" value="<?php echo esc_attr( $general_settings['root_sharepoint'] ); ?>" class="regular-text" /></td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Notificaciones por correo', 'secure-pdf-viewer' ); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cloudsync_general_settings[email_notifications]" value="1" <?php checked( (int) $general_settings['email_notifications'], 1 ); ?> />
                                        <?php esc_html_e( 'Enviar alertas cuando ocurran errores críticos.', 'secure-pdf-viewer' ); ?>
                                    </label>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <?php submit_button( __( 'Guardar cambios', 'secure-pdf-viewer' ) ); ?>
                </form>
            <?php elseif ( 'oauth' === $active_tab ) : ?>
                <div class="cloudsync-card">
                    <h2><?php esc_html_e( 'Credenciales OAuth', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Registra tus llaves OAuth. Los tokens sensibles se cifran automáticamente.', 'secure-pdf-viewer' ); ?></p>
                    <div class="cloudsync-oauth-grid">
                        <?php
                        foreach ( $service_definitions as $service => $definition ) :
                            $token_field      = $definition['token_field'];
                            $connected        = ! empty( $oauth_settings[ $token_field ] );
                            $required_fields  = isset( $definition['required_fields'] ) ? $definition['required_fields'] : array();
                            $requirements_met = true;

                            foreach ( $required_fields as $required_field ) {
                                if ( empty( $oauth_settings[ $required_field ] ) ) {
                                    $requirements_met = false;
                                    break;
                                }
                            }

                            $connect_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'  => 'cloudsync_oauth_connect',
                                        'service' => $service,
                                    ),
                                    admin_url( 'admin-post.php' )
                                ),
                                'cloudsync_oauth_action'
                            );

                            $revoke_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'action'  => 'cloudsync_revoke_access',
                                        'service' => $service,
                                    ),
                                    admin_url( 'admin-post.php' )
                                ),
                                'cloudsync_oauth_action'
                            );
                        ?>
                        <section class="cloudsync-service-card" aria-labelledby="cloudsync-card-<?php echo esc_attr( $service ); ?>">
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-service-form">
                                <?php wp_nonce_field( 'cloudsync_oauth_action', 'cloudsync_oauth_nonce' ); ?>
                                <input type="hidden" name="action" value="cloudsync_save_credentials" />
                                <input type="hidden" name="service" value="<?php echo esc_attr( $service ); ?>" />
                                <header>
                                    <div class="cloudsync-service-heading">
                                        <h3 id="cloudsync-card-<?php echo esc_attr( $service ); ?>"><?php echo esc_html( $definition['label'] ); ?></h3>
                                    </div>
                                    <?php echo $connected ? $status_icons[ true ] : $status_icons[ false ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </header>
                                <div class="cloudsync-service-fields">
                                    <?php foreach ( $definition['fields'] as $field_key => $field_meta ) :
                                        $is_sensitive   = ! empty( $field_meta['sensitive'] );
                                        $is_token       = ! empty( $field_meta['is_token'] );
                                        $stored_value   = isset( $oauth_settings[ $field_key ] ) ? $oauth_settings[ $field_key ] : '';
                                        $input_type     = $is_sensitive ? 'password' : 'text';
                                        $display_value  = $is_sensitive ? '' : $stored_value;
                                        $placeholder    = ( $is_sensitive && ! empty( $stored_value ) ) ? str_repeat( '•', 8 ) : '';
                                    ?>
                                    <p class="cloudsync-service-field">
                                        <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $field_meta['label'] ); ?></label>
                                        <input type="<?php echo esc_attr( $input_type ); ?>"
                                            id="<?php echo esc_attr( $field_key ); ?>"
                                            name="<?php echo esc_attr( $field_key ); ?>"
                                            value="<?php echo esc_attr( $display_value ); ?>"
                                            class="regular-text"
                                            <?php if ( $placeholder ) : ?>placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php endif; ?>
                                            <?php echo $is_sensitive ? 'autocomplete="off"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        />
                                        <?php if ( $is_sensitive && ! empty( $stored_value ) ) : ?>
                                            <input type="hidden" name="<?php echo esc_attr( $field_key . '_keep' ); ?>" value="1" />
                                        <?php endif; ?>
                                        <?php if ( $is_token && ! empty( $stored_value ) ) : ?>
                                            <span class="cloudsync-field-note"><?php esc_html_e( 'Token almacenado', 'secure-pdf-viewer' ); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <?php endforeach; ?>
                                </div>
                                <div class="cloudsync-actions">
                                    <?php
                                    $guide_config = isset( $definition['guide'] ) ? $definition['guide'] : '';
                                    if ( is_array( $guide_config ) && isset( $guide_config['type'] ) && 'internal' === $guide_config['type'] ) :
                                        $guide_slug = isset( $guide_config['slug'] ) ? $guide_config['slug'] : $service;
                                        ?>
                                        <button type="button" class="button button-link js-cloudsync-guide" data-service="<?php echo esc_attr( $guide_slug ); ?>"><?php esc_html_e( 'Guía de conexión', 'secure-pdf-viewer' ); ?></button>
                                    <?php elseif ( ! empty( $guide_config ) ) : ?>
                                        <a class="button button-link" href="<?php echo esc_url( $guide_config ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Guía de conexión', 'secure-pdf-viewer' ); ?></a>
                                    <?php endif; ?>
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar credenciales', 'secure-pdf-viewer' ); ?></button>
                                    <button type="button" class="button button-secondary js-cloudsync-connect" data-oauth-url="<?php echo esc_url( $connect_url ); ?>" data-service="<?php echo esc_attr( $service ); ?>" <?php disabled( ! $requirements_met ); ?>><?php esc_html_e( 'Dar acceso', 'secure-pdf-viewer' ); ?></button>
                                    <a class="button button-link-delete" href="<?php echo esc_url( $revoke_url ); ?>"><?php esc_html_e( 'Revocar acceso', 'secure-pdf-viewer' ); ?></a>
                                </div>
                                <?php if ( ! $requirements_met ) : ?>
                                    <p class="cloudsync-requirements"><?php esc_html_e( 'Completa los campos obligatorios antes de iniciar sesión.', 'secure-pdf-viewer' ); ?></p>
                                <?php endif; ?>
                            </form>
                        </section>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ( ! empty( $connection_guides ) ) : ?>
                    <div class="cloudsync-guide-container" aria-hidden="true">
                        <div class="cloudsync-guide-modal__backdrop" data-cloudsync-guide-backdrop hidden></div>
                        <?php foreach ( $connection_guides as $guide_service => $guide ) :
                            $modal_id = 'cloudsync-guide-' . $guide_service;
                            $title_id = $modal_id . '-title';
                            ?>
                            <div id="<?php echo esc_attr( $modal_id ); ?>" class="cloudsync-guide-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?php echo esc_attr( $title_id ); ?>" hidden tabindex="-1">
                                <div class="cloudsync-guide-modal__content">
                                    <button type="button" class="cloudsync-guide-modal__close" aria-label="<?php esc_attr_e( 'Cerrar guía', 'secure-pdf-viewer' ); ?>">&times;</button>
                                    <h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $guide['title'] ); ?></h2>
                                    <?php if ( ! empty( $guide['intro'] ) ) : ?>
                                        <p class="cloudsync-guide-modal__intro"><?php echo esc_html( $guide['intro'] ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $guide['redirect'] ) ) : ?>
                                        <p class="cloudsync-guide-modal__redirect"><?php echo wp_kses_post( sprintf( __( 'URI de redirección autorizada: <code>%s</code>', 'secure-pdf-viewer' ), esc_html( $guide['redirect'] ) ) ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $guide['steps'] ) ) : ?>
                                        <ol class="cloudsync-guide-modal__steps">
                                            <?php foreach ( $guide['steps'] as $step ) : ?>
                                                <li><?php echo wp_kses_post( $step ); ?></li>
                                            <?php endforeach; ?>
                                        </ol>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $guide['extra'] ) ) : ?>
                                        <p class="cloudsync-guide-modal__extra"><?php echo esc_html( $guide['extra'] ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ( 'sync' === $active_tab ) : ?>
                <div class="cloudsync-card cloudsync-sync">
                    <h2><?php esc_html_e( 'Sincronización manual', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Ejecuta una sincronización inmediata con los servicios externos.', 'secure-pdf-viewer' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <?php wp_nonce_field( 'cloudsync_manual_sync' ); ?>
                        <input type="hidden" name="action" value="cloudsync_manual_sync" />
                        <button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Sincronizar ahora', 'secure-pdf-viewer' ); ?></button>
                    </form>
                    <ul class="cloudsync-sync-stats">
                        <li><strong><?php esc_html_e( 'Última sincronización:', 'secure-pdf-viewer' ); ?></strong> <?php echo $last_sync ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_sync ) ) : esc_html__( 'Nunca', 'secure-pdf-viewer' ); ?></li>
                        <li><strong><?php esc_html_e( 'Cursos sincronizados:', 'secure-pdf-viewer' ); ?></strong> <?php echo esc_html( (string) $course_total ); ?></li>
                        <li><strong><?php esc_html_e( 'Lecciones sincronizadas:', 'secure-pdf-viewer' ); ?></strong> <?php echo esc_html( (string) $lesson_total ); ?></li>
                    </ul>
                    <h3><?php esc_html_e( 'Cambios detectados recientemente', 'secure-pdf-viewer' ); ?></h3>
                    <?php if ( empty( $recent_logs ) ) : ?>
                        <p><?php esc_html_e( 'Sin eventos recientes.', 'secure-pdf-viewer' ); ?></p>
                    <?php else : ?>
                        <ul class="cloudsync-changes">
                            <?php foreach ( $recent_logs as $entry ) :
                                $type = 'info';
                                if ( strpos( strtolower( $entry['message'] ), 'created' ) !== false || strpos( strtolower( $entry['message'] ), 'creado' ) !== false ) {
                                    $type = 'success';
                                } elseif ( strpos( strtolower( $entry['message'] ), 'rename' ) !== false || strpos( strtolower( $entry['message'] ), 'renombr' ) !== false ) {
                                    $type = 'warning';
                                } elseif ( strpos( strtolower( $entry['message'] ), 'delete' ) !== false || strpos( strtolower( $entry['message'] ), 'elimin' ) !== false ) {
                                    $type = 'error';
                                }
                                ?>
                                <li class="cloudsync-change cloudsync-change--<?php echo esc_attr( $type ); ?>">
                                    <span class="cloudsync-change__time"><?php echo esc_html( $entry['time'] ); ?></span>
                                    <span class="cloudsync-change__message"><?php echo esc_html( $entry['message'] ); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php elseif ( 'logs' === $active_tab ) : ?>
                <div class="cloudsync-card cloudsync-logs">
                    <h2><?php esc_html_e( 'Monitor y logs', 'secure-pdf-viewer' ); ?></h2>
                    <?php
                    $filter_service = isset( $_GET['service'] ) ? sanitize_text_field( wp_unslash( $_GET['service'] ) ) : '';
                    $filter_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
                    ?>
                    <form method="get" class="cloudsync-log-filter">
                        <input type="hidden" name="page" value="cloudsync-dashboard-logs" />
                        <input type="hidden" name="tab" value="logs" />
                        <label for="cloudsync-filter-service"><?php esc_html_e( 'Servicio', 'secure-pdf-viewer' ); ?></label>
                        <select id="cloudsync-filter-service" name="service">
                            <option value=""><?php esc_html_e( 'Todos', 'secure-pdf-viewer' ); ?></option>
                            <option value="google" <?php selected( $filter_service, 'google' ); ?>>Google Drive</option>
                            <option value="dropbox" <?php selected( $filter_service, 'dropbox' ); ?>>Dropbox</option>
                            <option value="sharepoint" <?php selected( $filter_service, 'sharepoint' ); ?>>SharePoint</option>
                        </select>
                        <label for="cloudsync-filter-search" class="screen-reader-text"><?php esc_html_e( 'Buscar en logs', 'secure-pdf-viewer' ); ?></label>
                        <input type="search" id="cloudsync-filter-search" name="s" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'Buscar…', 'secure-pdf-viewer' ); ?>" />
                        <button type="submit" class="button"><?php esc_html_e( 'Filtrar', 'secure-pdf-viewer' ); ?></button>
                    </form>
                    <div class="cloudsync-log-viewer">
                        <?php
                        $filtered_logs = $logs;
                        if ( $filter_service ) {
                            $filtered_logs = array_filter(
                                $filtered_logs,
                                function ( $entry ) use ( $filter_service ) {
                                    return isset( $entry['context']['service'] ) && $filter_service === $entry['context']['service'];
                                }
                            );
                        }

                        if ( $filter_search ) {
                            $filtered_logs = array_filter(
                                $filtered_logs,
                                function ( $entry ) use ( $filter_search ) {
                                    return false !== stripos( $entry['message'], $filter_search );
                                }
                            );
                        }

                        if ( empty( $filtered_logs ) ) {
                            echo '<p>' . esc_html__( 'No hay entradas para mostrar.', 'secure-pdf-viewer' ) . '</p>';
                        } else {
                            echo '<ul>';
                            foreach ( array_reverse( $filtered_logs ) as $entry ) {
                                printf(
                                    '<li><strong>%1$s</strong> — %2$s</li>',
                                    esc_html( $entry['time'] ),
                                    esc_html( $entry['message'] )
                                );
                            }
                            echo '</ul>';
                        }
                        ?>
                    </div>
                    <div class="cloudsync-log-actions">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'cloudsync_download_logs' ); ?>
                            <input type="hidden" name="action" value="cloudsync_download_logs" />
                            <button type="submit" class="button"><?php esc_html_e( 'Descargar log completo', 'secure-pdf-viewer' ); ?></button>
                        </form>
                        <div class="cloudsync-metrics">
                            <?php
                            $total_logs = count( $logs );
                            $error_logs = array_filter(
                                $logs,
                                function ( $entry ) {
                                    return false !== stripos( $entry['message'], 'error' ) || false !== stripos( $entry['message'], 'fail' );
                                }
                            );

                            $success_rate = $total_logs ? round( ( ( $total_logs - count( $error_logs ) ) / $total_logs ) * 100, 2 ) : 0;
                            ?>
                            <p><strong><?php esc_html_e( 'Entradas registradas:', 'secure-pdf-viewer' ); ?></strong> <?php echo esc_html( (string) $total_logs ); ?></p>
                            <p><strong><?php esc_html_e( 'Fallos registrados:', 'secure-pdf-viewer' ); ?></strong> <?php echo esc_html( (string) count( $error_logs ) ); ?></p>
                            <p><strong><?php esc_html_e( 'Tasa de éxito estimada:', 'secure-pdf-viewer' ); ?></strong> <?php echo esc_html( $success_rate . '%' ); ?></p>
                        </div>
                    </div>
                </div>
            <?php elseif ( 'explorer' === $active_tab ) : ?>
                <div class="cloudsync-card cloudsync-explorer-card">
                    <h2><?php esc_html_e( 'Explorador de archivos', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Revisa la estructura sincronizada con Google Drive, Dropbox y SharePoint. Expande cada carpeta para conocer su contenido y abrir elementos directamente en la nube.', 'secure-pdf-viewer' ); ?></p>
                    <div id="cloudsync-explorer" data-services="<?php echo esc_attr( wp_json_encode( $explorer_services_data ) ); ?>">
                        <?php foreach ( $service_definitions as $service => $definition ) :
                            $token_field  = $definition['token_field'];
                            $connected    = ! empty( $oauth_settings[ $token_field ] );
                            $service_root = isset( $explorer_roots[ $service ] ) ? $explorer_roots[ $service ] : '';
                            $badge        = $connected ? $status_icons[ true ] : $status_icons[ false ];
                            $icon_class   = 'cloudsync-explorer-service__icon--' . $service;
                        ?>
                        <section class="cloudsync-explorer-service" data-service="<?php echo esc_attr( $service ); ?>" data-root="<?php echo esc_attr( $service_root ); ?>" data-connected="<?php echo $connected ? '1' : '0'; ?>">
                            <header class="cloudsync-explorer-service__header">
                                <div class="cloudsync-explorer-service__title">
                                    <span class="cloudsync-explorer-service__icon <?php echo esc_attr( $icon_class ); ?>" aria-hidden="true"></span>
                                    <span class="cloudsync-explorer-service__label"><?php echo esc_html( $definition['label'] ); ?></span>
                                </div>
                                <div class="cloudsync-explorer-service__meta">
                                    <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                    <?php if ( $connected ) : ?>
                                        <button type="button" class="button button-secondary cloudsync-explorer-refresh" data-service="<?php echo esc_attr( $service ); ?>"><?php esc_html_e( 'Actualizar vista', 'secure-pdf-viewer' ); ?></button>
                                    <?php endif; ?>
                                </div>
                            </header>
                            <div class="cloudsync-explorer-service__body">
                                <?php if ( ! $connected ) : ?>
                                    <div class="notice notice-warning inline">
                                        <p><?php esc_html_e( 'Conecta este servicio para visualizar sus archivos.', 'secure-pdf-viewer' ); ?></p>
                                    </div>
                                <?php else : ?>
                                    <div class="cloudsync-tree" role="tree" aria-live="polite" aria-busy="false">
                                        <div class="cloudsync-tree__loading" hidden>
                                            <span class="spinner is-active"></span>
                                            <span><?php esc_html_e( 'Cargando...', 'secure-pdf-viewer' ); ?></span>
                                        </div>
                                        <div class="cloudsync-tree__error notice notice-error inline" hidden></div>
                                        <ul class="cloudsync-tree__list" role="group" data-loaded="false"></ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php elseif ( 'advanced' === $active_tab ) : ?>
                <div class="cloudsync-card cloudsync-advanced">
                    <h2><?php esc_html_e( 'Herramientas avanzadas', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Operaciones diseñadas para administradores técnicos y desarrolladores.', 'secure-pdf-viewer' ); ?></p>
                    <div class="cloudsync-tool-grid">
                        <section class="cloudsync-tool">
                            <h3><?php esc_html_e( 'Reinicializar estructura de carpetas', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Elimina los metadatos actuales y vuelve a generar las carpetas remotas asignadas a cada curso o lección.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Esta acción recreará todas las carpetas. ¿Deseas continuar?', 'secure-pdf-viewer' ) ); ?>');">
                                <?php wp_nonce_field( 'cloudsync_rebuild_structure' ); ?>
                                <input type="hidden" name="action" value="cloudsync_rebuild_structure" />
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Reinicializar', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section class="cloudsync-tool">
                            <h3><?php esc_html_e( 'Forzar sincronización ahora', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Ejecuta una sincronización completa en ambos sentidos sin esperar al cron.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'cloudsync_force_sync' ); ?>
                                <input type="hidden" name="action" value="cloudsync_force_sync" />
                                <button type="submit" class="button button-primary"><?php esc_html_e( 'Forzar sincronización', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section class="cloudsync-tool">
                            <h3><?php esc_html_e( 'Limpiar metadatos huérfanos', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Elimina referencias remotas de cursos o lecciones eliminadas para mantener la base de datos ligera.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'cloudsync_cleanup_meta' ); ?>
                                <input type="hidden" name="action" value="cloudsync_cleanup_meta" />
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Limpiar ahora', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section class="cloudsync-tool">
                            <h3><?php esc_html_e( 'Reiniciar tokens OAuth', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Revoca los tokens guardados en la base de datos para obligar a una nueva autorización.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-token-reset">
                                <?php wp_nonce_field( 'cloudsync_reset_tokens' ); ?>
                                <input type="hidden" name="action" value="cloudsync_reset_tokens" />
                                <label class="screen-reader-text" for="cloudsync-reset-service"><?php esc_html_e( 'Servicio a reiniciar', 'secure-pdf-viewer' ); ?></label>
                                <select id="cloudsync-reset-service" name="service">
                                    <option value=""><?php esc_html_e( 'Todos los servicios', 'secure-pdf-viewer' ); ?></option>
                                    <option value="google"><?php esc_html_e( 'Google Drive', 'secure-pdf-viewer' ); ?></option>
                                    <option value="dropbox"><?php esc_html_e( 'Dropbox', 'secure-pdf-viewer' ); ?></option>
                                    <option value="sharepoint"><?php esc_html_e( 'SharePoint / OneDrive', 'secure-pdf-viewer' ); ?></option>
                                </select>
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Reiniciar tokens', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                    </div>
                    <section class="cloudsync-tool cloudsync-tool--developer">
                        <h3><?php esc_html_e( 'Modo desarrollador', 'secure-pdf-viewer' ); ?></h3>
                        <p><?php esc_html_e( 'Activa información adicional sobre hooks y endpoints disponibles.', 'secure-pdf-viewer' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-developer-form">
                            <?php wp_nonce_field( 'cloudsync_save_advanced', 'cloudsync_advanced_nonce' ); ?>
                            <input type="hidden" name="action" value="cloudsync_save_advanced" />
                            <label>
                                <input type="checkbox" name="developer_mode" value="1" <?php checked( (int) $general_settings['developer_mode'], 1 ); ?> />
                                <?php esc_html_e( 'Mostrar herramientas para desarrolladores', 'secure-pdf-viewer' ); ?>
                            </label>
                            <button type="submit" class="button button-secondary"><?php esc_html_e( 'Actualizar', 'secure-pdf-viewer' ); ?></button>
                        </form>
                        <?php if ( (int) $general_settings['developer_mode'] ) : ?>
                            <div class="cloudsync-dev-info">
                                <h4><?php esc_html_e( 'Hooks activos', 'secure-pdf-viewer' ); ?></h4>
                                <ul>
                                    <li><code>cloudsync_after_create_course</code></li>
                                    <li><code>cloudsync_course_folder_name</code></li>
                                </ul>
                                <h4><?php esc_html_e( 'Endpoints REST sugeridos', 'secure-pdf-viewer' ); ?></h4>
                                <p><code>/wp-json/cloudsync/v1/status</code></p>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </div>
        <style>
            .cloudsync-dashboard .cloudsync-card {
                background: #fff;
                padding: 24px;
                margin-top: 24px;
                border-radius: 8px;
                box-shadow: 0 10px 30px -20px rgba(0,0,0,0.3);
            }
            .cloudsync-dashboard .cloudsync-oauth-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            .cloudsync-service-card {
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 18px;
                background: #fdfdfd;
                display: flex;
                flex-direction: column;
                gap: 16px;
            }
            .cloudsync-service-card header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .cloudsync-service-fields {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .cloudsync-service-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 4px;
            }
            .cloudsync-service-field input.regular-text {
                width: 100%;
            }
            .cloudsync-field-note {
                display: block;
                font-size: 12px;
                color: #646970;
                margin-top: 4px;
            }
            .cloudsync-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                align-items: center;
            }
            .cloudsync-requirements {
                margin: 0;
                font-size: 13px;
                color: #d63638;
            }
            .cloudsync-status-badge {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-weight: 600;
                border-radius: 999px;
                padding: 2px 10px;
            }
            .cloudsync-status-badge__dot {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                display: block;
                background: currentColor;
            }
            .cloudsync-status-badge.is-connected {
                background: #ecf7f1;
                color: #0a7f34;
            }
            .cloudsync-status-badge.is-disconnected {
                background: #fdeaea;
                color: #b20d30;
            }
            .cloudsync-sync-stats {
                list-style: disc inside;
                margin: 20px 0;
            }
            .cloudsync-changes {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .cloudsync-change {
                display: flex;
                gap: 16px;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #ececec;
            }
            .cloudsync-change--success .cloudsync-change__message { color: #0a7f34; }
            .cloudsync-change--warning .cloudsync-change__message { color: #b58105; }
            .cloudsync-change--error .cloudsync-change__message { color: #b20d30; }
            .cloudsync-log-viewer {
                max-height: 320px;
                overflow-y: auto;
                background: #1e1e1e;
                color: #f5f5f5;
                padding: 16px;
                border-radius: 6px;
                margin-bottom: 16px;
            }
            .cloudsync-log-viewer ul { margin: 0; padding-left: 20px; }
            .cloudsync-log-filter {
                display: flex;
                gap: 12px;
                align-items: center;
                margin-bottom: 16px;
            }
            .cloudsync-log-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 24px;
                flex-wrap: wrap;
            }
            .cloudsync-metrics p { margin: 0; }
            .cloudsync-tool-grid {
                display: grid;
                gap: 24px;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                margin-top: 20px;
            }
            .cloudsync-tool {
                border: 1px solid #dcdcde;
                border-radius: 8px;
                padding: 18px;
                background: #f8f9f9;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .cloudsync-token-reset {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .cloudsync-token-reset select {
                max-width: 100%;
            }
            .cloudsync-developer-form {
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
            }
            .cloudsync-tool--developer {
                margin-top: 24px;
                background: #fff;
            }
            .cloudsync-dev-info {
                margin-top: 12px;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 12px;
                border-radius: 6px;
            }
        </style>

        <?php
    }
}
