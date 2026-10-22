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
					'deleteLabel'        => \__( 'Delete', 'bloatbuster-performance-asset-cleaner' ),
					'disableLabel'       => \__( 'Disable autoload', 'bloatbuster-performance-asset-cleaner' ),
					/* translators: %d: number of selected options. */
					'selectedCount'      => \__( 'Selected options: %d.', 'bloatbuster-performance-asset-cleaner' ),
					'bulkDeleteTitle'    => \__( 'Delete the selected options?', 'bloatbuster-performance-asset-cleaner' ),
					'bulkDeleteMessage'  => \__( 'This can not be undone. Export them first if you may need them again.', 'bloatbuster-performance-asset-cleaner' ),
					'bulkDisableTitle'   => \__( 'Disable autoload for the selected options?', 'bloatbuster-performance-asset-cleaner' ),
					'bulkDisableMessage' => \__( 'Their values are kept. WordPress loads them only when they are needed.', 'bloatbuster-performance-asset-cleaner' ),
					'rowDeleteTitle'     => \__( 'Delete this option?', 'bloatbuster-performance-asset-cleaner' ),
					/* translators: %s: option name. */
					'rowDeleteMessage'   => \__( '%s will be deleted. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
					'loading'            => \__( 'Loading…', 'bloatbuster-performance-asset-cleaner' ),
					'loadError'          => \__( 'The value could not be loaded.', 'bloatbuster-performance-asset-cleaner' ),
					'masked'             => \__( 'This value may contain a secret, like an API key, so it is hidden.', 'bloatbuster-performance-asset-cleaner' ),
					'truncated'          => \__( 'The value is long, only the start is shown.', 'bloatbuster-performance-asset-cleaner' ),
					'binary'             => \__( 'This value has bytes that are not valid text. They are shown as � here, the stored value is not changed. Use Export to get the exact value.', 'bloatbuster-performance-asset-cleaner' ),
					'copied'             => \__( 'Copied', 'bloatbuster-performance-asset-cleaner' ),
					'copy'               => \__( 'Copy', 'bloatbuster-performance-asset-cleaner' ),
				),
			)
		);
	}
}
