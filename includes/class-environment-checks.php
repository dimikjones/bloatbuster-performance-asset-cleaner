<?php
/**
 * Environment checks.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the server and WordPress setup that affects speed: PHP, caches, WP-Cron, debug and database.
 */
final class Environment_Checks {

	/**
	 * PHP memory limit below which a higher limit is recommended, in bytes.
	 *
	 * @var int
	 */
	const MIN_MEMORY = 134217728;

	/**
	 * Check statuses, same wording as Site Health.
	 *
	 * @var array
	 */
	const STATUSES = array( 'good', 'recommended', 'info' );

	/**
	 * Get all environment checks.
	 *
	 * @return array - Checks with id, label, value, status, message and link (url, text).
	 *
	 * @since 1.0.0
	 */
	public static function get_checks() {
		$checks = array(
			self::check_php_version(),
			self::check_memory(),
			self::check_opcache(),
			self::check_object_cache(),
			self::check_page_cache(),
			self::check_cron(),
			self::check_database_server(),
			self::check_debug_mode(),
			self::check_debug_display(),
			self::check_debug_log(),
			self::check_autoload(),
			self::check_tables(),
		);

		/**
		 * Filter the environment checks. Use it to add, change or remove checks.
		 *
		 * @param array $checks - Checks with id, label, value, status (good, recommended or info), message and link (url, text).
		 *
		 * @since 1.0.0
		 */
		$checks = \apply_filters( 'bloatbuster_environment_checks', $checks );

		$valid = array();

		foreach ( (array) $checks as $check ) {
			if ( ! is_array( $check ) || empty( $check['id'] ) || empty( $check['label'] ) || empty( $check['status'] ) ) {
				continue;
			}

			$valid[] = self::normalize_check( $check );
		}

		return $valid;
	}

	/**
	 * Fill missing keys of a check and fall back to info for unknown statuses.
	 *
	 * @param array $check - Check data.
	 *
	 * @return array - Check with all keys set.
	 *
	 * @since 1.0.0
	 */
	public static function normalize_check( $check ) {
		$check = \wp_parse_args(
			$check,
			array(
				'value'   => '',
				'message' => '',
				'link'    => array(),
			)
		);

		if ( ! in_array( $check['status'], self::STATUSES, true ) ) {
			$check['status'] = 'info';
		}

		if ( ! is_array( $check['link'] ) || empty( $check['link']['url'] ) || empty( $check['link']['text'] ) ) {
			$check['link'] = array();
		}

		return $check;
	}

	/**
	 * Find a check by its id.
	 *
	 * @param array  $checks - Checks from get_checks().
	 * @param string $id     - Check id.
	 *
	 * @return array|null - The check, or null when it is not in the list.
	 *
	 * @since 1.0.0
	 */
	public static function find_check( $checks, $id ) {
		foreach ( $checks as $check ) {
			if ( $id === $check['id'] ) {
				return $check;
			}
		}

		return null;
	}

