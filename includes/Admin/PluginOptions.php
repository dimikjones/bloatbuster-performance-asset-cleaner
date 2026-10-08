<?php
/**
 * Register plugin admin page with options.
 *
 * @class       AdminPluginOptions
 * @version     1.0.0
 * @package     BloatBuster_Performance_Asset_Cleaner/Classes/
 */

namespace BloatBuster_Performance_Asset_Cleaner\Admin;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BloatBuster_Performance_Asset_Cleaner\AdminPage as AdminPageMain;

class PluginOptions extends AdminPageMain {

	/**
	 * Hook in methods.
	 */
	public static function hooks() {
		$instance = new PluginOptions();

		// Register our options_page to the admin_menu action hook.
		add_action( 'admin_menu', array( $instance, 'add_admin_page' ) );

		// Register our settings_init to the admin_init action hook.
		add_action( 'admin_init', array( $instance, 'configure_options' ) );
	}

	/**
	 * Adds the admin page to the menu.
	 */
	public function add_admin_page() {
		add_menu_page(
			$this->get_page_title(),
			$this->get_page_title(),
			'manage_options',
			$this->get_menu_slug(),
			array( $this, 'options_page_html' ),
			'dashicons-chart-pie'
		);
	}

	/**
	 * Configure the options page.
	 */
	public function configure_options() {
		// Register a new setting for plugin options page.
		register_setting( $this->get_menu_slug(), $this->get_menu_slug() );

		// Define fields.
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_block_editor_styles_frontend',
				'label'       => 'Disable Block Editor Styles on the Frontend',
				'description' => __( 'If not using Blocks disable their styles.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_heartbeat_frontend',
				'label'       => 'Disable Heartbeat API on the frontend',
				'description' => __( 'Disable the WordPress Heartbeat API on the frontend but keep it active in the admin and post editor.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'control_heartbeat_settings',
				'label'       => 'Control the Heartbeat API execution',
				'description' => __( 'Control the Heartbeat API execution based on user area (30s for admin and 60s for frontend).', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_dashicons_non_admin',
				'label'       => 'Disable Dashicons for non-admin',
				'description' => __( 'Disable default WordPress Dashicons styles on frontend for non-admin users.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_emojis',
				'label'       => 'Disable emoji scripts and styles',
				'description' => __( 'Disable all WordPress emoji scripts and styles.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_wp_oembed',
				'label'       => 'Disable all oEmbed-related scripts',
				'description' => __( 'Disable all oEmbed-related scripts and discovery links from WordPress.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_self_pingbacks',
				'label'       => 'Disable self-pingbacks',
				'description' => __( 'Disable self-pingbacks in WordPress to prevent unnecessary notifications.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'limit_post_revisions',
				'label'       => 'Limit post revisions',
				'description' => __( 'Limit WordPress post revisions to 5.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_capital_p_dangit',
				'label'       => 'Disable capital_P_dangit',
				'description' => __( 'It prevents WordPress from auto-correcting "wordpress" to "WordPress."', 'bloatbuster-performance-asset-cleaner' ),
			]
		);
		$this->add_field(
			[
				'type'        => 'toggle',
				'id'          => 'disable_comments',
				'label'       => 'Disable comments',
				'description' => __( 'Disable comments, pingbacks and trackbacks across the entire site, including the admin area.', 'bloatbuster-performance-asset-cleaner' ),
			]
		);

		// Add settings section.
		add_settings_section(
			$this->get_menu_slug(),
			__( 'Assets', 'bloatbuster-performance-asset-cleaner' ),
			[ $this, 'render_section' ],
			$this->get_menu_slug()
		);
	}

	/**
	 * Render the options page.
	 */
	public function options_page_html() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// WPCS: input var ok.
		if ( isset( $_REQUEST['settings-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			// Add settings saved message with the class of "updated".
			add_settings_error( 'messages', 'message', esc_html__( 'Settings Saved', 'bloatbuster-performance-asset-cleaner' ), 'updated' );
		}

		// Show error/update messages.
		settings_errors( 'messages' );
		?>
		<div class="wrap" id="bloatbuster-performance-asset-cleaner-options">
			<h1><?php echo esc_html( $this->get_page_title() ); ?></h1>
			<p>I want some introduction paragraph about my plugin here.</p>
			<form action="options.php" method="POST">
				<?php settings_fields( $this->get_menu_slug() ); ?>
				<?php do_settings_sections( $this->get_menu_slug() ); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public function add_field( $params ) {
		add_settings_field(
			$params['id'],
			$params['label'],
			[ $this, 'render_field' ],
			$this->get_menu_slug(),
			$this->get_menu_slug(),
			// Passing field data.
			$params
		);

		register_setting( $this->get_menu_slug(), $params['id'] );
	}

	// Render different field types dynamically.
	public function render_field( $args ) {
		$options     = get_option( $this->get_menu_slug() );
		$option_name = $this->get_menu_slug() . '[' . $args['id'] . ']';
		$value       = ( ! empty( $options ) && ! empty( $options[ $args['id'] ] ) ) ? $options[ $args['id'] ] : '';

		switch ( $args['type'] ) {
			case 'text':
				echo '<div class="bbpac-field text"><input type="text" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $value ) . '" /></div>';
				break;
			case 'checkbox':
				$checked = checked( 1, $value, false );
				echo '<div class="bbpac-field checkbox"><input type="checkbox" name="' . esc_attr( $option_name ) . '" value="1" ' . esc_attr( $checked ) . ' /></div>';
				break;
			case 'select':
				echo '<div class="bbpac-field select"><select name="' . esc_attr( $option_name ) . '">';
				if ( isset( $args['options'] ) ) {
					foreach ( $args['options'] as $option ) {
						$selected = selected( $value, $option, false );
						echo '<option value="' . esc_attr( $option ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $option ) . '</option>';
					}
				}
				echo '</select></div>';
				break;
			case 'toggle':
				$checked = checked( 1, $value, false );
				echo '<div class="bbpac-field toggle"><label>
                        <input type="checkbox" name="' . esc_attr( $option_name ) . '" value="1" ' . esc_attr( $checked ) . '>
                        <span class="slider round"></span>
                      </label></div>';
				break;
		}

		// Display the description below the field.
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Renders the section.
	 */
	public function render_section() {
		?>
		<p><?php esc_html_e( 'Boost WordPress performance with these options', 'bloatbuster-performance-asset-cleaner' ); ?></p>
		<?php
	}

	/**
	 * Get the options page menu slug and options name.
	 *
	 * @return string
	 */
	protected function get_menu_slug() {
		return 'bloatbuster_performance_asset_cleaner_options';
	}

	/**
	 * Get the options page title.
	 *
	 * @return string
	 */
	protected function get_page_title() {
		return __( 'BloatBuster', 'bloatbuster-performance-asset-cleaner' );
	}
}
