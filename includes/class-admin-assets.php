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

		\wp_localize_script(
			'bloatbuster-performance-asset-cleaner-admin',
			'bbpacAdmin',
			array(
				'valueUrl' => \esc_url_raw( \rest_url( Autoload_Rest::REST_NAMESPACE . '/autoload/value' ) ),
				'nonce'    => \wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'confirmDelete'  => \__( 'Delete the selected options? This can not be undone. Export them first if you may need them again.', 'bloatbuster-performance-asset-cleaner' ),
					'confirmDisable' => \__( 'Disable autoload for the selected options? Their values are kept.', 'bloatbuster-performance-asset-cleaner' ),
					'confirmRow'     => \__( 'Delete this option? This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
					'loading'        => \__( 'Loading…', 'bloatbuster-performance-asset-cleaner' ),
					'loadError'      => \__( 'The value could not be loaded.', 'bloatbuster-performance-asset-cleaner' ),
					'masked'         => \__( 'This value may contain a secret, like an API key, so it is hidden.', 'bloatbuster-performance-asset-cleaner' ),
					'truncated'      => \__( 'The value is long, only the start is shown.', 'bloatbuster-performance-asset-cleaner' ),
					'copied'         => \__( 'Copied', 'bloatbuster-performance-asset-cleaner' ),
					'copy'           => \__( 'Copy', 'bloatbuster-performance-asset-cleaner' ),
				),
			)
		);
	}
}
