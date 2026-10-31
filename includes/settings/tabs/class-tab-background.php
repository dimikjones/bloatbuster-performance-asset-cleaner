<?php
/**
 * Background Processes settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Heartbeat API and other background request options.
 */
final class Tab_Background extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'background';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Background Processes', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Control how often WordPress runs background requests like the Heartbeat API.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 40;
	}

	/**
	 * Get the tab fields.
	 *
	 * @return array - List of fields.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		$autosave_lock    = Options_Dispatch::is_autosave_interval_external() ? 'constant' : '';
		$autosave_message = \esc_html__( 'AUTOSAVE_INTERVAL is already set, for example in wp-config.php.', 'bloatbuster-performance-asset-cleaner' );

		return array(
			array(
				'type'            => 'report',
				'id'              => 'cron_status',
				'label'           => \__( 'WP-Cron status', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'WP-Cron runs scheduled tasks like publishing scheduled posts, update checks and cleanups. Overdue tasks mean it is not running.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_cron_status' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_heartbeat_frontend',
				'label'       => \__( 'Disable Heartbeat API on the frontend', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor. Some frontend plugins (live chat, forms, membership) may rely on Heartbeat.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'control_heartbeat_settings',
				'label'       => \__( 'Control the Heartbeat API interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Set how often the Heartbeat API runs on the frontend, in the dashboard and in the post editor.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'number',
				'id'          => 'heartbeat_interval_frontend',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Frontend interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests on the frontend (15-120). WordPress default is 60.', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 120,
			),
			array(
				'type'        => 'number',
				'id'          => 'heartbeat_interval_dashboard',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Dashboard interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests in the admin area, except the post editor (15-120). WordPress default is 60.', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 120,
			),
			array(
				'type'        => 'number',
				'id'          => 'heartbeat_interval_editor',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Post editor interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests in the post editor, used for autosave and post locking (15-120). WordPress default is 10.', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 30,
			),
			array(
				'type'           => 'toggle',
				'id'             => 'control_autosave_interval',
				'label'          => \__( 'Control the autosave interval', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Set how often the post editor saves an autosave of your changes. A longer interval means fewer requests and fewer autosave revisions.', 'bloatbuster-performance-asset-cleaner' ),
				'locked'         => $autosave_lock,
				'locked_message' => $autosave_message,
			),
			array(
				'type'           => 'number',
				'id'             => 'autosave_interval',
				'parent'         => 'control_autosave_interval',
				'label'          => \__( 'Autosave interval', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Seconds between autosaves in the post editor (10-600). WordPress default is 60.', 'bloatbuster-performance-asset-cleaner' ),
				'min'            => 10,
				'max'            => 600,
				'step'           => 10,
				'default'        => 60,
				'locked'         => $autosave_lock,
				'locked_message' => $autosave_message,
			),
		);
	}

	/**
	 * Render the WP-Cron status, mode, tip and next scheduled events.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_cron_status() {
		$limit  = 20;
		$status = Cron_Status::get_status();
		$events = Cron_Status::get_events( $limit );
		?>
		<?php if ( $status['overdue'] > 0 ) : ?>
			<p class="bbpac-status-warning">
				<?php
				/* translators: 1: number of overdue events, 2: human readable time difference, e.g. 2 hours. */
				echo \esc_html( sprintf( \_n( '%1$d event is overdue, the oldest by %2$s.', '%1$d events are overdue, the oldest by %2$s.', $status['overdue'], 'bloatbuster-performance-asset-cleaner' ), $status['overdue'], \human_time_diff( $status['oldest_overdue'] ) ) );
				echo ' ';

				if ( $status['disabled'] ) {
					\esc_html_e( 'Check that the server cron job is running.', 'bloatbuster-performance-asset-cleaner' );
				} else {
					\esc_html_e( 'WP-Cron may not be running.', 'bloatbuster-performance-asset-cleaner' );
				}
				?>
				<a href="<?php echo \esc_url( \admin_url( 'site-health.php' ) ); ?>"><?php \esc_html_e( 'Check Site Health', 'bloatbuster-performance-asset-cleaner' ); ?></a>
			</p>
		<?php elseif ( $status['disabled'] ) : ?>
			<p class="bbpac-status-good">
				<?php
				/* translators: %d: number of scheduled events. */
				echo \esc_html( sprintf( \_n( 'Scheduled events run on time, %d event scheduled. A server cron job runs them.', 'Scheduled events run on time, %d events scheduled. A server cron job runs them.', $status['total'], 'bloatbuster-performance-asset-cleaner' ), $status['total'] ) );
				?>
			</p>
		<?php else : ?>
			<p class="bbpac-status-good">
				<?php
				/* translators: %d: number of scheduled events. */
				echo \esc_html( sprintf( \_n( 'WP-Cron is running, %d event scheduled.', 'WP-Cron is running, %d events scheduled.', $status['total'], 'bloatbuster-performance-asset-cleaner' ), $status['total'] ) );
				?>
			</p>
		<?php endif; ?>
		<p>
			<?php
			if ( $status['disabled'] ) {
				\esc_html_e( 'Mode: page visits don\'t run WP-Cron (DISABLE_WP_CRON), a server cron job must call wp-cron.php.', 'bloatbuster-performance-asset-cleaner' );
			} else {
				\esc_html_e( 'Mode: runs on page visits.', 'bloatbuster-performance-asset-cleaner' );
			}

			if ( $status['alternate'] ) {
				echo ' ' . \esc_html__( 'ALTERNATE_WP_CRON is on, events run with a redirect on page visits.', 'bloatbuster-performance-asset-cleaner' );
			}

			if ( $status['last_spawn'] > 0 ) {
				/* translators: %s: human readable time difference, e.g. 5 minutes. */
				echo ' ' . \esc_html( sprintf( \__( 'Last started %s ago.', 'bloatbuster-performance-asset-cleaner' ), \human_time_diff( $status['last_spawn'] ) ) );
			}
			?>
		</p>
		<?php if ( ! $status['disabled'] ) : ?>
			<p class="description">
				<?php \esc_html_e( 'Tip: on busy sites, turn off WP-Cron on page visits with DISABLE_WP_CRON in wp-config.php and run it from a server cron job instead.', 'bloatbuster-performance-asset-cleaner' ); ?>
				<a href="<?php echo \esc_url( 'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/' ); ?>" target="_blank" rel="noopener noreferrer"><?php \esc_html_e( 'How to set up a server cron job', 'bloatbuster-performance-asset-cleaner' ); ?></a>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $events ) ) : ?>
			<table class="widefat striped bbpac-cron">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Hook', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Next run', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Schedule', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $events as $event ) : ?>
						<tr>
							<td><code><?php echo \esc_html( $event['hook'] ); ?></code></td>
							<?php if ( $event['timestamp'] < time() ) : ?>
								<td class="<?php echo $event['overdue'] ? 'bbpac-status-warning' : ''; ?>">
									<?php
									/* translators: %s: human readable time difference, e.g. 5 minutes. */
									echo \esc_html( sprintf( \__( '%s ago', 'bloatbuster-performance-asset-cleaner' ), \human_time_diff( $event['timestamp'] ) ) );
									?>
								</td>
							<?php else : ?>
								<td>
									<?php
									/* translators: %s: human readable time difference, e.g. 5 minutes. */
									echo \esc_html( sprintf( \__( 'in %s', 'bloatbuster-performance-asset-cleaner' ), \human_time_diff( $event['timestamp'] ) ) );
									?>
								</td>
							<?php endif; ?>
							<td><?php echo \esc_html( $event['schedule'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;

		if ( $status['total'] > $limit ) {
			$more = $status['total'] - $limit;

			/* translators: %d: number of events not shown in the list. */
			echo '<p>' . \esc_html( sprintf( \_n( 'And %d more.', 'And %d more.', $more, 'bloatbuster-performance-asset-cleaner' ), $more ) ) . '</p>';
		}
	}
}
