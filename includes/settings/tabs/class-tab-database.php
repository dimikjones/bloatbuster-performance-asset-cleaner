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
		$trash_links = self::get_trash_links();

		$trash_rule = \__( 'Not cleaned here.', 'bloatbuster-performance-asset-cleaner' );

		if ( ! empty( $trash_links ) ) {
			$trash_rule = \__( 'Not cleaned here, empty the trash from:', 'bloatbuster-performance-asset-cleaner' );
		}

		return array(
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
				'label'    => \__( 'Trashed posts', 'bloatbuster-performance-asset-cleaner' ),
				'stored'   => $counts['trashed_posts'],
				'eligible' => null,
				'rule'     => $trash_rule,
				'action'   => '',
				'links'    => $trash_links,
			),
		);
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

			$links[] = array(
				'url'  => \add_query_arg(
					array(
						'post_status' => 'trash',
						'post_type'   => $post_type,
					),
					\admin_url( 'edit.php' )
				),
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
