# BloatBuster – Performance & Asset Cleaner

Streamline your site's performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features below to keep your frontend fast, light, and clean.

## Features

Every option is off by default.

### Assets

* Disable Gutenberg block styles on the frontend
* Disable classic theme styles on the frontend
* Disable global styles on the frontend (classic themes only)
* Disable Dashicons on the frontend when the toolbar is hidden
* Disable jQuery Migrate on the frontend
* Disable emoji scripts and styles

### WordPress Core

* Disable oEmbed discovery and embed script (external embeds keep working)
* Disable comments site-wide
* Keep comments in admin to edit or delete existing comments while comments are disabled
* Disable self-pingbacks
* Limit post revisions to a configurable number
* Remove unnecessary head tags
* Disable XML-RPC
* Disable core XML sitemaps
* Disable user enumeration

### Database

* Database statistics: size and cleanup preview, with a full table list under Optimize tables
* Manually optimize selected tables with free space, at most 5 per run, with per-table results
* Cleanup preview: stored and eligible counts for each cleanup
* Clean expired transients, including timeouts left without a value
* Delete old revisions, keeping the newest ones per post
* Delete old auto-drafts
* Delete spam and trashed comments
* Delete trashed posts of all post types, trashed more than N days ago (default 7, 0 for all trash, at most 100 per run)
* Delete orphaned metadata: post, comment, term and user meta of objects that no longer exist (object ID 0 is kept, at most 1000 rows per type per run)
* Plugin temporary data through each plugin's own rules: old completed and canceled Action Scheduler actions, expired WooCommerce sessions. Other plugins can add their own with `bloatbuster_cleanup_integrations`
* Recent cleanups log (last 10 runs, filter `bloatbuster_cleanup_log_size`)

### Autoloaded Options

* Summary, browse, search, filter and sort options, with the likely plugin or theme owner
* Transients and their timeout rows are hidden by default; use "Include transients" to show them
* Autoload summary totals include hidden transients
* Enable or disable autoload (single and bulk), delete
* View values with secrets masked
* Export all persistent options or selected persistent options and restore from backup
* Core options are protected

To show transient values and timeout rows, check **Include transients** and click **Apply filters**. This works with or without a search term and applies across views, sorting and pagination. List counts follow the selected filters; summary cards always include all autoloaded options, including hidden transients. Showing or hiding transients does not change or delete options. Autoload cannot be enabled for ordinary transients with timeout rows, because WordPress would skip their expiration checks. They can still be viewed, deleted or have autoload disabled. Ordinary and site transient timeout rows cannot be deleted while their values exist. To delete a value and its timeout together, select both rows and use the Delete bulk action; if the value cannot be deleted, its timeout is kept.

Use "Export all options" to download every persistent option for the current site, including protected options, regardless of table filters or selection. Use "Export selected" in bulk actions for selected persistent options. Both exclude transient values and timeout rows; imports skip them too, including entries in older backups. Exports include actual sensitive values even when masked in the table, so store files securely. Backups larger than the 10 MB import limit are rejected; export smaller selections instead.

### Background Processes

* WP-Cron status checker with overdue events and next scheduled events
* Disable the Heartbeat API on the frontend
* Control Heartbeat API interval for the frontend, dashboard and post editor
* Control the post editor autosave interval

### Media

* Disable unneeded image sizes for new uploads
* Control large image scaling (big image size threshold)

### Advanced

* Control speculative loading mode and eagerness
* Disable capital_P_dangit
* Delete plugin data on uninstall
* Import, export and reset settings, to copy a setup to another site

## Multisite

* Settings are per site. Each site has its own settings page, configured by that site's administrator. There are no network-wide settings yet.
* Network activation works, every site starts with all options off.
* In the Autoloaded Options tab, only network administrators can view values, export, change autoload, delete or restore options. Site administrators can browse option names and sizes.
* Network transients (stored in the sitemeta table) are listed and cleaned only on the main site.
* Orphaned user meta is shared by the network, so it is listed and cleaned only on the main site by a network administrator.
* Database statistics on the main site only count the main site's tables.
* Optimizing global tables on multisite requires a network administrator. Subsites only list their own tables.
* Uninstall removes the plugin data on every site that opted in.

## Frequently Asked Questions

### Can I copy my settings to another site?

Yes. In the Advanced tab, click **Export settings** on the first site and upload the file with **Import** on the other site. A preview lists the settings that will change before you apply them. The file holds only BloatBuster settings, no site data. "Delete plugin data on uninstall" is not exported or imported. Settings not available on the other site, like block theme limits, are skipped.

### Does optimizing tables make my site faster?

