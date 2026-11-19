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

			$item        = $deps->registered[ $handle ];
			$src         = self::get_absolute_src( $deps, $item->src );
			$inline_size = self::get_inline_size( $deps, $handle, $type );
			$owner       = self::get_owner( $src, $handle );

			if ( '' !== $src ) {
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
				'type'        => $type,
				'handle'      => (string) $handle,
				'kind'        => $kind,
				'src'         => $src,
				'ver'         => is_string( $item->ver ) ? $item->ver : '',
				'deps'        => array_values( array_map( 'strval', (array) $item->deps ) ),
				'location'    => $location,
				'strategy'    => 'js' === $type ? (string) $deps->get_data( $handle, 'strategy' ) : '',
				'size'        => 'file' === $kind ? self::get_local_size( $src ) : null,
				'inline_size' => $inline_size,
				'owner'       => $owner['label'],
				'owner_id'    => $owner['id'],
				'confidence'  => $owner['confidence'],
			);
		}

		return $assets;
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