	/**
	 * Get the URL of a BloatBuster settings tab.
	 *
	 * @param string $slug - Tab slug.
	 *
	 * @return string - Tab URL.
	 *
	 * @since 1.0.0
	 */
	public static function get_tab_url( $slug ) {
		return \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION . '&tab=' . $slug );
	}

	/**
	 * Check if the site runs in production.
	 *
	 * @return bool - True for the production environment type.
	 *
	 * @since 1.0.0
	 */
	public static function is_production() {
		return 'production' === \wp_get_environment_type();
	}

	/**
	 * Check the PHP version against the WordPress.org recommendation.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_php_version() {
		$check = array(
			'id'     => 'php_version',
			'label'  => \__( 'PHP version', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => PHP_VERSION,
			'status' => 'good',
			'link'   => array(
				'url'  => \wp_get_update_php_url(),
				'text' => \__( 'Learn more about updating PHP', 'bloatbuster-performance-asset-cleaner' ),
			),
		);

		// Cached by WordPress for a week, the dashboard usually keeps it fresh.
		$response = function_exists( 'wp_check_php_version' ) ? \wp_check_php_version() : false;

		if ( ! is_array( $response ) || empty( $response['recommended_version'] ) ) {
			$check['status']  = 'info';
			$check['message'] = \__( 'Could not get the recommended PHP version from WordPress.org.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		if ( empty( $response['is_secure'] ) ) {
			$check['status'] = 'recommended';
			/* translators: %s: recommended PHP version, e.g. 8.3. */
			$check['message'] = sprintf( \__( 'This PHP version no longer gets security updates. Ask your host to update to PHP %s or newer.', 'bloatbuster-performance-asset-cleaner' ), $response['recommended_version'] );
		} elseif ( empty( $response['is_supported'] ) || version_compare( PHP_VERSION, $response['recommended_version'], '<' ) ) {
			$check['status'] = 'recommended';
			/* translators: %s: recommended PHP version, e.g. 8.3. */
			$check['message'] = sprintf( \__( 'Newer PHP versions are faster. WordPress recommends PHP %s or newer.', 'bloatbuster-performance-asset-cleaner' ), $response['recommended_version'] );
		} else {
			$check['message'] = \__( 'You run a recommended PHP version.', 'bloatbuster-performance-asset-cleaner' );
			$check['link']    = array();
		}

		return $check;
	}

	/**
	 * Check the PHP memory limit and the WordPress memory constants.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_memory() {
		$limit = (string) ini_get( 'memory_limit' );
		$bytes = \wp_convert_hr_to_bytes( $limit );

		if ( -1 === $bytes ) {
			$value = \__( 'Unlimited', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$value = \size_format( $bytes );
		}

		$check = array(
			'id'     => 'memory',
			'label'  => \__( 'PHP memory limit', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => $value,
			'status' => 'good',
		);

		$constants = sprintf(
			/* translators: 1: WP_MEMORY_LIMIT value, e.g. 40M, 2: WP_MAX_MEMORY_LIMIT value, e.g. 256M. */
			\__( 'WP_MEMORY_LIMIT is %1$s, WP_MAX_MEMORY_LIMIT (admin) is %2$s.', 'bloatbuster-performance-asset-cleaner' ),
			defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : '-',
			defined( 'WP_MAX_MEMORY_LIMIT' ) ? WP_MAX_MEMORY_LIMIT : '-'
		);

		if ( -1 !== $bytes && $bytes < self::MIN_MEMORY ) {
			$check['status'] = 'recommended';
			/* translators: %s: recommended memory size, e.g. 128 MB. */
			$check['message'] = sprintf( \__( 'Below %s. Larger sites and page builders can run out of memory.', 'bloatbuster-performance-asset-cleaner' ), \size_format( self::MIN_MEMORY ) ) . ' ' . $constants;
		} else {
			$check['message'] = \__( 'Enough memory for most sites. The admin limit may be raised by WordPress.', 'bloatbuster-performance-asset-cleaner' ) . ' ' . $constants;
		}

		return $check;
	}

	/**
	 * Check if OPcache is on, and its memory and hit rate when the status can be read.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_opcache() {
		$check = array(
			'id'     => 'opcache',
			'label'  => \__( 'OPcache', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => \__( 'On', 'bloatbuster-performance-asset-cleaner' ),
			'status' => 'good',
		);

		$enable = ini_get( 'opcache.enable' );

		if ( false === $enable ) {
			$check['value']   = \__( 'Not installed', 'bloatbuster-performance-asset-cleaner' );
			$check['status']  = 'recommended';
			$check['message'] = \__( 'OPcache keeps compiled PHP code in memory, so PHP does not read and compile files on every request. Ask your host to turn it on.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		if ( ! self::is_ini_on( $enable ) ) {
			$check['value']   = \__( 'Off', 'bloatbuster-performance-asset-cleaner' );
			$check['status']  = 'recommended';
			$check['message'] = \__( 'OPcache is installed but turned off (opcache.enable). Turning it on is one of the biggest speed gains for PHP.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$status = self::get_opcache_status();

		if ( null === $status ) {
			$check['message'] = \__( 'OPcache is on. Memory and hit rate are not available, the server restricts the OPcache status.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		if ( empty( $status['opcache_enabled'] ) ) {
			$check['value']   = \__( 'Off', 'bloatbuster-performance-asset-cleaner' );
			$check['status']  = 'recommended';
			$check['message'] = \__( 'OPcache is turned on in the settings but is not running. Ask your host to check the OPcache setup.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$used     = isset( $status['memory_usage']['used_memory'] ) ? (int) $status['memory_usage']['used_memory'] : 0;
		$free     = isset( $status['memory_usage']['free_memory'] ) ? (int) $status['memory_usage']['free_memory'] : 0;
		$hit_rate = isset( $status['opcache_statistics']['opcache_hit_rate'] ) ? (float) $status['opcache_statistics']['opcache_hit_rate'] : 0;

		$check['message'] = sprintf(
			/* translators: 1: used memory, e.g. 64 MB, 2: free memory, e.g. 64 MB, 3: hit rate percentage, e.g. 99.5. */
			\__( 'Memory: %1$s used, %2$s free. Hit rate: %3$s%%.', 'bloatbuster-performance-asset-cleaner' ),
			\size_format( $used, 1 ),
			\size_format( $free, 1 ),
			\number_format_i18n( $hit_rate, 1 )
		);

		if ( ! empty( $status['cache_full'] ) ) {
			$check['status']   = 'recommended';
			$check['message'] .= ' ' . \__( 'The OPcache memory is full, new files are not cached. Ask your host to raise opcache.memory_consumption.', 'bloatbuster-performance-asset-cleaner' );
		}

		return $check;
	}

	/**
	 * Get the OPcache status, only when the server allows reading it.
	 *
	 * @return array|null - OPcache status without the scripts list, or null when not available.
	 *
	 * @since 1.0.0
	 */
	public static function get_opcache_status() {
		if ( ! function_exists( 'opcache_get_status' ) ) {
			return null;
		}

		// With restrict_api set, opcache_get_status() raises a warning for scripts outside that path.
		if ( '' !== (string) ini_get( 'opcache.restrict_api' ) ) {
			return null;
		}

		// On PHP 7.4 disabled functions still exist and raise a warning when called.
		if ( false !== stripos( (string) ini_get( 'disable_functions' ), 'opcache_get_status' ) ) {
			return null;
		}

		$status = opcache_get_status( false );

		// The API restriction is checked above, so false means OPcache did not start.
		if ( false === $status ) {
			return array( 'opcache_enabled' => false );
		}

		if ( ! is_array( $status ) ) {
			return null;
		}

		return $status;
	}

	/**
	 * Check if a persistent object cache is used.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_object_cache() {
		$check = array(
			'id'     => 'object_cache',
			'label'  => \__( 'Persistent object cache', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => \__( 'Not detected', 'bloatbuster-performance-asset-cleaner' ),
			'status' => 'info',
		);

		if ( ! \wp_using_ext_object_cache() ) {
			$check['message'] = \__( 'Not detected. A persistent object cache, like Redis or Memcached, can help database-heavy sites.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$dropins = self::get_dropins();

		$check['status']  = 'good';
		$check['value']   = ! empty( $dropins['object-cache.php']['Name'] ) ? $dropins['object-cache.php']['Name'] : \__( 'Active', 'bloatbuster-performance-asset-cleaner' );
		$check['message'] = \__( 'Database query results are kept in memory between requests.', 'bloatbuster-performance-asset-cleaner' );

		return $check;
	}

	/**
	 * Check for a page cache plugin or an advanced-cache.php drop-in, without HTTP requests.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_page_cache() {
		$check = array(
			'id'     => 'page_cache',
			'label'  => \__( 'Page cache', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => \__( 'Not detected', 'bloatbuster-performance-asset-cleaner' ),
			'status' => 'info',
		);

		$wp_cache = defined( 'WP_CACHE' ) && WP_CACHE;
		$dropins  = self::get_dropins();
		$dropin   = isset( $dropins['advanced-cache.php'] );
		$plugin   = self::get_page_cache_plugin();

		if ( '' !== $plugin ) {
			$check['value']   = $plugin;
			$check['status']  = 'good';
			$check['message'] = \__( 'A page cache plugin is active. Check its settings to make sure page caching is turned on.', 'bloatbuster-performance-asset-cleaner' );
		} elseif ( $wp_cache && $dropin ) {
			$check['value']   = ! empty( $dropins['advanced-cache.php']['Name'] ) ? $dropins['advanced-cache.php']['Name'] : 'advanced-cache.php';
			$check['status']  = 'good';
			$check['message'] = \__( 'WP_CACHE is on and an advanced-cache.php drop-in is loaded.', 'bloatbuster-performance-asset-cleaner' );
		} elseif ( $dropin ) {
			$check['value']   = \__( 'Not loaded', 'bloatbuster-performance-asset-cleaner' );
			$check['status']  = 'recommended';
			$check['message'] = \__( 'An advanced-cache.php drop-in exists, but WP_CACHE is off, so it is not loaded. Turn WP_CACHE on in wp-config.php or remove the leftover file.', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$check['message'] = \__( 'Not detected, a server or host cache may still be active.', 'bloatbuster-performance-asset-cleaner' );
		}

		return $check;
	}

	/**
	 * Get the name of the first active known page cache plugin.
	 *
	 * @return string - Plugin name, or empty string when none is found.
	 *
	 * @since 1.0.0
	 */
	public static function get_page_cache_plugin() {
		$plugins = array(
			'WP Rocket'        => defined( 'WP_ROCKET_VERSION' ),
			'W3 Total Cache'   => defined( 'W3TC' ),
			'LiteSpeed Cache'  => defined( 'LSCWP_V' ),
			'WP Super Cache'   => defined( 'WPCACHEHOME' ),
			'WP Fastest Cache' => class_exists( 'WpFastestCache', false ),
			'Cache Enabler'    => class_exists( 'Cache_Enabler', false ),
		);

		foreach ( $plugins as $name => $is_active ) {
			if ( $is_active ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Get the installed drop-ins.
	 *
	 * @return array - Drop-in data keyed by file name.
	 *
	 * @since 1.0.0
	 */
	public static function get_dropins() {
		if ( ! function_exists( 'get_dropins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return (array) \get_dropins();
	}

	/**
	 * Check the WP-Cron mode and overdue events.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_cron() {
		$status = Cron_Status::get_status();

		if ( $status['disabled'] ) {
			$value = \__( 'Off on page visits (DISABLE_WP_CRON)', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$value = \__( 'Runs on page visits', 'bloatbuster-performance-asset-cleaner' );
		}

		if ( $status['alternate'] ) {
			$value .= ', ALTERNATE_WP_CRON';
		}

		$check = array(
			'id'     => 'cron',
			'label'  => \__( 'WP-Cron', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => $value,
			'status' => 'good',
			'link'   => array(
				'url'  => self::get_tab_url( Tab_Background::get_slug() ),
				'text' => \__( 'View scheduled events', 'bloatbuster-performance-asset-cleaner' ),
			),
		);

		if ( $status['overdue'] > 0 ) {
			$check['status'] = 'recommended';
			/* translators: %d: number of overdue events. */
			$check['message'] = sprintf( \_n( '%d scheduled event is overdue, WP-Cron may not be running.', '%d scheduled events are overdue, WP-Cron may not be running.', $status['overdue'], 'bloatbuster-performance-asset-cleaner' ), $status['overdue'] );

			if ( $status['disabled'] ) {
				$check['message'] .= ' ' . \__( 'Check that the server cron job calls wp-cron.php.', 'bloatbuster-performance-asset-cleaner' );
			}
		} elseif ( $status['disabled'] ) {
			$check['message'] = \__( 'Scheduled events run on time. Page visits don\'t run WP-Cron, so a server cron job must call wp-cron.php.', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$check['message'] = \__( 'Scheduled events run on time.', 'bloatbuster-performance-asset-cleaner' );
		}

		return $check;
	}

	/**
	 * Show the database server type and version.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_database_server() {
		global $wpdb;

		$info = (string) $wpdb->db_server_info();

		// MariaDB may report a "5.5.5-" prefix before its real version.
		if ( preg_match( '/(\d+\.\d+(?:\.\d+)?)-MariaDB/i', $info, $matches ) ) {
			$value = 'MariaDB ' . $matches[1];
		} elseif ( false !== stripos( $info, 'mariadb' ) ) {
			$value = 'MariaDB ' . $wpdb->db_version();
		} else {
			$value = 'MySQL ' . $wpdb->db_version();
		}

		return array(
			'id'      => 'database_server',
			'label'   => \__( 'Database server', 'bloatbuster-performance-asset-cleaner' ),
			'value'   => $value,
			'status'  => 'info',
			'message' => \__( 'Site Health checks this version against the WordPress requirements.', 'bloatbuster-performance-asset-cleaner' ),
		);
	}

	/**
	 * Check WP_DEBUG, SAVEQUERIES and SCRIPT_DEBUG.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_debug_mode() {
		$enabled = array();

		foreach ( array( 'WP_DEBUG', 'SAVEQUERIES', 'SCRIPT_DEBUG' ) as $constant ) {
			if ( defined( $constant ) && constant( $constant ) ) {
				$enabled[] = $constant;
			}
		}

		$check = array(
			'id'     => 'debug_mode',
			'label'  => \__( 'Debug mode', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => empty( $enabled ) ? \__( 'Off', 'bloatbuster-performance-asset-cleaner' ) : implode( ', ', $enabled ),
			'status' => 'good',
		);

		if ( empty( $enabled ) ) {
			$check['message'] = \__( 'WP_DEBUG, SAVEQUERIES and SCRIPT_DEBUG are off.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$check['message'] = \__( 'WP_DEBUG reports every PHP notice, SAVEQUERIES keeps every database query in memory and SCRIPT_DEBUG loads unminified files. Each adds work to every request.', 'bloatbuster-performance-asset-cleaner' );

		if ( self::is_production() ) {
			$check['status']   = 'recommended';
			$check['message'] .= ' ' . \__( 'Turn them off in wp-config.php on a production site.', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$check['status']   = 'info';
			$check['message'] .= ' ' . \__( 'Fine on a development or staging site.', 'bloatbuster-performance-asset-cleaner' );
		}

		return $check;
	}

	/**
	 * Check if PHP errors are shown on screen.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_debug_display() {
		// WordPress applies WP_DEBUG_DISPLAY to display_errors, so the ini value is the result.
		$shown = self::is_ini_on( ini_get( 'display_errors' ) );

		$check = array(
			'id'     => 'debug_display',
			'label'  => \__( 'Errors on screen', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => $shown ? \__( 'Shown', 'bloatbuster-performance-asset-cleaner' ) : \__( 'Hidden', 'bloatbuster-performance-asset-cleaner' ),
			'status' => 'good',
		);

		if ( ! $shown ) {
			$check['message'] = \__( 'PHP errors are not shown to visitors.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$check['message'] = \__( 'PHP errors are printed on the page (WP_DEBUG_DISPLAY or the PHP display_errors setting). This can break page layout and show file paths to visitors.', 'bloatbuster-performance-asset-cleaner' );

		if ( self::is_production() ) {
			$check['status']   = 'recommended';
			$check['message'] .= ' ' . \__( 'Set WP_DEBUG_DISPLAY to false on a production site.', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$check['status'] = 'info';
		}

		return $check;
	}

	/**
	 * Check where the WordPress debug log is written.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_debug_log() {
		$check = array(
			'id'     => 'debug_log',
			'label'  => \__( 'Debug log', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => \__( 'Off', 'bloatbuster-performance-asset-cleaner' ),
			'status' => 'good',
		);

		$log = defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : false;

		// WordPress only uses WP_DEBUG_LOG when WP_DEBUG is on.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! $log ) {
			$check['message'] = \__( 'WordPress does not write a debug log.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		if ( is_string( $log ) && ! in_array( strtolower( $log ), array( '1', 'true' ), true ) ) {
			$path = $log;
		} else {
			$path = WP_CONTENT_DIR . '/debug.log';
		}

		$check['value'] = $path;

		if ( ! self::is_public_path( $path ) ) {
			$check['status']  = 'info';
			$check['message'] = \__( 'The log file is outside the WordPress folders and the web root. Make sure the web server does not serve it.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$check['message'] = \__( 'The log file is inside a public folder, anyone who knows the URL may download it.', 'bloatbuster-performance-asset-cleaner' );

		if ( self::is_production() ) {
			$check['status']   = 'recommended';
			$check['message'] .= ' ' . \__( 'Turn WP_DEBUG_LOG off or set it to a path outside the web root.', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$check['status'] = 'info';
		}

		return $check;
	}

	/**
	 * Check if a file path is inside the WordPress folder, the wp-content folder or the web root.
	 *
	 * @param string $path - File path.
	 *
	 * @return bool - True when the path is inside a folder served by the web server.
	 *
	 * @since 1.0.0
	 */
	public static function is_public_path( $path ) {
		$path    = \wp_normalize_path( $path );
		$folders = array( ABSPATH, WP_CONTENT_DIR );

		// WordPress may be installed in a subfolder of the web root, e.g. /var/www/html/wordpress/.
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$folders[] = \sanitize_text_field( \wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );
		}

		foreach ( $folders as $folder ) {
			$folder = \trailingslashit( \wp_normalize_path( $folder ) );

			// A root folder would match every path.
			if ( '/' === $folder ) {
				continue;
			}

			if ( 0 === stripos( $path, $folder ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check the total size of autoloaded options.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_autoload() {
		$summary    = Autoload_Options::get_summary();
		$limit_size = \size_format( $summary['limit'] );

		$check = array(
			'id'     => 'autoload',
			'label'  => \__( 'Autoloaded options', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => \size_format( $summary['size'], 2 ),
			'status' => 'good',
			'link'   => array(
				'url'  => self::get_tab_url( Tab_Autoload::get_slug() ),
				'text' => \__( 'Review autoloaded options', 'bloatbuster-performance-asset-cleaner' ),
			),
		);

		if ( $summary['size'] > $summary['limit'] ) {
			$check['status'] = 'recommended';
			/* translators: %s: recommended size limit, e.g. 781 KB. */
			$check['message'] = sprintf( \__( 'Above the recommended limit of %s. Autoloaded options are loaded on every request.', 'bloatbuster-performance-asset-cleaner' ), $limit_size );
		} else {
			/* translators: %s: recommended size limit, e.g. 781 KB. */
			$check['message'] = sprintf( \__( 'Below the recommended limit of %s.', 'bloatbuster-performance-asset-cleaner' ), $limit_size );
		}

		return $check;
	}

	/**
	 * Show the total database size and the largest tables.
	 *
	 * @return array - Check data.
	 *
	 * @since 1.0.0
	 */
	public static function check_tables() {
		$stats = Database_Tools::get_table_stats();

		$check = array(
			'id'     => 'tables',
			'label'  => \__( 'Database size', 'bloatbuster-performance-asset-cleaner' ),
			'value'  => \size_format( $stats['totals']['size'], 1 ),
			'status' => 'info',
			'link'   => array(
				'url'  => self::get_tab_url( Tab_Database::get_slug() ),
				'text' => \__( 'Open database tools', 'bloatbuster-performance-asset-cleaner' ),
			),
		);

		if ( empty( $stats['tables'] ) ) {
			$check['value']   = '-';
			$check['message'] = \__( 'Table sizes are not available.', 'bloatbuster-performance-asset-cleaner' );

			return $check;
		}

		$largest = array();

		foreach ( array_slice( $stats['tables'], 0, 3 ) as $table ) {
			$largest[] = $table['name'] . ' (' . \size_format( $table['size'], 1 ) . ')';
		}

		$check['message'] = sprintf(
			/* translators: 1: number of tables, 2: comma separated list of table names with sizes. */
			\_n( '%1$d table. Largest: %2$s.', '%1$d tables. Largest: %2$s.', $stats['totals']['count'], 'bloatbuster-performance-asset-cleaner' ),
			$stats['totals']['count'],
			implode( ', ', $largest )
		);

		return $check;
	}

	/**
	 * Check if a PHP ini flag is on.
	 *
	 * @param string|false $value - Value from ini_get().
	 *
	 * @return bool - True for 1, on, yes or true.
	 *
	 * @since 1.0.0
	 */
	public static function is_ini_on( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'on', 'yes', 'true' ), true );
	}
}