Speed gains are not guaranteed. Optimization can reclaim unused space after large deletions. Only InnoDB, MyISAM and Aria tables reporting free space can be selected; shared or unverified InnoDB tablespace free space is unknown and cannot be selected. Verifying InnoDB storage requires access to its database metadata (PROCESS privilege). InnoDB tables are rebuilt, which can lock them and needs temporary free disk space about the size of the table. Make a database backup and run during low traffic. At most 5 selected tables run at a time; select any remaining tables and run again. Results show once. Estimated reclaimed space includes unused InnoDB storage and may differ from actual disk usage; reported sizes may update later.

## WP-CLI

There are no BloatBuster commands yet. All settings are stored in one array option, so they can be scripted with core WP-CLI commands:

```bash
# Show all settings.
wp option get bloatbuster_performance_asset_cleaner_options --format=json

# Turn a setting on (insert adds a new key, update changes an existing one).
wp option patch insert bloatbuster_performance_asset_cleaner_options disable_emojis 1
wp option patch update bloatbuster_performance_asset_cleaner_options post_revisions_limit 3

# Turn a setting off.
wp option patch delete bloatbuster_performance_asset_cleaner_options disable_emojis
```

* Setting IDs are the `id` keys in the tab classes in `includes/settings/tabs/`.
* Values set this way skip the settings page validation, so use the same values the settings page saves (`1` for toggles, numbers within the field limits).
* On multisite, add `--url=<site URL>` to target a site.

## Developers

### Settings structure

* `includes/class-plugin-options.php` – `Plugin_Options`, the settings page controller (menu, tabs, saving, page render).
* `includes/settings/class-fields.php` – `Fields`, renders and sanitizes fields by type.
* `includes/settings/tabs/` – one class per tab, each extends `Settings_Tab` (`class-settings-tab.php`).

All values are stored in one option array (`bloatbuster_performance_asset_cleaner_options`). Saving a tab only updates that tab's fields, so the other tabs keep their values.

### Adding a tab in core

1. Create `includes/settings/tabs/class-tab-{slug}.php` with a class that extends `Settings_Tab`. Use static methods only.
2. Implement `get_slug()`, `get_label()` and `get_fields()`. You can also override `get_description()`, `get_priority()`, `get_form_type()`, `get_form_action()`, `add_notices()` and `render_content()`.
3. Require the file in the main plugin file, inside the `is_admin()` block.
4. Add the class to `Plugin_Options::get_tab_classes()`.

Current tab priorities: Assets 10, WordPress Core 20, Database 30, Autoloaded Options 35, Background Processes 40, Media 45, Advanced 50.

Form types from `get_form_type()`: `'options'` (fields saved through `options.php`), `'admin-post'` (form sent to `admin-post.php` with `get_form_action()`), `'custom'` (no fields form, the tab prints its own content in `render_content()`, like Autoloaded Options).

Fields don't need a `tab` key. It's set from `get_slug()`. Tabs without fields are hidden.

### Field keys

| Key | Used by | Description |
| --- | --- | --- |
| `type` | all | `toggle`, `checkbox`, `checkbox_list`, `select`, `number`, `action`, `report`, or a custom type. Required. |
| `id` | all | Option key. Required. |
| `label` | all | Row title. |
| `description` | all | Help text below the field. |
| `note` | all | Extra help text below the description. |
| `parent` | all | ID of a toggle. The field only shows while that toggle is on. |
| `options` | `select`, `checkbox_list` | Array of `value => label`. `checkbox_list` saves an array of the checked values. |
| `min`, `max`, `step`, `default` | `number` | Input limits and the default value. |
| `button` | `action` | Submit button label. |
| `confirm` | `action` | Confirmation message shown before the action runs. |
| `render_callback` | `action`, `report` | Callable that prints the content. For `action`, returning `false` disables the button. |
| `locked`, `locked_message` | all | Disables the field and shows the message (HTML, run through `wp_kses_post()`). |

`action` and `report` fields are not saved.

### Hooks

