<?php
/**
 * Admin assets.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueues admin assets on the plugin settings page only.
 */
final class Admin_Assets {

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue admin styles and scripts on the plugin settings page.
	 *
	 * @param string $hook_suffix - Current admin page hook suffix.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . BLOATBUSTER_OPTION !== $hook_suffix ) {
			return;
		}

		\wp_enqueue_style(
			'bloatbuster-performance-asset-cleaner-admin',
			BLOATBUSTER_URL . 'assets/admin.css',
			array(),
			BLOATBUSTER_VERSION
		);

		\wp_enqueue_script(
			'bloatbuster-performance-asset-cleaner-admin',
			BLOATBUSTER_URL . 'assets/admin.js',
			array(),
			BLOATBUSTER_VERSION,
			true
		);
	}
}
