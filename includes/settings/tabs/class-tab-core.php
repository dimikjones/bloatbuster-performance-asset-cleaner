<?php
/**
 * WordPress Core settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress core features options.
 */
final class Tab_Core extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'core';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'WordPress Core', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Turn off WordPress core features you do not use, like oEmbed, comments and pingbacks.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 20;
	}

	/**
	 * Get the tab fields.
	 *
	 * @return array - List of fields.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		$sitemaps_lock = Options_Dispatch::get_sitemaps_lock();

		return array(
			array(
				'type'        => 'toggle',
				'id'          => 'disable_wp_oembed',
				'label'       => \__( 'Disable oEmbed discovery and embed script', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes oEmbed discovery links, the wp-embed script and the oEmbed REST endpoint, so other sites can no longer embed your posts. Embeds from YouTube, Vimeo and other services in your content keep working.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_comments',
				'label'       => \__( 'Disable comments', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable comments, pingbacks and trackbacks across the entire site, including the admin area. Existing comments are hidden, not deleted.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_comments_keep_admin',
				'parent'      => 'disable_comments',
				'label'       => \__( 'Keep comments in admin', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Keep the Comments screen in the admin area so existing comments can be edited or deleted. Comments stay disabled on the front-end.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_self_pingbacks',
				'label'       => \__( 'Disable self-pingbacks', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Minor core cleanup: stops WordPress from sending pingbacks to its own posts.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'limit_post_revisions',
				'label'       => \__( 'Limit post revisions', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Keep only a set number of revisions per post. Overrides WP_POST_REVISIONS from wp-config.php while enabled.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'number',
				'id'          => 'post_revisions_limit',
				'parent'      => 'limit_post_revisions',
				'label'       => \__( 'Revisions to keep', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Number of revisions to keep per post. 0 disables revisions.', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 0,
				'max'         => 100,
				'step'        => 1,
				'default'     => 5,
			),
			array(
				'type'        => 'toggle',
				'id'          => 'remove_head_tags',
				'label'       => \__( 'Remove unnecessary head tags', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Remove the WordPress version, RSD, Windows Live Writer, shortlink, REST API and adjacent post links from the page head and HTTP headers.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_xmlrpc',
				'label'       => \__( 'Disable XML-RPC', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all XML-RPC methods, including pingbacks. Do not enable if you use Jetpack or the WordPress mobile app.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'           => 'toggle',
				'id'             => 'disable_wp_sitemaps',
				'label'          => \__( 'Disable core XML sitemaps', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Removes the WordPress sitemap at /wp-sitemap.xml. Locked when another plugin or the Reading settings already turn it off.', 'bloatbuster-performance-asset-cleaner' ),
				'locked'         => $sitemaps_lock,
				'locked_message' => self::get_sitemaps_locked_message( $sitemaps_lock ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_user_enumeration',
				'label'       => \__( 'Disable user enumeration', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Hide usernames from the REST API users endpoint, ?author= URLs, the users sitemap and oEmbed data for visitors who can not edit posts. Makes brute force login attacks harder. Author archive pages keep working.', 'bloatbuster-performance-asset-cleaner' ),
			),
		);
	}

	/**
	 * Get the message explaining why the core XML sitemaps field is locked.
	 *
	 * @param string $reason - Lock reason, 'private', 'yoast', 'rank-math', 'plugin' or empty.
	 *
	 * @return string - Message HTML, empty when not locked.
	 *
	 * @since 1.0.0
	 */
	public static function get_sitemaps_locked_message( $reason ) {
		if ( 'private' === $reason ) {
			return \esc_html__( 'Core XML sitemaps are off because search engines are discouraged in the Reading settings.', 'bloatbuster-performance-asset-cleaner' )
				. ' <a href="' . \esc_url( \admin_url( 'options-reading.php' ) ) . '">'
				. \esc_html__( 'Go to Reading settings', 'bloatbuster-performance-asset-cleaner' )
				. '</a>';
		}

		if ( 'yoast' === $reason ) {
			return \esc_html__( 'Core XML sitemaps are already turned off by Yoast SEO.', 'bloatbuster-performance-asset-cleaner' );
		}

		if ( 'rank-math' === $reason ) {
			return \esc_html__( 'Core XML sitemaps are already turned off by Rank Math.', 'bloatbuster-performance-asset-cleaner' );
		}

		if ( 'plugin' === $reason ) {
			return \esc_html__( 'Core XML sitemaps are already turned off by another plugin or theme.', 'bloatbuster-performance-asset-cleaner' );
		}

		return '';
	}
}
