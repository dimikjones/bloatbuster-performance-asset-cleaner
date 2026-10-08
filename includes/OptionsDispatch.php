<?php
/**
 * Register admin assets.
 *
 * @class       OptionsDispatch
 * @version     1.0.0
 * @package     BloatBuster_Performance_Asset_Cleaner/Classes/
 */

namespace BloatBuster_Performance_Asset_Cleaner;

use BloatBuster_Performance_Asset_Cleaner\Admin\PluginOptions as Options;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin assets class
 */
final class OptionsDispatch {

	/**
	 * Hook in methods.
	 */
	public static function hooks() {
		add_filter( 'wp_enqueue_scripts', array( __CLASS__, 'disable_block_editor_styles_frontend' ), 100 );
		add_filter( 'wp_enqueue_scripts', array( __CLASS__, 'disable_heartbeat_frontend' ), 100 );
		add_filter( 'heartbeat_settings', array( __CLASS__, 'control_heartbeat_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'deregister_dashicons_non_admin' ) );
		add_action( 'init', array( __CLASS__, 'disable_emojis' ) );
		add_action( 'init', array( __CLASS__, 'disable_wp_oembed' ) );
		add_action( 'pre_ping', array( __CLASS__, 'disable_self_pingbacks' ) );
		add_filter( 'wp_revisions_to_keep', array( __CLASS__, 'limit_post_revisions' ) );
		add_action( 'init', array( __CLASS__, 'disable_capital_p_dangit' ) );
	}

	public static function get_option_value( $value ) {
		$plugin_options = get_option( 'bloatbuster_performance_asset_cleaner_options' );

		if ( ! empty( $plugin_options ) && ! empty( $plugin_options[ $value ] ) ) {
			return $plugin_options[ $value ];
		} else {
			return '';
		}
	}

	/**
	 * Dequeue block editor styles on frontend.
	 */
	public static function disable_block_editor_styles_frontend() {
		if ( Utils::is_request( 'frontend' ) ) {

			if ( self::get_option_value( 'disable_block_editor_styles_frontend' ) ) {
				// Removes core block styles.
				wp_dequeue_style( 'wp-block-library' );
				// Removes theme block styles.
				wp_dequeue_style( 'wp-block-library-theme' );
			}
		}
	}

	/**
	 * Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor.
	 */
	public static function disable_heartbeat_frontend() {
		if ( Utils::is_request( 'frontend' ) ) {

			if ( self::get_option_value( 'disable_heartbeat_frontend' ) ) {
				// Removes Heartbeat API script from loading.
				wp_deregister_script( 'heartbeat' );
			}
		}
	}

	/**
	 * Control the Heartbeat API execution based on user area.
	 *
	 * @param $settings
	 *
	 * @return mixed
	 */
	public static function control_heartbeat_settings( $settings ) {

		if ( self::get_option_value( 'control_heartbeat_settings' ) ) {
			if ( Utils::is_request( 'frontend' ) ) {
				// Slower execution for frontend.
				$settings['interval'] = 60;
			} else {
				// Faster execution in admin.
				$settings['interval'] = 30;
			}
		}

		return $settings;
	}

	/**
	 * Deregister Dashicons stylesheet on the frontend for non-admin users.
	 *
	 * This function checks if the current request is on the frontend and if the
	 * current user does not have the 'manage_options' capability (typically admins).
	 * If both conditions are met, it deregisters the 'dashicons' stylesheet.
	 */
	public static function deregister_dashicons_non_admin() {
		if ( self::get_option_value( 'disable_dashicons_non_admin' ) ) {
			// Check if we are on the frontend.
			// is_admin() returns true if in the admin area, false otherwise.
			if ( ! is_admin() ) {
				// Check if the current user does NOT have 'manage_options' capability.
				// Users with 'manage_options' are typically administrators.
				if ( ! current_user_can( 'manage_options' ) ) {
					// Deregister the 'dashicons' stylesheet.
					// This prevents it from being enqueued on the frontend for non-admin users.
					wp_deregister_style( 'dashicons' );
				}
			}
		}
	}

	/**
	 * Disable all WordPress emoji scripts and styles.
	 */
	public static function disable_emojis() {

		if ( self::get_option_value( 'disable_emojis' ) ) {
			// Remove emoji script from frontend and admin.
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

			// Remove emoji styles from frontend and admin.
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );

			// Prevent emojis from being injected in the RSS feed.
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

			// Remove TinyMCE emoji support (editor compatibility).
			add_filter( 'tiny_mce_plugins', array( __CLASS__, 'emojis_tinymce' ) );
			add_filter( 'wp_resource_hints', array( __CLASS__, 'emojis_remove_dns_prefetch' ), 10, 2 );
		}
	}

	/**
	 * Disable emojis tinymce.
	 */
	public static function emojis_tinymce( $plugins ) {
		// Bail if the plugins is not an array.
		if ( ! is_array( $plugins ) ) {
			return array();
		}

		// Remove the `wpemoji` plugin and return everything else.
		return array_diff( $plugins, array( 'wpemoji' ) );
	}

	/**
	 * Remove emoji CDN hostname from DNS prefetching hints.
	 *
	 * @param  array  $urls          URLs to print for resource hints.
	 * @param  string $relation_type The relation type the URLs are printed for.
	 * @return array                 Difference betwen the two arrays.
	 */
	public static function emojis_remove_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			/** This filter is documented in wp-includes/formatting.php */
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );

