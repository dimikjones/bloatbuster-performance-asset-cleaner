<?php
/**
 * Background Processes settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Heartbeat API and other background request options.
 */
final class Tab_Background extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'background';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Background Processes', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Control how often WordPress runs background requests like the Heartbeat API.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 40;
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
				'id'          => 'disable_heartbeat_frontend',
				'label'       => \__( 'Disable Heartbeat API on the frontend', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor. Some frontend plugins (live chat, forms, membership) may rely on Heartbeat.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'control_heartbeat_settings',
				'label'       => \__( 'Control the Heartbeat API interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Set how often the Heartbeat API runs on the frontend, in the dashboard and in the post editor.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'number',
				'id'          => 'heartbeat_interval_frontend',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Frontend interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests on the frontend (15-120).', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 60,
			),
			array(
				'type'        => 'number',
				'id'          => 'heartbeat_interval_dashboard',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Dashboard interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests in the admin area, except the post editor (15-120).', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 60,
			),
			array(
				'type'        => 'number',
				'id'          => 'heartbeat_interval_editor',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Post editor interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests in the post editor, used for autosave and post locking (15-120).', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 30,
			),
		);
	}
}
