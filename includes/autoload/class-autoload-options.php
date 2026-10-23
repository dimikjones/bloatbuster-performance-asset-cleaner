<?php
/**
 * Autoloaded options data layer.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, changes, exports and restores options in the options table.
 */
final class Autoload_Options {

	/**
	 * Core WordPress options. They are protected from autoload changes and deletion.
	 *
	 * @var array
	 */
	const CORE_OPTIONS = array(
		'siteurl',
		'home',
		'blogname',
		'blogdescription',
		'users_can_register',
		'admin_email',
		'new_admin_email',
		'adminhash',
		'start_of_week',
		'use_balanceTags',
		'use_smilies',
		'require_name_email',
		'comments_notify',
		'posts_per_rss',
		'rss_use_excerpt',
		'mailserver_url',
		'mailserver_login',
		'mailserver_pass',
		'mailserver_port',
		'default_category',
		'default_comment_status',
		'default_ping_status',
		'default_pingback_flag',
		'posts_per_page',
		'date_format',
		'time_format',
		'links_updated_date_format',
		'comment_moderation',
		'moderation_notify',
		'permalink_structure',
		'rewrite_rules',
		'hack_file',
		'blog_charset',
		'moderation_keys',
		'active_plugins',
		'category_base',
		'ping_sites',
		'comment_max_links',
		'gmt_offset',
		'default_email_category',
		'recently_edited',
		'template',
		'stylesheet',
		'comment_registration',
		'html_type',
		'use_trackback',
		'default_role',
		'db_version',
		'db_upgraded',
		'initial_db_version',
		'uploads_use_yearmonth_folders',
		'upload_path',
		'upload_url_path',
		'blog_public',
		'default_link_category',
		'show_on_front',
		'tag_base',
		'show_avatars',
		'avatar_rating',
		'avatar_default',
		'thumbnail_size_w',
		'thumbnail_size_h',
		'thumbnail_crop',
		'medium_size_w',
		'medium_size_h',
		'medium_large_size_w',
		'medium_large_size_h',
		'large_size_w',
		'large_size_h',
		'image_default_link_type',
		'image_default_size',
		'image_default_align',
		'close_comments_for_old_posts',
		'close_comments_days_old',
		'thread_comments',
		'thread_comments_depth',
		'page_comments',
		'comments_per_page',
		'default_comments_page',
		'comment_order',
		'sticky_posts',
		'uninstall_plugins',
		'timezone_string',
		'page_for_posts',
		'page_on_front',
		'default_post_format',
		'link_manager_enabled',
		'finished_splitting_shared_terms',
		'site_icon',
		'wp_page_for_privacy_policy',
		'show_comments_cookies_opt_in',
		'admin_email_lifespan',
		'disallowed_keys',
		'comment_previously_approved',
		'auto_plugin_theme_update_emails',
		'auto_update_core_dev',
		'auto_update_core_minor',
		'auto_update_core_major',
		'auto_update_plugins',
		'auto_update_themes',
		'wp_force_deactivated_plugins',
		'wp_attachment_pages_enabled',
		'fresh_site',
		'WPLANG',
		'cron',
		'sidebars_widgets',
		'current_theme',
		'theme_switched',
		'recently_activated',
		'can_compress_scripts',
		'user_count',
		'site_logo',
		'nav_menu_options',
		'wp_calendar_block_has_published_posts',
	);

	/**
	 * Autoload column values that can be stored.
	 *
	 * @var array
	 */
	const AUTOLOAD_VALUES = array( 'yes', 'no', 'on', 'off', 'auto', 'auto-on', 'auto-off' );

	/**
	 * Get the autoload column values that WordPress loads on every request.
	 *
	 * @return array - Autoload values.
	 *
	 * @since 1.0.0
	 */
	public static function get_autoload_values() {
		return \wp_autoload_values_to_autoload();
	}

