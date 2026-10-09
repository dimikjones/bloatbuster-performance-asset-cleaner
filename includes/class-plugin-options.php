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
 * Registers the plugin settings page and its fields.
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
	 * Get the settings fields.
	 *
	 * @return array - List of fields with id, label and description.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		return array(
			array(
				'type'        => 'toggle',
				'id'          => 'disable_block_editor_styles_frontend',
				'label'       => \__( 'Disable Block Editor Styles on the Frontend', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'If not using Blocks disable their styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_heartbeat_frontend',
				'label'       => \__( 'Disable Heartbeat API on the frontend', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'control_heartbeat_settings',
				'label'       => \__( 'Control the Heartbeat API execution', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Control the Heartbeat API execution based on user area (30s for admin and 60s for frontend).', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_dashicons_non_admin',
				'label'       => \__( 'Disable Dashicons for non-admin', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable default WordPress Dashicons styles on frontend for non-admin users.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_emojis',
				'label'       => \__( 'Disable emoji scripts and styles', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all WordPress emoji scripts and styles.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_wp_oembed',
				'label'       => \__( 'Disable all oEmbed-related scripts', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable all oEmbed-related scripts and discovery links from WordPress.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_self_pingbacks',
				'label'       => \__( 'Disable self-pingbacks', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable self-pingbacks in WordPress to prevent unnecessary notifications.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'limit_post_revisions',
				'label'       => \__( 'Limit post revisions', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Limit WordPress post revisions to 5.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_capital_p_dangit',
				'label'       => \__( 'Disable capital_P_dangit', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'It prevents WordPress from auto-correcting "wordpress" to "WordPress."', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'toggle',
				'id'          => 'disable_comments',
				'label'       => \__( 'Disable comments', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Disable comments, pingbacks and trackbacks across the entire site, including the admin area.', 'bloatbuster-performance-asset-cleaner' ),
			),
			array(
				'type'        => 'checkbox',
				'id'          => 'delete_data_on_uninstall',
				'label'       => \__( 'Delete plugin data on uninstall', 'bloatbuster-performance-asset-cleaner' ),
				'description' => \__( 'Remove all BloatBuster settings from the database when the plugin is deleted.', 'bloatbuster-performance-asset-cleaner' ),
			),
		);
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
	 * Register the setting, section and fields.
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

		\add_settings_section(
			BLOATBUSTER_OPTION,
			\__( 'Assets', 'bloatbuster-performance-asset-cleaner' ),
			array( __CLASS__, 'render_section' ),
			BLOATBUSTER_OPTION
		);

		foreach ( self::get_fields() as $field ) {
			\add_settings_field(
				$field['id'],
				$field['label'],
				array( __CLASS__, 'render_field' ),
				BLOATBUSTER_OPTION,
				BLOATBUSTER_OPTION,
				$field
			);
		}
	}

	/**
	 * Sanitize the saved options, keeping only known toggles.
	 *
	 * @param mixed $input - Raw submitted options.
	 *
	 * @return array - Sanitized options.
	 *
	 * @since 1.0.0
	 */
	public static function sanitize_options( $input ) {
		$sanitized = array();

		if ( ! is_array( $input ) ) {
			return $sanitized;
		}

		foreach ( self::get_fields() as $field ) {
			if ( ! empty( $input[ $field['id'] ] ) ) {
				$sanitized[ $field['id'] ] = 1;
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

		\settings_errors( 'messages' );
		?>
		<div class="wrap" id="bloatbuster-performance-asset-cleaner-options">
			<h1><?php echo \esc_html( self::get_page_title() ); ?></h1>
			<p><?php \esc_html_e( 'Streamline your site\'s performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features below to keep your frontend fast, light, and clean.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<form action="options.php" method="POST">
				<?php \settings_fields( BLOATBUSTER_OPTION ); ?>
				<?php \do_settings_sections( BLOATBUSTER_OPTION ); ?>
				<?php \submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a toggle or checkbox field.
	 *
	 * @param array $args - Field data with type, id, label and description.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_field( $args ) {
		$options     = \get_option( BLOATBUSTER_OPTION );
		$option_name = BLOATBUSTER_OPTION . '[' . $args['id'] . ']';
		$value       = ( ! empty( $options ) && ! empty( $options[ $args['id'] ] ) ) ? $options[ $args['id'] ] : '';

		if ( 'checkbox' === $args['type'] ) {
			?>
			<div class="bbpac-field checkbox">
				<input type="checkbox" name="<?php echo \esc_attr( $option_name ); ?>" value="1" <?php \checked( 1, $value ); ?>>
			</div>
			<?php
		} else {
			?>
			<div class="bbpac-field toggle">
				<label>
					<input type="checkbox" name="<?php echo \esc_attr( $option_name ); ?>" value="1" <?php \checked( 1, $value ); ?>>
					<span class="slider round"></span>
				</label>
			</div>
			<?php
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . \esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render the section intro.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_section() {
		?>
		<p><?php \esc_html_e( 'Boost WordPress performance with these options', 'bloatbuster-performance-asset-cleaner' ); ?></p>
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
