<?php
/**
 * Advanced settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Speculative loading and plugin behavior options.
 */
final class Tab_Advanced extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'advanced';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Advanced', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Plugin behavior settings.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 50;
	}

	/**
	 * Get the tab fields.
	 *
	 * @return array - List of fields.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		$lock           = Options_Dispatch::get_speculative_loading_lock();
		$locked_message = self::get_speculative_loading_locked_message( $lock );

		return array(
			array(
				'type'           => 'select',
				'id'             => 'speculative_loading_mode',
				'label'          => \__( 'Speculative loading mode', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Prerender loads pages faster than prefetch, but prefetch is safer for interactive content. Disabled turns speculative loading off completely.', 'bloatbuster-performance-asset-cleaner' ),
				'options'        => array(
					''          => \__( 'WordPress default', 'bloatbuster-performance-asset-cleaner' ),
					'disabled'  => \__( 'Disabled', 'bloatbuster-performance-asset-cleaner' ),
					'prefetch'  => \__( 'Prefetch', 'bloatbuster-performance-asset-cleaner' ),
					'prerender' => \__( 'Prerender', 'bloatbuster-performance-asset-cleaner' ),
				),
				'locked'         => $lock,
				'locked_message' => $locked_message,
			),
			array(
				'type'           => 'select',
				'id'             => 'speculative_loading_eagerness',
				'label'          => \__( 'Speculative loading eagerness', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'When pages start loading in the background. Eager starts the soonest, Conservative loads only pages the user is about to open.', 'bloatbuster-performance-asset-cleaner' ),
				'options'        => array(
					''             => \__( 'WordPress default', 'bloatbuster-performance-asset-cleaner' ),
					'conservative' => \__( 'Conservative (typically on click)', 'bloatbuster-performance-asset-cleaner' ),
					'moderate'     => \__( 'Moderate (typically on hover)', 'bloatbuster-performance-asset-cleaner' ),
					'eager'        => \__( 'Eager (on slightest suggestion)', 'bloatbuster-performance-asset-cleaner' ),
				),
				'locked'         => $lock,
				'locked_message' => $locked_message,
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_capital_p_dangit',
				'label'       => \__( 'Disable capital_P_dangit', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Stops WordPress from changing "Wordpress" to "WordPress" in content. Negligible performance impact.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'checkbox',
				'id'          => 'delete_data_on_uninstall',
				'label'       => \__( 'Delete plugin data on uninstall', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Remove all BloatBuster settings from the database when the plugin is deleted.', 'bloatbuster-performance-asset-cleaner' ),
			),
		);
	}

	/**
	 * Get the message explaining why the speculative loading fields are locked.
	 *
	 * @param string $reason - Lock reason, 'plugin', 'core' or empty.
	 *
	 * @return string - Message HTML, empty when not locked.
	 *
	 * @since 1.0.0
	 */
	public static function get_speculative_loading_locked_message( $reason ) {
		if ( 'plugin' === $reason ) {
			return \esc_html__( 'Speculative loading is already configured by the Speculative Loading plugin.', 'bloatbuster-performance-asset-cleaner' )
				. ' <a href="' . \esc_url( \admin_url( 'options-reading.php#speculative-loading' ) ) . '">'
				. \esc_html__( 'Go to its settings', 'bloatbuster-performance-asset-cleaner' )
				. '</a>';
		}

		if ( 'core' === $reason ) {
			return \esc_html__( 'Speculative loading requires WordPress 6.8 or newer.', 'bloatbuster-performance-asset-cleaner' );
		}

		return '';
	}
}
