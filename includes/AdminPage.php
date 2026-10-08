<?php
/**
 * Base class for all WordPress admin pages.
 *
 * @class       AdminPage
 * @version     1.0.0
 * @package     BloatBuster_Performance_Asset_Cleaner/Classes/
 */

namespace BloatBuster_Performance_Asset_Cleaner;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AdminPage {
	/**
	 * Plugin options abstract class.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * Register the admin page with all the appropriate WordPress hooks.
	 *
	 * @param null $page
	 */
	public static function register( $page = null ) {
		// If we didn't receive an initiated child class, instantiate one.
		if ( null === $page ) {
			$page = new static();
		}

		add_action( 'admin_init', array( $page, 'configure' ) );
		add_action( 'admin_menu', array( $page, 'add_admin_page' ) );
	}

	/**
	 * Adds the admin page to the menu.
	 */
	abstract public function add_admin_page();

	/**
	 * Configure options for the admin page using the settings API.
	 */
	abstract public function configure_options();

	/**
	 * Renders the admin page.
	 */
	abstract public function options_page_html();

	/**
	 * Render a form field.
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $value
	 * @param string $type
	 */
	protected function render_form_field( $id, $name, $value = '', $type = 'text' ) {
		?>
		<input id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>" value="<?php echo esc_attr( $value ); ?>" />
		<?php
	}

	/**
	 * Get the admin page menu slug.
	 *
	 * @return string
	 */
	abstract protected function get_menu_slug();

	/**
	 * Get the admin page title.
	 *
	 * @return string
	 */
	abstract protected function get_page_title();
}
