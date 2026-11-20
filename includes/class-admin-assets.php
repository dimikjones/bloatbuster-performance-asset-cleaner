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
				'valueUrl'     => \esc_url_raw( \rest_url( Autoload_Rest::REST_NAMESPACE . '/autoload/value' ) ),
				'nonce'        => \wp_create_nonce( 'wp_rest' ),
				'inspectNonce' => \wp_create_nonce( Asset_Inspector::NONCE_ACTION ),
				'homeUrl'      => \esc_url_raw( \home_url( '/' ) ),
				'tabUrl'       => \esc_url_raw( \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION . '&tab=' . Tab_Assets::get_slug() ) ),
				'scanArg'      => Asset_Inspector::SCAN_ARG,
				'resultArg'    => Asset_Inspector::RESULT_ARG,
				'i18n'         => array(
					'scanning'           => \__( 'Scanning the page…', 'bloatbuster-performance-asset-cleaner' ),
					'invalidUrl'         => \__( 'Enter a page URL of this site.', 'bloatbuster-performance-asset-cleaner' ),
					'scanLabel'          => \__( 'Scan page', 'bloatbuster-performance-asset-cleaner' ),
					'unsavedTitle'       => \__( 'Scan without saving?', 'bloatbuster-performance-asset-cleaner' ),
					'unsavedMessage'     => \__( 'The page reloads after the scan and your unsaved changes on this tab are lost.', 'bloatbuster-performance-asset-cleaner' ),
					/* translators: 1: number of shown assets, 2: total number of assets. */
					'shownCount'         => \__( '%1$d of %2$d shown', 'bloatbuster-performance-asset-cleaner' ),
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
