<?php
/**
 * Assets settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend styles and scripts options.
 */
final class Tab_Assets extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'assets';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Assets', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Remove styles and scripts WordPress loads on the frontend that your site may not need.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 10;
	}

	/**
	 * Get the tab fields.
	 *
	 * @return array - List of fields.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		// Block themes need block and global styles.
		$block_theme_lock = \wp_is_block_theme() ? 'block_theme' : '';

		// WordPress loads classic theme styles only for themes without a theme.json file, see wp_enqueue_classic_theme_styles().
		$theme_json_lock = \wp_theme_has_theme_json() ? 'theme_json' : '';

		return array(
			array(
				'type'           => 'toggle',
				'id'             => 'disable_block_editor_styles_frontend',
				'label'          => \__( 'Disable Gutenberg block styles', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Removes the block library CSS (wp-block-library) on the frontend. Only enable if your theme does not use blocks.', 'bloatbuster-performance-asset-cleaner' ),
				'locked'         => $block_theme_lock,
				'locked_message' => \esc_html__( 'Not available with a block theme, it is built from blocks and needs these styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'           => 'toggle',
				'id'             => 'disable_classic_theme_styles',
				'label'          => \__( 'Disable classic theme styles', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Removes the classic-theme-styles CSS on the frontend, default styles for the Button and File blocks. WordPress only loads it for themes without a theme.json file.', 'bloatbuster-performance-asset-cleaner' ),
				'locked'         => $theme_json_lock,
				'locked_message' => \esc_html__( 'Not needed with this theme, WordPress only loads these styles for themes without a theme.json file.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'           => 'toggle',
				'id'             => 'disable_global_styles',
				'label'          => \__( 'Disable global styles', 'bloatbuster-performance-asset-cleaner' ),
				'description'    => \__( 'Removes the global-styles inline CSS on the frontend, the color, font size and spacing presets used by blocks. Only for classic themes that do not use blocks, block colors and sizes may change.', 'bloatbuster-performance-asset-cleaner' ),
				'locked'         => $block_theme_lock,
				'locked_message' => \esc_html__( 'Not available with a block theme, its design comes from global styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_emojis',
				'label'       => \__( 'Disable emoji scripts and styles', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all WordPress emoji scripts and styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_dashicons_non_admin',
				'label'       => \__( 'Disable Dashicons on the frontend when the toolbar is hidden', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes the Dashicons stylesheet for logged-out visitors and users who do not see the admin toolbar. Users with the toolbar keep it, so the toolbar never breaks.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_jquery_migrate',
				'label'       => \__( 'Disable jQuery Migrate', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Stop loading jQuery Migrate on the frontend. It stays active in the admin area.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'            => 'report',
				'id'              => 'asset_inspector',
				'label'           => \__( 'Frontend asset inspector', 'bloatbuster-performance-asset-cleaner' ),
				'description'     => \__( 'See which CSS and JavaScript files a page loads, and which plugin or theme adds them. The page is loaded in a hidden frame as a visitor without the toolbar would see it, and nothing is changed.', 'bloatbuster-performance-asset-cleaner' ),
				'render_callback' => array( __CLASS__, 'render_asset_inspector' ),
			),
		);
	}

	/**
	 * Render the asset inspector: scan form, last scan summary, filters and the assets table.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_asset_inspector() {
		$scan = Asset_Inspector::get_scan();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the scan redirect.
		$requested_id = isset( $_GET[ Asset_Inspector::RESULT_ARG ] ) ? \sanitize_key( \wp_unslash( $_GET[ Asset_Inspector::RESULT_ARG ] ) ) : '';
		$failed       = '' !== $requested_id && ( empty( $scan['id'] ) || $requested_id !== $scan['id'] );
		$default_url  = ! empty( $scan['url'] ) ? $scan['url'] : \home_url( '/' );
		?>
		<div class="bbpac-inspector" id="bbpac-inspector">
			<div class="bbpac-inspector-form">
				<label class="screen-reader-text" for="bbpac-inspector-url"><?php \esc_html_e( 'Page URL to scan', 'bloatbuster-performance-asset-cleaner' ); ?></label>
				<?php
				/**
				 * No name attribute, so the URL is not saved with the settings. A text input, not type="url",
				 * so browser validation of a relative path can't block saving the settings form. JS validates it.
				 */
				?>
				<input type="text" inputmode="url" autocomplete="off" spellcheck="false" id="bbpac-inspector-url" class="regular-text" value="<?php echo \esc_url( $default_url ); ?>">
				<button type="button" class="button button-secondary" id="bbpac-inspector-scan"><?php \esc_html_e( 'Scan page', 'bloatbuster-performance-asset-cleaner' ); ?></button>
				<span class="spinner"></span>
			</div>
			<p class="bbpac-inspector-status" role="status" aria-live="polite"></p>
			<?php if ( $failed ) : ?>
				<p class="bbpac-status-warning"><?php \esc_html_e( 'Scan failed, the page may block frames or a cache served an old copy. Try again, or scan another page.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<?php endif; ?>
			<?php
			if ( empty( $scan ) ) {
				echo '<p class="description">' . \esc_html__( 'No scan yet.', 'bloatbuster-performance-asset-cleaner' ) . '</p>';
				echo '</div>';

				return;
			}

			self::render_scan_summary( $scan );
			self::render_assets_table( $scan['assets'] );
			?>
			<p class="description">
				<?php \esc_html_e( 'The scan runs as your user, so assets loaded only for logged-in users may show. Plugins that combine or minify files still list the original handles.', 'bloatbuster-performance-asset-cleaner' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the date, URL, counts and total size of a scan.
	 *
	 * @param array $scan - Scan from Asset_Inspector::get_scan().
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_scan_summary( $scan ) {
		$counts = array(
			'css' => 0,
			'js'  => 0,
		);
		$total  = 0;

		foreach ( $scan['assets'] as $asset ) {
			// For assets without a printed file, inline_size holds only the code still printed.
			$total += isset( $asset['inline_size'] ) ? (int) $asset['inline_size'] : 0;

			// Listed in the table, but the page did not load their file.
			if ( isset( $asset['kind'] ) && 'not_printed' === $asset['kind'] ) {
				continue;
			}

			if ( isset( $asset['type'], $counts[ $asset['type'] ] ) ) {
				++$counts[ $asset['type'] ];
			}

			$total += isset( $asset['size'] ) ? (int) $asset['size'] : 0;
		}

		$date = \wp_date( \get_option( 'date_format' ) . ' ' . \get_option( 'time_format' ), (int) $scan['time'] );
		?>
		<p class="bbpac-inspector-summary">
			<?php
			/* translators: 1: scan date and time, 2: number of CSS assets, 3: number of JavaScript assets, 4: total size, e.g. 120 KB. */
			echo \esc_html( sprintf( \__( 'Last scan: %1$s. %2$d CSS, %3$d JavaScript, %4$s in local files and inline code.', 'bloatbuster-performance-asset-cleaner' ), $date, $counts['css'], $counts['js'], \size_format( $total, 1 ) ) );
			?>
			<br>
			<a href="<?php echo \esc_url( $scan['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( $scan['url'] ); ?></a>
		</p>
		<?php
	}

	/**
	 * Render the filters and the table of scanned assets.
	 *
	 * @param array $assets - Assets keyed by "{type}:{handle}".
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_assets_table( $assets ) {
		if ( empty( $assets ) ) {
			echo '<p class="description">' . \esc_html__( 'No styles or scripts were found on this page.', 'bloatbuster-performance-asset-cleaner' ) . '</p>';

			return;
		}

		$filters = array(
			'all' => \__( 'All', 'bloatbuster-performance-asset-cleaner' ),
			'css' => \__( 'CSS', 'bloatbuster-performance-asset-cleaner' ),
			'js'  => \__( 'JavaScript', 'bloatbuster-performance-asset-cleaner' ),
		);
		?>
		<div class="bbpac-inspector-filters">
			<div class="bbpac-inspector-types" role="group" aria-label="<?php \esc_attr_e( 'Filter by type', 'bloatbuster-performance-asset-cleaner' ); ?>">
				<?php foreach ( $filters as $filter => $label ) : ?>
					<button type="button" class="button" data-bbpac-filter="<?php echo \esc_attr( $filter ); ?>" aria-pressed="<?php echo 'all' === $filter ? 'true' : 'false'; ?>"><?php echo \esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
			<label class="screen-reader-text" for="bbpac-inspector-search"><?php \esc_html_e( 'Search assets', 'bloatbuster-performance-asset-cleaner' ); ?></label>
			<input type="search" id="bbpac-inspector-search" placeholder="<?php \esc_attr_e( 'Search handle, owner or source', 'bloatbuster-performance-asset-cleaner' ); ?>">
			<span class="bbpac-inspector-count" aria-live="polite"></span>
		</div>
		<div class="bbpac-inspector-table-wrap">
			<table class="widefat striped bbpac-inspector-table">
				<thead>
					<tr>
						<th scope="col"><?php \esc_html_e( 'Handle', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Type', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Owner', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Size', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Location', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Strategy', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Dependencies', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th scope="col"><?php \esc_html_e( 'Source', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $assets as $asset ) : ?>
						<?php self::render_asset_row( $asset ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render one row of the assets table.
	 *
	 * @param array $asset - Asset data from Asset_Inspector::collect_assets().
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_asset_row( $asset ) {
		$asset = \wp_parse_args(
			(array) $asset,
			array(
				'type'               => '',
				'handle'             => '',
				'kind'               => 'file',
				'src'                => '',
				'registered_src'     => '',
				'extra_srcs'         => array(),
				'ver'                => '',
				'deps'               => array(),
				'location'           => '',
				'strategy'           => '',
				'requested_strategy' => '',
				'size'               => null,
				'inline_size'        => 0,
				'owner'              => '',
				'confidence'         => '',
			)
		);

		$type_label = 'css' === $asset['type'] ? \__( 'CSS', 'bloatbuster-performance-asset-cleaner' ) : \__( 'JavaScript', 'bloatbuster-performance-asset-cleaner' );
		// Lowercased in JS, PHP strtolower() would miss non-ASCII letters like É.
		$extra_srcs = array_filter( (array) $asset['extra_srcs'], 'is_string' );
		$search     = $asset['handle'] . ' ' . $asset['owner'] . ' ' . $asset['src'] . ' ' . $asset['registered_src'] . ' ' . implode( ' ', $extra_srcs );
		?>
		<tr data-bbpac-type="<?php echo \esc_attr( $asset['type'] ); ?>" data-bbpac-search="<?php echo \esc_attr( $search ); ?>">
			<td><code><?php echo \esc_html( $asset['handle'] ); ?></code></td>
			<td><?php echo \esc_html( $type_label ); ?></td>
			<td>
				<?php echo \esc_html( $asset['owner'] ); ?>
				<?php if ( 'likely' === $asset['confidence'] ) : ?>
					<span class="bbpac-inspector-meta"><?php \esc_html_e( '(likely)', 'bloatbuster-performance-asset-cleaner' ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo \esc_html( self::get_asset_size_label( $asset ) ); ?></td>
			<td><?php echo \esc_html( self::get_location_label( $asset['location'] ) ); ?></td>
			<td>
				<?php echo \esc_html( '' !== $asset['strategy'] ? $asset['strategy'] : '—' ); ?>
				<?php if ( '' !== $asset['requested_strategy'] && $asset['requested_strategy'] !== $asset['strategy'] ) : ?>
					<span class="bbpac-inspector-meta">
						<?php
						/* translators: %s: requested loading strategy, async or defer. */
						echo \esc_html( sprintf( \__( 'Requested: %s, not applied by WordPress', 'bloatbuster-performance-asset-cleaner' ), $asset['requested_strategy'] ) );
						?>
					</span>
				<?php endif; ?>
			</td>
			<td><?php echo \esc_html( ! empty( $asset['deps'] ) ? implode( ', ', (array) $asset['deps'] ) : '—' ); ?></td>
			<td class="bbpac-inspector-source">
				<?php if ( '' !== $asset['src'] ) : ?>
					<a href="<?php echo \esc_url( $asset['src'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( $asset['src'] ); ?></a>
					<?php foreach ( $extra_srcs as $extra_src ) : ?>
						<span class="bbpac-inspector-meta">
							<?php \esc_html_e( 'Also loaded:', 'bloatbuster-performance-asset-cleaner' ); ?>
							<a href="<?php echo \esc_url( $extra_src ); ?>" target="_blank" rel="noopener noreferrer"><?php echo \esc_html( $extra_src ); ?></a>
						</span>
					<?php endforeach; ?>
					<?php if ( '' !== $asset['registered_src'] ) : ?>
						<span class="bbpac-inspector-meta">
							<?php
							/* translators: %s: asset URL as registered, before WordPress or a plugin changed it. */
							echo \esc_html( sprintf( \__( 'Registered as: %s', 'bloatbuster-performance-asset-cleaner' ), $asset['registered_src'] ) );
							?>
						</span>
					<?php endif; ?>
					<?php if ( '' !== $asset['ver'] ) : ?>
						<span class="bbpac-inspector-meta">
							<?php
							/* translators: %s: asset version. */
							echo \esc_html( sprintf( \__( 'ver %s', 'bloatbuster-performance-asset-cleaner' ), $asset['ver'] ) );
							?>
						</span>
					<?php endif; ?>
				<?php elseif ( '' !== $asset['registered_src'] ) : ?>
					—
					<span class="bbpac-inspector-meta">
						<?php
						/* translators: %s: asset URL as registered, before WordPress or a plugin changed it. */
						echo \esc_html( sprintf( \__( 'Registered as: %s', 'bloatbuster-performance-asset-cleaner' ), $asset['registered_src'] ) );
						?>
					</span>
				<?php else : ?>
					—
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Get the size label of an asset: file size, inline code size or Group.
	 *
	 * @param array $asset - Asset data.
	 *
	 * @return string - Size label.
	 *
	 * @since 1.0.0
	 */
	public static function get_asset_size_label( $asset ) {
		$inline = (int) $asset['inline_size'];

		if ( 'group' === $asset['kind'] ) {
			return \__( 'Group, loads its dependencies', 'bloatbuster-performance-asset-cleaner' );
		}

		if ( 'inline' === $asset['kind'] ) {
			/* translators: %s: size of the inline code, e.g. 2 KB. */
			return sprintf( \__( 'Inline, %s', 'bloatbuster-performance-asset-cleaner' ), \size_format( $inline, 1 ) );
		}

		if ( 'not_printed' === $asset['kind'] ) {
			$label = \__( 'File not printed', 'bloatbuster-performance-asset-cleaner' );
		} elseif ( null === $asset['size'] ) {
			$label = '—';
		} else {
			$label = \size_format( (int) $asset['size'], 1 );
		}

		if ( $inline > 0 ) {
			/* translators: 1: file size or a dash when unknown, 2: size of the inline code, e.g. 2 KB. */
			$label = sprintf( \__( '%1$s + %2$s inline', 'bloatbuster-performance-asset-cleaner' ), $label, \size_format( $inline, 1 ) );
		}

		return $label;
	}

	/**
	 * Get the label of an asset location.
	 *
	 * @param string $location - header, footer or empty.
	 *
	 * @return string - Location label.
	 *
	 * @since 1.0.0
	 */
	public static function get_location_label( $location ) {
		if ( 'header' === $location ) {
			return \__( 'Header', 'bloatbuster-performance-asset-cleaner' );
		}

		if ( 'footer' === $location ) {
			return \__( 'Footer', 'bloatbuster-performance-asset-cleaner' );
		}

		return '—';
	}
}
