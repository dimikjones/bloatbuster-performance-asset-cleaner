<?php
/**
 * Frontend asset inspector.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records the styles and scripts printed on a frontend page, when the admin scans it from the Assets tab.
 *
 * The scan is a normal frontend request made by the logged-in admin in a hidden
 * iframe, so the list shows what really loads. No server-side HTTP requests.
 */
final class Asset_Inspector {

	/**
	 * Query arg with the scan id, set on the scanned frontend URL.
	 *
	 * @var string
	 */
	const SCAN_ARG = 'bbpac_inspect';

	/**
	 * Query arg with the scan id, set on the Assets tab URL after a scan.
	 *
	 * @var string
	 */
	const RESULT_ARG = 'bbpac_scan';

	/**
	 * Nonce action of the scan request.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'bloatbuster_asset_scan';

	/**
	 * User option with the last scan, stored per user and per site.
	 *
	 * @var string
	 */
	const USER_OPTION = 'bloatbuster_asset_scan';

	/**
	 * Id of the running scan, empty when this request is not a scan.
	 *
	 * @var string
	 */
	private static $scan_id = '';

	/**
	 * Whether the scan of this request was saved.
	 *
	 * @var bool
	 */
	private static $saved = false;

	/**
	 * Handles printed in the head, keyed by type.
	 *
	 * @var array|null
	 */
	private static $head_done = null;

	/**
	 * Installed plugin names keyed by folder.
	 *
	 * @var array|null
	 */
	private static $plugin_folders = null;

	/**
	 * Loading strategy of each printed script tag: async, defer or empty.
	 *
	 * @var array
	 */
	private static $printed_strategies = array();

	/**
	 * URLs of the printed asset tags, keyed by type and handle.
	 *
	 * @var array
	 */
	private static $printed_srcs = array(
		'css' => array(),
		'js'  => array(),
	);

	/**
	 * Whether a non-empty tag was printed, keyed by type and handle.
	 *
	 * @var array
	 */
	private static $printed_tags = array(
		'css' => array(),
		'js'  => array(),
	);

