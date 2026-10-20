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
	 * Maximum number of revisions or auto-drafts deleted per run.
	 *
	 * @var int
	 */
	const DELETE_BATCH = 500;

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

		if ( self::uses_network_transients() ) {
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

		if ( self::uses_network_transients() ) {
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
	 * Check if this site handles the network transients, stored in the sitemeta table.
	 *
	 * Only the main site of the main network, like core delete_expired_transients(),
	 * so subsite admins don't see or clean network data.
	 *
	 * @return bool - True on the main site of a multisite network.
	 *
	 * @since 1.0.0
	 */
	public static function uses_network_transients() {
		return \is_multisite() && \is_main_site() && \is_main_network();
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
	 * rows exist, so orphaned timeouts stay forever. A timeout is deleted only when
	 * its value row is missing, deleting the timeout of an existing value would make
	 * that transient never expire.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function delete_orphaned_transient_timeouts() {
		global $wpdb;

		$now = time();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleanup queries, the cache is cleared below.
		// Local transients, '_transient_timeout_' is 19 characters.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE t FROM {$wpdb->options} t
				LEFT JOIN {$wpdb->options} v ON v.option_name = CONCAT( '_transient_', SUBSTRING( t.option_name, 20 ) )
				WHERE t.option_name LIKE %s AND t.option_value < %d AND v.option_id IS NULL",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				$now
			)
		);

		// Site transients stored in the options table, '_site_transient_timeout_' is 24 characters.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE t FROM {$wpdb->options} t
				LEFT JOIN {$wpdb->options} v ON v.option_name = CONCAT( '_site_transient_', SUBSTRING( t.option_name, 25 ) )
				WHERE t.option_name LIKE %s AND t.option_value < %d AND v.option_id IS NULL",
				$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
				$now
			)
		);

		if ( self::uses_network_transients() ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE t FROM {$wpdb->sitemeta} t
					LEFT JOIN {$wpdb->sitemeta} v ON v.site_id = t.site_id AND v.meta_key = CONCAT( '_site_transient_', SUBSTRING( t.meta_key, 25 ) )
					WHERE t.meta_key LIKE %s AND t.meta_value < %d AND v.meta_id IS NULL",
					$wpdb->esc_like( '_site_transient_timeout_' ) . '%',
					$now
				)
			);
		}
		// phpcs:enable

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

			case 'delete_revisions':
				$keep   = isset( $_POST['bbpac_keep_revisions'] ) ? \absint( $_POST['bbpac_keep_revisions'] ) : 0;
				$result = self::delete_revisions( $keep );

				$args['bloatbuster_notice']    = 'revisions_deleted';
				$args['bloatbuster_count']     = $result['deleted'];
				$args['bloatbuster_remaining'] = $result['remaining'];
				break;

			case 'delete_auto_drafts':
				$result = self::delete_auto_drafts();

				$args['bloatbuster_notice']    = 'auto_drafts_deleted';
				$args['bloatbuster_count']     = $result['deleted'];
				$args['bloatbuster_remaining'] = $result['remaining'];
				break;
		}

		\wp_safe_redirect( \add_query_arg( $args, \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION ) ) );
		exit;
	}

	/**
	 * Get the size, rows and overhead of the tables with this site's prefix.
	 *
	 * @return array - Tables sorted by size, largest first, and totals.
	 *
	 * @since 1.0.0
	 */
	public static function get_table_stats() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live report from information_schema.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT TABLE_NAME AS name, TABLE_ROWS AS row_count, DATA_LENGTH + INDEX_LENGTH AS size, DATA_FREE AS overhead FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s ORDER BY size DESC',
				$wpdb->dbname,
				$wpdb->esc_like( $wpdb->prefix ) . '%'
			)
		);

		$tables = array();
		$totals = array(
			'count'    => 0,
			'size'     => 0,
			'overhead' => 0,
		);

		// On the multisite main site, the prefix also matches the tables of other sites, like wp_2_posts.
		$other_sites = '/^' . preg_quote( $wpdb->prefix, '/' ) . '[0-9]+_/';

		foreach ( (array) $rows as $row ) {
			if ( \is_multisite() && \is_main_site() && preg_match( $other_sites, $row->name ) ) {
				continue;
			}

			$tables[] = array(
				'name'     => (string) $row->name,
				'rows'     => (int) $row->row_count,
				'size'     => (int) $row->size,
				'overhead' => (int) $row->overhead,
			);

			++$totals['count'];
			$totals['size']     += (int) $row->size;
			$totals['overhead'] += (int) $row->overhead;
		}

		return array(
			'tables' => $tables,
			'totals' => $totals,
		);
	}

	/**
	 * Count revisions, auto-drafts, trashed posts and spam or trashed comments.
	 *
	 * @return array - Counts keyed by type.
	 *
	 * @since 1.0.0
	 */
	public static function get_content_counts() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts.
		$counts = array(
			'revisions'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", 'revision' ) ),
			'auto_drafts'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s", 'auto-draft' ) ),
			'trashed_posts'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s", 'trash' ) ),
			'spam_comments'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", 'spam' ) ),
			'trashed_comments' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s", 'trash' ) ),
		);
		// phpcs:enable

		return $counts;
	}

	/**
	 * Get the posts that have more revisions than the number to keep.
	 *
	 * @param int $keep - Newest revisions to keep per post.
	 *
	 * @return array - Rows with parent ID and revision count, most revisions first.
	 *
	 * @since 1.0.0
	 */
	public static function get_revision_parents( $keep ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_parent AS parent, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_parent HAVING total > %d ORDER BY total DESC",
				'revision',
				\absint( $keep )
			)
		);

		$parents = array();

		foreach ( (array) $rows as $row ) {
			$parents[] = array(
				'parent' => (int) $row->parent,
				'total'  => (int) $row->total,
			);
		}

		return $parents;
	}

	/**
	 * Count the revisions that would be deleted when keeping the newest ones per post.
	 *
	 * @param int $keep - Newest revisions to keep per post.
	 *
	 * @return int - Number of revisions to delete.
	 *
	 * @since 1.0.0
	 */
	public static function count_revisions( $keep ) {
		$count = 0;

		foreach ( self::get_revision_parents( $keep ) as $parent ) {
			$count += $parent['total'] - \absint( $keep );
		}

		return $count;
	}

	/**
	 * Delete old revisions, keeping the newest ones of each post.
	 *
	 * Deletes at most DELETE_BATCH revisions per run, the rest can be deleted by running it again.
	 *
	 * @param int $keep - Newest revisions to keep per post.
	 *
	 * @return array - Number of deleted and remaining revisions.
	 *
	 * @since 1.0.0
	 */
	public static function delete_revisions( $keep ) {
		global $wpdb;

		$keep    = \absint( $keep );
		$deleted = 0;

		foreach ( self::get_revision_parents( $keep ) as $parent ) {
			if ( $deleted >= self::DELETE_BATCH ) {
				break;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live list, newest first, skipping the ones to keep.
			$revision_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent = %d ORDER BY post_date DESC, ID DESC LIMIT %d, %d",
					'revision',
					$parent['parent'],
					$keep,
					self::DELETE_BATCH - $deleted
				)
			);

			foreach ( $revision_ids as $revision_id ) {
				if ( \wp_delete_post_revision( (int) $revision_id ) ) {
					++$deleted;
				}
			}
		}

		return array(
			'deleted'   => $deleted,
			'remaining' => self::count_revisions( $keep ),
		);
	}

	/**
	 * Get the date before which auto-drafts are deleted. Newer ones may still be open in the editor.
	 *
	 * @return string - Date in the site timezone, MySQL format.
	 *
	 * @since 1.0.0
	 */
	public static function get_auto_draft_cutoff() {
		return \wp_date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
	}

	/**
	 * Count auto-drafts older than one day.
	 *
	 * @return int - Number of auto-drafts.
	 *
	 * @since 1.0.0
	 */
	public static function count_auto_drafts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live count.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s AND post_date < %s",
				'auto-draft',
				self::get_auto_draft_cutoff()
			)
		);
	}

	/**
	 * Delete auto-drafts older than one day.
	 *
	 * Deletes at most DELETE_BATCH auto-drafts per run, the rest can be deleted by running it again.
	 *
	 * @return array - Number of deleted and remaining auto-drafts.
	 *
	 * @since 1.0.0
	 */
	public static function delete_auto_drafts() {
		global $wpdb;

		$deleted = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live list.
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = %s AND post_date < %s ORDER BY ID ASC LIMIT %d",
				'auto-draft',
				self::get_auto_draft_cutoff(),
				self::DELETE_BATCH
			)
		);

		foreach ( $post_ids as $post_id ) {
			if ( \wp_delete_post( (int) $post_id, true ) ) {
				++$deleted;
			}
		}

		return array(
			'deleted'   => $deleted,
			'remaining' => self::count_auto_drafts(),
		);
	}
}
