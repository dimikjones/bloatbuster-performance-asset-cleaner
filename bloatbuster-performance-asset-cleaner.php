<?php
/**
 * Plugin Name:       BloatBuster – Performance & Asset Cleaner
 * Description:       Strip away WordPress bloat, cut HTTP requests and tune background API execution for a faster, lighter frontend.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Marko Dimitrijevic
 * Author URI:        https://www.linkedin.com/in/diwebdeveloper/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bloatbuster-performance-asset-cleaner
 * Domain Path:       /languages
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- CONSTANTS ---
define( 'BLOATBUSTER_VERSION', '1.0.0' );
define( 'BLOATBUSTER_FILE', __FILE__ );
define( 'BLOATBUSTER_DIR', __DIR__ );
define( 'BLOATBUSTER_URL', \plugin_dir_url( __FILE__ ) );
define( 'BLOATBUSTER_OPTION', 'bloatbuster_performance_asset_cleaner_options' );

// --- INCLUDES ---
// Options logic, required on both front-end and admin.
require_once BLOATBUSTER_DIR . '/includes/class-options-dispatch.php';

// Autoloaded options data and REST route, REST requests are not admin requests.
require_once BLOATBUSTER_DIR . '/includes/autoload/class-autoload-options.php';
require_once BLOATBUSTER_DIR . '/includes/autoload/class-autoload-rest.php';

Options_Dispatch::hooks();
Autoload_Rest::hooks();

// Settings page and its assets.
if ( \is_admin() ) {
	require_once BLOATBUSTER_DIR . '/includes/settings/class-fields.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-settings-tab.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-tab-assets.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-tab-core.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-tab-database.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-tab-autoload.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-tab-background.php';
	require_once BLOATBUSTER_DIR . '/includes/settings/tabs/class-tab-advanced.php';
	require_once BLOATBUSTER_DIR . '/includes/class-plugin-options.php';
	require_once BLOATBUSTER_DIR . '/includes/class-admin-assets.php';
	require_once BLOATBUSTER_DIR . '/includes/class-database-tools.php';
	require_once BLOATBUSTER_DIR . '/includes/autoload/class-autoload-owners.php';
	require_once BLOATBUSTER_DIR . '/includes/autoload/class-autoload-actions.php';

	Plugin_Options::hooks();
	Admin_Assets::hooks();
	Database_Tools::hooks();
	Autoload_Actions::hooks();
}
