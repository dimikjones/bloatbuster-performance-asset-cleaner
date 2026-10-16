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
 *
 * Tabs are Settings_Tab classes in includes/settings/tabs/. Add tabs with
 * the bloatbuster_settings_tabs filter and fields with the
 * bloatbuster_settings_fields filter.
 */
final class Plugin_Options {

	/**
	 * Tabs cache for the current request.
	 *
	 * @var array|null
	 */
	private static $tabs = null;

	/**
	 * Fields cache for the current request.
	 *
	 * @var array|null
	 */
	private static $fields = null;

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
	 * Get the registered tab class names, sorted by priority.
	 *
	 * @return array - Settings_Tab class names.
	 *
	 * @since 1.0.0
	 */
	public static function get_tab_classes() {
		$classes = array(
			Tab_Assets::class,
			Tab_Core::class,
			Tab_Database::class,
			Tab_Background::class,
			Tab_Advanced::class,
		);

		/**
		 * Filter the settings tab classes. Each class must extend Settings_Tab.
		 *
		 * @param array $classes - Settings_Tab class names.
		 *
		 * @since 1.0.0
		 */
		$classes = \apply_filters( 'bloatbuster_settings_tabs', $classes );

		$valid = array();

		foreach ( (array) $classes as $class ) {
			if ( is_string( $class ) && is_subclass_of( $class, Settings_Tab::class ) ) {
				$valid[] = $class;
			}
		}

		$valid = array_values( array_unique( $valid ) );

		usort( $valid, array( __CLASS__, 'compare_tab_priority' ) );

		return $valid;
	}

	/**
	 * Compare two tab classes by priority, for usort().
	 *
	 * @param string $a - First tab class name.
	 * @param string $b - Second tab class name.
	 *
	 * @return int - Negative, zero or positive.
	 *
	 * @since 1.0.0
	 */
	public static function compare_tab_priority( $a, $b ) {
		return (int) $a::get_priority() - (int) $b::get_priority();
	}

	/**
	 * Get the settings tabs that have at least one field.
	 *
	 * @return array - Tabs keyed by slug, each with label, description and class.
	 *
	 * @since 1.0.0
	 */
	public static function get_tabs() {
		if ( null !== self::$tabs ) {
			return self::$tabs;
		}

		$used_tabs = array_flip( array_column( self::get_fields(), 'tab' ) );
		$tabs      = array();

		foreach ( self::get_tab_classes() as $class ) {
			$slug = \sanitize_key( $class::get_slug() );

			// Hide tabs without fields.
			if ( ! isset( $used_tabs[ $slug ] ) || isset( $tabs[ $slug ] ) ) {
				continue;
			}

			$tabs[ $slug ] = array(
				'label'       => $class::get_label(),
				'description' => $class::get_description(),
				'class'       => $class,
			);
		}

		self::$tabs = $tabs;

		return self::$tabs;
	}

	/**
	 * Get the settings fields of all tabs.
	 *
	 * @return array - List of fields with tab, type, id, label and description.
	 *
	 * @since 1.0.0
	 */
	public static function get_fields() {
		if ( null !== self::$fields ) {
			return self::$fields;
		}

		$fields = array();

		foreach ( self::get_tab_classes() as $class ) {
			$slug = \sanitize_key( $class::get_slug() );

			foreach ( (array) $class::get_fields() as $field ) {
				$field['tab'] = $slug;
				$fields[]     = $field;
			}
		}

		/**
		 * Filter the settings fields. Use it to add fields to existing tabs.
		 *
		 * @param array $fields - List of fields with tab, type, id, label and description.
		 *
		 * @since 1.0.0
		 */
		$fields = \apply_filters( 'bloatbuster_settings_fields', $fields );

		// Drop fields missing the keys every field needs.
		self::$fields = array();

		foreach ( (array) $fields as $field ) {
			if ( is_array( $field ) && ! empty( $field['tab'] ) && ! empty( $field['type'] ) && ! empty( $field['id'] ) ) {
				self::$fields[] = $field;
			}
		}

		return self::$fields;
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
				$field['label'] ?? '',
				array( Fields::class, 'render_field' ),
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
			if ( ! empty( $field['locked'] ) || ! Fields::is_saved_field( $field ) ) {
				continue;
			}

			$value = Fields::sanitize_field( $field, $input );

			if ( null === $value ) {
				unset( $sanitized[ $field['id'] ] );
			} else {
				$sanitized[ $field['id'] ] = $value;
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

		$tabs        = self::get_tabs();
		$current_tab = self::get_current_tab();

		if ( empty( $tabs[ $current_tab ] ) ) {
			return;
		}

		$tab_class = $tabs[ $current_tab ]['class'];

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by options.php redirect.
		if ( isset( $_GET['settings-updated'] ) ) {
			\add_settings_error( 'messages', 'message', \esc_html__( 'Settings Saved', 'bloatbuster-performance-asset-cleaner' ), 'updated' );
		}

		$tab_class::add_notices();

		/**
		 * Fires before the settings notices are printed. Use add_settings_error() with the 'messages' setting.
		 *
		 * @param string $current_tab - Current tab slug.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'bloatbuster_settings_page_notices', $current_tab );

		\settings_errors( 'messages' );
		?>
		<div class="wrap" id="bloatbuster-performance-asset-cleaner-options">
			<h1><?php echo \esc_html( self::get_page_title() ); ?></h1>
			<p><?php \esc_html_e( 'Streamline your site\'s performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features below to keep your frontend fast, light, and clean.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $tab ) : ?>
					<a href="<?php echo \esc_url( \add_query_arg( 'tab', $slug, \menu_page_url( BLOATBUSTER_OPTION, false ) ) ); ?>" class="nav-tab<?php echo $slug === $current_tab ? ' nav-tab-active' : ''; ?>"><?php echo \esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			/**
			 * Fires before the current tab form.
			 *
			 * @param string $current_tab - Current tab slug.
			 *
			 * @since 1.0.0
			 */
			\do_action( 'bloatbuster_before_tab_content', $current_tab );
			?>
			<?php if ( 'admin-post' === $tab_class::get_form_type() ) : ?>
				<form action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" method="POST">
					<input type="hidden" name="action" value="<?php echo \esc_attr( $tab_class::get_form_action() ); ?>">
					<?php \wp_nonce_field( $tab_class::get_form_action() ); ?>
					<?php \do_settings_sections( self::get_tab_page( $current_tab ) ); ?>
				</form>
			<?php else : ?>
				<form action="options.php" method="POST">
					<?php \settings_fields( BLOATBUSTER_OPTION ); ?>
					<input type="hidden" name="<?php echo \esc_attr( BLOATBUSTER_OPTION . '[_tab]' ); ?>" value="<?php echo \esc_attr( $current_tab ); ?>">
					<?php \do_settings_sections( self::get_tab_page( $current_tab ) ); ?>
					<?php \submit_button(); ?>
				</form>
			<?php endif; ?>
			<?php
			/**
			 * Fires after the current tab form.
			 *
			 * @param string $current_tab - Current tab slug.
			 *
			 * @since 1.0.0
			 */
			\do_action( 'bloatbuster_after_tab_content', $current_tab );
			?>
		</div>
		<?php
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