			$urls = array_diff( $urls, array( $emoji_svg_url ) );
		}

		return $urls;
	}

	/**
	 * Remove oEmbed discovery links from <head> (prevents unnecessary requests).
	 */
	public static function disable_wp_oembed() {
		if ( self::get_option_value( 'disable_wp_oembed' ) ) {

			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

			// Remove oEmbed-specific JavaScript that loads on the frontend.
			remove_action( 'wp_head', 'wp_oembed_add_host_js' );

			// Disable REST API oEmbed endpoints (prevents external sites from embedding WP content).
			remove_action( 'rest_api_init', 'wp_oembed_register_route' );

			// Remove oEmbed filtering from content processing (stops WP auto-converting URLs).
			remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
			remove_filter( 'oembed_response_data', 'get_oembed_response_data', 10 );

			// Disable automatic oEmbed URL conversion for posts/comments.
			remove_filter( 'the_content', [ $GLOBALS['wp_embed'], 'autoembed' ], 8 );
			remove_filter( 'widget_text_content', [ $GLOBALS['wp_embed'], 'autoembed' ], 8 );
		}
	}

	/**
	 * Disable self-pingbacks in WordPress to prevent unnecessary notifications.
	 *
	 * Pingbacks allow automatic notifications when linking to a post on another site.
	 * However, self-pingbacks occur when a site links to its own posts, cluttering comments.
	 * This function removes links from the ping process if they belong to the same domain.
	 */
	public static function disable_self_pingbacks( &$links ) {
		if ( self::get_option_value( 'disable_self_pingbacks' ) ) {
			// Get the site's base URL.
			$home_url = home_url();

			foreach ( $links as $key => $link ) {
				// Check if the link belongs to this site.
				if ( strpos( $link, $home_url ) === 0 ) {
					// Remove self-pingback link.
					unset( $links[ $key ] );
				}
			}
		}
	}

	/**
	 * Limit WordPress post revisions to 5.
	 *
	 * This reduces unnecessary database storage while retaining useful revisions for editing.
	 */
	public static function limit_post_revisions() {
		if ( self::get_option_value( 'limit_post_revisions' ) ) {
			// Set the max number of revisions.
			return 5;
		}
	}

	/**
	 * Disable the `capital_P_dangit` function in WordPress.
	 *
	 * This function is normally applied to content, titles, comments, and feeds.
	 * It prevents WordPress from auto-correcting "WordPress" to "WordPress."
	 */
	public static function disable_capital_p_dangit() {
		if ( self::get_option_value( 'disable_capital_p_dangit' ) ) {
			remove_filter( 'the_content', 'capital_P_dangit', 11 );
			remove_filter( 'the_title', 'capital_P_dangit', 11 );
			remove_filter( 'wp_title', 'capital_P_dangit', 11 );
			remove_filter( 'document_title', 'capital_P_dangit', 11 );
			remove_filter( 'widget_text_content', 'capital_P_dangit', 11 );
			remove_filter( 'comment_text', 'capital_P_dangit', 31 );
		}
	}
}
