<?php
/**
 * Base class for a settings page tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A settings tab defines its slug, label, description, order and fields.
 *
 * Extend this class and add the class name to the bloatbuster_settings_tabs
 * filter to register a new tab.
 */
abstract class Settings_Tab {

	/**
	 * Get the tab slug, used in the URL and as the field tab key.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	abstract public static function get_slug();

	/**
	 * Get the tab label shown in the tab navigation.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	abstract public static function get_label();

	/**
	 * Get the tab fields. The tab key is added automatically.
	 *
	 * @return array - List of fields with type, id, label and description.
	 *
	 * @since 1.0.0
	 */
	abstract public static function get_fields();

	/**
	 * Get the tab description shown above the fields.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return '';
	}

	/**
	 * Get the tab position in the navigation. Lower numbers come first.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 10;
	}

	/**
	 * Get how the tab form is submitted.
	 *
	 * 'options' saves fields through options.php, 'admin-post' sends the
	 * form to admin-post.php with the action from get_form_action(),
	 * 'custom' skips the fields form and calls render_content().
	 *
	 * @return string - Form type.
	 *
	 * @since 1.0.0
	 */
	public static function get_form_type() {
		return 'options';
	}

	/**
	 * Get the admin-post action and nonce action for 'admin-post' forms.
	 *
	 * @return string - Action name.
	 *
	 * @since 1.0.0
	 */
	public static function get_form_action() {
		return '';
	}

	/**
	 * Add notices shown on top of the tab, e.g. the result of an action.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function add_notices() {
	}

	/**
	 * Render the tab content for 'custom' form type tabs.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_content() {
	}
}
