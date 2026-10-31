<?php
/**
 * Applies the enabled plugin options.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks plugin options into WordPress.
 */
final class Options_Dispatch {

	/**
	 * True when AUTOSAVE_INTERVAL was set before this plugin could set it, e.g. in wp-config.php.
	 *
	 * @var bool
	 */
	private static $autosave_interval_external = false;

	/**
	 * Hook in methods.
	 */
	public static function hooks() {
		// Core defines AUTOSAVE_INTERVAL right after plugins_loaded, see wp_functionality_constants().
		\add_action( 'plugins_loaded', array( __CLASS__, 'define_autosave_interval' ), PHP_INT_MAX );
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_block_editor_styles_frontend' ), 100 );
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_theme_styles' ), 100 );
		\add_filter( 'intermediate_image_sizes_advanced', array( __CLASS__, 'disable_image_sizes' ) );
		\add_filter( 'big_image_size_threshold', array( __CLASS__, 'control_big_image_threshold' ) );
		\add_filter( 'wp_sitemaps_enabled', array( __CLASS__, 'disable_wp_sitemaps' ) );
		\add_action( 'wp_footer', array( __CLASS__, 'disable_global_styles_footer' ), 2 );
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'disable_heartbeat_frontend' ), 100 );
		\add_filter( 'heartbeat_settings', array( __CLASS__, 'control_heartbeat_settings' ) );
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'deregister_dashicons_non_admin' ) );
		\add_action( 'init', array( __CLASS__, 'disable_emojis' ) );
		\add_action( 'init', array( __CLASS__, 'disable_wp_oembed' ) );
		\add_action( 'pre_ping', array( __CLASS__, 'disable_self_pingbacks' ) );
		\add_filter( 'wp_revisions_to_keep', array( __CLASS__, 'limit_post_revisions' ) );
		\add_action( 'init', array( __CLASS__, 'disable_capital_p_dangit' ) );
		\add_action( 'init', array( __CLASS__, 'disable_comments' ) );
		\add_action( 'wp_default_scripts', array( __CLASS__, 'disable_jquery_migrate' ) );
		\add_action( 'init', array( __CLASS__, 'remove_head_tags' ) );
		\add_action( 'init', array( __CLASS__, 'disable_xmlrpc' ) );
		\add_action( 'init', array( __CLASS__, 'disable_user_enumeration' ) );
		\add_filter( 'wp_speculation_rules_configuration', array( __CLASS__, 'control_speculative_loading' ), 20 );
	}

	/**
	 * Check if the current request is a front-end request.
	 *
	 * @return bool - True on front-end (including AJAX), false in admin or cron.
	 *
	 * @since 1.0.0
	 */
	public static function is_frontend() {
		$is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$is_cron = defined( 'DOING_CRON' ) && DOING_CRON;

		return ( ! \is_admin() || $is_ajax ) && ! $is_cron;
	}

	/**
	 * Get a single plugin option value.
	 *
	 * @param string $value    - Option key.
	 * @param mixed  $fallback - Value returned when the option is not set.
	 *
	 * @return mixed - Option value or fallback if not set.
	 *
	 * @since 1.0.0
	 */
	public static function get_option_value( $value, $fallback = '' ) {
		$plugin_options = \get_option( BLOATBUSTER_OPTION );

		// isset() so a saved 0, like zero revisions, is not replaced by the fallback.
		if ( is_array( $plugin_options ) && isset( $plugin_options[ $value ] ) && '' !== $plugin_options[ $value ] ) {
			return $plugin_options[ $value ];
		}

		return $fallback;
	}

	/**
	 * Dequeue block editor styles on frontend.
	 */
	public static function disable_block_editor_styles_frontend() {
		// Never on block themes, they are built from blocks. Also covers a value saved before a theme switch.
		if ( self::is_frontend() && ! \wp_is_block_theme() ) {

			if ( self::get_option_value( 'disable_block_editor_styles_frontend' ) ) {
				// Removes core block styles.
				\wp_dequeue_style( 'wp-block-library' );
				// Removes theme block styles.
				\wp_dequeue_style( 'wp-block-library-theme' );
			}
		}
	}

	/**
	 * Define the AUTOSAVE_INTERVAL constant from the plugin option, before WordPress sets its default.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function define_autosave_interval() {
		if ( defined( 'AUTOSAVE_INTERVAL' ) ) {
			self::$autosave_interval_external = true;
			return;
		}

		if ( ! self::get_option_value( 'control_autosave_interval' ) ) {
			return;
		}

		$interval = (int) self::get_option_value( 'autosave_interval', 60 );

		define( 'AUTOSAVE_INTERVAL', min( 600, max( 10, $interval ) ) );
	}

	/**
	 * Check if AUTOSAVE_INTERVAL was set outside this plugin, so the option can't change it.
	 *
	 * @return bool - True if set elsewhere, e.g. in wp-config.php.
	 *
	 * @since 1.0.0
	 */
	public static function is_autosave_interval_external() {
		return self::$autosave_interval_external;
	}

	/**
	 * Remove the disabled image sizes from the sizes created on upload.
	 *
	 * @param array $sizes - Image sizes to create, keyed by size name.
	 *
	 * @return array - Image sizes without the disabled ones.
	 *
	 * @since 1.0.0
	 */
	public static function disable_image_sizes( $sizes ) {
		$disabled = self::get_option_value( 'disabled_image_sizes', array() );

		if ( empty( $disabled ) || ! is_array( $disabled ) || ! is_array( $sizes ) ) {
			return $sizes;
		}

		foreach ( $disabled as $size_name ) {
			unset( $sizes[ $size_name ] );
		}

		return $sizes;
	}

	/**
	 * Set the size from which uploaded images are scaled down.
	 *
	 * @param int|false $threshold - Size in pixels, or false to turn scaling off.
	 *
	 * @return int|false - Size in pixels, or false when set to 0.
	 *
	 * @since 1.0.0
	 */
	public static function control_big_image_threshold( $threshold ) {
		if ( ! self::get_option_value( 'control_big_image_threshold' ) ) {
			return $threshold;
		}

		$size = (int) self::get_option_value( 'big_image_threshold', 2560 );

		if ( 0 === $size ) {
			return false;
		}

		return $size;
	}

	/**
	 * Turn off the core XML sitemaps.
	 *
	 * @param bool $is_enabled - Whether sitemaps are enabled.
	 *
	 * @return bool - False when the option is on.
	 *
	 * @since 1.0.0
	 */
	public static function disable_wp_sitemaps( $is_enabled ) {
		if ( self::get_option_value( 'disable_wp_sitemaps' ) ) {
			return false;
		}

		return $is_enabled;
	}

	/**
	 * Dequeue the classic theme styles and global styles on the frontend.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function disable_theme_styles() {
		if ( ! self::is_frontend() ) {
			return;
		}

		if ( self::get_option_value( 'disable_classic_theme_styles' ) ) {
			\wp_dequeue_style( 'classic-theme-styles' );
		}

		if ( self::should_disable_global_styles() ) {
			\wp_dequeue_style( 'global-styles' );
		}
	}

	/**
	 * Dequeue global styles again in the footer, where WordPress enqueues them a second time.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function disable_global_styles_footer() {
		if ( self::is_frontend() && self::should_disable_global_styles() ) {
			\wp_dequeue_style( 'global-styles' );
		}
	}

	/**
	 * Check if global styles should be removed. Never for block themes, their design comes from global styles.
	 *
	 * @return bool - True to remove global styles.
	 *
	 * @since 1.0.0
	 */
	public static function should_disable_global_styles() {
		return self::get_option_value( 'disable_global_styles' ) && ! \wp_is_block_theme();
	}

	/**
	 * Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor.
	 */
	public static function disable_heartbeat_frontend() {
		if ( self::is_frontend() ) {

			if ( self::get_option_value( 'disable_heartbeat_frontend' ) ) {
				// Removes Heartbeat API script from loading.
				\wp_deregister_script( 'heartbeat' );
			}
		}
	}

	/**
	 * Set the Heartbeat API interval for the frontend, dashboard and post editor.
	 *
	 * @param array $settings - Heartbeat settings.
	 *
	 * @return array - Filtered Heartbeat settings.
	 */
	public static function control_heartbeat_settings( $settings ) {
		global $pagenow;

		if ( ! self::get_option_value( 'control_heartbeat_settings' ) ) {
			return $settings;
		}

		if ( self::is_frontend() ) {
			$interval = self::get_option_value( 'heartbeat_interval_frontend', 60 );
		} elseif ( in_array( $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			$interval = self::get_option_value( 'heartbeat_interval_editor', 30 );
		} else {
			$interval = self::get_option_value( 'heartbeat_interval_dashboard', 60 );
		}

		// WordPress accepts 15 to 120 seconds.
		$settings['interval'] = min( 120, max( 15, (int) $interval ) );

		return $settings;
	}

	/**
	 * Deregister the Dashicons stylesheet on the frontend when the admin toolbar is hidden.
	 *
	 * Covers logged-out visitors and users without the toolbar. The admin-bar
	 * stylesheet depends on Dashicons, so it is kept whenever the toolbar shows.
	 *
	 * @return void
	 */
	public static function deregister_dashicons_non_admin() {
		if ( ! self::get_option_value( 'disable_dashicons_non_admin' ) ) {
			return;
		}

		if ( \is_admin() || \is_admin_bar_showing() ) {
			return;
		}

		\wp_deregister_style( 'dashicons' );
	}

	/**
	 * Disable all WordPress emoji scripts and styles.
	 */
	public static function disable_emojis() {

		if ( self::get_option_value( 'disable_emojis' ) ) {
			// Remove emoji script from frontend and admin.
			\remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			\remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );

			// Remove emoji styles from frontend and admin.
			\remove_action( 'wp_print_styles', 'print_emoji_styles' );
			\remove_action( 'admin_print_styles', 'print_emoji_styles' );

			// Prevent emojis from being injected in the RSS feed.
			\remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			\remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			\remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

			// Remove TinyMCE emoji support (editor compatibility).
			\add_filter( 'tiny_mce_plugins', array( __CLASS__, 'emojis_tinymce' ) );
			\add_filter( 'wp_resource_hints', array( __CLASS__, 'emojis_remove_dns_prefetch' ), 10, 2 );
		}
	}

	/**
	 * Disable emojis tinymce.
	 *
	 * @param array $plugins - TinyMCE plugins.
	 *
	 * @return array - TinyMCE plugins without wpemoji.
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
			$emoji_svg_url = \apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );

			$urls = array_diff( $urls, array( $emoji_svg_url ) );
		}

		return $urls;
	}

	/**
	 * Remove oEmbed discovery links, the wp-embed script and the oEmbed REST endpoint.
	 *
	 * External embeds (YouTube, Vimeo, etc.) in content keep working and stay sanitized,
	 * only this site stops acting as an oEmbed provider for other sites.
	 *
	 * @return void
	 */
	public static function disable_wp_oembed() {
		if ( ! self::get_option_value( 'disable_wp_oembed' ) ) {
			return;
		}

		// Discovery links in the page head, core hooks them at priority 4 and 10.
		\remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 4 );
		\remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

		// wp-embed script, core checks this action before enqueueing it.
		\remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		\remove_filter( 'embed_oembed_html', 'wp_maybe_enqueue_oembed_host_js' );

		// oEmbed REST endpoint, other sites can no longer embed this site's posts.
		\remove_action( 'rest_api_init', 'wp_oembed_register_route' );
	}

	/**
	 * Disable self-pingbacks in WordPress to prevent unnecessary notifications.
	 *
	 * Pingbacks allow automatic notifications when linking to a post on another site.
	 * However, self-pingbacks occur when a site links to its own posts, cluttering comments.
	 * This function removes links from the ping process if they belong to the same domain.
	 *
	 * @param array $links - Links to ping, passed by reference.
	 *
	 * @return void
	 */
	public static function disable_self_pingbacks( &$links ) {
		if ( self::get_option_value( 'disable_self_pingbacks' ) ) {
			// Get the site's base URL.
			$home_url = \home_url();

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
	 * Limit WordPress post revisions to the configured number.
	 *
	 * This reduces unnecessary database storage while retaining useful revisions for editing.
	 *
	 * @param int $num - Number of revisions to store.
	 *
	 * @return int - Number of revisions to store.
	 */
	public static function limit_post_revisions( $num ) {
		if ( self::get_option_value( 'limit_post_revisions' ) ) {
			return (int) self::get_option_value( 'post_revisions_limit', 5 );
		}

		return $num;
	}

	/**
	 * Disable the `capital_P_dangit` function in WordPress.
	 *
	 * This function is normally applied to content, titles, comments, and feeds.
	 * It prevents WordPress from auto-correcting "WordPress" to "WordPress."
	 */
	public static function disable_capital_p_dangit() {
		if ( self::get_option_value( 'disable_capital_p_dangit' ) ) {
			\remove_filter( 'the_content', 'capital_P_dangit', 11 );
			\remove_filter( 'the_title', 'capital_P_dangit', 11 );
			\remove_filter( 'wp_title', 'capital_P_dangit', 11 );
			\remove_filter( 'document_title', 'capital_P_dangit', 11 );
			\remove_filter( 'widget_text_content', 'capital_P_dangit', 11 );
			\remove_filter( 'comment_text', 'capital_P_dangit', 31 );
		}
	}

	/**
	 * Disables all WordPress comments across the entire site.
	 *
	 * Hooks all actions and filters needed to fully disable comments,
	 * pingbacks and trackbacks in the admin area, front-end, feeds, REST API and XML-RPC.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function disable_comments() {
		if ( ! self::get_option_value( 'disable_comments' ) ) {
			return;
		}

		// Admin logic.
		\add_action( 'admin_init', array( __CLASS__, 'admin_disable_comments' ) );

		// Front-end close comments.
		\add_filter( 'comments_open', '__return_false', 20 );
		\add_filter( 'pings_open', '__return_false', 20 );

		// Hide existing comments and their count.
		\add_filter( 'comments_array', '__return_empty_array', 20 );
		\add_filter( 'get_comments_number', '__return_zero', 20 );

		// Block themes query comments directly, so hide the Comments block output.
		\add_filter( 'render_block_core/comments', '__return_empty_string' );

		// Remove comments feed link.
		\add_filter( 'feed_links_show_comments_feed', '__return_false' );

		// Remove comment reply script.
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'deregister_comment_reply_script' ), 100 );

		// Remove REST API and XML-RPC comment endpoints.
		\add_filter( 'rest_endpoints', array( __CLASS__, 'remove_comments_rest_endpoints' ) );
		\add_filter( 'xmlrpc_methods', array( __CLASS__, 'remove_comments_xmlrpc_methods' ) );

		// Remove menu items.
		\add_action( 'admin_menu', array( __CLASS__, 'remove_comments_menu' ) );

		// Keep the admin bar item when comments stay manageable in admin.
		if ( ! self::get_option_value( 'disable_comments_keep_admin' ) ) {
			\add_action( 'admin_bar_menu', array( __CLASS__, 'remove_admin_bar_comments' ), 999 );
		}
	}

	/**
	 * Disable comments in the admin area.
	 *
	 * Redirects comment admin screens, removes the dashboard widget
	 * and comment support from all post types.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function admin_disable_comments() {
		global $pagenow;

		$keep_admin = self::get_option_value( 'disable_comments_keep_admin' );

		if ( 'options-discussion.php' === $pagenow || ( ! $keep_admin && 'edit-comments.php' === $pagenow ) ) {
			\wp_safe_redirect( \admin_url() );
			exit;
		}

		if ( ! $keep_admin ) {
			\remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
		}

		foreach ( \get_post_types() as $post_type ) {
			if ( \post_type_supports( $post_type, 'comments' ) ) {
				\remove_post_type_support( $post_type, 'comments' );
				\remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	}

	/**
	 * Remove Comments and Discussion admin menu items.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function remove_comments_menu() {
		if ( ! self::get_option_value( 'disable_comments_keep_admin' ) ) {
			\remove_menu_page( 'edit-comments.php' );
		}

		\remove_submenu_page( 'options-general.php', 'options-discussion.php' );
	}

	/**
	 * Remove Comments item from the admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar - Admin bar instance.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function remove_admin_bar_comments( $wp_admin_bar ) {
		$wp_admin_bar->remove_node( 'comments' );
	}

	/**
	 * Deregister the comment reply script on the front-end.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function deregister_comment_reply_script() {
		\wp_deregister_script( 'comment-reply' );
	}

	/**
	 * Remove comments endpoints from the REST API.
	 *
	 * @param array $endpoints - Registered REST API endpoints.
	 *
	 * @return array - Filtered endpoints.
	 *
	 * @since 1.0.0
	 */
	public static function remove_comments_rest_endpoints( $endpoints ) {
		unset( $endpoints['/wp/v2/comments'] );
		unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	/**
	 * Remove comment creation method from XML-RPC.
	 *
	 * @param array $methods - Registered XML-RPC methods.
	 *
	 * @return array - Filtered methods.
	 *
	 * @since 1.0.0
	 */
	public static function remove_comments_xmlrpc_methods( $methods ) {
		unset( $methods['wp.newComment'] );

		return $methods;
	}

	/**
	 * Remove jQuery Migrate from the jQuery dependencies on the frontend.
	 *
	 * @param \WP_Scripts $scripts - Default scripts instance.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function disable_jquery_migrate( $scripts ) {
		if ( \is_admin() || ! self::get_option_value( 'disable_jquery_migrate' ) ) {
			return;
		}

		if ( empty( $scripts->registered['jquery'] ) ) {
			return;
		}

		$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, array( 'jquery-migrate' ) );
	}

	/**
	 * Remove unnecessary tags from the page head and HTTP headers.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function remove_head_tags() {
		if ( ! self::get_option_value( 'remove_head_tags' ) ) {
			return;
		}

		// Page head tags.
		\remove_action( 'wp_head', 'rsd_link' );
		\remove_action( 'wp_head', 'wlwmanifest_link' );
		\remove_action( 'wp_head', 'wp_generator' );
		\remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		\remove_action( 'wp_head', 'rest_output_link_wp_head' );
		\remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );

		// HTTP headers.
		\remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		\remove_action( 'template_redirect', 'wp_shortlink_header', 11 );

		// WordPress version in feeds.
		\add_filter( 'the_generator', '__return_empty_string' );
	}

	/**
	 * Disable XML-RPC completely, including pingbacks.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function disable_xmlrpc() {
		if ( ! self::get_option_value( 'disable_xmlrpc' ) ) {
			return;
		}

		// Block direct requests to xmlrpc.php, system.* methods can't be removed by filters.
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			// wp_die() can't be used here, its XML-RPC handler needs IXR classes not loaded yet.
			\status_header( 403 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo \esc_html__( 'XML-RPC is disabled on this site.', 'bloatbuster-performance-asset-cleaner' );
			exit;
		}

		// Disables authenticated methods.
		\add_filter( 'xmlrpc_enabled', '__return_false' );

		// Disables all remaining methods, like pingback.ping.
		\add_filter( 'xmlrpc_methods', '__return_empty_array', 999 );

		// Remove X-Pingback header and the RSD link pointing to xmlrpc.php.
		\add_filter( 'wp_headers', array( __CLASS__, 'remove_x_pingback_header' ) );
		\remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Hide usernames from visitors who can't edit posts.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function disable_user_enumeration() {
		if ( ! self::get_option_value( 'disable_user_enumeration' ) ) {
			return;
		}

		\add_filter( 'rest_endpoints', array( __CLASS__, 'remove_users_rest_endpoints' ) );

		// Runs before redirect_canonical() (priority 10), which reveals the author slug.
		\add_action( 'template_redirect', array( __CLASS__, 'block_author_query' ), 9 );

		\add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'remove_users_sitemap' ), 10, 2 );
		\add_filter( 'oembed_response_data', array( __CLASS__, 'remove_oembed_author' ) );
	}

	/**
	 * Remove the REST API users endpoints for users who can't edit posts.
	 *
	 * @param array $endpoints - Registered REST API endpoints.
	 *
	 * @return array - Filtered endpoints.
	 *
	 * @since 1.0.0
	 */
	public static function remove_users_rest_endpoints( $endpoints ) {
		// The block editor author dropdown needs these endpoints.
		if ( \current_user_can( 'edit_posts' ) ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	/**
	 * Redirect ?author= requests to the home page so they can't reveal the author slug.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function block_author_query() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check, no data is changed.
		if ( ! isset( $_GET['author'] ) || \current_user_can( 'edit_posts' ) ) {
			return;
		}

		\wp_safe_redirect( \home_url( '/' ), 301 );
		exit;
	}

	/**
	 * Remove the users sitemap provider.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider - Sitemap provider.
	 * @param string                      $name     - Provider name.
	 *
	 * @return \WP_Sitemaps_Provider|false - Provider, or false for the users sitemap.
	 *
	 * @since 1.0.0
	 */
	public static function remove_users_sitemap( $provider, $name ) {
		if ( 'users' === $name ) {
			return false;
		}

		return $provider;
	}

	/**
	 * Remove the author name and URL from oEmbed responses.
	 *
	 * @param array $data - oEmbed response data.
	 *
	 * @return array - Filtered response data.
	 *
	 * @since 1.0.0
	 */
	public static function remove_oembed_author( $data ) {
		unset( $data['author_name'] );
		unset( $data['author_url'] );

		return $data;
	}

	/**
	 * Remove the X-Pingback HTTP header.
	 *
	 * @param array $headers - HTTP headers.
	 *
	 * @return array - Filtered HTTP headers.
	 *
	 * @since 1.0.0
	 */
	public static function remove_x_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Get the reason why speculative loading options can't be used.
	 *
	 * @return string - 'plugin' if the Speculative Loading plugin is active, 'core' if WordPress is older than 6.8, empty otherwise.
	 *
	 * @since 1.0.0
	 */
	public static function get_speculative_loading_lock() {
		if ( defined( 'SPECULATION_RULES_VERSION' ) ) {
			return 'plugin';
		}

		if ( ! function_exists( 'wp_get_speculation_rules_configuration' ) ) {
			return 'core';
		}

		return '';
	}

	/**
	 * Apply the speculative loading mode and eagerness.
	 *
	 * @param array|null $config - Speculation rules configuration with mode and eagerness, or null when disabled.
	 *
	 * @return array|null - Filtered configuration.
	 *
	 * @since 1.0.0
	 */
	public static function control_speculative_loading( $config ) {
		if ( '' !== self::get_speculative_loading_lock() ) {
			return $config;
		}

		$mode      = self::get_option_value( 'speculative_loading_mode' );
		$eagerness = self::get_option_value( 'speculative_loading_eagerness' );

		if ( 'disabled' === $mode ) {
			return null;
		}

		// Core disables it for logged-in users and sites without pretty permalinks, keep that.
		if ( ! is_array( $config ) ) {
			return $config;
		}

		if ( in_array( $mode, array( 'prefetch', 'prerender' ), true ) ) {
			$config['mode'] = $mode;
		}

		if ( in_array( $eagerness, array( 'conservative', 'moderate', 'eager' ), true ) ) {
			$config['eagerness'] = $eagerness;
		}

		return $config;
	}
}