	/**
	 * Check if an autoload column value means the option is autoloaded.
	 *
	 * @param string $autoload - Autoload column value.
	 *
	 * @return bool - True if autoloaded.
	 *
	 * @since 1.0.0
	 */
	public static function is_autoloaded( $autoload ) {
		return in_array( $autoload, self::get_autoload_values(), true );
	}

	/**
	 * Get the size from which an autoloaded option needs review.
	 *
	 * @return int - Size in bytes.
	 *
	 * @since 1.0.0
	 */
	public static function get_large_threshold() {
		/**
		 * Filter the size from which an autoloaded option needs review.
		 *
		 * @param int $threshold - Size in bytes. Default 10 KB.
		 *
		 * @since 1.0.0
		 */
		return (int) \apply_filters( 'bloatbuster_autoload_large_threshold', 10 * KB_IN_BYTES );
	}

	/**
	 * Get the full list of option names that are core WordPress options.
	 *
	 * @return array - Option names.
	 *
	 * @since 1.0.0
	 */
	public static function get_core_options() {
		global $wpdb;

		$options   = self::CORE_OPTIONS;
		$options[] = $wpdb->get_blog_prefix() . 'user_roles';

		return $options;
	}

	/**
	 * Check if an option is protected from autoload changes and deletion.
	 *
	 * @param string $name - Option name.
	 *
	 * @return bool - True if protected.
	 *
	 * @since 1.0.0
	 */
	public static function is_protected( $name ) {
		$protected   = self::get_core_options();
		$protected[] = BLOATBUSTER_OPTION;

		/**
		 * Filter the options protected from autoload changes and deletion.
		 *
		 * @param array $protected - Option names.
		 *
		 * @since 1.0.0
		 */
		$protected = (array) \apply_filters( 'bloatbuster_autoload_protected_options', $protected );

		return in_array( $name, $protected, true );
	}

	/**
	 * Check if an option name looks like it holds a secret, like an API key.
	 *
	 * @param string $name - Option name.
	 *
	 * @return bool - True if the value should be masked.
	 *
	 * @since 1.0.0
	 */
	public static function is_sensitive( $name ) {
		$patterns = array( 'key', 'secret', 'token', 'pass', 'license', 'licence', 'auth', 'salt', 'nonce', 'private', 'credential', 'api' );

		/**
		 * Filter the name parts that mark an option value as sensitive.
		 *
		 * @param array $patterns - Case-insensitive name parts.
		 *
		 * @since 1.0.0
		 */
		$patterns = (array) \apply_filters( 'bloatbuster_autoload_sensitive_patterns', $patterns );

		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $name, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the summary of autoloaded options.
	 *
	 * @return array - Count, size, largest option name and size, needs review count and Site Health limit.
	 *
	 * @since 1.0.0
	 */
	public static function get_summary() {
		global $wpdb;

		$values       = self::get_autoload_values();
		$placeholders = self::get_placeholders( count( $values ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live report, placeholders built from the values count.
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(LENGTH(option_value)) AS size, SUM(LENGTH(option_value) >= %d) AS review FROM {$wpdb->options} WHERE autoload IN ( {$placeholders} )",
				array_merge( array( self::get_large_threshold() ), $values )
			)
		);

		$largest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_name AS name, LENGTH(option_value) AS size FROM {$wpdb->options} WHERE autoload IN ( {$placeholders} ) ORDER BY size DESC LIMIT 1",
				$values
			)
		);
		// phpcs:enable

