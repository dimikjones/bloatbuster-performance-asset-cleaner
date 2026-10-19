<?php
/**
 * Media settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Image sizes and large image scaling options.
 */
final class Tab_Media extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'media';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Media', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Control which image files WordPress creates for every upload. These settings only apply to new uploads, existing files are not changed.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 45;
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
				'type'        => 'checkbox_list',
				'id'          => 'disabled_image_sizes',
				'label'       => \__( 'Disable image sizes', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'WordPress creates a file for every image size on upload. Checked sizes are no longer created. The thumbnail size is used by the Media Library grid, and the 1536x1536 and 2048x2048 sizes are used for sharp images on large screens.', 'bloatbuster-performance-asset-cleaner' ),
				'options'     => self::get_image_size_options(),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'control_big_image_threshold',
				'label'       => \__( 'Control large image scaling', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'WordPress scales down uploaded images larger than 2560 pixels and keeps the original file. Set a different limit, or turn scaling off.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'number',
				'id'          => 'big_image_threshold',
				'parent'      => 'control_big_image_threshold',
				'label'       => \__( 'Maximum image size', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Largest width or height in pixels. 0 turns scaling off and keeps the uploaded size.', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 0,
				'max'         => 10000,
				'step'        => 1,
				'default'     => 2560,
			),
		);
	}

	/**
	 * Get the registered image sizes as checkbox options.
	 *
	 * @return array - Size name => label with dimensions.
	 *
	 * @since 1.0.0
	 */
	public static function get_image_size_options() {
		$options = array();

		foreach ( \wp_get_registered_image_subsizes() as $name => $size ) {
			if ( ! empty( $size['crop'] ) ) {
				/* translators: 1: image size name, 2: width in pixels, 3: height in pixels. */
				$label = \__( '%1$s (%2$d × %3$d, cropped)', 'bloatbuster-performance-asset-cleaner' );
			} else {
				/* translators: 1: image size name, 2: width in pixels, 3: height in pixels. */
				$label = \__( '%1$s (%2$d × %3$d)', 'bloatbuster-performance-asset-cleaner' );
			}

			$options[ $name ] = sprintf( $label, $name, (int) $size['width'], (int) $size['height'] );
		}

		return $options;
	}
}
