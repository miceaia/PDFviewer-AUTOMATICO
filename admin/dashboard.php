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
            'advanced' => __( 'Avanzado', 'secure-pdf-viewer' ),
        );

        if ( ! isset( $tabs[ $active_tab ] ) ) {
            $active_tab = 'general';
        }

        $general_settings = cloudsync_get_general_settings();
        $oauth_settings   = cloudsync_get_settings();
        $logs             = cloudsync_get_logs();
        $notice           = isset( $_GET['cloudsync_notice'] ) ? sanitize_key( wp_unslash( $_GET['cloudsync_notice'] ) ) : '';

        $last_sync = (int) get_option( 'cloudsync_last_sync', 0 );
        $courses   = wp_count_posts( 'curso' );
        $lessons   = wp_count_posts( 'leccion' );

        $course_total  = isset( $courses->publish ) ? (int) $courses->publish : 0;
        $lesson_total  = isset( $lessons->publish ) ? (int) $lessons->publish : 0;
        $recent_logs   = array_slice( array_reverse( $logs ), 0, 10 );

        $status_icons = array(
            true  => '<span class="cloudsync-status cloudsync-status--ok">🟢 ' . esc_html__( 'Conectado', 'secure-pdf-viewer' ) . '</span>',
            false => '<span class="cloudsync-status cloudsync-status--fail">🔴 ' . esc_html__( 'Desconectado', 'secure-pdf-viewer' ) . '</span>',
        );

        ?>
        <div class="wrap cloudsync-dashboard">
            <h1>☁️ <?php esc_html_e( 'CloudSync LMS Dashboard', 'secure-pdf-viewer' ); ?></h1>

            <?php if ( ! empty( $notice ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        switch ( $notice ) {
                            case 'manual-sync':
                                esc_html_e( 'Sincronización manual completada correctamente.', 'secure-pdf-viewer' );
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
                <form method="post" action="options.php" class="cloudsync-card">
                    <?php settings_fields( 'cloudsync_general' ); ?>
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
                <form method="post" action="options.php" class="cloudsync-card">
                    <?php settings_fields( 'cloudsync_oauth' ); ?>
                    <h2><?php esc_html_e( 'Credenciales OAuth', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Registra tus llaves OAuth. Los tokens sensibles se cifran automáticamente.', 'secure-pdf-viewer' ); ?></p>
                    <div class="cloudsync-oauth-grid">
                        <?php
                        $services = array(
                            'google'    => array(
                                'title'   => __( 'Google Drive', 'secure-pdf-viewer' ),
                                'fields'  => array(
                                    'google_client_id'     => __( 'Client ID', 'secure-pdf-viewer' ),
                                    'google_client_secret' => __( 'Client Secret', 'secure-pdf-viewer' ),
                                    'google_refresh_token' => __( 'Refresh Token', 'secure-pdf-viewer' ),
                                ),
                                'connected' => ! empty( $oauth_settings['google_refresh_token'] ),
                            ),
                            'dropbox'   => array(
                                'title'   => __( 'Dropbox', 'secure-pdf-viewer' ),
                                'fields'  => array(
                                    'dropbox_app_key'      => __( 'App Key', 'secure-pdf-viewer' ),
                                    'dropbox_app_secret'   => __( 'App Secret', 'secure-pdf-viewer' ),
                                    'dropbox_refresh_token'=> __( 'Refresh Token', 'secure-pdf-viewer' ),
                                ),
                                'connected' => ! empty( $oauth_settings['dropbox_refresh_token'] ),
                            ),
                            'sharepoint'=> array(
                                'title'   => __( 'SharePoint / OneDrive', 'secure-pdf-viewer' ),
                                'fields'  => array(
                                    'sharepoint_client_id'     => __( 'Client ID', 'secure-pdf-viewer' ),
                                    'sharepoint_secret'        => __( 'Client Secret', 'secure-pdf-viewer' ),
                                    'sharepoint_refresh_token' => __( 'Refresh Token', 'secure-pdf-viewer' ),
                                ),
                                'connected' => ! empty( $oauth_settings['sharepoint_refresh_token'] ),
                            ),
                        );
                        foreach ( $services as $service => $data ) :
                            ?>
                            <section class="cloudsync-service-card">
                                <header>
                                    <h3><?php echo esc_html( $data['title'] ); ?></h3>
                                    <?php echo $data['connected'] ? $status_icons[ true ] : $status_icons[ false ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </header>
                                <?php foreach ( $data['fields'] as $field => $label ) : ?>
                                    <p>
                                        <label for="<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
                                        <input type="text" id="<?php echo esc_attr( $field ); ?>" name="cloudsync_settings[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $oauth_settings[ $field ] ); ?>" class="regular-text" />
                                    </p>
                                <?php endforeach; ?>
                                <div class="cloudsync-actions">
                                    <?php
                                    $guide_link = 'https://developers.google.com/drive/api/v3/about-auth';
                                    if ( 'dropbox' === $service ) {
                                        $guide_link = 'https://developers.dropbox.com/oauth-guide';
                                    } elseif ( 'sharepoint' === $service ) {
                                        $guide_link = 'https://learn.microsoft.com/graph/auth-v2-user';
                                    }

                                    $revoke_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'action'  => 'cloudsync_reset_tokens',
                                                'service' => $service,
                                            ),
                                            admin_url( 'admin-post.php' )
                                        ),
                                        'cloudsync_reset_tokens'
                                    );
                                    ?>
                                    <a class="button button-secondary" href="<?php echo esc_url( $guide_link ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Guía de conexión', 'secure-pdf-viewer' ); ?></a>
                                    <a class="button-link delete-link" href="<?php echo esc_url( $revoke_url ); ?>"><?php esc_html_e( 'Revocar acceso', 'secure-pdf-viewer' ); ?></a>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                    <?php submit_button( __( 'Guardar credenciales', 'secure-pdf-viewer' ) ); ?>
                </form>
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
            <?php elseif ( 'advanced' === $active_tab ) : ?>
                <div class="cloudsync-card cloudsync-advanced">
                    <h2><?php esc_html_e( 'Herramientas avanzadas', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Operaciones diseñadas para administradores técnicos y desarrolladores.', 'secure-pdf-viewer' ); ?></p>
                    <div class="cloudsync-tool-grid">
                        <section>
                            <h3><?php esc_html_e( 'Limpiar metadatos huérfanos', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Elimina referencias remotas de cursos o lecciones eliminados.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'cloudsync_cleanup_meta' ); ?>
                                <input type="hidden" name="action" value="cloudsync_cleanup_meta" />
                                <button type="submit" class="button"><?php esc_html_e( 'Limpiar ahora', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section>
                            <h3><?php esc_html_e( 'Reiniciar tokens OAuth', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Revoca los tokens guardados en la base de datos.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'cloudsync_reset_tokens' ); ?>
                                <input type="hidden" name="action" value="cloudsync_reset_tokens" />
                                <button type="submit" class="button"><?php esc_html_e( 'Reiniciar tokens', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section>
                            <h3><?php esc_html_e( 'Reinicializar estructura de carpetas', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Elimina metadatos actuales y vuelve a crear las carpetas remotas.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Esta acción recreará todas las carpetas. ¿Deseas continuar?', 'secure-pdf-viewer' ) ); ?>');">
                                <?php wp_nonce_field( 'cloudsync_rebuild_structure' ); ?>
                                <input type="hidden" name="action" value="cloudsync_rebuild_structure" />
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Reinicializar', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section>
                            <h3><?php esc_html_e( 'Modo desarrollador', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Activa información adicional sobre hooks y endpoints disponibles.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-inline-form">
                                <?php wp_nonce_field( 'cloudsync_toggle_dev_mode' ); ?>
                                <input type="hidden" name="action" value="cloudsync_toggle_dev_mode" />
                                <label>
                                    <input type="checkbox" name="developer_mode" value="1" <?php checked( (int) $general_settings['developer_mode'], 1 ); ?> />
                                    <?php esc_html_e( 'Mostrar herramientas para desarrolladores', 'secure-pdf-viewer' ); ?>
                                </label>
                                <button type="submit" class="button"><?php esc_html_e( 'Actualizar', 'secure-pdf-viewer' ); ?></button>
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
                border-radius: 6px;
                padding: 16px;
                background: #fefefe;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .cloudsync-service-card header {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .cloudsync-actions {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .cloudsync-inline-form {
                display: inline-flex;
                gap: 12px;
                align-items: center;
                margin: 0;
            }
            .cloudsync-status {
                font-weight: 600;
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
            }
            .cloudsync-metrics p { margin: 0; }
            .cloudsync-tool-grid {
                display: grid;
                gap: 24px;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
            .cloudsync-tool-grid section {
                border: 1px solid #dcdcde;
                border-radius: 6px;
                padding: 16px;
                background: #f8f9f9;
            }
            .cloudsync-dev-info {
                margin-top: 12px;
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 12px;
                border-radius: 6px;
            }
            .cloudsync-status--fail { color: #d63638; }
            .cloudsync-status--ok { color: #10843f; }
        </style>
        <?php
    }
}
