<?php
/**
 * Plugin settings page.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin settings page, its tabs and fields.
 */
final class Plugin_Options {

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		\add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		\add_action( 'admin_init', array( __CLASS__, 'configure_options' ) );
	}

	/**
	 * Get the settings tabs that have at least one field.
	 *
	 * @return array - Tabs keyed by slug, each with label and description.
	 *
	 * @since 1.0.0
	 */
	public static function get_tabs() {
		$tabs = array(
			'assets'     => array(
				'label'       => \__( 'Assets', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Remove styles and scripts WordPress loads on the frontend that your site may not need.', 'bloatbuster-performance-asset-cleaner' ),
			),
			'core'       => array(
				'label'       => \__( 'WordPress Core', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Turn off WordPress core features you do not use, like oEmbed, comments and pingbacks.', 'bloatbuster-performance-asset-cleaner' ),
			),
			'database'   => array(
				'label'       => \__( 'Database', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Clean up data WordPress no longer needs.', 'bloatbuster-performance-asset-cleaner' ),
			),
			'background' => array(
				'label'       => \__( 'Background Processes', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Control how often WordPress runs background requests like the Heartbeat API.', 'bloatbuster-performance-asset-cleaner' ),
			),
			'advanced'   => array(
				'label'       => \__( 'Advanced', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Plugin behavior settings.', 'bloatbuster-performance-asset-cleaner' ),
			),
		);

		$used_tabs = array_unique( array_column( self::get_fields(), 'tab' ) );

		// Hide tabs without fields.
		return array_intersect_key( $tabs, array_flip( $used_tabs ) );
	}

	/**
	 * Get the settings fields.
	 *
	 * @return array - List of fields with tab, type, id, label and description.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		return array(
			// Assets.
			array(
				'tab'         => 'assets',
				'type'        => 'toggle',
				'id'          => 'disable_block_editor_styles_frontend',
				'label'       => \__( 'Disable Gutenberg block styles', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes the block library CSS (wp-block-library) on the frontend. Only enable if your theme does not use blocks.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'assets',
				'type'        => 'toggle',
				'id'          => 'disable_emojis',
				'label'       => \__( 'Disable emoji scripts and styles', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all WordPress emoji scripts and styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'assets',
				'type'        => 'toggle',
				'id'          => 'disable_dashicons_non_admin',
				'label'       => \__( 'Disable Dashicons on the frontend when the toolbar is hidden', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes the Dashicons stylesheet for logged-out visitors and users who do not see the admin toolbar. Users with the toolbar keep it, so the toolbar never breaks.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'assets',
				'type'        => 'toggle',
				'id'          => 'disable_jquery_migrate',
				'label'       => \__( 'Disable jQuery Migrate', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Stop loading jQuery Migrate on the frontend. It stays active in the admin area.', 'bloatbuster-performance-asset-cleaner' ),
			),

			// WordPress Core.
			array(
				'tab'         => 'core',
				'type'        => 'toggle',
				'id'          => 'disable_wp_oembed',
				'label'       => \__( 'Disable oEmbed discovery and embed script', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Removes oEmbed discovery links, the wp-embed script and the oEmbed REST endpoint, so other sites can no longer embed your posts. Embeds from YouTube, Vimeo and other services in your content keep working.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'core',
				'type'        => 'toggle',
				'id'          => 'disable_comments',
				'label'       => \__( 'Disable comments', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable comments, pingbacks and trackbacks across the entire site, including the admin area. Existing comments are hidden, not deleted.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'core',
				'type'        => 'toggle',
				'id'          => 'disable_self_pingbacks',
				'label'       => \__( 'Disable self-pingbacks', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Minor core cleanup: stops WordPress from sending pingbacks to its own posts.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'core',
				'type'        => 'toggle',
				'id'          => 'limit_post_revisions',
				'label'       => \__( 'Limit post revisions', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Keep only a set number of revisions per post. Overrides WP_POST_REVISIONS from wp-config.php while enabled.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'core',
				'type'        => 'number',
				'id'          => 'post_revisions_limit',
				'parent'      => 'limit_post_revisions',
				'label'       => \__( 'Revisions to keep', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Number of revisions to keep per post. 0 disables revisions.', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 0,
				'max'         => 100,
				'step'        => 1,
				'default'     => 5,
			),
			array(
				'tab'         => 'core',
				'type'        => 'toggle',
				'id'          => 'remove_head_tags',
				'label'       => \__( 'Remove unnecessary head tags', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Remove the WordPress version, RSD, Windows Live Writer, shortlink, REST API and adjacent post links from the page head and HTTP headers.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'core',
				'type'        => 'toggle',
				'id'          => 'disable_xmlrpc',
				'label'       => \__( 'Disable XML-RPC', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all XML-RPC methods, including pingbacks. Do not enable if you use Jetpack or the WordPress mobile app.', 'bloatbuster-performance-asset-cleaner' ),
			),

			// Background Processes.
			array(
				'tab'         => 'background',
				'type'        => 'toggle',
				'id'          => 'disable_heartbeat_frontend',
				'label'       => \__( 'Disable Heartbeat API on the frontend', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor. Some frontend plugins (live chat, forms, membership) may rely on Heartbeat.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'background',
				'type'        => 'toggle',
				'id'          => 'control_heartbeat_settings',
				'label'       => \__( 'Control the Heartbeat API interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Set how often the Heartbeat API runs on the frontend, in the dashboard and in the post editor.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'background',
				'type'        => 'number',
				'id'          => 'heartbeat_interval_frontend',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Frontend interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests on the frontend (15-120).', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 60,
			),
			array(
				'tab'         => 'background',
				'type'        => 'number',
				'id'          => 'heartbeat_interval_dashboard',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Dashboard interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests in the admin area, except the post editor (15-120).', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 60,
			),
			array(
				'tab'         => 'background',
				'type'        => 'number',
				'id'          => 'heartbeat_interval_editor',
				'parent'      => 'control_heartbeat_settings',
				'label'       => \__( 'Post editor interval', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Seconds between Heartbeat requests in the post editor, used for autosave and post locking (15-120).', 'bloatbuster-performance-asset-cleaner' ),
				'min'         => 15,
				'max'         => 120,
				'step'        => 5,
				'default'     => 30,
			),

			// Database.
			array(
				'tab'         => 'database',
				'type'        => 'report',
				'id'          => 'autoload_summary',
				'label'       => \__( 'Autoloaded options', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Options WordPress loads on every page request. Large values here slow down the whole site.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'database',
				'type'        => 'action',
				'id'          => 'clean_expired_transients',
				'label'       => \__( 'Clean expired transients', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Delete transients that have expired but are still stored in the database.', 'bloatbuster-performance-asset-cleaner' ),
				'button'      => \__( 'Clean now', 'bloatbuster-performance-asset-cleaner' ),
			),

			// Advanced.
			array(
				'tab'         => 'advanced',
				'type'        => 'select',
				'id'          => 'speculative_loading_mode',
				'label'       => \__( 'Speculative loading mode', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Prerender loads pages faster than prefetch, but prefetch is safer for interactive content. Disabled turns speculative loading off completely.', 'bloatbuster-performance-asset-cleaner' ),
				'options'     => array(
					''          => \__( 'WordPress default', 'bloatbuster-performance-asset-cleaner' ),
					'disabled'  => \__( 'Disabled', 'bloatbuster-performance-asset-cleaner' ),
					'prefetch'  => \__( 'Prefetch', 'bloatbuster-performance-asset-cleaner' ),
					'prerender' => \__( 'Prerender', 'bloatbuster-performance-asset-cleaner' ),
				),
				'locked'      => Options_Dispatch::get_speculative_loading_lock(),
			),
			array(
				'tab'         => 'advanced',
				'type'        => 'select',
				'id'          => 'speculative_loading_eagerness',
				'label'       => \__( 'Speculative loading eagerness', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'When pages start loading in the background. Eager starts the soonest, Conservative loads only pages the user is about to open.', 'bloatbuster-performance-asset-cleaner' ),
				'options'     => array(
					''             => \__( 'WordPress default', 'bloatbuster-performance-asset-cleaner' ),
					'conservative' => \__( 'Conservative (typically on click)', 'bloatbuster-performance-asset-cleaner' ),
					'moderate'     => \__( 'Moderate (typically on hover)', 'bloatbuster-performance-asset-cleaner' ),
					'eager'        => \__( 'Eager (on slightest suggestion)', 'bloatbuster-performance-asset-cleaner' ),
				),
				'locked'      => Options_Dispatch::get_speculative_loading_lock(),
			),
			array(
				'tab'         => 'advanced',
				'type'        => 'toggle',
				'id'          => 'disable_capital_p_dangit',
				'label'       => \__( 'Disable capital_P_dangit', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Stops WordPress from changing "Wordpress" to "WordPress" in content. Negligible performance impact.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'tab'         => 'advanced',
				'type'        => 'checkbox',
				'id'          => 'delete_data_on_uninstall',
				'label'       => \__( 'Delete plugin data on uninstall', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Remove all BloatBuster settings from the database when the plugin is deleted.', 'bloatbuster-performance-asset-cleaner' ),
			),
		);
	}

	/**
	 * Get the currently selected tab, falling back to the first tab.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_current_tab() {
		$tabs = self::get_tabs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view flag.
		$tab = isset( $_GET['tab'] ) ? \sanitize_key( \wp_unslash( $_GET['tab'] ) ) : '';

		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = (string) array_key_first( $tabs );
		}

		return $tab;
	}

	/**
	 * Get the settings page slug used for a tab's sections.
	 *
	 * @param string $tab - Tab slug.
	 *
	 * @return string - Settings page slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_tab_page( $tab ) {
		return BLOATBUSTER_OPTION . '_' . $tab;
	}

	/**
	 * Add the settings page to the admin menu.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function add_admin_page() {
		\add_menu_page(
			self::get_page_title(),
			self::get_page_title(),
			'manage_options',
			BLOATBUSTER_OPTION,
			array( __CLASS__, 'options_page_html' ),
			'dashicons-chart-pie'
		);
	}

	/**
	 * Register the setting, one section per tab and the fields.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function configure_options() {
		\register_setting(
			BLOATBUSTER_OPTION,
			BLOATBUSTER_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_options' ),
				'default'           => array(),
			)
		);

		foreach ( self::get_tabs() as $slug => $tab ) {
			// Empty title, render_section() prints the title and description inside one wrapper.
			\add_settings_section(
				$slug,
				'',
				array( __CLASS__, 'render_section' ),
				self::get_tab_page( $slug )
			);
		}

		foreach ( self::get_fields() as $field ) {
			// Child fields get a row class, hidden until their parent toggle is on.
			if ( ! empty( $field['parent'] ) ) {
				$field['class'] = 'bbpac-child';

				if ( ! Options_Dispatch::get_option_value( $field['parent'] ) ) {
					$field['class'] .= ' bbpac-hidden';
				}
			}

			\add_settings_field(
				$field['id'],
				$field['label'],
				array( __CLASS__, 'render_field' ),
				self::get_tab_page( $field['tab'] ),
				$field['tab'],
				$field
			);
		}
	}

	/**
	 * Sanitize the saved options, keeping only known fields and values.
	 *
	 * When saved from a tab, only that tab's fields are updated so the
	 * other tabs keep their values.
	 *
	 * @param mixed $input - Raw submitted options.
	 *
	 * @return array - Sanitized options.
	 *
	 * @since 1.0.0
	 */
	public static function sanitize_options( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$tabs      = self::get_tabs();
		$tab       = isset( $input['_tab'] ) ? \sanitize_key( $input['_tab'] ) : '';
		$is_tab    = isset( $tabs[ $tab ] );
		$sanitized = array();

		if ( $is_tab ) {
			$stored    = \get_option( BLOATBUSTER_OPTION );
			$sanitized = is_array( $stored ) ? $stored : array();
		}

		foreach ( self::get_fields() as $field ) {
			if ( $is_tab && $field['tab'] !== $tab ) {
				continue;
			}

			// Locked fields are not submitted, keep their stored value. Actions store nothing.
			if ( ! empty( $field['locked'] ) || ! self::is_saved_field( $field ) ) {
				continue;
			}

			if ( 'select' === $field['type'] ) {
				$value = isset( $input[ $field['id'] ] ) ? \sanitize_key( $input[ $field['id'] ] ) : '';

				if ( '' !== $value && isset( $field['options'][ $value ] ) ) {
					$sanitized[ $field['id'] ] = $value;
				} else {
					unset( $sanitized[ $field['id'] ] );
				}

				continue;
			}

			if ( 'number' === $field['type'] ) {
				$value = isset( $input[ $field['id'] ] ) && '' !== $input[ $field['id'] ] ? \absint( $input[ $field['id'] ] ) : $field['default'];

				$sanitized[ $field['id'] ] = min( $field['max'], max( $field['min'], $value ) );

				continue;
			}

			if ( ! empty( $input[ $field['id'] ] ) ) {
				$sanitized[ $field['id'] ] = 1;
			} else {
				unset( $sanitized[ $field['id'] ] );
			}
		}

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function options_page_html() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by options.php redirect.
		if ( isset( $_GET['settings-updated'] ) ) {
			\add_settings_error( 'messages', 'message', \esc_html__( 'Settings Saved', 'bloatbuster-performance-asset-cleaner' ), 'updated' );
		}

		self::add_action_notice();

		$current_tab = self::get_current_tab();

		\settings_errors( 'messages' );
		?>
		<div class="wrap" id="bloatbuster-performance-asset-cleaner-options">
			<h1><?php echo \esc_html( self::get_page_title() ); ?></h1>
			<p><?php \esc_html_e( 'Streamline your site\'s performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features below to keep your frontend fast, light, and clean.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<nav class="nav-tab-wrapper">
				<?php foreach ( self::get_tabs() as $slug => $tab ) : ?>
					<a href="<?php echo \esc_url( \add_query_arg( 'tab', $slug, \menu_page_url( BLOATBUSTER_OPTION, false ) ) ); ?>" class="nav-tab<?php echo $slug === $current_tab ? ' nav-tab-active' : ''; ?>"><?php echo \esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php if ( self::tab_has_settings( $current_tab ) ) : ?>
				<form action="options.php" method="POST">
					<?php \settings_fields( BLOATBUSTER_OPTION ); ?>
					<input type="hidden" name="<?php echo \esc_attr( BLOATBUSTER_OPTION . '[_tab]' ); ?>" value="<?php echo \esc_attr( $current_tab ); ?>">
					<?php \do_settings_sections( self::get_tab_page( $current_tab ) ); ?>
					<?php \submit_button(); ?>
				</form>
			<?php else : ?>
				<form action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" method="POST">
					<input type="hidden" name="action" value="<?php echo \esc_attr( Database_Tools::ACTION ); ?>">
					<?php \wp_nonce_field( Database_Tools::ACTION ); ?>
					<?php \do_settings_sections( self::get_tab_page( $current_tab ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Check if a tab has fields that are saved as options.
	 *
	 * @param string $tab - Tab slug.
	 *
	 * @return bool - True if the tab has at least one saved field.
	 *
	 * @since 1.0.0
	 */
	public static function tab_has_settings( $tab ) {
		foreach ( self::get_fields() as $field ) {
			if ( $tab === $field['tab'] && self::is_saved_field( $field ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a field is saved as an option. Actions and reports are not.
	 *
	 * @param array $field - Field data.
	 *
	 * @return bool - True if the field value is saved.
	 *
	 * @since 1.0.0
	 */
	public static function is_saved_field( $field ) {
		return ! in_array( $field['type'], array( 'action', 'report' ), true );
	}

	/**
	 * Add the result notice of an action run from the settings page.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function add_action_notice() {
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
	 * Render a toggle, checkbox, select, number, action or report field.
	 *
	 * @param array $args - Field data with type, id, label, description and optional options, locked, button, parent, min, max, step and default.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_field( $args ) {
		$options     = \get_option( BLOATBUSTER_OPTION );
		$option_name = BLOATBUSTER_OPTION . '[' . $args['id'] . ']';
		$value       = ( ! empty( $options ) && ! empty( $options[ $args['id'] ] ) ) ? $options[ $args['id'] ] : '';
		$is_locked   = ! empty( $args['locked'] );

		if ( 'action' === $args['type'] ) {
			self::render_action_field( $args );
			return;
		}

		if ( 'report' === $args['type'] ) {
			if ( 'autoload_summary' === $args['id'] ) {
				self::render_autoload_summary();
			}

			if ( ! empty( $args['description'] ) ) {
				echo '<p class="description">' . \esc_html( $args['description'] ) . '</p>';
			}

			return;
		}

		if ( 'select' === $args['type'] ) {
			?>
			<div class="bbpac-field select">
				<select name="<?php echo \esc_attr( $option_name ); ?>" <?php \disabled( $is_locked ); ?>>
					<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
						<option value="<?php echo \esc_attr( $option_value ); ?>" <?php \selected( $value, $option_value ); ?>><?php echo \esc_html( $option_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<?php
		} elseif ( 'number' === $args['type'] ) {
			$number = isset( $options[ $args['id'] ] ) ? (int) $options[ $args['id'] ] : $args['default'];
			?>
			<div class="bbpac-field text number" data-bbpac-parent="<?php echo \esc_attr( $args['parent'] ?? '' ); ?>">
				<input type="number" name="<?php echo \esc_attr( $option_name ); ?>" value="<?php echo \esc_attr( $number ); ?>" min="<?php echo \esc_attr( $args['min'] ); ?>" max="<?php echo \esc_attr( $args['max'] ); ?>" step="<?php echo \esc_attr( $args['step'] ); ?>">
			</div>
			<?php
		} elseif ( 'checkbox' === $args['type'] ) {
			?>
			<div class="bbpac-field checkbox">
				<input type="checkbox" name="<?php echo \esc_attr( $option_name ); ?>" value="1" <?php \checked( 1, $value ); ?>>
			</div>
			<?php
		} else {
			?>
			<div class="bbpac-field toggle">
				<label>
					<input type="checkbox" id="<?php echo \esc_attr( 'bbpac-' . $args['id'] ); ?>" name="<?php echo \esc_attr( $option_name ); ?>" value="1" <?php \checked( 1, $value ); ?>>
					<span class="slider round"></span>
				</label>
			</div>
			<?php
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . \esc_html( $args['description'] ) . '</p>';
		}

		if ( $is_locked ) {
			self::render_locked_notice( $args['locked'] );
		}
	}

	/**
	 * Render an action field with its status and submit button.
	 *
	 * @param array $args - Field data with id, description and button.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_action_field( $args ) {
		$has_items = true;

		if ( 'clean_expired_transients' === $args['id'] ) {
			$has_items = self::render_expired_transients();
		}
		?>
		<p>
			<button type="submit" name="bloatbuster_action" value="<?php echo \esc_attr( $args['id'] ); ?>" class="button button-secondary" <?php \disabled( ! $has_items ); ?>><?php echo \esc_html( $args['button'] ); ?></button>
		</p>
		<?php
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . \esc_html( $args['description'] ) . '</p>';
		}

		if ( 'clean_expired_transients' === $args['id'] && \wp_using_ext_object_cache() ) {
			echo '<p class="description">' . \esc_html__( 'An object cache is active, so transients are mostly stored there. Only database rows are cleaned.', 'bloatbuster-performance-asset-cleaner' ) . '</p>';
		}
	}

	/**
	 * Render the autoloaded options summary, status and largest options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_autoload_summary() {
		$summary  = Database_Tools::get_autoload_summary();
		$largest  = Database_Tools::get_largest_autoloaded_options( 20 );
		$too_big  = $summary['size'] > $summary['limit'];
		$limit_size = \size_format( $summary['limit'] );
		?>
		<p>
			<?php
			/* translators: 1: number of autoloaded options, 2: total size, e.g. 512 KB. */
			echo \esc_html( sprintf( \__( '%1$d autoloaded options, %2$s in total.', 'bloatbuster-performance-asset-cleaner' ), $summary['count'], \size_format( $summary['size'], 1 ) ) );
			?>
		</p>
		<?php if ( $too_big ) : ?>
			<p class="bbpac-status-warning">
				<?php
				/* translators: %s: recommended size limit, e.g. 781 KB. */
				echo \esc_html( sprintf( \__( 'Too large: above the recommended limit of %s.', 'bloatbuster-performance-asset-cleaner' ), $limit_size ) );
				?>
				<a href="<?php echo \esc_url( \admin_url( 'site-health.php' ) ); ?>"><?php \esc_html_e( 'Check Site Health', 'bloatbuster-performance-asset-cleaner' ); ?></a>
			</p>
		<?php else : ?>
			<p class="bbpac-status-good">
				<?php
				/* translators: %s: recommended size limit, e.g. 781 KB. */
				echo \esc_html( sprintf( \__( 'Good: below the recommended limit of %s.', 'bloatbuster-performance-asset-cleaner' ), $limit_size ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $largest ) ) : ?>
			<table class="widefat striped bbpac-autoload">
				<thead>
					<tr>
						<th><?php \esc_html_e( 'Largest options', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						<th><?php \esc_html_e( 'Size', 'bloatbuster-performance-asset-cleaner' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $largest as $option ) : ?>
						<tr>
							<td><code><?php echo \esc_html( $option['name'] ); ?></code></td>
							<td><?php echo \esc_html( \size_format( $option['size'], 1 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		endif;
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

	/**
	 * Render the notice explaining why a field is locked.
	 *
	 * @param string $reason - Lock reason, 'plugin' or 'core'.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_locked_notice( $reason ) {
		if ( 'plugin' === $reason ) {
			?>
			<p class="description bbpac-locked">
				<?php \esc_html_e( 'Speculative loading is already configured by the Speculative Loading plugin.', 'bloatbuster-performance-asset-cleaner' ); ?>
				<a href="<?php echo \esc_url( \admin_url( 'options-reading.php#speculative-loading' ) ); ?>"><?php \esc_html_e( 'Go to its settings', 'bloatbuster-performance-asset-cleaner' ); ?></a>
			</p>
			<?php
		} elseif ( 'core' === $reason ) {
			?>
			<p class="description bbpac-locked"><?php \esc_html_e( 'Speculative loading requires WordPress 6.8 or newer.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<?php
		}
	}

	/**
	 * Render the tab section title and description in one wrapper.
	 *
	 * @param array $args - Section data with id, title and callback.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_section( $args ) {
		$tabs = self::get_tabs();

		if ( empty( $tabs[ $args['id'] ] ) ) {
			return;
		}

		$tab = $tabs[ $args['id'] ];
		?>
		<div class="bbpac-section-description">
			<h2><?php echo \esc_html( $tab['label'] ); ?></h2>
			<?php if ( ! empty( $tab['description'] ) ) : ?>
				<p><?php echo \esc_html( $tab['description'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get the settings page title.
	 *
	 * @return string - Page title.
	 *
	 * @since 1.0.0
	 */
	public static function get_page_title() {
		return \__( 'BloatBuster', 'bloatbuster-performance-asset-cleaner' );
	}
}
