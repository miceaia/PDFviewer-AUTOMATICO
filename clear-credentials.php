<?php
/**
 * Emergency credential cleanup script.
 *
 * IMPORTANT: This script clears ALL corrupted CloudSync credentials.
 * Use this if you're experiencing errors when saving credentials.
 *
 * HOW TO USE:
 * 1. Upload this file to your WordPress root directory
 * 2. Visit: https://your-site.com/clear-credentials.php
 * 3. Delete this file after use for security
 *
 * @package SecurePDFViewer\CloudSync
 */

// Prevent direct access if not from WordPress root
if ( ! file_exists( './wp-load.php' ) && ! file_exists( '../wp-load.php' ) ) {
    die( 'Error: This script must be placed in WordPress root directory.' );
}

// Load WordPress
if ( file_exists( './wp-load.php' ) ) {
    require_once './wp-load.php';
} else {
    require_once '../wp-load.php';
}

// Security check
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this page.' );
}

// Load CloudSync helpers
if ( ! function_exists( 'cloudsync_clear_all_credentials' ) ) {
    $helpers_path = WP_PLUGIN_DIR . '/secure-pdf-viewer/includes/helpers.php';
    if ( file_exists( $helpers_path ) ) {
        require_once $helpers_path;
    } else {
        wp_die( 'CloudSync helpers not found. Make sure the plugin is installed.' );
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CloudSync Credential Cleanup</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            border-bottom: 2px solid #2271b1;
            padding-bottom: 10px;
        }
        .warning {
            background: #fcf3cf;
            border-left: 4px solid #f39c12;
            padding: 15px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px 5px 10px 0;
        }
        .button:hover {
            background: #135e96;
        }
        .button-danger {
            background: #dc3545;
        }
        .button-danger:hover {
            background: #c82333;
        }
        code {
            background: #f5f5f5;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 CloudSync Credential Cleanup</h1>

        <?php
        if ( isset( $_GET['action'] ) && 'clear' === $_GET['action'] && check_admin_referer( 'clear_cloudsync_credentials' ) ) {
            // Clear all credentials
            cloudsync_clear_all_credentials();
            ?>
            <div class="success">
                <h3>✅ Credentials Cleared Successfully!</h3>
                <p>All CloudSync credentials have been removed from the database.</p>
                <p><strong>Next steps:</strong></p>
                <ol>
                    <li>Go to your WordPress admin panel</li>
                    <li>Navigate to <strong>CloudSync Dashboard</strong></li>
                    <li>Enter your credentials again (they will be encrypted correctly)</li>
                    <li><strong>Delete this file (<code>clear-credentials.php</code>) for security</strong></li>
                </ol>
                <a href="<?php echo admin_url( 'admin.php?page=cloudsync-dashboard' ); ?>" class="button">Go to CloudSync Dashboard</a>
            </div>
            <?php
        } else {
            ?>
            <div class="warning">
                <h3>⚠️ Warning</h3>
                <p>This script will <strong>permanently delete</strong> all stored CloudSync credentials for:</p>
                <ul>
                    <li>Google Drive</li>
                    <li>Dropbox</li>
                    <li>SharePoint / OneDrive</li>
                </ul>
                <p>You will need to re-enter your credentials after running this cleanup.</p>
            </div>

            <h2>Why use this?</h2>
            <p>If you're experiencing a <strong>"critical error"</strong> when trying to save credentials, it's likely due to corrupted (double-encrypted) data in the database. This script cleans that up.</p>

            <h2>Ready to proceed?</h2>
            <a href="<?php echo wp_nonce_url( add_query_arg( 'action', 'clear' ), 'clear_cloudsync_credentials' ); ?>" class="button button-danger" onclick="return confirm('Are you sure you want to clear all credentials? This cannot be undone.');">Clear All Credentials</a>
            <a href="<?php echo admin_url(); ?>" class="button">Cancel - Go to Dashboard</a>
            <?php
        }
        ?>

        <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
        <p style="color: #666; font-size: 14px;">
            <strong>Security Note:</strong> Delete this file after use. It should only be used once to fix corrupted credentials.
        </p>
    </div>
</body>
</html>
