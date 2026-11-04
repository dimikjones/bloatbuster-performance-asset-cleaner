<?php
/**
 * Database settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database reports and cleanup actions.
 */
final class Tab_Database extends Settings_Tab {

	/**
	 * Revisions kept per post by default when deleting old revisions, same as the "Revisions to keep" setting default.
	 *
	 * @var int
	 */
	const DEFAULT_REVISIONS_TO_KEEP = 5;

	/**
	 * Orphaned meta counts of this request, the preview and the action field both use them.
	 *
	 * @var array|null
	 */
	private static $orphaned_meta_counts = null;

	/**
	 * Plugin data counts of this request, keyed by integration ID, the preview and the action field both use them.
	 *
	 * @var array|null
	 */
	private static $integration_counts = null;

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'database';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Database', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Clean up data WordPress no longer needs.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 30;
	}

	/**
	 * Get how the tab form is submitted.
	 *
	 * @return string - Form type.
	 *
	 * @since 1.0.0
	 */
	public static function get_form_type() {
		return 'admin-post';
	}

	/**
	 * Get the admin-post action and nonce action.
	 *
	 * @return string - Action name.
	 *
	 * @since 1.0.0
	 */
	public static function get_form_action() {
		return Database_Tools::ACTION;
	}

	/**
	 * Get the tab fields.
	 *
	 * @return array - List of fields.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		$note = '';

		if ( \wp_using_ext_object_cache() ) {
			$note = \__( 'An object cache is active, so transients are mostly stored there. Only database rows are cleaned.', 'bloatbuster-performance-asset-cleaner' );
		}

		return array(
			array(
				'type'            => 'report',
				'id'              => 'database_stats',
				'label'           => \__( 'Database statistics', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Size of the tables of this site and what each cleanup would remove. Free space is unused space inside a table file, InnoDB keeps a few MB free in larger tables, which is normal. Row counts are estimates for most tables.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_database_stats' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'clean_expired_transients',
				'label'           => \__( 'Clean expired transients', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Delete transients that have expired but are still stored in the database.', 'bloatbuster-performance-asset-cleaner' ),
				'note'            => $note,
				'button'          => \__( 'Clean now', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_expired_transients' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'delete_revisions',
				'label'           => \__( 'Delete old revisions', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Delete post revisions and keep only the newest ones of each post. At most 500 are deleted per run.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Delete revisions', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Delete old revisions?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'Revisions beyond the number to keep are deleted from every post. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_revisions' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'delete_auto_drafts',
				'label'           => \__( 'Delete auto-drafts', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Delete auto-drafts older than one day. WordPress creates them when you open a new post and deletes them by itself after 7 days. At most 500 are deleted per run.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Delete auto-drafts', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Delete auto-drafts?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'Auto-drafts older than one day are deleted. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_auto_drafts' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'delete_spam_comments',
				'label'           => \__( 'Delete spam comments', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Permanently delete comments marked as spam. At most 500 are deleted per run.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Delete spam comments', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Delete spam comments?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'All spam comments are deleted. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_spam_comments' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'delete_trashed_comments',
				'label'           => \__( 'Delete trashed comments', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Permanently delete comments in the trash. At most 500 are deleted per run.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Delete trashed comments', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Delete trashed comments?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'All comments in the trash are deleted. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_trashed_comments' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'delete_trashed_posts',
				'label'           => \__( 'Delete trashed posts', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Permanently delete posts, pages and other items in the trash, of all post types. Their meta, terms and comments are deleted too, and trashed media files are removed from the server. At most 100 are deleted per run.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Delete trashed posts', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Delete trashed posts?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'Items in the trash are permanently deleted, with their meta, terms, comments and media files. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_trashed_posts' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'delete_orphaned_meta',
				'label'           => \__( 'Delete orphaned metadata', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Delete meta rows of posts, comments, terms and users that no longer exist. Meta of existing objects is never touched. At most 1000 rows per type are deleted per run. Make a database backup first.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Delete orphaned metadata', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Delete orphaned metadata?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'Meta rows of deleted objects are deleted for the selected types. Make sure you have a database backup. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_orphaned_meta' ),
			),
			array(
				'type'            => 'action',
				'id'              => 'clean_plugin_data',
				'label'           => \__( 'Plugin temporary data', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Delete expired data of supported plugins, using each plugin\'s own rules. Data is never deleted by name patterns.', 'bloatbuster-performance-asset-cleaner' ),
				'button'          => \__( 'Clean plugin data', 'bloatbuster-performance-asset-cleaner' ),
				'confirm_title'   => \__( 'Clean plugin data?', 'bloatbuster-performance-asset-cleaner' ),
				'confirm'         => \__( 'Expired data of the selected plugins is deleted. This can not be undone.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_plugin_data' ),
			),
			array(
				'type'            => 'report',
				'id'              => 'cleanup_log',
				'label'           => \__( 'Recent cleanups', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'The last cleanups run from this tab.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_cleanup_log' ),
			),
		);
	}

	/**
	 * Add the result notice of an action run from this tab.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function add_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only flags set by the action redirect.
		$notice    = isset( $_GET['bloatbuster_notice'] ) ? \sanitize_key( \wp_unslash( $_GET['bloatbuster_notice'] ) ) : '';
		$count     = isset( $_GET['bloatbuster_count'] ) ? \absint( $_GET['bloatbuster_count'] ) : 0;
		$remaining = isset( $_GET['bloatbuster_remaining'] ) ? \absint( $_GET['bloatbuster_remaining'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$message = '';

		if ( 'transients_cleaned' === $notice ) {
			/* translators: %d: number of deleted transients. */
			$message = sprintf( \_n( 'Deleted %d expired transient.', 'Deleted %d expired transients.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'revisions_deleted' === $notice ) {
			/* translators: %d: number of deleted revisions. */
			$message = sprintf( \_n( 'Deleted %d revision.', 'Deleted %d revisions.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'auto_drafts_deleted' === $notice ) {
			/* translators: %d: number of deleted auto-drafts. */
			$message = sprintf( \_n( 'Deleted %d auto-draft.', 'Deleted %d auto-drafts.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'spam_comments_deleted' === $notice ) {
			/* translators: %d: number of deleted spam comments. */
			$message = sprintf( \_n( 'Deleted %d spam comment.', 'Deleted %d spam comments.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'trashed_comments_deleted' === $notice ) {
			/* translators: %d: number of deleted trashed comments. */
			$message = sprintf( \_n( 'Deleted %d trashed comment.', 'Deleted %d trashed comments.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'orphaned_meta_deleted' === $notice ) {
			/* translators: %d: number of deleted orphaned meta rows. */
			$message = sprintf( \_n( 'Deleted %d orphaned meta row.', 'Deleted %d orphaned meta rows.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'trashed_posts_deleted' === $notice ) {
			/* translators: %d: number of deleted trashed posts. */
			$message = sprintf( \_n( 'Deleted %d trashed item.', 'Deleted %d trashed items.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'plugin_data_cleaned' === $notice ) {
			/* translators: %d: number of deleted plugin data items. */
			$message = sprintf( \_n( 'Deleted %d expired plugin item.', 'Deleted %d expired plugin items.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		}

		if ( '' === $message ) {
			return;
		}

		if ( $remaining > 0 && 'revisions_deleted' === $notice ) {
			$keep = self::get_revisions_to_keep();

			/* translators: 1: number of revisions left to delete, 2: number of revisions kept per post. */
			$message .= ' ' . sprintf( \_n( '%1$d more left, run it again to delete it (keeping %2$d per post).', '%1$d more left, run it again to delete them (keeping %2$d per post).', $remaining, 'bloatbuster-performance-asset-cleaner' ), $remaining, $keep );
		} elseif ( $remaining > 0 ) {
			/* translators: %d: number of items left to delete. */
			$message .= ' ' . sprintf( \_n( '%d more left, run it again to delete it.', '%d more left, run it again to delete them.', $remaining, 'bloatbuster-performance-asset-cleaner' ), $remaining );
		}

		\add_settings_error( 'messages', 'bloatbuster_notice', \esc_html( $message ), 'updated' );
	}

	/**
	 * Render the database size, content counts and largest tables.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_database_stats() {
		$limit  = 15;
		$stats  = Database_Tools::get_table_stats();
		$totals = $stats['totals'];
		?>
		<p>
			<?php
			/* translators: 1: database size, e.g. 12 MB, 2: number of tables. */
			echo \esc_html( sprintf( \__( 'Database size: %1$s in %2$d tables.', 'bloatbuster-performance-asset-cleaner' ), \size_format( $totals['size'], 1 ), $totals['count'] ) );
			?>
		</p>
		<?php self::render_cleanup_preview(); ?>
		<?php if ( ! empty( $stats['tables'] ) ) : ?>
			<table class="widefat striped bbpac-db-tables">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Table', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Rows (approx.)', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Size', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Free space', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $stats['tables'], 0, $limit ) as $table ) : ?>
						<tr>
							<td><code><?php echo \esc_html( $table['name'] ); ?></code></td>
							<td><?php echo \esc_html( \number_format_i18n( $table['rows'] ) ); ?></td>
							<td><?php echo \esc_html( \size_format( $table['size'], 1 ) ); ?></td>
							<td><?php echo \esc_html( $table['free_space'] > 0 ? \size_format( $table['free_space'], 1 ) : '-' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;

		if ( $totals['count'] > $limit ) {
			$more = $totals['count'] - $limit;

			/* translators: %d: number of tables not shown in the list. */
			echo '<p>' . \esc_html( sprintf( \_n( 'And %d more.', 'And %d more.', $more, 'bloatbuster-performance-asset-cleaner' ), $more ) ) . '</p>';
		}
	}

	/**
	 * Get the cleanup preview rows: what is stored and what each action would delete now.
	 *
	 * Eligible counts use the same methods as the actions, so they match the action results.
	 * A null count is shown as "-".
	 *
	 * @return array - Rows with label, stored, eligible, rule, action and links.
	 *
	 * @since 1.0.0
	 */
	public static function get_cleanup_preview_rows() {
		$counts      = Database_Tools::get_content_counts();
		$keep        = self::get_revisions_to_keep();
		$trash_days  = self::get_trash_days();
		$trash_links = self::get_trash_links();

		if ( 0 === $trash_days ) {
			$trash_rule = \__( 'All items in the trash.', 'bloatbuster-performance-asset-cleaner' );
		} else {
			/* translators: %d: number of days. */
			$trash_rule = sprintf( \_n( 'Trashed more than %d day ago.', 'Trashed more than %d days ago.', $trash_days, 'bloatbuster-performance-asset-cleaner' ), $trash_days );
		}

		if ( ! empty( $trash_links ) ) {
			$trash_rule .= ' ' . \__( 'Review:', 'bloatbuster-performance-asset-cleaner' );
		}

		$rows = array(
			array(
				'label'    => \__( 'Revisions', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => $counts['revisions'],
				'eligible' => Database_Tools::count_revisions( $keep ),
				/* translators: %d: number of revisions kept per post. */
				'rule'     => sprintf( \_n( 'Beyond the newest %d per post.', 'Beyond the newest %d per post.', $keep, 'bloatbuster-performance-asset-cleaner' ), $keep ),
				'action'   => 'delete_revisions',
				'links'    => array(),
			),
			array(
				'label'    => \__( 'Auto-drafts', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => $counts['auto_drafts'],
				'eligible' => Database_Tools::count_auto_drafts(),
				'rule'     => \__( 'Older than one day.', 'bloatbuster-performance-asset-cleaner' ),
				'action'   => 'delete_auto_drafts',
				'links'    => array(),
			),
			array(
				'label'    => \__( 'Expired transients', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => null,
				'eligible' => Database_Tools::count_expired_transients(),
				'rule'     => \__( 'Expired, but still in the database.', 'bloatbuster-performance-asset-cleaner' ),
				'action'   => 'clean_expired_transients',
				'links'    => array(),
			),
			array(
				'label'    => \__( 'Spam comments', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => $counts['spam_comments'],
				'eligible' => Database_Tools::count_comments( 'spam' ),
				'rule'     => \__( 'All spam comments.', 'bloatbuster-performance-asset-cleaner' ),
				'action'   => 'delete_spam_comments',
				'links'    => array(),
			),
			array(
				'label'    => \__( 'Trashed comments', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => $counts['trashed_comments'],
				'eligible' => Database_Tools::count_comments( 'trash' ),
				'rule'     => \__( 'All comments in the trash.', 'bloatbuster-performance-asset-cleaner' ),
				'action'   => 'delete_trashed_comments',
				'links'    => array(),
			),
			array(
				'label'    => \__( 'Orphaned metadata', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => null,
				'eligible' => array_sum( self::get_orphaned_meta_counts() ),
				'rule'     => \__( 'Meta rows of deleted posts, comments, terms and users.', 'bloatbuster-performance-asset-cleaner' ),
				'action'   => 'delete_orphaned_meta',
				'links'    => array(),
			),
			array(
				'label'    => \__( 'Trashed posts', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => $counts['trashed_posts'],
				'eligible' => Database_Tools::count_trashed_posts( $trash_days ),
				'rule'     => $trash_rule,
				'action'   => 'delete_trashed_posts',
				'links'    => $trash_links,
			),
		);

		$integration_counts = self::get_integration_counts();

		foreach ( Cleanup_Integrations::get_available_integrations() as $id => $integration ) {
			$rows[] = array(
				'label'    => $integration['label'],
				'stored'   => null,
				'eligible' => $integration_counts[ $id ] ?? 0,
				'rule'     => $integration['description'],
				'action'   => 'clean_plugin_data',
				'links'    => array(),
			);
		}

		return $rows;
	}

	/**
	 * Get a trash link for each post type with trashed posts and a list screen the user can open.
	 *
	 * Post types without a list screen are still counted in the preview, they just get no link.
	 *
	 * @return array - Links with url and text, most trashed post type first.
	 *
	 * @since 1.0.0
	 */
	public static function get_trash_links() {
		$links = array();

		foreach ( Database_Tools::get_trashed_post_type_counts() as $post_type => $count ) {
			$object = \get_post_type_object( $post_type );

			if ( ! $object || ! $object->show_ui || ! \current_user_can( $object->cap->edit_posts ) ) {
				continue;
			}

			if ( 'attachment' === $post_type ) {
				// edit.php redirects attachments to upload.php without the trash filter, the list mode has a trash view.
				$url = \add_query_arg(
					array(
						'mode'              => 'list',
						'attachment-filter' => 'trash',
					),
					\admin_url( 'upload.php' )
				);
			} else {
				$url = \add_query_arg(
					array(
						'post_status' => 'trash',
						'post_type'   => $post_type,
					),
					\admin_url( 'edit.php' )
				);
			}

			$links[] = array(
				'url'  => $url,
				/* translators: 1: post type name, e.g. Pages, 2: number of trashed items. */
				'text' => sprintf( \__( '%1$s (%2$s)', 'bloatbuster-performance-asset-cleaner' ), $object->labels->name, \number_format_i18n( $count ) ),
			);
		}

		return $links;
	}

	/**
	 * Render the cleanup preview table.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_cleanup_preview() {
		$rows = self::get_cleanup_preview_rows();
		?>
		<table class="widefat striped bbpac-db-preview">
			<thead>
				<tr>
					<th><?php \esc_html_e( 'Cleanup', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Stored', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Eligible now', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Rule', 'bloatbuster-performance-asset-cleaner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo \esc_html( $row['label'] ); ?></td>
						<td><?php echo \esc_html( null === $row['stored'] ? '-' : \number_format_i18n( $row['stored'] ) ); ?></td>
						<td>
							<?php if ( null === $row['eligible'] ) : ?>
								-
							<?php elseif ( $row['eligible'] > 0 && '' !== $row['action'] ) : ?>
								<a href="<?php echo \esc_attr( '#bbpac-action-' . $row['action'] ); ?>"><?php echo \esc_html( \number_format_i18n( $row['eligible'] ) ); ?></a>
							<?php else : ?>
								<?php echo \esc_html( \number_format_i18n( $row['eligible'] ) ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo \esc_html( $row['rule'] ); ?>
							<?php foreach ( $row['links'] as $index => $link ) : ?>
								<?php echo 0 < $index ? ', ' : ''; ?><a href="<?php echo \esc_url( $link['url'] ); ?>"><?php echo \esc_html( $link['text'] ); ?></a>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php \esc_html_e( 'Deleting rows does not always shrink the database file size.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
		<?php
	}

	/**
	 * Get the number of revisions to keep per post shown in the form.
	 *
	 * Uses the "Revisions to keep" setting when the revision limit is on, otherwise DEFAULT_REVISIONS_TO_KEEP.
	 *
	 * @return int - Revisions to keep per post.
	 *
	 * @since 1.0.0
	 */
	public static function get_default_revisions_to_keep() {
		if ( ! Options_Dispatch::get_option_value( 'limit_post_revisions' ) ) {
			return self::DEFAULT_REVISIONS_TO_KEEP;
		}

		return \absint( Options_Dispatch::get_option_value( 'post_revisions_limit', self::DEFAULT_REVISIONS_TO_KEEP ) );
	}

	/**
	 * Get the number of revisions to keep per post for the form.
	 *
	 * After a batch run, uses the value of that run so the next run keeps the same revisions.
	 *
	 * @return int - Revisions to keep per post, 0 to 100.
	 *
	 * @since 1.0.0
	 */
	public static function get_revisions_to_keep() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the action redirect.
		if ( isset( $_GET['bloatbuster_keep'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the action redirect.
			return min( 100, \absint( $_GET['bloatbuster_keep'] ) );
		}

		return self::get_default_revisions_to_keep();
	}

	/**
	 * Get the trash age in days for the form, 0 for all trash.
	 *
	 * After a batch run, uses the value of that run so the next run deletes by the same age,
	 * otherwise DEFAULT_TRASH_DAYS.
	 *
	 * @return int - Days, 0 to MAX_TRASH_DAYS.
	 *
	 * @since 1.0.0
	 */
	public static function get_trash_days() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the action redirect.
		if ( isset( $_GET['bloatbuster_trash_days'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the action redirect.
			return min( Database_Tools::MAX_TRASH_DAYS, \absint( $_GET['bloatbuster_trash_days'] ) );
		}

		return Database_Tools::DEFAULT_TRASH_DAYS;
	}

	/**
	 * Render the revisions count, the number to keep and the posts with most revisions.
	 *
	 * @return bool - True if there are revisions to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_revisions() {
		$keep    = self::get_revisions_to_keep();
		$count   = Database_Tools::count_revisions( $keep );
		$parents = Database_Tools::get_revision_parents( 0, 10 );
		?>
		<p>
			<label for="bbpac-keep-revisions"><?php \esc_html_e( 'Keep the newest revisions per post:', 'bloatbuster-performance-asset-cleaner' ); ?></label>
			<input type="number" id="bbpac-keep-revisions" name="bbpac_keep_revisions" value="<?php echo \esc_attr( $keep ); ?>" min="0" max="100" step="1" class="small-text" required>
		</p>
		<p>
			<?php
			/* translators: 1: number of revisions to delete, 2: number of revisions kept per post. */
			echo \esc_html( sprintf( \_n( '%1$d revision to delete when keeping %2$d per post.', '%1$d revisions to delete when keeping %2$d per post.', $count, 'bloatbuster-performance-asset-cleaner' ), $count, $keep ) );
			?>
		</p>
		<?php if ( ! empty( $parents ) ) : ?>
			<table class="widefat striped bbpac-revisions">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Post', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Revisions', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $parents as $parent ) : ?>
						<?php $title = \get_the_title( $parent['parent'] ); ?>
						<tr>
							<td>
								<?php if ( \get_edit_post_link( $parent['parent'] ) ) : ?>
									<a href="<?php echo \esc_url( \get_edit_post_link( $parent['parent'] ) ); ?>"><?php echo \esc_html( '' !== $title ? $title : \__( '(no title)', 'bloatbuster-performance-asset-cleaner' ) ); ?></a>
								<?php else : ?>
									<?php echo \esc_html( '' !== $title ? $title : \__( '(no title)', 'bloatbuster-performance-asset-cleaner' ) ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo \esc_html( \number_format_i18n( $parent['total'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;

		// Enabled when any post has revisions, the number to keep can be changed in the form.
		return ! empty( $parents );
	}

	/**
	 * Render the number of auto-drafts older than one day.
	 *
	 * @return bool - True if there are auto-drafts to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_auto_drafts() {
		$count = Database_Tools::count_auto_drafts();
		?>
		<p>
			<?php
			/* translators: %d: number of auto-drafts. */
			echo \esc_html( sprintf( \_n( '%d auto-draft older than one day.', '%d auto-drafts older than one day.', $count, 'bloatbuster-performance-asset-cleaner' ), $count ) );
			?>
		</p>
		<?php
		return $count > 0;
	}

	/**
	 * Render the number of spam comments.
	 *
	 * @return bool - True if there are spam comments to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_spam_comments() {
		$count = Database_Tools::count_comments( 'spam' );
		?>
		<p>
			<?php
			/* translators: %d: number of spam comments. */
			echo \esc_html( sprintf( \_n( '%d spam comment.', '%d spam comments.', $count, 'bloatbuster-performance-asset-cleaner' ), $count ) );
			?>
		</p>
		<?php
		return $count > 0;
	}

	/**
	 * Render the number of trashed comments.
	 *
	 * @return bool - True if there are trashed comments to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_trashed_comments() {
		$count = Database_Tools::count_comments( 'trash' );
		?>
		<p>
			<?php
			/* translators: %d: number of trashed comments. */
			echo \esc_html( sprintf( \_n( '%d comment in the trash.', '%d comments in the trash.', $count, 'bloatbuster-performance-asset-cleaner' ), $count ) );
			?>
		</p>
		<?php
		return $count > 0;
	}

	/**
	 * Render the trash age input and the trashed posts per post type.
	 *
	 * @return bool - True if there are trashed posts to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_trashed_posts() {
		$days   = self::get_trash_days();
		$counts = Database_Tools::get_trashed_post_type_counts( $days );
		$count  = array_sum( $counts );
		?>
		<p>
			<label for="bbpac-trash-days"><?php \esc_html_e( 'Delete items trashed more than this many days ago (0 for all):', 'bloatbuster-performance-asset-cleaner' ); ?></label>
			<input type="number" id="bbpac-trash-days" name="bbpac_trash_days" value="<?php echo \esc_attr( $days ); ?>" min="0" max="<?php echo \esc_attr( Database_Tools::MAX_TRASH_DAYS ); ?>" step="1" class="small-text" required>
		</p>
		<p>
			<?php
			if ( 0 === $days ) {
				/* translators: %d: number of trashed items. */
				echo \esc_html( sprintf( \_n( '%d item in the trash.', '%d items in the trash.', $count, 'bloatbuster-performance-asset-cleaner' ), $count ) );
			} else {
				/* translators: 1: number of trashed items, 2: number of days. */
				echo \esc_html( sprintf( \_n( '%1$d item trashed more than %2$d days ago.', '%1$d items trashed more than %2$d days ago.', $count, 'bloatbuster-performance-asset-cleaner' ), $count, $days ) );
			}
			?>
		</p>
		<?php if ( ! empty( $counts ) ) : ?>
			<table class="widefat striped bbpac-trash-types">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Type', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Items', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $counts as $post_type => $total ) : ?>
						<?php $object = \get_post_type_object( $post_type ); ?>
						<tr>
							<td>
								<?php if ( $object ) : ?>
									<?php echo \esc_html( $object->labels->name ); ?>
								<?php else : ?>
									<code><?php echo \esc_html( $post_type ); ?></code>
								<?php endif; ?>
							</td>
							<td><?php echo \esc_html( \number_format_i18n( $total ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;

		// Enabled when anything is in the trash, the age can be changed in the form.
		return Database_Tools::count_trashed_posts() > 0;
	}

	/**
	 * Get the meta type labels shown in the orphaned metadata table.
	 *
	 * @return array - Labels keyed by meta type.
	 *
	 * @since 1.0.0
	 */
	public static function get_meta_type_labels() {
		return array(
			'post'    => \__( 'Post meta', 'bloatbuster-performance-asset-cleaner' ),
			'comment' => \__( 'Comment meta', 'bloatbuster-performance-asset-cleaner' ),
			'term'    => \__( 'Term meta', 'bloatbuster-performance-asset-cleaner' ),
			'user'    => \__( 'User meta', 'bloatbuster-performance-asset-cleaner' ),
		);
	}

	/**
	 * Get the orphaned meta counts of the types the current user may clean.
	 *
	 * Counted once per request, the preview and the action field both need them.
	 *
	 * @return array - Counts keyed by meta type.
	 *
	 * @since 1.0.0
	 */
	public static function get_orphaned_meta_counts() {
		if ( null !== self::$orphaned_meta_counts ) {
			return self::$orphaned_meta_counts;
		}

		$counts = array();

		foreach ( array_keys( Database_Tools::get_meta_types() ) as $type ) {
			if ( Database_Tools::can_clean_meta_type( $type ) ) {
				$counts[ $type ] = Database_Tools::count_orphaned_meta( $type );
			}
		}

		self::$orphaned_meta_counts = $counts;

		return $counts;
	}

	/**
	 * Render the orphaned meta rows per type, with a checkbox to select the types to clean.
	 *
	 * @return bool - True if there are orphaned meta rows to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_orphaned_meta() {
		$types  = Database_Tools::get_meta_types();
		$labels = self::get_meta_type_labels();
		$counts = self::get_orphaned_meta_counts();
		?>
		<table class="widefat striped bbpac-orphans">
			<thead>
				<tr>
					<td class="check-column"><span class="screen-reader-text"><?php \esc_html_e( 'Select', 'bloatbuster-performance-asset-cleaner' ); ?></span></td>
					<th><?php \esc_html_e( 'Type', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Table', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Orphaned rows', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Top keys', 'bloatbuster-performance-asset-cleaner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $types as $type => $tables ) : ?>
					<?php $input_id = 'bbpac-orphan-' . $type; ?>
					<tr>
						<?php if ( ! isset( $counts[ $type ] ) ) : ?>
							<td class="check-column"></td>
							<td><?php echo \esc_html( $labels[ $type ] ); ?></td>
							<td><code><?php echo \esc_html( $tables['meta_table'] ); ?></code></td>
							<td colspan="2"><?php \esc_html_e( 'User meta is shared by the network, clean it on the main site as a network admin.', 'bloatbuster-performance-asset-cleaner' ); ?></td>
						<?php else : ?>
							<?php $keys = $counts[ $type ] > 0 ? Database_Tools::get_orphaned_meta_keys( $type ) : array(); ?>
							<td class="check-column">
								<input type="checkbox" id="<?php echo \esc_attr( $input_id ); ?>" name="bbpac_orphan_types[]" value="<?php echo \esc_attr( $type ); ?>" <?php \checked( $counts[ $type ] > 0 ); ?> <?php \disabled( 0 === $counts[ $type ] ); ?>>
							</td>
							<td><label for="<?php echo \esc_attr( $input_id ); ?>"><?php echo \esc_html( $labels[ $type ] ); ?></label></td>
							<td><code><?php echo \esc_html( $tables['meta_table'] ); ?></code></td>
							<td><?php echo \esc_html( \number_format_i18n( $counts[ $type ] ) ); ?></td>
							<td>
								<?php
								if ( empty( $keys ) ) {
									echo '-';
								}

								foreach ( $keys as $index => $key ) {
									echo 0 < $index ? ', ' : '';
									echo '<code>' . \esc_html( $key['key'] ) . '</code> (' . \esc_html( \number_format_i18n( $key['total'] ) ) . ')';
								}
								?>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return array_sum( $counts ) > 0;
	}

	/**
	 * Get the item counts of the available plugin data integrations.
	 *
	 * Counted once per request, the preview and the action field both need them.
	 *
	 * @return array - Counts keyed by integration ID.
	 *
	 * @since 1.0.0
	 */
	public static function get_integration_counts() {
		if ( null !== self::$integration_counts ) {
			return self::$integration_counts;
		}

		$counts = array();

		foreach ( Cleanup_Integrations::get_available_integrations() as $id => $integration ) {
			$counts[ $id ] = Cleanup_Integrations::run_count( $integration );
		}

		self::$integration_counts = $counts;

		return $counts;
	}

	/**
	 * Render the available plugin data integrations, with a checkbox to select the ones to clean.
	 *
	 * @return bool - True if there is plugin data to delete.
	 *
	 * @since 1.0.0
	 */
	public static function render_plugin_data() {
		$integrations = Cleanup_Integrations::get_available_integrations();
		$counts       = self::get_integration_counts();

		if ( empty( $integrations ) ) {
			echo '<p>' . \esc_html__( 'No supported plugin is active.', 'bloatbuster-performance-asset-cleaner' ) . '</p>';
			return false;
		}
		?>
		<table class="widefat striped bbpac-integrations">
			<thead>
				<tr>
					<td class="check-column"><span class="screen-reader-text"><?php \esc_html_e( 'Select', 'bloatbuster-performance-asset-cleaner' ); ?></span></td>
					<th><?php \esc_html_e( 'Plugin data', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'What is deleted', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Items', 'bloatbuster-performance-asset-cleaner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $integrations as $id => $integration ) : ?>
					<?php
					$input_id = 'bbpac-integration-' . $id;
					$count    = $counts[ $id ] ?? 0;
					?>
					<tr>
						<td class="check-column">
							<input type="checkbox" id="<?php echo \esc_attr( $input_id ); ?>" name="bbpac_integrations[]" value="<?php echo \esc_attr( $id ); ?>" <?php \checked( $count > 0 ); ?> <?php \disabled( 0 === $count ); ?>>
						</td>
						<td><label for="<?php echo \esc_attr( $input_id ); ?>"><?php echo \esc_html( $integration['label'] ); ?></label></td>
						<td><?php echo \esc_html( $integration['description'] ); ?></td>
						<td><?php echo \esc_html( \number_format_i18n( $count ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return array_sum( $counts ) > 0;
	}

	/**
	 * Get the label of an action field, used in the cleanup log.
	 *
	 * @param string $action_id - Action field ID.
	 *
	 * @return string - Field label, or the ID when the action no longer exists.
	 *
	 * @since 1.0.0
	 */
	public static function get_action_label( $action_id ) {
		foreach ( self::get_fields() as $field ) {
			if ( 'action' === $field['type'] && $action_id === $field['id'] ) {
				return $field['label'];
			}
		}

		return $action_id;
	}

	/**
	 * Render the recent cleanup runs.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_cleanup_log() {
		$log = Database_Tools::get_cleanup_log();

		if ( empty( $log ) ) {
			echo '<p>' . \esc_html__( 'No cleanups run yet.', 'bloatbuster-performance-asset-cleaner' ) . '</p>';
			return;
		}

		$date_format = \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' );
		?>
		<table class="widefat striped bbpac-cleanup-log">
			<thead>
				<tr>
					<th><?php \esc_html_e( 'When', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Cleanup', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Removed', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Left', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'By', 'bloatbuster-performance-asset-cleaner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $log as $entry ) : ?>
					<?php
					if ( ! is_array( $entry ) ) {
						continue;
					}

					$time = \absint( $entry['time'] ?? 0 );
					$user = \get_userdata( \absint( $entry['user'] ?? 0 ) );
					?>
					<tr>
						<td>
							<span title="<?php echo \esc_attr( \wp_date( $date_format, $time ) ); ?>">
								<?php
								/* translators: %s: human readable time difference, e.g. 5 minutes. */
								echo \esc_html( sprintf( \__( '%s ago', 'bloatbuster-performance-asset-cleaner' ), \human_time_diff( $time ) ) );
								?>
							</span>
						</td>
						<td><?php echo \esc_html( self::get_action_label( (string) ( $entry['action'] ?? '' ) ) ); ?></td>
						<td><?php echo \esc_html( \number_format_i18n( \absint( $entry['deleted'] ?? 0 ) ) ); ?></td>
						<td><?php echo \esc_html( \number_format_i18n( \absint( $entry['remaining'] ?? 0 ) ) ); ?></td>
						<td><?php echo \esc_html( $user ? $user->display_name : \__( 'Unknown user', 'bloatbuster-performance-asset-cleaner' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render the expired transients count and list.
	 *
	 * @return bool - True if there are expired transients to clean.
	 *
	 * @since 1.0.0
	 */
	public static function render_expired_transients() {
		$limit = 50;
		$count = Database_Tools::count_expired_transients();

		if ( 0 === $count ) {
			echo '<p>' . \esc_html__( 'No expired transients found.', 'bloatbuster-performance-asset-cleaner' ) . '</p>';
			return false;
		}

		$transients = Database_Tools::get_expired_transients( $limit );
		?>
		<p>
			<?php
			/* translators: %d: number of expired transients. */
			echo \esc_html( sprintf( \__( 'Expired transients in database: %d', 'bloatbuster-performance-asset-cleaner' ), $count ) );
			?>
		</p>
		<table class="widefat striped bbpac-transients">
			<thead>
				<tr>
					<th><?php \esc_html_e( 'Name', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Type', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					<th><?php \esc_html_e( 'Expired', 'bloatbuster-performance-asset-cleaner' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $transients as $transient ) : ?>
					<tr>
						<td><code><?php echo \esc_html( $transient['name'] ); ?></code></td>
						<td><?php echo $transient['network'] ? \esc_html__( 'Network', 'bloatbuster-performance-asset-cleaner' ) : \esc_html__( 'Site', 'bloatbuster-performance-asset-cleaner' ); ?></td>
						<td>
							<?php
							/* translators: %s: human readable time difference, e.g. 5 minutes. */
							echo \esc_html( sprintf( \__( '%s ago', 'bloatbuster-performance-asset-cleaner' ), \human_time_diff( $transient['expired'] ) ) );
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		if ( $count > $limit ) {
			$more = $count - $limit;

			/* translators: %d: number of expired transients not shown in the list. */
			echo '<p>' . \esc_html( sprintf( \_n( 'And %d more.', 'And %d more.', $more, 'bloatbuster-performance-asset-cleaner' ), $more ) ) . '</p>';
		}

		return true;
	}
}
