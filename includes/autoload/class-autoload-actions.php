<?php
/**
 * Autoloaded options actions: autoload changes, delete, export and import.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the Autoloaded Options tab form submissions.
 */
final class Autoload_Actions {

	/**
	 * Bulk action name and nonce action.
	 *
	 * @var string
	 */
	const BULK_ACTION = 'bloatbuster_autoload_bulk';

	/**
	 * Bulk nonce action, printed by WP_List_Table::display() from the table's plural name.
	 *
	 * @var string
	 */
	const BULK_NONCE = 'bulk-bbpac-options';

	/**
	 * Row action name and nonce action prefix.
	 *
	 * @var string
	 */
	const ROW_ACTION = 'bloatbuster_autoload_row';

	/**
	 * Import action name and nonce action.
	 *
	 * @var string
	 */
	const IMPORT_ACTION = 'bloatbuster_autoload_import';

	/**
	 * Maximum import file size in bytes.
	 *
	 * @var int
	 */
	const MAX_IMPORT_SIZE = 10485760;

	/**
	 * Notice query args removed from the redirect URL.
	 *
	 * @var array
	 */
	const NOTICE_ARGS = array( 'bloatbuster_notice', 'bloatbuster_count', 'bloatbuster_skipped', 'bloatbuster_created', 'bloatbuster_updated', 'bloatbuster_error' );

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		\add_action( 'admin_post_' . self::BULK_ACTION, array( __CLASS__, 'handle_bulk_action' ) );
		\add_action( 'admin_post_' . self::ROW_ACTION, array( __CLASS__, 'handle_row_action' ) );
		\add_action( 'admin_post_' . self::IMPORT_ACTION, array( __CLASS__, 'handle_import' ) );
	}

	/**
	 * Get the URL of a row action link.
	 *
	 * @param string $action - Action: enable, disable or delete.
	 * @param string $name   - Option name.
	 *
	 * @return string - Action URL with nonce.
	 *
	 * @since 1.0.0
	 */
	public static function get_row_action_url( $action, $name ) {
		return \add_query_arg(
			array(
				'action'   => self::ROW_ACTION,
				'do'       => $action,
				'option'   => rawurlencode( $name ),
				'_wpnonce' => \wp_create_nonce( self::get_row_nonce_action( $action, $name ) ),
			),
			\admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Get the nonce action of a row action.
	 *
	 * @param string $action - Action: enable, disable or delete.
	 * @param string $name   - Option name.
	 *
	 * @return string - Nonce action.
	 *
	 * @since 1.0.0
	 */
	public static function get_row_nonce_action( $action, $name ) {
		return self::ROW_ACTION . '_' . $action . '_' . $name;
	}

	/**
	 * Stop the request if the user can't manage options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function check_permission() {
		if ( ! \current_user_can( 'manage_options' ) ) {
			\wp_die( \esc_html__( 'You are not allowed to do this.', 'bloatbuster-performance-asset-cleaner' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Check if the current user can change, delete or restore options.
	 *
	 * On multisite only network administrators can. Site administrators are not fully
	 * trusted there, and restored values are unserialized by WordPress when read.
	 *
	 * @return bool - True if the user can change options.
	 *
	 * @since 1.0.0
	 */
	public static function can_change_options() {
		if ( \is_multisite() ) {
			return \is_super_admin();
		}

		return \current_user_can( 'manage_options' );
	}

	/**
	 * Stop the request if the user can't change options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function check_change_permission() {
		if ( ! self::can_change_options() ) {
			\wp_die( \esc_html__( 'Only network administrators can change options on a multisite network.', 'bloatbuster-performance-asset-cleaner' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Handle a bulk action from the options table.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_bulk_action() {
		self::check_permission();

		\check_admin_referer( self::BULK_NONCE );

		$action = isset( $_POST['bbpac_bulk_action'] ) ? \sanitize_key( \wp_unslash( $_POST['bbpac_bulk_action'] ) ) : '-1';

		if ( '-1' === $action || '' === $action ) {
			$action = isset( $_POST['bbpac_bulk_action2'] ) ? \sanitize_key( \wp_unslash( $_POST['bbpac_bulk_action2'] ) ) : '';
		}

		// Option names are unslashed and checked against the database in filter_names().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw_names = isset( $_POST['option_names'] ) ? (array) $_POST['option_names'] : array();

		if ( ! in_array( $action, array( 'enable', 'disable', 'delete', 'export' ), true ) ) {
			self::redirect( array() );
		}

		// Export only reads options, the other actions change them.
		if ( 'export' !== $action ) {
			self::check_change_permission();
		}

		$filtered = Autoload_Options::filter_names( $raw_names, 'export' === $action );

		if ( empty( $filtered['names'] ) ) {
			self::redirect(
				array(
					'bloatbuster_notice'  => 'autoload_none',
					'bloatbuster_skipped' => $filtered['skipped'],
				)
			);
		}

		if ( 'export' === $action ) {
			self::send_export( $filtered['names'] );
		}

		self::redirect( self::run_action( $action, $filtered ) );
	}

	/**
	 * Handle a row action link from the options table.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_row_action() {
		self::check_permission();
		self::check_change_permission();

		$action = isset( $_GET['do'] ) ? \sanitize_key( \wp_unslash( $_GET['do'] ) ) : '';

		// The option name is checked against the database in filter_names().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$name = isset( $_GET['option'] ) && is_string( $_GET['option'] ) ? \wp_unslash( $_GET['option'] ) : '';

		\check_admin_referer( self::get_row_nonce_action( $action, $name ) );

		if ( ! in_array( $action, array( 'enable', 'disable', 'delete' ), true ) ) {
			self::redirect( array() );
		}

		// filter_names() expects slashed input.
		$filtered = Autoload_Options::filter_names( array( \wp_slash( $name ) ) );

		self::redirect( self::run_action( $action, $filtered ) );
	}

	/**
	 * Run an autoload change or delete on filtered option names.
	 *
	 * @param string $action   - Action: enable, disable or delete.
	 * @param array  $filtered - Result of Autoload_Options::filter_names().
	 *
	 * @return array - Notice query args.
	 *
	 * @since 1.0.0
	 */
	public static function run_action( $action, $filtered ) {
		if ( 'delete' === $action ) {
			$count = Autoload_Options::delete( $filtered['names'] );
		} else {
			$count = Autoload_Options::set_autoload( $filtered['names'], 'enable' === $action );
		}

		return array(
			'bloatbuster_notice'  => 'autoload_' . $action,
			'bloatbuster_count'   => $count,
			'bloatbuster_skipped' => $filtered['skipped'],
		);
	}

	/**
	 * Send the export file of options as a download.
	 *
	 * @param array $names - Option names.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function send_export( $names ) {
		$host     = \wp_parse_url( \home_url(), PHP_URL_HOST );
		$filename = 'bloatbuster-options-' . \sanitize_file_name( (string) $host ) . '-' . \gmdate( 'Y-m-d-His' ) . '.json';

		\nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		echo \wp_json_encode( Autoload_Options::get_export( $names ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Handle the import form.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_import() {
		self::check_permission();
		self::check_change_permission();

		\check_admin_referer( self::IMPORT_ACTION );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- File upload array, checked below.
		$file = isset( $_FILES['bbpac_import'] ) && is_array( $_FILES['bbpac_import'] ) ? $_FILES['bbpac_import'] : array();

		if ( empty( $file['tmp_name'] ) || ! isset( $file['error'] ) || UPLOAD_ERR_OK !== $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			self::redirect_import_error( 'upload' );
		}

		if ( empty( $file['size'] ) || $file['size'] > self::MAX_IMPORT_SIZE ) {
			self::redirect_import_error( 'size' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		$contents = file_get_contents( $file['tmp_name'] );
		$data     = is_string( $contents ) ? json_decode( $contents, true ) : null;

		if ( ! is_array( $data ) ) {
			self::redirect_import_error( 'json' );
		}

		$result = Autoload_Options::import( $data );

		if ( \is_wp_error( $result ) ) {
			self::redirect_import_error( $result->get_error_code() );
		}

		self::redirect(
			array(
				'bloatbuster_notice'  => 'autoload_imported',
				'bloatbuster_created' => $result['created'],
				'bloatbuster_updated' => $result['updated'],
				'bloatbuster_skipped' => $result['skipped'],
			)
		);
	}

	/**
	 * Redirect back with an import error notice.
	 *
	 * @param string $code - Error code.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function redirect_import_error( $code ) {
		self::redirect(
			array(
				'bloatbuster_notice' => 'autoload_import_error',
				'bloatbuster_error'  => $code,
			)
		);
	}

	/**
	 * Redirect back to the page the action came from, with notice args.
	 *
	 * @param array $args - Notice query args.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function redirect( $args ) {
		$fallback = \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION . '&tab=' . Tab_Autoload::get_slug() );
		$referer  = \wp_get_referer();
		$url      = $referer ? $referer : $fallback;
		$url      = \remove_query_arg( self::NOTICE_ARGS, $url );

		\wp_safe_redirect( \add_query_arg( $args, $url ), 303, 'BloatBuster' );
		exit;
	}
}
