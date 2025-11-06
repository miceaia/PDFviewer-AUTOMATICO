<?php
/**
 * Cloud sync logs admin page.
 *
 * @package SecurePDFViewer\CloudSync
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$logs = array_reverse( cloudsync_get_logs() );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Cloud Sync Logs', 'secure-pdf-viewer' ); ?></h1>
    <p><?php esc_html_e( 'Use this screen to monitor synchronization activity and troubleshoot issues.', 'secure-pdf-viewer' ); ?></p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Date', 'secure-pdf-viewer' ); ?></th>
                <th><?php esc_html_e( 'Message', 'secure-pdf-viewer' ); ?></th>
                <th><?php esc_html_e( 'Context', 'secure-pdf-viewer' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr>
                    <td colspan="3"><?php esc_html_e( 'No log entries yet.', 'secure-pdf-viewer' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['time'] ); ?></td>
                        <td><?php echo esc_html( $log['message'] ); ?></td>
                        <td><code><?php echo esc_html( wp_json_encode( $log['context'] ) ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <p class="description">
        <?php esc_html_e( 'Logs are truncated to the 200 most recent entries.', 'secure-pdf-viewer' ); ?>
    </p>
</div>
