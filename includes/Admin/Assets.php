<?php
/**
 * Register admin assets.
 *
 * @class       AdminAssets
 * @version     1.0.0
 * @package     BloatBuster_Performance_Asset_Cleaner/Classes/
 */

namespace BloatBuster_Performance_Asset_Cleaner\Admin;

use BloatBuster_Performance_Asset_Cleaner\Assets as AssetsMain;
use BloatBuster_Performance_Asset_Cleaner\Utils;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin assets class
 */
final class Assets {

	/**
	 * Hook in methods.
	 */
	public static function hooks() {
		add_filter( 'bloatbuster_performance_asset_cleaner_enqueue_styles', array( __CLASS__, 'add_styles' ), 9 );
		add_filter( 'bloatbuster_performance_asset_cleaner_enqueue_scripts', array( __CLASS__, 'add_scripts' ), 9 );
		add_action( 'admin_enqueue_scripts', array( AssetsMain::class, 'load_scripts' ) );
		add_action( 'admin_print_scripts', array( AssetsMain::class, 'localize_printed_scripts' ), 5 );
		add_action( 'admin_print_footer_scripts', array( AssetsMain::class, 'localize_printed_scripts' ), 5 );
	}


	/**
	 * Add styles for the admin.
	 *
	 * @param array $styles Admin styles.
	 * @return array<string,array>
	 */
	public static function add_styles( $styles ) {

		$styles['bloatbuster-performance-asset-cleaner-admin'] = array(
			'src' => AssetsMain::localize_asset( 'admin.css' ),
		);

		return $styles;
	}


	/**
	 * Add scripts for the admin.
	 *
	 * @param  array $scripts Admin scripts.
	 * @return array<string,array>
	 */
	public static function add_scripts( $scripts ) {

		$scripts['bloatbuster-performance-asset-cleaner-admin'] = array(
			'src'  => AssetsMain::localize_asset( 'admin.js' ),
			'data' => array(
				'ajax_url' => Utils::ajax_url(),
			),
		);

		return $scripts;
	}
}
