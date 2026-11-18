<?php
/**
 * Environment settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the server and WordPress setup that affects speed.
 */
final class Tab_Environment extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'environment';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Environment', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Many slow sites are caused by the hosting setup, not by extra CSS or JS. Check your PHP, caches, WP-Cron and debug settings here.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 5;
	}

	/**
	 * Get how the tab form is submitted.
	 *
	 * @return string - Form type.
	 *
	 * @since 1.0.0
	 */
	public static function get_form_type() {
		return 'custom';
	}

	/**
	 * The tab has no settings fields.
	 *
	 * @return array - Empty list.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		return array();
	}

	/**
	 * Render the summary cards, the checks list and the Site Health link.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_content() {
		$checks = Environment_Checks::get_checks();

		self::render_summary( $checks );
		self::render_checks( $checks );
		?>
		<p class="description">
			<?php \esc_html_e( 'For more tests, like HTTPS, REST API and loopback requests, use Site Health.', 'bloatbuster-performance-asset-cleaner' ); ?>
			<a href="<?php echo \esc_url( \admin_url( 'site-health.php' ) ); ?>"><?php \esc_html_e( 'Open Site Health', 'bloatbuster-performance-asset-cleaner' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Render the summary cards for the main checks.
	 *
	 * @param array $checks - Checks from Environment_Checks::get_checks().
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_summary( $checks ) {
		$card_ids = array( 'php_version', 'object_cache', 'page_cache', 'autoload' );
		?>
		<div class="bbpac-cards bbpac-env-cards">
			<?php
			foreach ( $card_ids as $id ) :
				$check = Environment_Checks::find_check( $checks, $id );

				if ( null === $check ) {
					continue;
				}
				?>
				<div class="bbpac-card<?php echo 'recommended' === $check['status'] ? ' bbpac-card-warning' : ''; ?>">
					<span class="bbpac-card-label"><?php echo \esc_html( $check['label'] ); ?></span>
					<span class="bbpac-card-value"><?php echo \esc_html( $check['value'] ); ?></span>
					<span class="bbpac-card-meta"><?php echo \esc_html( self::get_status_label( $check['status'] ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render the list of all checks with status icons, messages and links.
	 *
	 * @param array $checks - Checks from Environment_Checks::get_checks().
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_checks( $checks ) {
		if ( empty( $checks ) ) {
			return;
		}
		?>
		<ul class="bbpac-env-checks">
			<?php foreach ( $checks as $check ) : ?>
				<li class="bbpac-env-check bbpac-env-<?php echo \esc_attr( $check['status'] ); ?>">
					<span class="dashicons <?php echo \esc_attr( self::get_status_icon( $check['status'] ) ); ?>" aria-hidden="true"></span>
					<div class="bbpac-env-check-body">
						<p class="bbpac-env-check-title">
							<strong><?php echo \esc_html( $check['label'] ); ?></strong>
							<?php if ( '' !== (string) $check['value'] ) : ?>
								<span class="bbpac-env-check-value"><?php echo \esc_html( $check['value'] ); ?></span>
							<?php endif; ?>
							<span class="screen-reader-text"><?php echo \esc_html( self::get_status_label( $check['status'] ) ); ?></span>
						</p>
						<?php if ( '' !== (string) $check['message'] || ! empty( $check['link'] ) ) : ?>
							<p class="description">
								<?php echo \esc_html( $check['message'] ); ?>
								<?php if ( ! empty( $check['link'] ) ) : ?>
									<?php self::render_link( $check['link'] ); ?>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * Render a check link, opening links outside the admin in a new tab.
	 *
	 * @param array $link - Link with url and text.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_link( $link ) {
		$is_external = 0 !== strpos( $link['url'], \admin_url() );
		?>
		<a href="<?php echo \esc_url( $link['url'] ); ?>"<?php echo $is_external ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo \esc_html( $link['text'] ); ?></a>
		<?php
	}

	/**
	 * Get the readable label of a check status.
	 *
	 * @param string $status - Check status.
	 *
	 * @return string - Status label.
	 *
	 * @since 1.0.0
	 */
	public static function get_status_label( $status ) {
		$labels = array(
			'good'        => \__( 'Good', 'bloatbuster-performance-asset-cleaner' ),
			'recommended' => \__( 'Recommended improvement', 'bloatbuster-performance-asset-cleaner' ),
			'info'        => \__( 'Info', 'bloatbuster-performance-asset-cleaner' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['info'];
	}

	/**
	 * Get the dashicon class of a check status.
	 *
	 * @param string $status - Check status.
	 *
	 * @return string - Dashicon class.
	 *
	 * @since 1.0.0
	 */
	public static function get_status_icon( $status ) {
		$icons = array(
			'good'        => 'dashicons-yes-alt',
			'recommended' => 'dashicons-warning',
			'info'        => 'dashicons-info',
		);

		return isset( $icons[ $status ] ) ? $icons[ $status ] : $icons['info'];
	}
}
