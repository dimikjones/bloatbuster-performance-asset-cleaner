# BloatBuster – Performance & Asset Cleaner

Streamline your site's performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features below to keep your frontend fast, light, and clean.

## Features

* Disable Gutenberg block styles on the frontend
* Disable classic theme styles on the frontend
* Disable global styles on the frontend (classic themes only)
* Disable the Heartbeat API on the frontend
* Control Heartbeat API interval for the frontend, dashboard and post editor
* Disable Dashicons on the frontend when the toolbar is hidden
* Disable jQuery Migrate on the frontend
* Disable emoji scripts and styles
* Disable oEmbed discovery and embed script (external embeds keep working)
* Disable self-pingbacks
* Limit post revisions to a configurable number
* Disable capital_P_dangit
* Remove unnecessary head tags
* Disable XML-RPC
* Disable core XML sitemaps
* WP-Cron status checker with overdue events and next scheduled events
* Control the post editor autosave interval
* Disable unneeded image sizes for new uploads
* Control large image scaling (big image size threshold)
* Disable user enumeration
* Control speculative loading mode and eagerness
* Manually clean expired transients
* Delete old revisions, keeping the newest ones per post
* Delete old auto-drafts
* Database statistics: size, largest tables with free space, revisions, auto-drafts, trash and spam counts
* Autoloaded options manager: summary, browse, search, filter and sort all options, likely plugin or theme owner,
  enable or disable autoload (single and bulk), delete, view values with secrets masked, export and restore from backup
* Disable comments site-wide

## Multisite

* Settings are per site. Each site has its own settings page, configured by that site's administrator. There are no network-wide settings yet.
* Network activation works, every site starts with all options off.
* In the Autoloaded Options tab, only network administrators can change autoload, delete or restore options. Site administrators can browse, view and export them.
* Network transients (stored in the sitemeta table) are listed and cleaned only on the main site.
* Database statistics on the main site only count the main site's tables.
* Uninstall removes the plugin data on every site that opted in.

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
| `bloatbuster_autoload_protected_options` | filter | `$protected` | Option names that can't have autoload changed or be deleted. |
| `bloatbuster_autoload_sensitive_patterns` | filter | `$patterns` | Name parts that mask an option value in the value viewer. |
| `bloatbuster_autoload_known_prefixes` | filter | `$known` | Option prefix => plugin file, for owner detection. |
| `bloatbuster_autoload_large_threshold` | filter | `$threshold` | Size in bytes from which an autoloaded option needs review. Default 10 KB. |
| `bloatbuster_autoload_per_page` | filter | `$per_page` | Options per page in the Autoloaded Options table. Default 50. |

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