		return array(
			'count'        => isset( $totals->total ) ? (int) $totals->total : 0,
			'size'         => isset( $totals->size ) ? (int) $totals->size : 0,
			'review'       => isset( $totals->review ) ? (int) $totals->review : 0,
			'largest_name' => isset( $largest->name ) ? (string) $largest->name : '',
			'largest_size' => isset( $largest->size ) ? (int) $largest->size : 0,
			/** This filter is documented in wp-admin/includes/class-wp-site-health.php */
			'limit'        => (int) \apply_filters( 'site_status_autoloaded_options_size_limit', 800000 ),
		);
	}

	/**
	 * Get a page of options with name, size and autoload value.
	 *
	 * @param array $args - Query args: view, search, include_transients, orderby, order, per_page and page.
	 *
	 * @return array - Items and total count.
	 *
	 * @since 1.0.0
	 */
	public static function query( $args ) {
		global $wpdb;

		$args = \wp_parse_args(
			$args,
			array(
				'view'               => 'all',
				'search'             => '',
				'orderby'            => 'size',
				'order'              => 'desc',
				'per_page'           => 50,
				'page'               => 1,
				'include_transients' => false,
			)
		);

		$orderby_columns = array(
			'name'     => 'option_name',
			'size'     => 'size',
			'autoload' => 'autoload',
		);

		$orderby  = isset( $orderby_columns[ $args['orderby'] ] ) ? $orderby_columns[ $args['orderby'] ] : 'size';
		$order    = 'asc' === $args['order'] ? 'ASC' : 'DESC';
		$per_page = max( 1, \absint( $args['per_page'] ) );
		$offset   = ( max( 1, \absint( $args['page'] ) ) - 1 ) * $per_page;
		$where    = self::get_where( $args );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live list, WHERE built with placeholders, ORDER BY from a whitelist.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name AS name, LENGTH(option_value) AS size, autoload FROM {$wpdb->options} WHERE {$where['sql']} ORDER BY {$orderby} {$order}, option_name ASC LIMIT %d OFFSET %d",
				array_merge( $where['args'], array( $per_page, $offset ) )
			)
		);
		// phpcs:enable

		$items = array();

		foreach ( $rows as $row ) {
			$items[] = array(
				'name'     => (string) $row->name,
				'size'     => (int) $row->size,
				'autoload' => (string) $row->autoload,
			);
		}

		return array(
			'items' => $items,
			'total' => self::count( $args ),
		);
	}

	/**
	 * Count options for a view.
	 *
	 * @param array $args - Query args: view, search and include_transients.
	 *
	 * @return int - Number of options.
	 *
	 * @since 1.0.0
	 */
	public static function count( $args ) {
		global $wpdb;

		$where = self::get_where( $args );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live count, WHERE built with placeholders.
		if ( empty( $where['args'] ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE {$where['sql']}" );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE {$where['sql']}", $where['args'] ) );
		// phpcs:enable
	}

	/**
	 * Build the WHERE clause for a view and search.
	 *
	 * Transient values and timeout rows are hidden unless explicitly included.
	 * Autoload status does not establish whether a transient expires.
	 *
	 * @param array $args - Query args: view, search and include_transients.
	 *
	 * @return array - SQL with placeholders and its args.
	 *
	 * @since 1.0.0
	 */
	public static function get_where( $args ) {
		global $wpdb;

		$sql    = array( '1=1' );
		$params = array();
		$view   = isset( $args['view'] ) ? $args['view'] : 'all';
		$values = self::get_autoload_values();

		if ( 'autoloaded' === $view || 'review' === $view ) {
			$sql[]  = 'autoload IN ( ' . self::get_placeholders( count( $values ) ) . ' )';
			$params = array_merge( $params, $values );
		} elseif ( 'not_autoloaded' === $view ) {
			$sql[]  = 'autoload NOT IN ( ' . self::get_placeholders( count( $values ) ) . ' )';
			$params = array_merge( $params, $values );
		}

		if ( 'review' === $view ) {
			$sql[]    = 'LENGTH(option_value) >= %d';
			$params[] = self::get_large_threshold();
		}

		if ( empty( $args['include_transients'] ) ) {
			$sql[]    = '( option_name NOT LIKE %s AND option_name NOT LIKE %s )';
			$params[] = $wpdb->esc_like( '_transient_' ) . '%';
			$params[] = $wpdb->esc_like( '_site_transient_' ) . '%';
		}

		// Not empty(), '0' is a valid search term.
		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$sql[]    = 'option_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		return array(
			'sql'  => implode( ' AND ', $sql ),
			'args' => $params,
		);
	}

	/**
	 * Get raw database rows for options, without option filters or unserializing.
	 *
	 * @param array $names - Option names.
	 *
	 * @return array - Rows with name, value and autoload, keyed by name.
	 *
	 * @since 1.0.0
	 */
	public static function get_raw_options( $names ) {
		global $wpdb;

		$names   = array_values( $names );
		$options = array();

		if ( empty( $names ) ) {
			return $options;
		}

		foreach ( array_chunk( $names, 500 ) as $chunk ) {
			$placeholders = self::get_placeholders( count( $chunk ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Raw values on purpose, placeholders built from the names count.
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name AS name, option_value AS value, autoload FROM {$wpdb->options} WHERE option_name IN ( {$placeholders} )",
					$chunk
				)
			);
			// phpcs:enable

			foreach ( $rows as $row ) {
				$options[ $row->name ] = array(
					'name'     => (string) $row->name,
					'value'    => (string) $row->value,
					'autoload' => (string) $row->autoload,
				);
			}
		}

		return $options;
	}

	/**
	 * Get the existing option names from a list of names.
	 *
	 * @param array $names - Option names.
	 *
	 * @return array - Existing option names.
	 *
	 * @since 1.0.0
	 */
	public static function get_existing_names( $names ) {
		global $wpdb;

		$names    = array_values( $names );
		$existing = array();

		foreach ( array_chunk( $names, 500 ) as $chunk ) {
			$placeholders = self::get_placeholders( count( $chunk ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live check, placeholders built from the names count.
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name IN ( {$placeholders} )",
					$chunk
				)
			);
			// phpcs:enable

			$existing = array_merge( $existing, $found );
		}

		return $existing;
	}

	/**
	 * Clean submitted option names, keeping only existing options.
	 *
	 * Names are not run through sanitize_text_field(), it would change some
	 * names. They are only used as prepared query values and API arguments.
	 *
	 * @param mixed $raw_names       - Submitted option names, slashed.
	 * @param bool  $allow_protected - True to keep protected options.
	 *
	 * @return array - Clean names and the number of skipped protected options.
	 *
	 * @since 1.0.0
	 */
	public static function filter_names( $raw_names, $allow_protected = false ) {
		$names   = array();
		$skipped = 0;

		foreach ( (array) $raw_names as $raw_name ) {
			if ( ! is_string( $raw_name ) ) {
				continue;
			}

			$name = \wp_unslash( $raw_name );

			if ( '' === $name || strlen( $name ) > 191 ) {
				continue;
			}

			if ( ! $allow_protected && self::is_protected( $name ) ) {
				++$skipped;
				continue;
			}

			$names[ $name ] = $name;
		}

		return array(
			'names'   => self::get_existing_names( $names ),
			'skipped' => $skipped,
		);
	}

	/**
	 * Get the potential timeout name of any ordinary transient option.
	 *
	 * @param string $name - Option name.
	 *
	 * @return string - Timeout option name, or an empty string for other options.
	 *
	 * @since 1.0.0
	 */
	public static function get_transient_timeout_name( $name ) {
		$name = (string) $name;

		if ( 0 !== strpos( $name, '_transient_' ) ) {
			return '';
		}

		return '_transient_timeout_' . substr( $name, strlen( '_transient_' ) );
	}

	/**
	 * Find ordinary transient values with existing or incoming timeout rows.
	 *
	 * @param array $names            - Option names to check.
	 * @param array $additional_names - Timeout names that will be imported.
	 *
	 * @return array - Transient value names that must not have autoload enabled.
	 *
	 * @since 1.0.0
	 */
	public static function get_expiring_transient_names( $names, $additional_names = array() ) {
		$timeouts = array();

		foreach ( $names as $name ) {
			$timeout = self::get_transient_timeout_name( $name );

			if ( '' !== $timeout ) {
				$timeouts[ $timeout ] = $name;
			}
		}

		$existing = self::get_existing_names( array_keys( $timeouts ) );
		$found    = array_intersect_key( $timeouts, array_fill_keys( array_merge( $existing, $additional_names ), true ) );

		return array_values( $found );
	}

	/**
	 * Enable or disable autoload for options, without changing their values.
	 *
	 * @param array $names    - Option names.
	 * @param bool  $autoload - True to autoload.
	 *
	 * @return array - Changed and skipped option counts.
	 *
	 * @since 1.0.0
	 */
	public static function set_autoload( $names, $autoload ) {
		$names   = array_values( array_unique( $names ) );
		$blocked = $autoload ? self::get_expiring_transient_names( $names ) : array();
		$names   = array_values( array_diff( $names, $blocked ) );
		$results = empty( $names ) ? array() : \wp_set_options_autoload( $names, (bool) $autoload );

		return array(
			'changed' => count( array_filter( $results ) ),
			'skipped' => count( $blocked ),
		);
	}

	/**
	 * Delete options.
	 *
	 * @param array $names - Option names.
	 *
	 * @return int - Number of deleted options.
	 *
	 * @since 1.0.0
	 */
	public static function delete( $names ) {
		$deleted = 0;

		foreach ( $names as $name ) {
			if ( \delete_option( $name ) ) {
				++$deleted;
			}
		}

		return $deleted;
	}

	/**
	 * Build the export data for options.
	 *
	 * Values are the raw database strings. Values that are not valid UTF-8
	 * are base64 encoded so they are restored byte for byte.
	 *
	 * @param array $names - Option names.
	 *
	 * @return array - Export data.
	 *
	 * @since 1.0.0
	 */
	public static function get_export( $names ) {
		$options = array();

		foreach ( self::get_raw_options( $names ) as $option ) {
			$item = array(
				'name'     => $option['name'],
				'autoload' => $option['autoload'],
			);

			if ( \wp_check_invalid_utf8( $option['value'] ) === $option['value'] ) {
				$item['value'] = $option['value'];
			} else {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Keeps binary values intact in JSON.
				$item['value_base64'] = base64_encode( $option['value'] );
			}

			$options[] = $item;
		}

		return array(
			'plugin'   => 'bloatbuster',
			'version'  => BLOATBUSTER_VERSION,
			'site_url' => \home_url(),
			'created'  => \gmdate( 'c' ),
			'options'  => $options,
		);
	}

	/**
	 * Order import names so a transient value precedes its potential timeout.
	 *
	 * Every derived timeout name is longer than its value name. Sorting keys
	 * by length preserves numeric names and handles repeated timeout_ prefixes.
	 *
	 * @param string|int $first  - First option name, possibly a numeric array key.
	 * @param string|int $second - Second option name, possibly a numeric array key.
	 *
	 * @return int - Comparison of option name lengths.
	 *
	 * @since 1.0.0
	 */
	public static function compare_import_option_names( $first, $second ) {
		return strlen( (string) $first ) <=> strlen( (string) $second );
	}

	/**
	 * Restore options from export data.
	 *
	 * Missing options are created with their exported raw value. Existing
	 * options only get their autoload state restored, values are kept.
	 * Protected options are skipped.
	 *
	 * @param array $data - Decoded export data.
	 *
	 * @return array|\WP_Error - Counts of created, updated, unchanged and skipped options, or an error.
	 *
	 * @since 1.0.0
	 */
	public static function import( $data ) {
		global $wpdb;

		if ( ! is_array( $data ) || ! isset( $data['plugin'] ) || 'bloatbuster' !== $data['plugin'] || ! isset( $data['options'] ) || ! is_array( $data['options'] ) ) {
			return new \WP_Error( 'invalid_file', \__( 'This is not a BloatBuster options export file.', 'bloatbuster-performance-asset-cleaner' ) );
		}

		$options = self::get_valid_import_options( $data['options'] );

		if ( empty( $options ) ) {
			return new \WP_Error( 'no_options', \__( 'The file has no valid options to restore.', 'bloatbuster-performance-asset-cleaner' ) );
		}

		$counts = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'skipped'   => count( $data['options'] ) - count( $options ),
		);

		$names         = array_map( 'strval', array_keys( $options ) );
		$blocked       = array_fill_keys( self::get_expiring_transient_names( $names, $names ), true );
		$related_names = $names;

		foreach ( $names as $name ) {
			if ( 0 === strpos( $name, '_transient_timeout_' ) ) {
				$related_names[] = '_transient_' . substr( $name, strlen( '_transient_timeout_' ) );
			}
		}

		$current = self::get_raw_options( array_unique( $related_names ) );

		// Preserve option-name keys and restore each value before its potential timeout.
		uksort( $options, array( __CLASS__, 'compare_import_option_names' ) );

		foreach ( $options as $name => $option ) {
			$name            = (string) $name;
			$should_autoload = self::is_autoloaded( $option['autoload'] );

			if ( $should_autoload && isset( $blocked[ $name ] ) ) {
				++$counts['skipped'];
				continue;
			}

			if ( 0 === strpos( $name, '_transient_timeout_' ) ) {
				$value_name = '_transient_' . substr( $name, strlen( '_transient_timeout_' ) );

				if ( isset( $current[ $value_name ] ) && self::is_autoloaded( $current[ $value_name ]['autoload'] ) ) {
					++$counts['skipped'];
					continue;
				}
			}

			if ( isset( $current[ $name ] ) ) {

				if ( self::is_autoloaded( $current[ $name ]['autoload'] ) === $should_autoload ) {
					++$counts['unchanged'];
					continue;
				}

				if ( \wp_set_option_autoload( $name, $should_autoload ) ) {
					++$counts['updated'];
					$current[ $name ]['autoload'] = $should_autoload ? 'on' : 'off';
				}

				continue;
			}

			// Raw insert, the value is already in database format. Never unserialize uploaded data here.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Restores the raw stored value.
			$inserted = $wpdb->insert(
				$wpdb->options,
				array(
					'option_name'  => $name,
					'option_value' => $option['value'],
					'autoload'     => $option['autoload'],
				),
				array( '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				++$counts['created'];
				$current[ $name ] = $option;
				\wp_cache_delete( $name, 'options' );
			}
		}

		\wp_cache_delete( 'alloptions', 'options' );
		\wp_cache_delete( 'notoptions', 'options' );

		return $counts;
	}

	/**
	 * Validate the options of an export file.
	 *
	 * @param array $items - Exported options.
	 *
	 * @return array - Valid options keyed by name, each with value and autoload.
	 *
	 * @since 1.0.0
	 */
	public static function get_valid_import_options( $items ) {
		$options = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['name'], $item['autoload'] ) || ! is_string( $item['name'] ) || ! is_string( $item['autoload'] ) ) {
				continue;
			}

			$name = $item['name'];

			if ( '' === $name || strlen( $name ) > 191 || self::is_protected( $name ) ) {
				continue;
			}

			if ( ! in_array( $item['autoload'], self::AUTOLOAD_VALUES, true ) ) {
				continue;
			}

			if ( isset( $item['value'] ) && is_string( $item['value'] ) ) {
				$value = $item['value'];
			} elseif ( isset( $item['value_base64'] ) && is_string( $item['value_base64'] ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Restores binary values from the export.
				$value = base64_decode( $item['value_base64'], true );

				if ( false === $value ) {
					continue;
				}
			} else {
				continue;
			}

			$options[ $name ] = array(
				'value'    => $value,
				'autoload' => $item['autoload'],
			);
		}

		return $options;
	}

	/**
	 * Get a comma separated list of %s placeholders.
	 *
	 * @param int $count - Number of placeholders.
	 *
	 * @return string - Placeholders.
	 *
	 * @since 1.0.0
	 */
	public static function get_placeholders( $count ) {
		return implode( ', ', array_fill( 0, max( 1, $count ), '%s' ) );
	}
}
