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
	 * Option that stores the recent cleanup runs, not autoloaded.
	 *
	 * @var string
	 */
	const LOG_OPTION = 'bloatbuster_cleanup_log';

	/**
	 * Comment statuses that can be deleted.
	 *
	 * @var array
	 */
	const COMMENT_STATUSES = array( 'spam', 'trash' );

	/**
	 * Maximum number of orphaned meta rows deleted per type per run.
	 *
	 * @var int
	 */
	const META_DELETE_BATCH = 1000;

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

				self::log_cleanup( $action, $args['bloatbuster_count'], self::count_expired_transients() );
				break;

			case 'delete_revisions':
				// Same limit as the form input.
				$raw_keep = isset( $_POST['bbpac_keep_revisions'] ) ? \sanitize_text_field( \wp_unslash( $_POST['bbpac_keep_revisions'] ) ) : '';

				// An empty or non-numeric value must never become 0, that would delete every revision.
				$keep   = ( '' !== $raw_keep && is_numeric( $raw_keep ) ) ? min( 100, \absint( $raw_keep ) ) : Tab_Database::get_default_revisions_to_keep();
				$result = self::delete_revisions( $keep );

				$args['bloatbuster_notice']    = 'revisions_deleted';
				$args['bloatbuster_count']     = $result['deleted'];
				$args['bloatbuster_remaining'] = $result['remaining'];

				// Fill the form with the same value, so the next batch keeps the same revisions.
				$args['bloatbuster_keep'] = $keep;

				self::log_cleanup( $action, $result['deleted'], $result['remaining'] );
				break;

			case 'delete_auto_drafts':
				$result = self::delete_auto_drafts();

				$args['bloatbuster_notice']    = 'auto_drafts_deleted';
				$args['bloatbuster_count']     = $result['deleted'];
				$args['bloatbuster_remaining'] = $result['remaining'];

				self::log_cleanup( $action, $result['deleted'], $result['remaining'] );
				break;

			case 'delete_spam_comments':
				$result = self::delete_comments( 'spam' );

				$args['bloatbuster_notice']    = 'spam_comments_deleted';
				$args['bloatbuster_count']     = $result['deleted'];
				$args['bloatbuster_remaining'] = $result['remaining'];

				self::log_cleanup( $action, $result['deleted'], $result['remaining'] );
				break;

			case 'delete_trashed_comments':
				$result = self::delete_comments( 'trash' );

				$args['bloatbuster_notice']    = 'trashed_comments_deleted';
				$args['bloatbuster_count']     = $result['deleted'];
				$args['bloatbuster_remaining'] = $result['remaining'];

				self::log_cleanup( $action, $result['deleted'], $result['remaining'] );
				break;

			case 'delete_orphaned_meta':
				$raw_types = isset( $_POST['bbpac_orphan_types'] ) ? (array) \wp_unslash( $_POST['bbpac_orphan_types'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
				$deleted   = 0;
				$remaining = 0;

				foreach ( array_unique( array_map( 'sanitize_key', $raw_types ) ) as $type ) {
					// Unknown types and network user meta on a subsite are skipped.
					if ( ! self::can_clean_meta_type( $type ) ) {
						continue;
					}

					$result     = self::delete_orphaned_meta( $type );
					$deleted   += $result['deleted'];
					$remaining += $result['remaining'];
				}

				$args['bloatbuster_notice']    = 'orphaned_meta_deleted';
				$args['bloatbuster_count']     = $deleted;
				$args['bloatbuster_remaining'] = $remaining;

				self::log_cleanup( $action, $deleted, $remaining );
				break;
		}

		\wp_safe_redirect( \add_query_arg( $args, \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION ) ) );
		exit;
	}

	/**
	 * Get the size, rows and free space of the tables with this site's prefix.
	 *
	 * Free space is not added up: InnoDB keeps some free space reserved in larger
	 * tables, and without innodb_file_per_table every InnoDB table reports the free
	 * space of the same shared file, so it is shown as unknown for those tables.
	 *
	 * @return array - Tables sorted by size, largest first, and totals.
	 *
	 * @since 1.0.0
	 */
	public static function get_table_stats() {
		global $wpdb;

		$where = 'TABLE_SCHEMA = %s AND TABLE_NAME LIKE %s';
		$args  = array( $wpdb->dbname, $wpdb->esc_like( $wpdb->prefix ) . '%' );

		// On the multisite main site, the prefix also matches the tables of other sites, like wp_2_posts.
		if ( \is_multisite() && \is_main_site() ) {
			$where .= ' AND TABLE_NAME NOT REGEXP %s';
			$args[] = '^' . preg_quote( $wpdb->prefix, '/' ) . '[0-9]+_';
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Live report from information_schema, WHERE built with placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT TABLE_NAME AS name, ENGINE AS engine, TABLE_ROWS AS row_count, DATA_LENGTH + INDEX_LENGTH AS size, DATA_FREE AS free_space FROM information_schema.TABLES WHERE {$where} ORDER BY size DESC",
				$args
			)
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Server setting, read once.
		$file_per_table = (bool) $wpdb->get_var( 'SELECT @@innodb_file_per_table' );

		$tables = array();
		$totals = array(
			'count' => 0,
			'size'  => 0,
		);

		foreach ( (array) $rows as $row ) {
			$is_shared = 'innodb' === strtolower( (string) $row->engine ) && ! $file_per_table;

			$tables[] = array(
				'name'       => (string) $row->name,
				'rows'       => (int) $row->row_count,
				'size'       => (int) $row->size,
				'free_space' => $is_shared ? null : (int) $row->free_space,
			);

			++$totals['count'];
			$totals['size'] += (int) $row->size;
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
	 * Count trashed posts per post type.
	 *
	 * @return array - Counts keyed by post type, most trashed first.
	 *
	 * @since 1.0.0
	 */
	public static function get_trashed_post_type_counts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_type, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_status = %s GROUP BY post_type ORDER BY total DESC",
				'trash'
			)
		);

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->post_type ] = (int) $row->total;
		}

		return $counts;
	}

	/**
	 * Get the posts with the most revisions, above the number to keep.
	 *
	 * @param int $keep  - Newest revisions to keep per post.
	 * @param int $limit - Maximum number of posts.
	 *
	 * @return array - Rows with parent ID and revision count, most revisions first.
	 *
	 * @since 1.0.0
	 */
	public static function get_revision_parents( $keep, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_parent AS parent, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_parent HAVING total > %d ORDER BY total DESC LIMIT %d",
				'revision',
				\absint( $keep ),
				\absint( $limit )
			)
		);

		return self::format_revision_parents( $rows );
	}

	/**
	 * Get a page of posts with more revisions than the number to keep, after a post ID.
	 *
	 * Keyset paging by post ID, so a revision that fails to delete can't make the loop repeat.
	 *
	 * @param int $keep  - Newest revisions to keep per post.
	 * @param int $after - Only posts with a higher ID than this.
	 * @param int $limit - Maximum number of posts.
	 *
	 * @return array - Rows with parent ID and revision count, lowest post ID first.
	 *
	 * @since 1.0.0
	 */
	public static function get_revision_parents_after( $keep, $after, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live counts.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_parent AS parent, COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s AND post_parent > %d GROUP BY post_parent HAVING total > %d ORDER BY post_parent ASC LIMIT %d",
				'revision',
				// Not absint(), the first page starts at -1 so orphaned revisions with parent 0 are included.
				(int) $after,
				\absint( $keep ),
				\absint( $limit )
			)
		);

		return self::format_revision_parents( $rows );
	}

	/**
	 * Convert revision parent rows to arrays with integer values.
	 *
	 * @param array $rows - Database rows with parent and total.
	 *
	 * @return array - Rows with parent ID and revision count.
	 *
	 * @since 1.0.0
	 */
	public static function format_revision_parents( $rows ) {
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
		global $wpdb;

		$keep = \absint( $keep );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live count, summed in SQL.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE( SUM( total - %d ), 0 ) FROM ( SELECT COUNT(*) AS total FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_parent HAVING total > %d ) AS parents",
				$keep,
				'revision',
				$keep
			)
		);
	}

	/**
	 * Delete old revisions, keeping the newest ones of each post.
	 *
	 * Deletes at most DELETE_BATCH revisions per run, the rest can be deleted by running it again.
	 * Posts are read in pages of 50, so memory use doesn't grow with the size of the site.
	 *
	 * @param int $keep - Newest revisions to keep per post.
	 *
	 * @return array - Number of deleted and remaining revisions.
	 *
	 * @since 1.0.0
	 */
	public static function delete_revisions( $keep ) {
		global $wpdb;

		$keep        = \absint( $keep );
		$deleted     = 0;
		$last_parent = -1;

		while ( $deleted < self::DELETE_BATCH ) {
			$parents = self::get_revision_parents_after( $keep, $last_parent, 50 );

			if ( empty( $parents ) ) {
				break;
			}

			foreach ( $parents as $parent ) {
				$last_parent = $parent['parent'];

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

	/**
	 * Count comments with a status, spam or trash.
	 *
	 * @param string $status - Comment status, spam or trash.
	 *
	 * @return int - Number of comments.
	 *
	 * @since 1.0.0
	 */
	public static function count_comments( $status ) {
		global $wpdb;

		if ( ! in_array( $status, self::COMMENT_STATUSES, true ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live count.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s",
				$status
			)
		);
	}

	/**
	 * Delete comments with a status, spam or trash.
	 *
	 * Deletes at most DELETE_BATCH comments per run, the rest can be deleted by running it again.
	 * Uses wp_delete_comment(), so core hooks, comment meta and post comment counts stay correct.
	 *
	 * @param string $status - Comment status, spam or trash.
	 *
	 * @return array - Number of deleted and remaining comments.
	 *
	 * @since 1.0.0
	 */
	public static function delete_comments( $status ) {
		global $wpdb;

		$deleted = 0;

		if ( ! in_array( $status, self::COMMENT_STATUSES, true ) ) {
			return array(
				'deleted'   => 0,
				'remaining' => 0,
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live list.
		$comment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = %s ORDER BY comment_ID ASC LIMIT %d",
				$status,
				self::DELETE_BATCH
			)
		);

		foreach ( $comment_ids as $comment_id ) {
			if ( \wp_delete_comment( (int) $comment_id, true ) ) {
				++$deleted;
			}
		}

		return array(
			'deleted'   => $deleted,
			'remaining' => self::count_comments( $status ),
		);
	}

	/**
	 * Add a cleanup run to the log, newest first.
	 *
	 * @param string $action    - Action ID, same as the field ID.
	 * @param int    $deleted   - Number of deleted items.
	 * @param int    $remaining - Number of items left to delete.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function log_cleanup( $action, $deleted, $remaining ) {
		$log = self::get_cleanup_log();

		array_unshift(
			$log,
			array(
				'time'      => time(),
				'action'    => \sanitize_key( $action ),
				'user'      => \get_current_user_id(),
				'deleted'   => \absint( $deleted ),
				'remaining' => \absint( $remaining ),
			)
		);

		/**
		 * Filters how many cleanup runs are kept in the log.
		 *
		 * @param int $size - Number of entries to keep, default 10.
		 *
		 * @since 1.0.0
		 */
		$size = max( 1, \absint( \apply_filters( 'bloatbuster_cleanup_log_size', 10 ) ) );

		// Not autoloaded, the log is only read on the Database tab.
		\update_option( self::LOG_OPTION, array_slice( $log, 0, $size ), false );
	}

	/**
	 * Get the recent cleanup runs, newest first.
	 *
	 * @return array - Entries with time, action, user, deleted and remaining.
	 *
	 * @since 1.0.0
	 */
	public static function get_cleanup_log() {
		$log = \get_option( self::LOG_OPTION, array() );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return $log;
	}

	/**
	 * Get the meta tables and the object tables they belong to.
	 *
	 * Table names come from $wpdb, so they include the prefix of the current site.
	 *
	 * @return array - Meta types with meta_table, object_table, object_id, meta_object and meta_id column names.
	 *
	 * @since 1.0.0
	 */
	public static function get_meta_types() {
		global $wpdb;

		return array(
			'post'    => array(
				'meta_table'   => $wpdb->postmeta,
				'object_table' => $wpdb->posts,
				'object_id'    => 'ID',
				'meta_object'  => 'post_id',
				'meta_id'      => 'meta_id',
			),
			'comment' => array(
				'meta_table'   => $wpdb->commentmeta,
				'object_table' => $wpdb->comments,
				'object_id'    => 'comment_ID',
				'meta_object'  => 'comment_id',
				'meta_id'      => 'meta_id',
			),
			'term'    => array(
				'meta_table'   => $wpdb->termmeta,
				'object_table' => $wpdb->terms,
				'object_id'    => 'term_id',
				'meta_object'  => 'term_id',
				'meta_id'      => 'meta_id',
			),
			'user'    => array(
				'meta_table'   => $wpdb->usermeta,
				'object_table' => $wpdb->users,
				'object_id'    => 'ID',
				'meta_object'  => 'user_id',
				'meta_id'      => 'umeta_id',
			),
		);
	}

	/**
	 * Check if the current user can find and delete orphaned meta of a type.
	 *
	 * User meta is shared by the whole network on multisite, so there only a network
	 * admin on the main site can clean it.
	 *
	 * @param string $type - Meta type, e.g. post or user.
	 *
	 * @return bool - True if the type exists and may be cleaned.
	 *
	 * @since 1.0.0
	 */
	public static function can_clean_meta_type( $type ) {
		if ( ! array_key_exists( $type, self::get_meta_types() ) ) {
			return false;
		}

		if ( 'user' !== $type || ! \is_multisite() ) {
			return true;
		}

		return \is_main_site() && \current_user_can( 'manage_network_options' );
	}

	/**
	 * Count meta rows whose object no longer exists.
	 *
	 * Rows with object ID 0 are skipped, some plugins store global data there on purpose.
	 *
	 * @param string $type - Meta type, e.g. post or user.
	 *
	 * @return int - Number of orphaned meta rows.
	 *
	 * @since 1.0.0
	 */
	public static function count_orphaned_meta( $type ) {
		global $wpdb;

		$types = self::get_meta_types();

		if ( ! isset( $types[ $type ] ) ) {
			return 0;
		}

		$t = $types[ $type ];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live count, table and column names come from $wpdb and get_meta_types().
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t['meta_table']} m LEFT JOIN {$t['object_table']} o ON o.{$t['object_id']} = m.{$t['meta_object']} WHERE o.{$t['object_id']} IS NULL AND m.{$t['meta_object']} > %d",
				0
			)
		);
		// phpcs:enable
	}

	/**
	 * Get the meta keys with the most orphaned rows.
	 *
	 * @param string $type  - Meta type, e.g. post or user.
	 * @param int    $limit - Maximum number of keys.
	 *
	 * @return array - Rows with key and total, most rows first.
	 *
	 * @since 1.0.0
	 */
	public static function get_orphaned_meta_keys( $type, $limit = 5 ) {
		global $wpdb;

		$types = self::get_meta_types();

		if ( ! isset( $types[ $type ] ) ) {
			return array();
		}

		$t = $types[ $type ];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live list, table and column names come from $wpdb and get_meta_types().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.meta_key AS meta_key, COUNT(*) AS total FROM {$t['meta_table']} m LEFT JOIN {$t['object_table']} o ON o.{$t['object_id']} = m.{$t['meta_object']} WHERE o.{$t['object_id']} IS NULL AND m.{$t['meta_object']} > %d GROUP BY m.meta_key ORDER BY total DESC LIMIT %d",
				0,
				\absint( $limit )
			)
		);
		// phpcs:enable

		$keys = array();

		foreach ( (array) $rows as $row ) {
			$keys[] = array(
				'key'   => (string) $row->meta_key,
				'total' => (int) $row->total,
			);
		}

		return $keys;
	}

	/**
	 * Delete meta rows whose object no longer exists.
	 *
	 * Deletes at most META_DELETE_BATCH rows per run, the rest can be deleted by running it again.
	 * Direct SQL instead of delete_metadata(): the objects are gone, so per-row hooks are not
	 * needed and would be slow. The join is checked again in the DELETE, so a row whose object
	 * was created meanwhile is kept.
	 *
	 * @param string $type - Meta type, e.g. post or user.
	 *
	 * @return array - Number of deleted and remaining orphaned meta rows.
	 *
	 * @since 1.0.0
	 */
	public static function delete_orphaned_meta( $type ) {
		global $wpdb;

		$types = self::get_meta_types();

		if ( ! isset( $types[ $type ] ) ) {
			return array(
				'deleted'   => 0,
				'remaining' => 0,
			);
		}

		$t = $types[ $type ];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live list, table and column names come from $wpdb and get_meta_types().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.{$t['meta_id']} AS meta_id, m.{$t['meta_object']} AS object_id FROM {$t['meta_table']} m LEFT JOIN {$t['object_table']} o ON o.{$t['object_id']} = m.{$t['meta_object']} WHERE o.{$t['object_id']} IS NULL AND m.{$t['meta_object']} > %d ORDER BY m.{$t['meta_id']} ASC LIMIT %d",
				0,
				self::META_DELETE_BATCH
			)
		);
		// phpcs:enable

		$meta_ids   = array();
		$object_ids = array();

		foreach ( (array) $rows as $row ) {
			$meta_ids[]   = (int) $row->meta_id;
			$object_ids[] = (int) $row->object_id;
		}

		$deleted = 0;

		if ( ! empty( $meta_ids ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $meta_ids ), '%d' ) );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Cleanup query, the cache is cleared below. Names come from get_meta_types(), IDs use %d placeholders.
			$deleted = (int) $wpdb->query(
				$wpdb->prepare(
					"DELETE m FROM {$t['meta_table']} m LEFT JOIN {$t['object_table']} o ON o.{$t['object_id']} = m.{$t['meta_object']} WHERE o.{$t['object_id']} IS NULL AND m.{$t['meta_id']} IN ( {$placeholders} )",
					$meta_ids
				)
			);
			// phpcs:enable

			\wp_cache_delete_multiple( array_unique( $object_ids ), $type . '_meta' );
		}

		return array(
			'deleted'   => max( 0, $deleted ),
			'remaining' => self::count_orphaned_meta( $type ),
		);
	}
}
