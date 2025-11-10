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
            $flash_notice = get_transient( 'cloudsync_admin_notice' );
            if ( $flash_notice ) :
                delete_transient( 'cloudsync_admin_notice' );
                $notice_class = ( isset( $flash_notice['type'] ) && 'success' === $flash_notice['type'] ) ? 'notice notice-success' : 'notice notice-error';
                ?>
                <div class="<?php echo esc_attr( $notice_class ); ?> is-dismissible">
                    <p><?php echo esc_html( $flash_notice['msg'] ); ?></p>
                </div>
            <?php elseif ( isset( $_GET['settings-updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ <?php esc_html_e( 'Cambios guardados correctamente.', 'secure-pdf-viewer' ); ?></p>
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
                    <?php wp_nonce_field( 'cloudsync_config_nonce' ); ?>
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
                <?php
                $service_credentials = array(
                    'google'     => cloudsync_get_service_credentials( 'google' ),
                    'dropbox'    => cloudsync_get_service_credentials( 'dropbox' ),
                    'sharepoint' => cloudsync_get_service_credentials( 'sharepoint' ),
                );

                $drive_connected      = ! empty( $service_credentials['google']['refresh_token'] );
                $dropbox_connected    = ! empty( $service_credentials['dropbox']['refresh_token'] );
                $sharepoint_connected = ! empty( $service_credentials['sharepoint']['refresh_token'] );

                $drive_connect_url  = wp_nonce_url( admin_url( 'admin-post.php?action=cloudsync_oauth_connect&service=google' ), 'cloudsync_credentials_nonce' );
                $drive_revoke_url   = wp_nonce_url( admin_url( 'admin-post.php?action=cloudsync_revoke_credentials&service=google' ), 'cloudsync_credentials_nonce' );
                $dropbox_connect_url = wp_nonce_url( admin_url( 'admin-post.php?action=cloudsync_oauth_connect&service=dropbox' ), 'cloudsync_credentials_nonce' );
                $dropbox_revoke_url = wp_nonce_url( admin_url( 'admin-post.php?action=cloudsync_revoke_credentials&service=dropbox' ), 'cloudsync_credentials_nonce' );
                $share_connect_url  = wp_nonce_url( admin_url( 'admin-post.php?action=cloudsync_oauth_connect&service=sharepoint' ), 'cloudsync_credentials_nonce' );
                $share_revoke_url   = wp_nonce_url( admin_url( 'admin-post.php?action=cloudsync_revoke_credentials&service=sharepoint' ), 'cloudsync_credentials_nonce' );

                $drive_secret_placeholder       = ! empty( $service_credentials['google']['client_secret'] ) ? str_repeat( '•', 8 ) : '';
                $drive_refresh_placeholder      = $drive_connected ? str_repeat( '•', 8 ) : '';
                $dropbox_secret_placeholder     = ! empty( $service_credentials['dropbox']['client_secret'] ) ? str_repeat( '•', 8 ) : '';
                $dropbox_refresh_placeholder    = $dropbox_connected ? str_repeat( '•', 8 ) : '';
                $sharepoint_secret_placeholder  = ! empty( $service_credentials['sharepoint']['client_secret'] ) ? str_repeat( '•', 8 ) : '';
                $sharepoint_refresh_placeholder = $sharepoint_connected ? str_repeat( '•', 8 ) : '';
                ?>
                <div class="cloudsync-card">
                    <h2><?php esc_html_e( 'Credenciales OAuth', 'secure-pdf-viewer' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Guarda tus claves de cliente. Los secretos se cifran automáticamente antes de almacenarse.', 'secure-pdf-viewer' ); ?></p>
                    <div class="cloudsync-oauth-grid">
                        <section class="cloudsync-service-card" aria-labelledby="cloudsync-card-drive">
                            <header>
                                <div class="cloudsync-service-heading">
                                    <h3 id="cloudsync-card-drive"><?php esc_html_e( 'Google Drive', 'secure-pdf-viewer' ); ?></h3>
                                    <?php echo $drive_connected ? $status_icons[ true ] : $status_icons[ false ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            </header>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-service-form">
                                <?php wp_nonce_field( 'cloudsync_credentials_nonce' ); ?>
                                <input type="hidden" name="action" value="cloudsync_save_credentials" />
                                <input type="hidden" name="service" value="google" />
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-drive-client-id"><?php esc_html_e( 'Client ID', 'secure-pdf-viewer' ); ?></label>
                                    <input type="text" class="regular-text" id="cloudsync-drive-client-id" name="client_id" value="<?php echo esc_attr( $service_credentials['google']['client_id'] ?? '' ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-drive-client-secret"><?php esc_html_e( 'Client Secret', 'secure-pdf-viewer' ); ?></label>
                                    <input type="password" class="regular-text" id="cloudsync-drive-client-secret" name="client_secret" placeholder="<?php echo esc_attr( $drive_secret_placeholder ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-drive-refresh"><?php esc_html_e( 'Refresh Token', 'secure-pdf-viewer' ); ?></label>
                                    <input type="password" class="regular-text" id="cloudsync-drive-refresh" name="refresh_token" placeholder="<?php echo esc_attr( $drive_refresh_placeholder ); ?>" autocomplete="off" />
                                </p>
                                <div class="cloudsync-actions">
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar credenciales', 'secure-pdf-viewer' ); ?></button>
                                    <button type="button" class="button js-cloudsync-connect" data-oauth-url="<?php echo esc_url( $drive_connect_url ); ?>"><?php esc_html_e( 'Dar acceso', 'secure-pdf-viewer' ); ?></button>
                                    <a class="button-link-delete" href="<?php echo esc_url( $drive_revoke_url ); ?>"><?php esc_html_e( 'Revocar acceso', 'secure-pdf-viewer' ); ?></a>
                                    <?php if ( isset( $connection_guides['google'] ) ) : ?>
                                        <button type="button" class="button button-link js-cloudsync-guide" data-service="google"><?php esc_html_e( 'Guía de conexión', 'secure-pdf-viewer' ); ?></button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </section>

                        <section class="cloudsync-service-card" aria-labelledby="cloudsync-card-dropbox">
                            <header>
                                <div class="cloudsync-service-heading">
                                    <h3 id="cloudsync-card-dropbox"><?php esc_html_e( 'Dropbox', 'secure-pdf-viewer' ); ?></h3>
                                    <?php echo $dropbox_connected ? $status_icons[ true ] : $status_icons[ false ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            </header>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-service-form">
                                <?php wp_nonce_field( 'cloudsync_credentials_nonce' ); ?>
                                <input type="hidden" name="action" value="cloudsync_save_credentials" />
                                <input type="hidden" name="service" value="dropbox" />
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-dropbox-key"><?php esc_html_e( 'App Key / Client ID', 'secure-pdf-viewer' ); ?></label>
                                    <input type="text" class="regular-text" id="cloudsync-dropbox-key" name="client_id" value="<?php echo esc_attr( $service_credentials['dropbox']['client_id'] ?? '' ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-dropbox-secret"><?php esc_html_e( 'App Secret / Client Secret', 'secure-pdf-viewer' ); ?></label>
                                    <input type="password" class="regular-text" id="cloudsync-dropbox-secret" name="client_secret" placeholder="<?php echo esc_attr( $dropbox_secret_placeholder ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-dropbox-refresh"><?php esc_html_e( 'Refresh Token', 'secure-pdf-viewer' ); ?></label>
                                    <input type="password" class="regular-text" id="cloudsync-dropbox-refresh" name="refresh_token" placeholder="<?php echo esc_attr( $dropbox_refresh_placeholder ); ?>" autocomplete="off" />
                                </p>
                                <div class="cloudsync-actions">
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar credenciales', 'secure-pdf-viewer' ); ?></button>
                                    <button type="button" class="button js-cloudsync-connect" data-oauth-url="<?php echo esc_url( $dropbox_connect_url ); ?>"><?php esc_html_e( 'Dar acceso', 'secure-pdf-viewer' ); ?></button>
                                    <a class="button-link-delete" href="<?php echo esc_url( $dropbox_revoke_url ); ?>"><?php esc_html_e( 'Revocar acceso', 'secure-pdf-viewer' ); ?></a>
                                    <?php if ( isset( $connection_guides['dropbox'] ) ) : ?>
                                        <button type="button" class="button button-link js-cloudsync-guide" data-service="dropbox"><?php esc_html_e( 'Guía de conexión', 'secure-pdf-viewer' ); ?></button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </section>

                        <section class="cloudsync-service-card" aria-labelledby="cloudsync-card-sharepoint">
                            <header>
                                <div class="cloudsync-service-heading">
                                    <h3 id="cloudsync-card-sharepoint"><?php esc_html_e( 'SharePoint / OneDrive', 'secure-pdf-viewer' ); ?></h3>
                                    <?php echo $sharepoint_connected ? $status_icons[ true ] : $status_icons[ false ]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                            </header>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-service-form">
                                <?php wp_nonce_field( 'cloudsync_credentials_nonce' ); ?>
                                <input type="hidden" name="action" value="cloudsync_save_credentials" />
                                <input type="hidden" name="service" value="sharepoint" />
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-sharepoint-tenant"><?php esc_html_e( 'Tenant ID', 'secure-pdf-viewer' ); ?></label>
                                    <input type="text" class="regular-text" id="cloudsync-sharepoint-tenant" name="tenant_id" value="<?php echo esc_attr( $service_credentials['sharepoint']['tenant_id'] ?? '' ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-sharepoint-client"><?php esc_html_e( 'Client ID', 'secure-pdf-viewer' ); ?></label>
                                    <input type="text" class="regular-text" id="cloudsync-sharepoint-client" name="client_id" value="<?php echo esc_attr( $service_credentials['sharepoint']['client_id'] ?? '' ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-sharepoint-secret"><?php esc_html_e( 'Client Secret', 'secure-pdf-viewer' ); ?></label>
                                    <input type="password" class="regular-text" id="cloudsync-sharepoint-secret" name="client_secret" placeholder="<?php echo esc_attr( $sharepoint_secret_placeholder ); ?>" autocomplete="off" />
                                </p>
                                <p class="cloudsync-service-field">
                                    <label for="cloudsync-sharepoint-refresh"><?php esc_html_e( 'Refresh Token', 'secure-pdf-viewer' ); ?></label>
                                    <input type="password" class="regular-text" id="cloudsync-sharepoint-refresh" name="refresh_token" placeholder="<?php echo esc_attr( $sharepoint_refresh_placeholder ); ?>" autocomplete="off" />
                                </p>
                                <div class="cloudsync-actions">
                                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar credenciales', 'secure-pdf-viewer' ); ?></button>
                                    <button type="button" class="button js-cloudsync-connect" data-oauth-url="<?php echo esc_url( $share_connect_url ); ?>"><?php esc_html_e( 'Dar acceso', 'secure-pdf-viewer' ); ?></button>
                                    <a class="button-link-delete" href="<?php echo esc_url( $share_revoke_url ); ?>"><?php esc_html_e( 'Revocar acceso', 'secure-pdf-viewer' ); ?></a>
                                    <?php if ( isset( $connection_guides['sharepoint'] ) ) : ?>
                                        <button type="button" class="button button-link js-cloudsync-guide" data-service="sharepoint"><?php esc_html_e( 'Guía de conexión', 'secure-pdf-viewer' ); ?></button>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </section>
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
                                <input type="hidden" name="action" value="cloudsync_reinitialize_folders" />
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
                                <input type="hidden" name="action" value="cloudsync_cleanup_orphans" />
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Limpiar ahora', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                        <section class="cloudsync-tool">
                            <h3><?php esc_html_e( 'Reiniciar tokens OAuth', 'secure-pdf-viewer' ); ?></h3>
                            <p><?php esc_html_e( 'Revoca los tokens guardados en la base de datos para obligar a una nueva autorización.', 'secure-pdf-viewer' ); ?></p>
                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-token-reset">
                                <?php wp_nonce_field( 'cloudsync_reset_tokens' ); ?>
                                <input type="hidden" name="action" value="cloudsync_reset_tokens" />
                                <button type="submit" class="button button-secondary"><?php esc_html_e( 'Reiniciar tokens', 'secure-pdf-viewer' ); ?></button>
                            </form>
                        </section>
                    </div>
                    <section class="cloudsync-tool cloudsync-tool--developer">
                        <h3><?php esc_html_e( 'Modo desarrollador', 'secure-pdf-viewer' ); ?></h3>
                        <p><?php esc_html_e( 'Activa información adicional sobre hooks y endpoints disponibles.', 'secure-pdf-viewer' ); ?></p>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cloudsync-developer-form">
                            <?php wp_nonce_field( 'cloudsync_advanced_nonce' ); ?>
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