| Hook | Type | Arguments | Use |
| --- | --- | --- | --- |
| `bloatbuster_settings_tabs` | filter | `$classes` | Add tab class names. Classes that don't extend `Settings_Tab` are ignored. |
| `bloatbuster_settings_fields` | filter | `$fields` | Add or change fields in any tab. Set the `tab` key. |
| `bloatbuster_render_field_{type}` | action | `$args, $value, $option_name` | Render a custom field type. |
| `bloatbuster_sanitize_field_{type}` | filter | `$value, $raw, $field` | Sanitize a custom field type. Return `null` to remove the stored value. |
| `bloatbuster_settings_page_notices` | action | `$current_tab` | Add notices with `add_settings_error( 'messages', ... )`. |
| `bloatbuster_before_tab_content` | action | `$current_tab` | Print content before the tab form. |
| `bloatbuster_after_tab_content` | action | `$current_tab` | Print content after the tab form. |
| `bloatbuster_settings_transfer_excluded` | filter | `$excluded` | Field IDs left out of settings export, import and reset. Default `delete_data_on_uninstall`. |
| `bloatbuster_autoload_protected_options` | filter | `$protected` | Option names that can't have autoload changed or be deleted. |
| `bloatbuster_autoload_sensitive_patterns` | filter | `$patterns` | Name parts that mask an option value in the value viewer. |
| `bloatbuster_autoload_known_prefixes` | filter | `$known` | Option prefix => plugin file, for owner detection. |
| `bloatbuster_autoload_large_threshold` | filter | `$threshold` | Size in bytes from which an autoloaded option needs review. Default 10 KB. |
| `bloatbuster_autoload_per_page` | filter | `$per_page` | Options per page in the Autoloaded Options table. Default 50. |
| `bloatbuster_cleanup_integrations` | filter | `$integrations` | Add plugin data cleanups to the Database tab. See below. |
| `bloatbuster_cleanup_log_size` | filter | `$size` | Number of runs kept in the Recent cleanups log. Default 10. |

### Plugin data cleanups

Each entry needs `id`, `label`, `description` and three callables: `is_available` (returns bool), `count` (returns int) and `delete` (deletes one batch and returns `array( 'deleted' => int, 'remaining' => int )`). Only entries whose `is_available` returns true are shown. Use the plugin's own API, never delete by name patterns.

```php
\add_filter( 'bloatbuster_cleanup_integrations', array( My_Cleanup::class, 'register' ) );

public static function register( $integrations ) {
	$integrations[] = array(
		'id'           => 'my_plugin_logs',
		'label'        => \__( 'My Plugin logs', 'my-plugin' ),
		'description'  => \__( 'Logs older than 30 days.', 'my-plugin' ),
		'is_available' => array( My_Cleanup::class, 'is_active' ),
		'count'        => array( My_Cleanup::class, 'count_old_logs' ),
		'delete'       => array( My_Cleanup::class, 'delete_old_logs' ),
	);

	return $integrations;
}
```

### Extending from an addon

```php
namespace BloatBuster_Pro;

final class Settings {

	public static function hooks() {
		if ( ! \is_admin() ) {
			return;
		}

		\add_filter( 'bloatbuster_settings_tabs', array( __CLASS__, 'add_tabs' ) );
		\add_filter( 'bloatbuster_settings_fields', array( __CLASS__, 'add_core_fields' ) );
		\add_action( 'bloatbuster_render_field_text', array( __CLASS__, 'render_text_field' ), 10, 3 );
		\add_filter( 'bloatbuster_sanitize_field_text', array( __CLASS__, 'sanitize_text_field' ), 10, 3 );
	}

	public static function add_tabs( $classes ) {
		// Settings_Tab is only loaded in the admin, so require the tab class here.
		require_once __DIR__ . '/class-tab-pro.php';

		$classes[] = Tab_Pro::class;

		return $classes;
	}

	public static function add_core_fields( $fields ) {
		$fields[] = array(
			'tab'         => 'core',
			'type'        => 'toggle',
			'id'          => 'pro_disable_feeds',
			'label'       => \__( 'Disable RSS feeds', 'bloatbuster-pro' ),
			'description' => \__( 'Remove all RSS feeds.', 'bloatbuster-pro' ),
		);

		return $fields;
	}

	public static function render_text_field( $args, $value, $option_name ) {
		echo '<input type="text" class="regular-text" name="' . \esc_attr( $option_name ) . '" value="' . \esc_attr( $value ) . '">';
	}

	public static function sanitize_text_field( $value, $raw, $field ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return null;
		}

		return \sanitize_text_field( $raw );
	}
}

\add_action( 'plugins_loaded', array( Settings::class, 'hooks' ) );
```

`class-tab-pro.php`:

```php
namespace BloatBuster_Pro;

final class Tab_Pro extends \BloatBuster\Settings_Tab {

	public static function get_slug() {
		return 'pro';
	}

	public static function get_label() {
		return \__( 'Pro', 'bloatbuster-pro' );
	}

	public static function get_priority() {
		return 45;
	}

	public static function get_fields() {
		return array(
			array(
				'type'  => 'text',
				'id'    => 'pro_license_key',
				'label' => \__( 'License key', 'bloatbuster-pro' ),
			),
		);
	}
}
```

### Notes

* Tabs and fields are cached once per request. Register filters before `admin_init`, e.g. on `plugins_loaded`.
* Tabs that return `'admin-post'` from `get_form_type()` (like Database) submit to `admin-post.php`, so their fields are not saved through `options.php`.
* Addon fields are stored in the same option array. Prefix their IDs (e.g. `pro_`) to avoid conflicts.
