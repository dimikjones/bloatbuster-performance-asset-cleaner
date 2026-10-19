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
				'type'            => 'action',
				'id'              => 'clean_expired_transients',
				'label'           => \__( 'Clean expired transients', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'Delete transients that have expired but are still stored in the database.', 'bloatbuster-performance-asset-cleaner' ),
				'note'            => $note,
				'button'          => \__( 'Clean now', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_expired_transients' ),
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
		$notice = isset( $_GET['bloatbuster_notice'] ) ? \sanitize_key( \wp_unslash( $_GET['bloatbuster_notice'] ) ) : '';
		$count  = isset( $_GET['bloatbuster_count'] ) ? \absint( $_GET['bloatbuster_count'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'transients_cleaned' === $notice ) {
			/* translators: %d: number of deleted transients. */
			$message = sprintf( \_n( 'Deleted %d expired transient.', 'Deleted %d expired transients.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );

			\add_settings_error( 'messages', 'bloatbuster_notice', \esc_html( $message ), 'updated' );
		}
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
