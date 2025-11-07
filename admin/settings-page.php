<?php
/**
 * Cloud sync settings page.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = cloudsync_get_settings();
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Cloud Sync Settings', 'secure-pdf-viewer' ); ?></h1>
    <form action="options.php" method="post">
        <?php settings_fields( 'cloudsync_settings_group' ); ?>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="google_client_id"><?php esc_html_e( 'Google Client ID', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[google_client_id]" type="text" id="google_client_id" value="<?php echo esc_attr( $settings['google_client_id'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="google_client_secret"><?php esc_html_e( 'Google Client Secret', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[google_client_secret]" type="text" id="google_client_secret" value="<?php echo esc_attr( $settings['google_client_secret'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="google_refresh_token"><?php esc_html_e( 'Google Refresh Token', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[google_refresh_token]" type="text" id="google_refresh_token" value="<?php echo esc_attr( $settings['google_refresh_token'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dropbox_client_id"><?php esc_html_e( 'Dropbox Client ID', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[dropbox_client_id]" type="text" id="dropbox_client_id" value="<?php echo esc_attr( $settings['dropbox_client_id'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dropbox_client_secret"><?php esc_html_e( 'Dropbox Client Secret', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[dropbox_client_secret]" type="text" id="dropbox_client_secret" value="<?php echo esc_attr( $settings['dropbox_client_secret'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="dropbox_refresh_token"><?php esc_html_e( 'Dropbox Refresh Token', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[dropbox_refresh_token]" type="text" id="dropbox_refresh_token" value="<?php echo esc_attr( $settings['dropbox_refresh_token'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sharepoint_tenant_id"><?php esc_html_e( 'SharePoint Tenant ID', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[sharepoint_tenant_id]" type="text" id="sharepoint_tenant_id" value="<?php echo esc_attr( $settings['sharepoint_tenant_id'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sharepoint_client_id"><?php esc_html_e( 'SharePoint Client ID', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[sharepoint_client_id]" type="text" id="sharepoint_client_id" value="<?php echo esc_attr( $settings['sharepoint_client_id'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sharepoint_secret"><?php esc_html_e( 'SharePoint Client Secret', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[sharepoint_secret]" type="text" id="sharepoint_secret" value="<?php echo esc_attr( $settings['sharepoint_secret'] ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sharepoint_refresh_token"><?php esc_html_e( 'SharePoint Refresh Token', 'secure-pdf-viewer' ); ?></label></th>
                    <td><input name="cloudsync_settings[sharepoint_refresh_token]" type="text" id="sharepoint_refresh_token" value="<?php echo esc_attr( $settings['sharepoint_refresh_token'] ); ?>" class="regular-text" /></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button(); ?>
    </form>
    <p class="description">
        <?php esc_html_e( 'After saving credentials, use the respective OAuth consoles to grant access and paste the refresh tokens here.', 'secure-pdf-viewer' ); ?>
    </p>
</div>
<?php
/**
 * Developers can hook into the settings page to render extra fields.
 *
 * @example add_action( 'cloudsync_settings_after_form', function() { echo '<p>Custom</p>'; } );
 */
do_action( 'cloudsync_settings_after_form' );