	/**
	 * Bytes of printed tags a filter replaced with inline code, keyed by type and handle.
	 *
	 * @var array
	 */
	private static $inlined_bytes = array(
		'css' => array(),
		'js'  => array(),
	);

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		// Early on init, before anything checks if the admin bar shows.
		\add_action( 'init', array( __CLASS__, 'maybe_start_scan' ), 1 );
	}

	/**
	 * Start a scan when a valid scan request comes from an administrator.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function maybe_start_scan() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is verified below.
		if ( \is_admin() || ! isset( $_GET[ self::SCAN_ARG ] ) ) {
			return;
		}

		$scan_id = substr( \sanitize_key( \wp_unslash( $_GET[ self::SCAN_ARG ] ) ), 0, 40 );
		$nonce   = isset( $_GET['_wpnonce'] ) ? \sanitize_text_field( \wp_unslash( $_GET['_wpnonce'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $scan_id || ! \wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		self::$scan_id = $scan_id;

		// The admin bar adds its own assets, visitors don't load them.
		\show_admin_bar( false );

		// Don't let a page cache store or serve this request.
		\nocache_headers();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		/**
		 * REST and AJAX requests never reach template_redirect. Feeds, sitemaps and
		 * redirects do, but print no wp_head, so nothing is saved for them.
		 */
		\add_action( 'template_redirect', array( __CLASS__, 'add_scan_hooks' ) );
	}

	/**
	 * Add the hooks that record the printed assets of the page.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function add_scan_hooks() {
		\add_action( 'wp_head', array( __CLASS__, 'record_head_assets' ), PHP_INT_MAX );

		// Last, so they see the final tags, also URLs and async or defer changed by other plugins.
		\add_filter( 'style_loader_tag', array( __CLASS__, 'record_style_tag' ), PHP_INT_MAX, 2 );
		\add_filter( 'script_loader_tag', array( __CLASS__, 'record_script_tag' ), PHP_INT_MAX, 2 );

		// Footer scripts and late styles are printed on wp_footer priority 20.
		\add_action( 'wp_footer', array( __CLASS__, 'save_scan' ), PHP_INT_MAX );

		// Fallback for themes without wp_footer(). shutdown may run after the iframe has loaded.
		\add_action( 'shutdown', array( __CLASS__, 'save_scan_on_shutdown' ) );
	}

	/**
	 * Save the scan on shutdown, only when a page was printed.
	 *
	 * Feeds, sitemaps, robots.txt and redirects pass template_redirect but
	 * never print wp_head, so they would save an empty scan.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function save_scan_on_shutdown() {
		if ( ! \did_action( 'wp_head' ) ) {
			return;
		}

		self::save_scan();
	}

	/**
	 * Remember the URLs of a printed stylesheet tag.
	 *
	 * On RTL pages WordPress prints the -rtl file instead of, or next to, the
	 * registered one, and style_loader_src filters can change the URL.
	 *
	 * @param string $tag    - Style tag HTML.
	 * @param string $handle - Style handle.
	 *
	 * @return string - The tag, unchanged.
	 *
	 * @since 1.0.0
	 */
	public static function record_style_tag( $tag, $handle ) {
		$handle  = (string) $handle;
		$urls    = self::get_stylesheet_urls( (string) $tag );
		$printed = '' !== trim( (string) $tag );
		$inlined = 0;

		/**
		 * No stylesheet link: a preload or a lazy loader keeps the URL in another attribute.
		 * A tag without any URL, like critical CSS in a <style>, replaced the file with inline code.
		 */
		if ( $printed && empty( $urls ) ) {
			$urls = self::get_referenced_urls( (string) $tag );

			if ( empty( $urls ) ) {
				$inlined = strlen( (string) $tag );
			}
		}

		/**
		 * On RTL pages core filters the -rtl tag in a second call with the same handle.
		 * With rtl "replace" only that tag is printed, otherwise both are.
		 */
		if ( isset( self::$printed_tags['css'][ $handle ] ) && 'replace' !== \wp_styles()->get_data( $handle, 'rtl' ) ) {
			$first_urls = isset( self::$printed_srcs['css'][ $handle ] ) ? self::$printed_srcs['css'][ $handle ] : array();
			$urls       = array_values( array_unique( array_merge( $first_urls, $urls ) ) );
			$printed    = $printed || self::$printed_tags['css'][ $handle ];
			$inlined   += isset( self::$inlined_bytes['css'][ $handle ] ) ? self::$inlined_bytes['css'][ $handle ] : 0;
		}

		self::$printed_tags['css'][ $handle ] = $printed;
		self::$printed_srcs['css'][ $handle ] = $urls;

		if ( $inlined > 0 ) {
			self::$inlined_bytes['css'][ $handle ] = $inlined;
		} else {
			unset( self::$inlined_bytes['css'][ $handle ] );
		}

		return $tag;
	}

	/**
	 * Remember the URL and loading strategy of a printed script tag.
	 *
	 * WordPress drops a requested async or defer in some cases, e.g. with an
	 * inline "after" script, so the printed tag is the only reliable source.
	 *
	 * @param string $tag    - Script tag HTML.
	 * @param string $handle - Script handle.
	 *
	 * @return string - The tag, unchanged.
	 *
	 * @since 1.0.0
	 */
	public static function record_script_tag( $tag, $handle ) {
		$handle    = (string) $handle;
		$processor = new \WP_HTML_Tag_Processor( (string) $tag );
		$strategy  = '';
		$printed   = '' !== trim( (string) $tag );

		self::$printed_tags['js'][ $handle ] = $printed;

		while ( $processor->next_tag( 'script' ) ) {
			$src = $processor->get_attribute( 'src' );

			// The tag also holds inline before/after scripts, only the one with src is the file.
			if ( ! self::is_file_url( $src ) ) {
				continue;
			}

			self::$printed_srcs['js'][ $handle ] = array( $src );

			if ( null !== $processor->get_attribute( 'async' ) ) {
				$strategy = 'async';
			} elseif ( null !== $processor->get_attribute( 'defer' ) ) {
				$strategy = 'defer';
			}

			break;
		}

		// Delayed scripts keep the URL in another attribute, e.g. data-src. A tag without any URL replaced the file with inline code.
		if ( $printed && empty( self::$printed_srcs['js'][ $handle ] ) ) {
			$urls = self::get_referenced_urls( (string) $tag );

			if ( ! empty( $urls ) ) {
				self::$printed_srcs['js'][ $handle ] = $urls;
			} else {
				self::$inlined_bytes['js'][ $handle ] = strlen( (string) $tag );
			}
		}

		self::$printed_strategies[ $handle ] = $strategy;

		return $tag;
	}

	/**
	 * Get the file URLs a tag points to, e.g. a preload href or a delayed script data-src.
	 *
	 * @param string $tag - Tag HTML.
	 *
	 * @return array - Unique URLs, values of attributes named *src or *href first, then other URL-like values.
	 *
	 * @since 1.0.0
	 */
	public static function get_referenced_urls( $tag ) {
		$processor = new \WP_HTML_Tag_Processor( $tag );
		$named     = array();
		$other     = array();

		while ( $processor->next_tag() ) {
			foreach ( (array) $processor->get_attribute_names_with_prefix( '' ) as $name ) {
				$value = $processor->get_attribute( $name );
				$value = is_string( $value ) ? trim( $value ) : '';

				if ( ! self::is_file_url( $value ) ) {
					continue;
				}

				if ( preg_match( '/(^|-)(src|href)$/', $name ) ) {
					$named[] = $value;
				} elseif ( preg_match( '#^(https?:)?//#i', $value ) || preg_match( '#^/[^/]#', $value ) ) {
					$other[] = $value;
				}
			}
		}

		return array_values( array_unique( array_merge( $named, $other ) ) );
	}

	/**
	 * Check if an attribute value can point to a file.
	 *
	 * @param mixed $value - Attribute value.
	 *
	 * @return bool - False for empty values, fragments, data URIs (inline code) and javascript: URLs.
	 *
	 * @since 1.0.0
	 */
	public static function is_file_url( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		if ( '' === $value || '#' === $value[0] ) {
			return false;
		}

		return ! preg_match( '/^(data|javascript):/i', $value );
	}

	/**
	 * Get the URLs of the stylesheet links in a style tag.
	 *
	 * Other links, like a preload added by a theme, don't load the file as a stylesheet.
	 *
	 * @param string $tag - Style tag HTML.
	 *
	 * @return array - Unique URLs in printed order.
	 *
	 * @since 1.0.0
	 */
	public static function get_stylesheet_urls( $tag ) {
		$processor = new \WP_HTML_Tag_Processor( $tag );
		$urls      = array();

		while ( $processor->next_tag( 'link' ) ) {
			$rel  = strtolower( (string) $processor->get_attribute( 'rel' ) );
			$href = $processor->get_attribute( 'href' );

			if ( ! in_array( 'stylesheet', preg_split( '/\s+/', $rel ), true ) ) {
				continue;
			}

			if ( self::is_file_url( $href ) && ! in_array( $href, $urls, true ) ) {
				$urls[] = $href;
			}
		}

		return $urls;
	}

	/**
	 * Remember which handles were printed in the head.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function record_head_assets() {
		self::$head_done = array(
			'css' => (array) \wp_styles()->done,
			'js'  => (array) \wp_scripts()->done,
		);
	}

	/**
	 * Save the printed assets as the user's last scan.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function save_scan() {
		if ( self::$saved || '' === self::$scan_id ) {
			return;
		}

		self::$saved = true;

		$url    = self::get_current_url();
		$assets = array_merge(
			self::collect_assets( \wp_styles(), 'css' ),
			self::collect_assets( \wp_scripts(), 'js' )
		);

		/**
		 * Filter the assets found by the asset inspector before they are saved.
		 *
		 * @param array  $assets - Assets keyed by "{type}:{handle}".
		 * @param string $url    - Scanned page URL.
		 *
		 * @since 1.0.0
		 */
		$assets = (array) \apply_filters( 'bloatbuster_asset_inspector_assets', $assets, $url );

		\update_user_option(
			\get_current_user_id(),
			self::USER_OPTION,
			array(
				'id'     => self::$scan_id,
				'url'    => $url,
				'time'   => time(),
				'assets' => $assets,
			)
		);
	}

	/**
	 * Get the last scan of the current user.
	 *
	 * @return array - Scan with id, url, time and assets, or an empty array.
	 *
	 * @since 1.0.0
	 */
	public static function get_scan() {
		$scan = \get_user_option( self::USER_OPTION );

		if ( ! is_array( $scan ) || empty( $scan['id'] ) || ! isset( $scan['assets'] ) || ! is_array( $scan['assets'] ) ) {
			return array();
		}

		return $scan;
	}

	/**
	 * Get the URL of the current request, without the scan query args.
	 *
	 * @return string - Page URL.
	 *
	 * @since 1.0.0
	 */
	public static function get_current_url() {
		$request = isset( $_SERVER['REQUEST_URI'] ) ? \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$request = \remove_query_arg( array( self::SCAN_ARG, '_wpnonce' ), $request );

		// The host comes from the site settings, not from the request headers.
		$home = \wp_parse_url( \home_url() );
		$host = isset( $home['host'] ) ? $home['host'] : '';

		if ( isset( $home['port'] ) ) {
			$host .= ':' . $home['port'];
		}

		return ( \is_ssl() ? 'https://' : 'http://' ) . $host . $request;
	}

	/**
	 * Collect the printed assets of a dependency registry.
	 *
	 * @param \WP_Styles|\WP_Scripts $deps - wp_styles() or wp_scripts().
	 * @param string                 $type - Asset type: css or js.
	 *
	 * @return array - Assets keyed by "{type}:{handle}".
	 *
	 * @since 1.0.0
	 */
	public static function collect_assets( $deps, $type ) {
		$assets    = array();
		$head_done = isset( self::$head_done[ $type ] ) ? self::$head_done[ $type ] : null;

		foreach ( array_unique( (array) $deps->done ) as $handle ) {
			if ( ! isset( $deps->registered[ $handle ] ) ) {
				continue;
			}

			$item           = $deps->registered[ $handle ];
			$registered_src = self::get_absolute_src( $deps, $item->src );
			$printed_srcs   = self::get_printed_srcs( $type, $handle );
			$src            = ! empty( $printed_srcs ) ? $printed_srcs[0] : $registered_src;
			$inline_size    = self::get_inline_size( $deps, $handle, $type );

			// The registered URL points to the plugin or theme folder, also when a CDN rewrites the printed one.
			$owner = self::get_owner( '' !== $registered_src ? $registered_src : $src, $handle );

			/**
			 * WordPress marks a handle done also when a *_loader_src filter empties the URL or a
			 * *_loader_tag filter returns an empty tag, so the file was never printed.
			 */
			if ( '' !== $registered_src && empty( self::$printed_tags[ $type ][ $handle ] ) ) {
				$src         = '';
				$kind        = 'not_printed';
				$inline_size = self::get_printed_inline_size( $deps, $handle, $type );
			} elseif ( empty( $printed_srcs ) && isset( self::$inlined_bytes[ $type ][ $handle ] ) ) {
				// A *_loader_tag filter printed the code inline, the page made no request for the file.
				$src         = '';
				$kind        = 'inlined';
				$inline_size = self::$inlined_bytes[ $type ][ $handle ] + self::get_printed_inline_size( $deps, $handle, $type );
			} elseif ( '' !== $src ) {
				$kind = 'file';
			} elseif ( $inline_size > 0 ) {
				$kind = 'inline';
			} else {
				// A handle without a file or code only loads its dependencies, like jquery.
				$kind = 'group';
			}

			if ( null === $head_done ) {
				$location = '';
			} elseif ( in_array( $handle, $head_done, true ) ) {
				$location = 'header';
			} else {
				$location = 'footer';
			}

			$assets[ $type . ':' . $handle ] = array(
				'type'               => $type,
				'handle'             => (string) $handle,
				'kind'               => $kind,
				'src'                => $src,
				'registered_src'     => self::is_same_url( $registered_src, $src ) ? '' : $registered_src,
				'extra_srcs'         => array_slice( $printed_srcs, 1 ),
				'ver'                => is_string( $item->ver ) ? $item->ver : '',
				'deps'               => array_values( array_map( 'strval', (array) $item->deps ) ),
				'location'           => $location,
				'strategy'           => 'js' === $type && isset( self::$printed_strategies[ $handle ] ) ? self::$printed_strategies[ $handle ] : '',
				'requested_strategy' => 'js' === $type ? (string) $deps->get_data( $handle, 'strategy' ) : '',
				'size'               => 'file' === $kind ? self::get_files_size( ! empty( $printed_srcs ) ? $printed_srcs : array( $src ) ) : null,
				'inline_size'        => $inline_size,
				'owner'              => $owner['label'],
				'owner_id'           => $owner['id'],
				'confidence'         => $owner['confidence'],
			);
		}

		return $assets;
	}

	/**
	 * Get the absolute URLs printed for a handle.
	 *
	 * @param string $type   - Asset type: css or js.
	 * @param string $handle - Asset handle.
	 *
	 * @return array - Absolute URLs, empty when no tag with a URL was recorded.
	 *
	 * @since 1.0.0
	 */
	public static function get_printed_srcs( $type, $handle ) {
		if ( empty( self::$printed_srcs[ $type ][ $handle ] ) ) {
			return array();
		}

		$page_url = self::get_current_url();
		$urls     = array();

		foreach ( self::$printed_srcs[ $type ][ $handle ] as $url ) {
			// Already in the HTML, so resolve it like the browser does, against the page and not the registry base_url.
			$url = (string) \WP_Http::make_absolute_url( $url, $page_url );

			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}

	/**
	 * Check if two asset URLs point to the same file, ignoring the query and fragment.
	 *
	 * @param string $first  - First URL.
	 * @param string $second - Second URL.
	 *
	 * @return bool - True when the URLs match without query and fragment.
	 *
	 * @since 1.0.0
	 */
	public static function is_same_url( $first, $second ) {
		$first  = preg_replace( '/[?#].*$/', '', $first );
		$second = preg_replace( '/[?#].*$/', '', $second );

		return $first === $second;
	}

	/**
	 * Get the total size of local asset files.
	 *
	 * @param array $urls - Absolute asset URLs.
	 *
	 * @return int|null - Size in bytes, or null when none of the files is local.
	 *
	 * @since 1.0.0
	 */
	public static function get_files_size( $urls ) {
		$total = null;

		foreach ( $urls as $url ) {
			$size = self::get_local_size( $url );

			if ( null !== $size ) {
				$total = (int) $total + $size;
			}
		}

		return $total;
	}

	/**
	 * Get the absolute URL of an asset source, the same way WordPress prints it.
	 *
	 * @param \WP_Styles|\WP_Scripts $deps - wp_styles() or wp_scripts().
	 * @param string|bool            $src  - Registered source.
	 *
	 * @return string - Absolute URL, or empty string for handles without a file.
	 *
	 * @since 1.0.0
	 */
	public static function get_absolute_src( $deps, $src ) {
		if ( ! is_string( $src ) || '' === $src ) {
			return '';
		}

		if ( 0 === strpos( $src, '//' ) ) {
			return \set_url_scheme( $src );
		}

		if ( ! preg_match( '|^https?://|', $src ) && ! ( $deps->content_url && 0 === strpos( $src, $deps->content_url ) ) ) {
			$src = $deps->base_url . $src;
		}

		return $src;
	}

	/**
	 * Get the size of the inline code added to a handle.
	 *
	 * @param \WP_Styles|\WP_Scripts $deps   - wp_styles() or wp_scripts().
	 * @param string                 $handle - Asset handle.
	 * @param string                 $type   - Asset type: css or js.
	 *
	 * @return int - Size in bytes.
	 *
	 * @since 1.0.0
	 */
	public static function get_inline_size( $deps, $handle, $type ) {
		$size = 0;

		foreach ( array( 'before', 'after' ) as $position ) {
			foreach ( (array) $deps->get_data( $handle, $position ) as $code ) {
				if ( is_string( $code ) ) {
					$size += strlen( $code );
				}
			}
		}

		// Data added with wp_localize_script().
		if ( 'js' === $type ) {
			$data = $deps->get_data( $handle, 'data' );

			if ( is_string( $data ) ) {
				$size += strlen( $data );
			}
		}

		return $size;
	}

	/**
	 * Get the size of the inline code core prints outside the file tag, for a handle whose file tag was not printed or was inlined.
	 *
	 * Scripts: wp_localize_script() data is printed before the src check in
	 * WP_Scripts::do_item(), before/after code is part of the filtered tag.
	 * Styles: an empty href returns before any output, an empty tag from
	 * style_loader_tag still prints the inline style after it.
	 *
	 * @param \WP_Styles|\WP_Scripts $deps   - wp_styles() or wp_scripts().
	 * @param string                 $handle - Asset handle.
	 * @param string                 $type   - Asset type: css or js.
	 *
	 * @return int - Size in bytes.
	 *
	 * @since 1.0.0
	 */
	public static function get_printed_inline_size( $deps, $handle, $type ) {
		if ( 'js' === $type ) {
			$data = $deps->get_data( $handle, 'data' );

			return is_string( $data ) ? strlen( $data ) : 0;
		}

		// style_loader_tag did not run, so the href was empty and nothing was printed.
		if ( ! isset( self::$printed_tags['css'][ $handle ] ) ) {
			return 0;
		}

		$size = 0;

		foreach ( (array) $deps->get_data( $handle, 'after' ) as $code ) {
			if ( is_string( $code ) ) {
				$size += strlen( $code );
			}
		}

		return $size;
	}

	/**
	 * Guess the plugin, theme or core part that adds an asset.
	 *
	 * @param string $src    - Absolute asset URL, or empty string.
	 * @param string $handle - Asset handle.
	 *
	 * @return array - Owner label, id (e.g. plugin:woocommerce) and confidence: confirmed, likely or unknown.
	 *
	 * @since 1.0.0
	 */
	public static function get_owner( $src, $handle ) {
		if ( '' !== $src ) {
			$owner = self::get_owner_from_path( $src );

			if ( null !== $owner ) {
				return $owner;
			}
		} elseif ( self::is_core_inline_handle( $handle ) ) {
			return self::owner( \__( 'WordPress core', 'bloatbuster-performance-asset-cleaner' ), 'core', 'likely' );
		}

		$owner = self::get_owner_from_handle( $handle );

		if ( null !== $owner ) {
			return $owner;
		}

		if ( '' === $src ) {
			return self::owner( \__( 'Unknown', 'bloatbuster-performance-asset-cleaner' ), 'unknown', 'unknown' );
		}

		$host = (string) \wp_parse_url( $src, PHP_URL_HOST );
		$home = (string) \wp_parse_url( \home_url(), PHP_URL_HOST );

		if ( '' === $host || strtolower( $host ) === strtolower( $home ) ) {
			return self::owner( \__( 'This site', 'bloatbuster-performance-asset-cleaner' ), 'site', 'unknown' );
		}

		/* translators: %s: host name, e.g. cdn.example.com. */
		return self::owner( sprintf( \__( 'External (%s)', 'bloatbuster-performance-asset-cleaner' ), $host ), 'external:' . strtolower( $host ), 'confirmed' );
	}

	/**
	 * Check if a handle without a file is one WordPress core adds, like global-styles or jquery.
	 *
	 * @param string $handle - Asset handle.
	 *
	 * @return bool - True for known core handles and block styles.
	 *
	 * @since 1.0.0
	 */
	public static function is_core_inline_handle( $handle ) {
		$handles = array(
			'jquery',
			'global-styles',
			'core-block-supports',
			'core-block-supports-duotone',
			'wp-emoji-styles',
			'wp-img-auto-sizes-contain',
			'wp-fonts-local',
			'block-style-variation-styles',
			'wp-global-styles-placeholder',
		);

		// Block styles inlined by wp_maybe_inline_styles() lose their file URL.
		return in_array( $handle, $handles, true ) || 0 === strpos( $handle, 'wp-block-' );
	}

	/**
	 * Find the owner from the folder of the asset URL.
	 *
	 * @param string $src - Absolute asset URL.
	 *
	 * @return array|null - Owner, or null when the URL is not in a known folder.
	 *
	 * @since 1.0.0
	 */
	public static function get_owner_from_path( $src ) {
		$uploads = \wp_get_upload_dir();
		$folders = array(
			'mu-plugin' => WPMU_PLUGIN_URL,
			'plugin'    => \plugins_url(),
			'theme'     => \get_theme_root_uri(),
			'uploads'   => $uploads['baseurl'],
			'core'      => \includes_url(),
			'admin'     => \admin_url(),
		);

		foreach ( $folders as $folder_type => $folder_url ) {
			$relative = self::get_relative_path( $src, $folder_url );

			if ( null === $relative ) {
				continue;
			}

			$slug = strtok( $relative, '/' );
			$slug = false === $slug ? '' : $slug;

			if ( 'core' === $folder_type || 'admin' === $folder_type ) {
				return self::owner( \__( 'WordPress core', 'bloatbuster-performance-asset-cleaner' ), 'core', 'confirmed' );
			}

			if ( 'mu-plugin' === $folder_type ) {
				/* translators: %s: must-use plugin file or folder name. */
				return self::owner( sprintf( \__( 'Must-use plugin: %s', 'bloatbuster-performance-asset-cleaner' ), $slug ), 'mu-plugin:' . $slug, 'confirmed' );
			}

			if ( 'theme' === $folder_type ) {
				$theme = \wp_get_theme( $slug );

				if ( $theme->exists() ) {
					return self::owner( $theme->get( 'Name' ), 'theme:' . $slug, 'confirmed' );
				}

				return self::owner( $slug, 'theme:' . $slug, 'likely' );
			}

			$plugins = self::get_plugin_folders();

			if ( 'plugin' === $folder_type ) {
				if ( isset( $plugins[ $slug ] ) ) {
					return self::owner( $plugins[ $slug ], 'plugin:' . $slug, 'confirmed' );
				}

				return self::owner( $slug, 'plugin:' . $slug, 'likely' );
			}

			// Plugins like Elementor write generated files to uploads/{plugin-folder}/.
			if ( isset( $plugins[ $slug ] ) ) {
				return self::owner( $plugins[ $slug ], 'plugin:' . $slug, 'likely' );
			}

			return self::owner( \__( 'Uploads', 'bloatbuster-performance-asset-cleaner' ), 'uploads', 'confirmed' );
		}

		return null;
	}

	/**
	 * Find the owner from a plugin or theme prefix of the handle.
	 *
	 * @param string $handle - Asset handle.
	 *
	 * @return array|null - Owner with likely confidence, or null when no prefix matches.
	 *
	 * @since 1.0.0
	 */
	public static function get_owner_from_handle( $handle ) {
		if ( ! class_exists( __NAMESPACE__ . '\Autoload_Owners' ) ) {
			require_once BLOATBUSTER_DIR . '/includes/autoload/class-autoload-owners.php';
		}

		foreach ( Autoload_Owners::get_prefixes() as $prefix => $plugin ) {
			if ( ! Autoload_Owners::has_prefix( $handle, (string) $prefix ) ) {
				continue;
			}

			$id = ! empty( $plugin['id'] ) ? $plugin['id'] : 'unknown';

			return self::owner( $plugin['name'], $id, 'likely' );
		}

		return null;
	}

	/**
	 * Get the installed plugin names keyed by plugin folder.
	 *
	 * @return array - Folder => plugin name.
	 *
	 * @since 1.0.0
	 */
	public static function get_plugin_folders() {
		if ( null !== self::$plugin_folders ) {
			return self::$plugin_folders;
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		self::$plugin_folders = array();

		foreach ( \get_plugins() as $file => $plugin ) {
			// Single file plugins have no folder for assets.
			if ( false === strpos( $file, '/' ) ) {
				continue;
			}

			self::$plugin_folders[ dirname( $file ) ] = $plugin['Name'];
		}

		return self::$plugin_folders;
	}

	/**
	 * Get the path of a URL relative to a folder URL, ignoring the scheme.
	 *
	 * @param string $url        - Absolute URL.
	 * @param string $folder_url - Folder URL.
	 *
	 * @return string|null - Relative path without query string, or null when the URL is not inside the folder.
	 *
	 * @since 1.0.0
	 */
	public static function get_relative_path( $url, $folder_url ) {
		$url        = (string) preg_replace( '#^https?:#i', '', strtok( $url, '?#' ) );
		$folder_url = \trailingslashit( (string) preg_replace( '#^https?:#i', '', $folder_url ) );

		if ( '//' === $folder_url || 0 !== stripos( $url, $folder_url ) ) {
			return null;
		}

		return substr( $url, strlen( $folder_url ) );
	}

	/**
	 * Get the size of a local asset file.
	 *
	 * @param string $src - Absolute asset URL.
	 *
	 * @return int|null - Size in bytes, or null when the file is not local or not found.
	 *
	 * @since 1.0.0
	 */
	public static function get_local_size( $src ) {
		$path = self::get_local_path( $src );

		if ( '' === $path ) {
			return null;
		}

		$size = filesize( $path );

		return false === $size ? null : (int) $size;
	}

	/**
	 * Map an asset URL to a file inside the WordPress or wp-content folder.
	 *
	 * @param string $src - Absolute asset URL.
	 *
	 * @return string - File path, or empty string when the URL is not a local file.
	 *
	 * @since 1.0.0
	 */
	public static function get_local_path( $src ) {
		$map = array(
			array( \content_url(), WP_CONTENT_DIR ),
			array( \includes_url(), ABSPATH . WPINC ),
			array( \site_url(), ABSPATH ),
		);

		$allowed = array();

		foreach ( array( ABSPATH, WP_CONTENT_DIR ) as $folder ) {
			$real = realpath( $folder );

			if ( false !== $real ) {
				$allowed[] = \trailingslashit( \wp_normalize_path( $real ) );
			}
		}

		foreach ( $map as $pair ) {
			$relative = self::get_relative_path( $src, $pair[0] );

			if ( null === $relative || '' === $relative ) {
				continue;
			}

			$file = realpath( \trailingslashit( $pair[1] ) . rawurldecode( $relative ) );

			if ( false === $file || ! is_file( $file ) ) {
				return '';
			}

			$file = \wp_normalize_path( $file );

			// realpath() resolved "..", so the file must still be inside a WordPress folder.
			foreach ( $allowed as $folder ) {
				if ( 0 === stripos( $file, $folder ) ) {
					return $file;
				}
			}

			return '';
		}

		return '';
	}

	/**
	 * Build an owner array.
	 *
	 * @param string $label      - Owner label.
	 * @param string $id         - Owner id, e.g. plugin:woocommerce.
	 * @param string $confidence - confirmed, likely or unknown.
	 *
	 * @return array - Owner label, id and confidence.
	 *
	 * @since 1.0.0
	 */
	public static function owner( $label, $id, $confidence ) {
		return array(
			'label'      => (string) $label,
			'id'         => (string) $id,
			'confidence' => $confidence,
		);
	}
}
