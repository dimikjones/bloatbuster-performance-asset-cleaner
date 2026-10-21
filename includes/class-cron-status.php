<?php
/**
 * WP-Cron status.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads the WP-Cron state and scheduled events.
 */
final class Cron_Status {

	/**
	 * Seconds after its run time an event counts as overdue.
	 *
	 * @var int
	 */
	const OVERDUE_AFTER = 600;

	/**
	 * Get the WP-Cron status.
	 *
	 * @return array - Disabled and alternate flags, total and overdue counts, oldest overdue and last spawn timestamps.
	 *
	 * @since 1.0.0
	 */
	public static function get_status() {
		$events         = self::get_all_events();
		$overdue        = 0;
		$oldest_overdue = 0;

		foreach ( $events as $event ) {
			if ( ! $event['overdue'] ) {
				continue;
			}

			++$overdue;

			if ( 0 === $oldest_overdue || $event['timestamp'] < $oldest_overdue ) {
				$oldest_overdue = $event['timestamp'];
			}
		}

		return array(
			'disabled'       => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'alternate'      => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'total'          => count( $events ),
			'overdue'        => $overdue,
			'oldest_overdue' => $oldest_overdue,
			'last_spawn'     => (int) \get_transient( 'doing_cron' ),
		);
	}

	/**
	 * Get the next scheduled events, soonest first.
	 *
	 * @param int $limit - Maximum number of events.
	 *
	 * @return array - Events with hook, timestamp, schedule label and overdue flag.
	 *
	 * @since 1.0.0
	 */
	public static function get_events( $limit = 20 ) {
		return array_slice( self::get_all_events(), 0, \absint( $limit ) );
	}

	/**
	 * Get all scheduled events, soonest first.
	 *
	 * @return array - Events with hook, timestamp, schedule label and overdue flag.
	 *
	 * @since 1.0.0
	 */
	public static function get_all_events() {
		$crons     = \_get_cron_array();
		$schedules = \wp_get_schedules();
		$now       = time();
		$events    = array();

		if ( empty( $crons ) || ! is_array( $crons ) ) {
			return $events;
		}

		// The cron array is keyed by timestamp, sorted soonest first.
		foreach ( $crons as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}

			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( (array) $hook_events as $event ) {
					$schedule = isset( $event['schedule'] ) ? (string) $event['schedule'] : '';

					if ( '' === $schedule ) {
						$label = \__( 'Once', 'bloatbuster-performance-asset-cleaner' );
					} elseif ( isset( $schedules[ $schedule ]['display'] ) ) {
						$label = $schedules[ $schedule ]['display'];
					} else {
						$label = $schedule;
					}

					$events[] = array(
						'hook'      => (string) $hook,
						'timestamp' => (int) $timestamp,
						'schedule'  => $label,
						'overdue'   => (int) $timestamp < $now - self::OVERDUE_AFTER,
					);
				}
			}
		}

		return $events;
	}
}
