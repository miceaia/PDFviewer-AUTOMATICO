# CloudSync_Manager Codex Reference

## Description
`CloudSync_Manager` centralises the two-way synchronisation between WordPress custom post types (`curso` and `leccion`) and supported cloud providers. It registers the required post types, hooks into save/delete events, and coordinates cron jobs that poll remote APIs.

## Usage
```php
$manager = new CloudSync_Manager();
$manager->init();
```

This bootstrapper should run during the `plugins_loaded` lifecycle (already handled by the plugin bootstrap). You can manually instantiate the manager when writing integration tests or extending the plugin.

## Actions
- `cloudsync_after_create_course( int $post_id, string $folder_id )`
  Fired every time a new remote folder is created or linked to a course/lesson. Use this to enqueue background tasks or to register additional metadata.

## Filters
- `cloudsync_course_folder_name( string $name, int $post_id )`
  Filter the folder name before it is sent to the remote APIs. Useful for appending unique slugs or enforcing naming conventions.

## Helper Functions
- `cloudsync_get_settings()` returns decrypted credentials.
- `cloudsync_add_log( string $message, array $context = [] )` persists diagnostic entries visible under `Tools → Cloud Sync Logs`.

## Extending Connectors
Each connector implements the following methods:

```php
public function create_folder( $name, $parent_id = null );
public function list_changes( $since_token );
public function rename_folder( $id, $new_name );
public function delete_folder( $id );
```

To add a new provider create a class that matches this interface and register it from a `plugins_loaded` callback:

```php
add_action( 'plugins_loaded', function() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-connector-myservice.php';
    $manager = SecurePDFViewer::get_instance();
    // Use hooks exposed by CloudSync_Manager to inject the new connector.
} );
```

## Remote → WordPress Sync
Remote changes are processed hourly by the `cloudsync_check_remote_changes` cron event. You can speed up the polling interval by rescheduling this event on plugin activation.

## Error Handling
The manager stores up to 200 log entries. Use `cloudsync_get_logs()` programmatically when writing CLI commands to inspect the latest sync operations.

## SharePoint Roadmap
1. Configure Azure AD credentials in **Settings → Cloud Sync**.
2. Implement the REST calls inside `Connector_SharePoint` using `wp_remote_get()`/`wp_remote_post()`.
3. Store resulting folder IDs in the `_sp_folder_id` meta.
4. Register Microsoft Graph webhooks to receive delta notifications and call `$manager->pull_remote_changes()` when they fire.
