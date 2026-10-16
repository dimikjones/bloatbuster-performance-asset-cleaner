<?php
/**
 * Assets settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend styles and scripts options.
 */
final class Tab_Assets extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'assets';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Assets', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Remove styles and scripts WordPress loads on the frontend that your site may not need.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 10;
	}

	/**
	 * Get the tab fields.
	 *
	 * @return array - List of fields.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		return array(
			array(
				'type'        => 'toggle',
				'id'          => 'disable_block_editor_styles_frontend',
				'label'       => \__( 'Disable Gutenberg block styles', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes the block library CSS (wp-block-library) on the frontend. Only enable if your theme does not use blocks.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_emojis',
				'label'       => \__( 'Disable emoji scripts and styles', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all WordPress emoji scripts and styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_dashicons_non_admin',
				'label'       => \__( 'Disable Dashicons on the frontend when the toolbar is hidden', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes the Dashicons stylesheet for logged-out visitors and users who do not see the admin toolbar. Users with the toolbar keep it, so the toolbar never breaks.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_jquery_migrate',
				'label'       => \__( 'Disable jQuery Migrate', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Stop loading jQuery Migrate on the frontend. It stays active in the admin area.', 'bloatbuster-performance-asset-cleaner' ),
			),
		);
	}
}
