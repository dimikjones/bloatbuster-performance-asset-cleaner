<?php
/**
 * Cleanup of plugin temporary data, through each plugin's own API.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of plugin data cleanups shown in the Database tab.
 *
 * Plugin data is never deleted by name patterns, a key that looks temporary can still be
 * needed by an active plugin. Each integration uses the plugin's own API or rules.
 */
final class Cleanup_Integrations {

	/**
	 * Maximum number of Action Scheduler actions deleted per run.
	 *
	 * @var int
	 */
	const ACTION_SCHEDULER_BATCH = 500;

	/**
	 * Maximum number of expired WooCommerce sessions deleted per run.
	 *
	 * @var int
	 */
	const WOO_SESSIONS_BATCH = 1000;

	/**
	 * Registered integrations of this request, keyed by ID.
	 *
	 * @var array|null
	 */
	private static $integrations = null;

	/**
	 * Get all registered integrations, built-in and added with the filter.
	 *
	 * @return array - Integrations keyed by ID, each with id, label, description, is_available, count and delete.
	 *
	 * @since 1.0.0
	 */
	public static function get_integrations() {
		if ( null !== self::$integrations ) {
			return self::$integrations;
		}

		/**
		 * Filters the plugin data cleanups shown in the Database tab.
		 *
		 * Each integration is an array with:
		 * - id:           unique ID, lowercase letters, numbers, dashes and underscores.
		 * - label:        name shown in the table.
		 * - description:  what is deleted.
		 * - is_available: callable, returns true when the plugin is active.
		 * - count:        callable, returns the number of items that would be deleted.
		 * - delete:       callable, deletes one batch and returns array( 'deleted' => int, 'remaining' => int ).
		 *
		 * @param array $integrations - List of integrations.
		 *
		 * @since 1.0.0
		 */
		$list = \apply_filters( 'bloatbuster_cleanup_integrations', self::get_builtin_integrations() );

		$integrations = array();

		foreach ( (array) $list as $integration ) {
			if ( ! self::is_valid_integration( $integration ) ) {
				continue;
			}

			$id = \sanitize_key( $integration['id'] );

			$integrations[ $id ] = array(
				'id'           => $id,
				'label'        => (string) $integration['label'],
				'description'  => (string) $integration['description'],
				'is_available' => $integration['is_available'],
				'count'        => $integration['count'],
				'delete'       => $integration['delete'],
			);
		}

		self::$integrations = $integrations;

		return $integrations;
	}

