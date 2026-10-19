<?php
/**
 * Database tools run from the settings page.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database cleanup actions.
 */
final class Database_Tools {

	/**
	 * Admin-post action name and nonce action.
	 *
	 * @var string
	 */
	const ACTION = 'bloatbuster_run_action';

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		\add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle_action' ) );
	}

	/**
	 * Count expired transients stored in the database.
	 *
	 * @return int - Number of expired transients.
	 *
	 * @since 1.0.0
	 */
	public static function count_expired_transients() {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live count, must not be cached.
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				$now
			)
		);

		if ( \is_multisite() && \is_main_network() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live count, must not be cached.
			$count += (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d",
					$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
					$now
				)
			);
		}

		return $count;
	}

	/**
	 * Get expired transients stored in the database, oldest first.
	 *
	 * @param int $limit - Maximum number of transients to return.
	 *
	 * @return array - List of transients with name, network and expired timestamp.
	 *
	 * @since 1.0.0
	 */
	public static function get_expired_transients( $limit = 50 ) {
		global $wpdb;

		$now        = time();
		$limit      = \absint( $limit );
		$transients = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live list, must not be cached.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name AS name, option_value AS expired FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND option_value < %d ORDER BY option_value ASC LIMIT %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				$now,
				$limit
			)
		);

		if ( \is_multisite() && \is_main_network() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live list, must not be cached.
			$network_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_key AS name, meta_value AS expired FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d ORDER BY meta_value ASC LIMIT %d",
					$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
					$now,
					$limit
				)
			);

			$rows = array_merge( $rows, $network_rows );
		}

		foreach ( $rows as $row ) {
			$is_network = 0 === strpos( $row->name, '_site_transient_timeout_' );
			$prefix     = $is_network ? '_site_transient_timeout_' : '_transient_timeout_';

			$transients[] = array(
				'name'    => substr( $row->name, strlen( $prefix ) ),
				'network' => $is_network,
				'expired' => (int) $row->expired,
			);
		}

		usort(
			$transients,
			array( __CLASS__, 'compare_expired' )
		);

		return array_slice( $transients, 0, $limit );
	}

	/**
	 * Sort callback, oldest expired transient first.
	 *
	 * @param array $a - First transient.
	 * @param array $b - Second transient.
	 *
	 * @return int - Comparison result.
	 *
	 * @since 1.0.0
	 */
	public static function compare_expired( $a, $b ) {
		return $a['expired'] <=> $b['expired'];
	}

	/**
	 * Delete expired transients from the database.
	 *
	 * @return int - Number of deleted transients.
	 *
	 * @since 1.0.0
	 */
	public static function clean_expired_transients() {
		$before = self::count_expired_transients();

		// Force database cleanup even when an external object cache is used.
		\delete_expired_transients( true );

		self::delete_orphaned_transient_timeouts();

		$after = self::count_expired_transients();

		return max( 0, $before - $after );
	}

	/**
	 * Delete expired transient timeout rows that have no transient value row.
	 *
	 * Core only deletes an expired transient when both the value and the timeout
	 * rows exist, so orphaned timeouts stay forever. Run after
	 * delete_expired_transients(), when every expired timeout left is an orphan.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function delete_orphaned_transient_timeouts() {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup query, the cache is cleared below.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE ( option_name LIKE %s OR option_name LIKE %s ) AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				$now
			)
		);

		if ( \is_multisite() && \is_main_network() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup query, network options are read from the database.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE %s AND meta_value < %d",
					$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
					$now
				)
			);
		}

		\wp_cache_delete( 'alloptions', 'options' );
		\wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Handle an action submitted from the settings page.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_action() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You are not allowed to do this.', 'bloatbuster-performance-asset-cleaner' ), '', array( 'response' => 403 ) );
		}

		\check_admin_referer( self::ACTION );

		$action = isset( $_POST['bloatbuster_action'] ) ? \sanitize_key( \wp_unslash( $_POST['bloatbuster_action'] ) ) : '';
		$args   = array( 'tab' => 'database' );

		switch ( $action ) {
			case 'clean_expired_transients':
				$args['bloatbuster_notice'] = 'transients_cleaned';
				$args['bloatbuster_count']  = self::clean_expired_transients();
				break;
		}

		\wp_safe_redirect( \add_query_arg( $args, \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION ) ) );
		exit;
	}
}
