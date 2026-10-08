<?php
/**
 * Handle plugin's install actions.
 *
 * @class       Install
 * @version     1.0.0
 * @package     BloatBuster_Performance_Asset_Cleaner/Classes/
 */

namespace BloatBuster_Performance_Asset_Cleaner;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Install class
 */
final class Install {

	/**
	 * Install action.
	 */
	public static function install( $sitewide = false ) {

		// Perform install actions here.

		// Trigger action.
		do_action( 'bloatbuster_performance_asset_cleaner_installed', $sitewide );
	}
}