	/**
	 * Check that an integration has all the keys and callables it needs.
	 *
	 * @param mixed $integration - Integration from the filter.
	 *
	 * @return bool - True if it can be used.
	 *
	 * @since 1.0.0
	 */
	public static function is_valid_integration( $integration ) {
		if ( ! is_array( $integration ) ) {
			return false;
		}

		if ( empty( $integration['id'] ) || ! is_string( $integration['id'] ) || '' === \sanitize_key( $integration['id'] ) ) {
			return false;
		}

		if ( ! isset( $integration['label'], $integration['description'] ) ) {
			return false;
		}

		foreach ( array( 'is_available', 'count', 'delete' ) as $key ) {
			if ( ! isset( $integration[ $key ] ) || ! is_callable( $integration[ $key ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the integrations whose plugin is active.
	 *
	 * @return array - Integrations keyed by ID.
	 *
	 * @since 1.0.0
	 */
	public static function get_available_integrations() {
		$available = array();

		foreach ( self::get_integrations() as $id => $integration ) {
			if ( call_user_func( $integration['is_available'] ) ) {
				$available[ $id ] = $integration;
			}
		}

		return $available;
	}

	/**
	 * Count the items an integration would delete.
	 *
	 * @param array $integration - Integration from get_integrations().
	 *
	 * @return int - Number of items.
	 *
	 * @since 1.0.0
	 */
	public static function run_count( $integration ) {
		return \absint( call_user_func( $integration['count'] ) );
	}

	/**
	 * Delete one batch of an integration's items.
	 *
	 * @param array $integration - Integration from get_integrations().
	 *
	 * @return array - Number of deleted and remaining items.
	 *
	 * @since 1.0.0
	 */
	public static function run_delete( $integration ) {
		$result = call_user_func( $integration['delete'] );

		return array(
			'deleted'   => \absint( is_array( $result ) && isset( $result['deleted'] ) ? $result['deleted'] : 0 ),
			'remaining' => \absint( is_array( $result ) && isset( $result['remaining'] ) ? $result['remaining'] : 0 ),
		);
	}

	/**
	 * Get the built-in integrations.
	 *
	 * @return array - List of integrations.
	 *
	 * @since 1.0.0
	 */
	public static function get_builtin_integrations() {
		return array(
			array(
				'id'           => 'action_scheduler',
				'label'        => \__( 'Action Scheduler', 'bloatbuster-performance-asset-cleaner' ),
				'description'  => \__( 'Completed and canceled actions older than the Action Scheduler retention period, with their logs. Failed actions are kept.', 'bloatbuster-performance-asset-cleaner' ),
				'is_available' => array( __CLASS__, 'is_action_scheduler_available' ),
				'count'        => array( __CLASS__, 'count_action_scheduler' ),
				'delete'       => array( __CLASS__, 'delete_action_scheduler' ),
			),
			array(
				'id'           => 'woocommerce_sessions',
				'label'        => \__( 'WooCommerce sessions', 'bloatbuster-performance-asset-cleaner' ),
				'description'  => \__( 'Expired customer sessions. Active carts are kept.', 'bloatbuster-performance-asset-cleaner' ),
				'is_available' => array( __CLASS__, 'is_woo_sessions_available' ),
				'count'        => array( __CLASS__, 'count_woo_sessions' ),
				'delete'       => array( __CLASS__, 'delete_woo_sessions' ),
			),
		);
	}

	/**
	 * Check if Action Scheduler is loaded and its data store is ready.
	 *
	 * @return bool - True if Action Scheduler can be used.
	 *
	 * @since 1.0.0
	 */
	public static function is_action_scheduler_available() {
		return class_exists( 'ActionScheduler' ) && class_exists( 'ActionScheduler_Store' ) && \ActionScheduler::is_initialized();
	}

	/**
	 * Get the Action Scheduler query for old completed and canceled actions.
	 *
	 * Same rule as the Action Scheduler queue cleaner: last modified before the retention period.
	 *
	 * @param int $per_page - Maximum number of actions, 0 for a count.
	 *
	 * @return array - Query arguments for query_actions().
	 *
	 * @since 1.0.0
	 */
	public static function get_action_scheduler_query( $per_page = 0 ) {
		// Action Scheduler filter, same default and fallback as its own cleaner.
		$retention = \apply_filters( 'action_scheduler_retention_period', MONTH_IN_SECONDS ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Action Scheduler hook.
		$retention = is_numeric( $retention ) ? max( 0, (int) $retention ) : MONTH_IN_SECONDS;

		return array(
			'status'           => array( \ActionScheduler_Store::STATUS_COMPLETE, \ActionScheduler_Store::STATUS_CANCELED ),
			'modified'         => new \DateTime( '@' . ( time() - $retention ) ),
			'modified_compare' => '<=',
			'per_page'         => \absint( $per_page ),
			'orderby'          => 'none',
		);
	}

	/**
	 * Count old completed and canceled Action Scheduler actions.
	 *
	 * @return int - Number of actions.
	 *
	 * @since 1.0.0
	 */
	public static function count_action_scheduler() {
		if ( ! self::is_action_scheduler_available() ) {
			return 0;
		}

		return (int) \ActionScheduler::store()->query_actions( self::get_action_scheduler_query(), 'count' );
	}

	/**
	 * Delete old completed and canceled Action Scheduler actions, with their logs.
	 *
	 * Deletes at most ACTION_SCHEDULER_BATCH actions per run.
	 *
	 * @return array - Number of deleted and remaining actions.
	 *
	 * @since 1.0.0
	 */
	public static function delete_action_scheduler() {
		$deleted = 0;

		if ( ! self::is_action_scheduler_available() ) {
			return array(
				'deleted'   => 0,
				'remaining' => 0,
			);
		}

		$store      = \ActionScheduler::store();
		$action_ids = (array) $store->query_actions( self::get_action_scheduler_query( self::ACTION_SCHEDULER_BATCH ) );

		foreach ( $action_ids as $action_id ) {
			try {
				$store->delete_action( (int) $action_id );
				++$deleted;
			} catch ( \Exception $e ) {
				// The action is gone or can't be deleted, skip it like the Action Scheduler cleaner.
				continue;
			}
		}

		return array(
			'deleted'   => $deleted,
			'remaining' => self::count_action_scheduler(),
		);
	}

	/**
	 * Get the WooCommerce sessions table name of this site.
	 *
	 * @return string - Table name.
	 *
	 * @since 1.0.0
	 */
	public static function get_woo_sessions_table() {
		global $wpdb;

		return $wpdb->prefix . 'woocommerce_sessions';
	}

	/**
	 * Check if WooCommerce is active and its sessions table exists.
	 *
	 * @return bool - True if WooCommerce sessions can be cleaned.
	 *
	 * @since 1.0.0
	 */
	public static function is_woo_sessions_available() {
		global $wpdb;

		if ( ! class_exists( 'WC_Session_Handler' ) ) {
			return false;
		}

		$table = self::get_woo_sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live check.
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	/**
	 * Count expired WooCommerce sessions.
	 *
	 * @return int - Number of expired sessions.
	 *
	 * @since 1.0.0
	 */
	public static function count_woo_sessions() {
		global $wpdb;

		if ( ! self::is_woo_sessions_available() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live count.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE session_expiry < %d',
				self::get_woo_sessions_table(),
				time()
			)
		);
	}

	/**
	 * Delete expired WooCommerce sessions, with the same query and cache clearing as WooCommerce.
	 *
	 * Deletes at most WOO_SESSIONS_BATCH sessions per run, the rest can be deleted by running it again.
	 * WC_Session_Handler::cleanup_sessions() is not used, it deletes the whole backlog in one request
	 * and can run past the request timeout on large tables.
	 *
	 * @return array - Number of deleted and remaining expired sessions.
	 *
	 * @since 1.0.0
	 */
	public static function delete_woo_sessions() {
		global $wpdb;

		if ( ! self::is_woo_sessions_available() ) {
			return array(
				'deleted'   => 0,
				'remaining' => 0,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup query, the session cache is cleared below.
		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE session_expiry < %d ORDER BY session_expiry LIMIT %d',
				self::get_woo_sessions_table(),
				time(),
				self::WOO_SESSIONS_BATCH
			)
		);

		if ( $deleted > 0 && class_exists( 'WC_Cache_Helper' ) && defined( 'WC_SESSION_CACHE_GROUP' ) ) {
			\WC_Cache_Helper::invalidate_cache_group( WC_SESSION_CACHE_GROUP );
		}

		return array(
			'deleted'   => max( 0, $deleted ),
			'remaining' => self::count_woo_sessions(),
		);
	}
}
