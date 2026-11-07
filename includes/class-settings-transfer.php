<?php
/**
 * Settings import, export and reset.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exports the plugin settings to a JSON file, imports them with a preview
 * of the changes, and resets them to the defaults.
 */
final class Settings_Transfer {

	/**
	 * Export action name and nonce action.
	 *
	 * @var string
	 */
	const EXPORT_ACTION = 'bloatbuster_settings_export';

	/**
	 * Import (upload) action name and nonce action.
	 *
	 * @var string
	 */
	const IMPORT_ACTION = 'bloatbuster_settings_import';

	/**
	 * Apply import action name and nonce action.
	 *
	 * @var string
	 */
	const APPLY_ACTION = 'bloatbuster_settings_import_apply';

	/**
	 * Cancel import action name and nonce action.
	 *
	 * @var string
	 */
	const CANCEL_ACTION = 'bloatbuster_settings_import_cancel';

	/**
	 * Reset action name and nonce action.
	 *
	 * @var string
	 */
	const RESET_ACTION = 'bloatbuster_settings_reset';

	/**
	 * User option holding the pending import.
	 *
	 * @var string
	 */
	const PENDING_OPTION = 'bloatbuster_settings_import';

	/**
	 * Export file format version.
	 *
	 * @var int
	 */
	const FORMAT = 1;

	/**
	 * Maximum import file size in bytes.
	 *
	 * @var int
	 */
	const MAX_IMPORT_SIZE = 1048576;

	/**
	 * Seconds a pending import is kept before it is discarded.
	 *
	 * @var int
	 */
	const PENDING_LIFETIME = 3600;

	/**
	 * Maximum number of ignored key names kept for the preview notes.
	 *
	 * @var int
	 */
	const MAX_IGNORED_NAMES = 20;

	/**
	 * Query arg that shows the import preview.
	 *
	 * @var string
	 */
	const PREVIEW_ARG = 'bbpac_import_preview';

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		\add_action( 'admin_post_' . self::EXPORT_ACTION, array( __CLASS__, 'handle_export' ) );
		\add_action( 'admin_post_' . self::IMPORT_ACTION, array( __CLASS__, 'handle_import' ) );
		\add_action( 'admin_post_' . self::APPLY_ACTION, array( __CLASS__, 'handle_apply' ) );
		\add_action( 'admin_post_' . self::CANCEL_ACTION, array( __CLASS__, 'handle_cancel' ) );
		\add_action( 'admin_post_' . self::RESET_ACTION, array( __CLASS__, 'handle_reset' ) );
		\add_action( 'bloatbuster_before_tab_content', array( __CLASS__, 'render_preview' ) );
		\add_action( 'bloatbuster_after_tab_content', array( __CLASS__, 'render_section' ) );
	}

	/**
	 * Get the field IDs that are never exported, imported or reset.
	 *
	 * @return array - Field IDs.
	 *
	 * @since 1.0.0
	 */
	public static function get_excluded_keys() {
		$excluded = array( 'delete_data_on_uninstall' );

		/**
		 * Filter the site-specific field IDs left out of settings export, import and reset.
		 *
		 * @param array $excluded - Field IDs.
		 *
		 * @since 1.0.0
		 */
		$excluded = \apply_filters( 'bloatbuster_settings_transfer_excluded', $excluded );

		return array_map( 'strval', (array) $excluded );
	}

	/**
	 * Get the fields that can be exported, imported and reset.
	 *
	 * @return array - Fields keyed by ID.
	 *
	 * @since 1.0.0
	 */
	public static function get_importable_fields() {
		$excluded = self::get_excluded_keys();
		$fields   = array();

		foreach ( Plugin_Options::get_fields() as $field ) {
			if ( ! Fields::is_saved_field( $field ) ) {
				continue;
			}

			if ( in_array( (string) $field['id'], $excluded, true ) ) {
				continue;
			}

			$fields[ $field['id'] ] = $field;
		}

		return $fields;
	}

	/**
	 * Get the stored plugin options.
	 *
	 * @return array - Stored options.
	 *
	 * @since 1.0.0
	 */
	public static function get_stored_options() {
		$stored = \get_option( BLOATBUSTER_OPTION );

		return is_array( $stored ) ? $stored : array();
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
	 * Download the importable settings as a JSON file.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_export() {
		self::check_permission();

		\check_admin_referer( self::EXPORT_ACTION );

		$stored   = self::get_stored_options();
		$settings = array();

		foreach ( self::get_importable_fields() as $id => $field ) {
			if ( isset( $stored[ $id ] ) ) {
				$settings[ $id ] = $stored[ $id ];
			}
		}

		$data = array(
			'plugin'   => 'bloatbuster',
			'format'   => self::FORMAT,
			'version'  => BLOATBUSTER_VERSION,
			'exported' => gmdate( 'c' ),
			// An object, also when empty, so the importer always gets key/value pairs.
			'settings' => (object) $settings,
		);

		$host     = \wp_parse_url( \home_url(), PHP_URL_HOST );
		$filename = 'bloatbuster-settings-' . \sanitize_file_name( (string) $host ) . '-' . gmdate( 'Y-m-d' ) . '.json';

		\nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		echo \wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Handle the import upload: validate and sanitize the file, then save it for the preview.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_import() {
		self::check_permission();

		\check_admin_referer( self::IMPORT_ACTION );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- File upload array, checked below.
		$file = isset( $_FILES['bbpac_settings_import'] ) && is_array( $_FILES['bbpac_settings_import'] ) ? $_FILES['bbpac_settings_import'] : array();

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

		if ( ! isset( $data['plugin'] ) || 'bloatbuster' !== $data['plugin'] || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			self::redirect_import_error( 'invalid_file' );
		}

		if ( ! isset( $data['format'] ) || ! is_int( $data['format'] ) || 1 > $data['format'] || self::FORMAT < $data['format'] ) {
			self::redirect_import_error( 'format' );
		}

		$pending         = self::parse_settings( $data['settings'] );
		$pending['time'] = time();
		$pending['id']   = \wp_generate_uuid4();

		// User meta functions unslash the value, slash it so backslashes in values are kept.
		\update_user_option( \get_current_user_id(), self::PENDING_OPTION, \wp_slash( $pending ) );

		$url = \add_query_arg( self::PREVIEW_ARG, 1, self::get_tab_url() );

		\wp_safe_redirect( $url . '#bbpac-settings-import-preview', 303, 'BloatBuster' );
		exit;
	}

	/**
	 * Sanitize the settings of an import file.
	 *
	 * Unknown and excluded keys are dropped, locked fields are skipped and
	 * values changed by sanitizing are listed as adjusted.
	 *
	 * @param array $raw - Settings from the import file.
	 *
	 * @return array - Pending import with settings, ignored, ignored_count, locked and adjusted.
	 *
	 * @since 1.0.0
	 */
	public static function parse_settings( $raw ) {
		$fields        = self::get_importable_fields();
		$settings      = array();
		$ignored       = array();
		$ignored_count = 0;
		$locked        = array();
		$adjusted      = array();

		foreach ( array_keys( $raw ) as $key ) {
			if ( isset( $fields[ $key ] ) ) {
				continue;
			}

			++$ignored_count;

			if ( self::MAX_IGNORED_NAMES > count( $ignored ) ) {
				$ignored[] = \sanitize_text_field( (string) $key );
			}
		}

		foreach ( $fields as $id => $field ) {
			if ( ! empty( $field['locked'] ) ) {
				if ( array_key_exists( $id, $raw ) ) {
					$locked[] = $id;
				}

				continue;
			}

			$value = Fields::sanitize_field( $field, $raw );

			if ( array_key_exists( $id, $raw ) && self::is_value_adjusted( $field, $raw[ $id ], $value ) ) {
				$adjusted[] = $id;
			}

			if ( null !== $value ) {
				$settings[ $id ] = $value;
			}
		}

		return array(
			'settings'      => $settings,
			'ignored'       => $ignored,
			'ignored_count' => $ignored_count,
			'locked'        => $locked,
			'adjusted'      => $adjusted,
		);
	}

	/**
	 * Check if sanitizing changed an imported value, e.g. an invalid select value or a number out of range.
	 *
	 * @param array $field - Field data.
	 * @param mixed $raw   - Value from the import file.
	 * @param mixed $value - Sanitized value, null when removed.
	 *
	 * @return bool - True if the value was changed.
	 *
	 * @since 1.0.0
	 */
	public static function is_value_adjusted( $field, $raw, $value ) {
		// Toggles only know on and off, any value is valid.
		if ( 'toggle' === $field['type'] || 'checkbox' === $field['type'] ) {
			return false;
		}

		if ( null === $value ) {
			return ! empty( $raw );
		}

		if ( is_array( $value ) ) {
			return ! is_array( $raw ) || count( $raw ) !== count( $value );
		}

		return ! is_scalar( $raw ) || (string) $raw !== (string) $value;
	}

	/**
	 * Get the pending import of the current user, discarding it when expired.
	 *
	 * @return array|null - Pending import, or null if there is none.
	 *
	 * @since 1.0.0
	 */
	public static function get_pending() {
		$user_id = \get_current_user_id();
		$pending = \get_user_option( self::PENDING_OPTION, $user_id );

		if ( ! is_array( $pending ) || ! isset( $pending['settings'], $pending['time'], $pending['id'] ) || ! is_array( $pending['settings'] ) || ! is_string( $pending['id'] ) ) {
			return null;
		}

		if ( time() - (int) $pending['time'] > self::PENDING_LIFETIME ) {
			\delete_user_option( $user_id, self::PENDING_OPTION );

			return null;
		}

		return $pending;
	}

	/**
	 * Get the fields a pending import would change.
	 *
	 * @param array $pending - Pending import.
	 *
	 * @return array - Rows with tab, label, current and new display values.
	 *
	 * @since 1.0.0
	 */
	public static function get_changes( $pending ) {
		$stored  = self::get_stored_options();
		$tabs    = Plugin_Options::get_tabs();
		$changes = array();

		foreach ( self::get_importable_fields() as $id => $field ) {
			if ( ! empty( $field['locked'] ) ) {
				continue;
			}

			$current = self::format_value( $field, isset( $stored[ $id ] ) ? $stored[ $id ] : null );
			$new     = self::format_value( $field, isset( $pending['settings'][ $id ] ) ? $pending['settings'][ $id ] : null );

			if ( $current === $new ) {
				continue;
			}

			$changes[] = array(
				'tab'     => isset( $tabs[ $field['tab'] ] ) ? $tabs[ $field['tab'] ]['label'] : $field['tab'],
				'label'   => isset( $field['label'] ) ? $field['label'] : $id,
				'current' => $current,
				'new'     => $new,
			);
		}

		return $changes;
	}

	/**
	 * Get a readable value of a field for the preview.
	 *
	 * @param array $field - Field data.
	 * @param mixed $value - Stored value, null when not set.
	 *
	 * @return string - Readable value.
	 *
	 * @since 1.0.0
	 */
	public static function format_value( $field, $value ) {
		if ( 'toggle' === $field['type'] || 'checkbox' === $field['type'] ) {
			if ( empty( $value ) ) {
				return \__( 'Off', 'bloatbuster-performance-asset-cleaner' );
			}

			return \__( 'On', 'bloatbuster-performance-asset-cleaner' );
		}

		if ( 'select' === $field['type'] ) {
			$key = is_scalar( $value ) ? (string) $value : '';

			if ( isset( $field['options'][ $key ] ) ) {
				return (string) $field['options'][ $key ];
			}

			return '—';
		}

		if ( 'number' === $field['type'] ) {
			if ( null === $value ) {
				$value = $field['default'];
			}

			return (string) (int) $value;
		}

		if ( 'checkbox_list' === $field['type'] ) {
			$values = is_array( $value ) ? array_map( 'strval', $value ) : array();
			$labels = array();

			// Field options order, so the same values in another order are not shown as a change.
			foreach ( $field['options'] as $option_value => $option_label ) {
				if ( in_array( (string) $option_value, $values, true ) ) {
					$labels[] = $option_label;
				}
			}

			if ( empty( $labels ) ) {
				return \__( 'None', 'bloatbuster-performance-asset-cleaner' );
			}

			return implode( ', ', $labels );
		}

		if ( null === $value ) {
			return '—';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return (string) \wp_json_encode( $value );
	}

	/**
	 * Apply the pending import.
	 *
	 * Importable, unlocked fields get the imported value; fields missing
	 * from the file are turned off or set to their default. Excluded and
	 * locked fields keep their stored value.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_apply() {
		self::check_permission();

		\check_admin_referer( self::APPLY_ACTION );

		$import_id = isset( $_POST['bbpac_import_id'] ) && is_string( $_POST['bbpac_import_id'] ) ? \sanitize_key( \wp_unslash( $_POST['bbpac_import_id'] ) ) : '';
		$pending   = self::get_pending();

		if ( null === $pending ) {
			self::redirect_import_error( 'expired' );
		}

		// A newer upload replaced the import shown in this preview.
		if ( ! self::is_current_import( $pending, $import_id ) ) {
			self::redirect_import_error( 'changed' );
		}

		$count  = count( self::get_changes( $pending ) );
		$stored = self::get_stored_options();

		foreach ( self::get_importable_fields() as $id => $field ) {
			if ( ! empty( $field['locked'] ) ) {
				continue;
			}

			// Sanitized again, the fields may have changed since the upload.
			$value = Fields::sanitize_field( $field, $pending['settings'] );

			if ( null === $value ) {
				unset( $stored[ $id ] );
			} else {
				$stored[ $id ] = $value;
			}
		}

		\update_option( BLOATBUSTER_OPTION, $stored );
		\delete_user_option( \get_current_user_id(), self::PENDING_OPTION );

		self::redirect(
			array(
				'bloatbuster_notice' => 'settings_imported',
				'bloatbuster_count'  => $count,
			)
		);
	}

	/**
	 * Discard the pending import.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_cancel() {
		self::check_permission();

		\check_admin_referer( self::CANCEL_ACTION );

		$import_id = isset( $_POST['bbpac_import_id'] ) && is_string( $_POST['bbpac_import_id'] ) ? \sanitize_key( \wp_unslash( $_POST['bbpac_import_id'] ) ) : '';
		$pending   = self::get_pending();

		// Cancel from an old preview must not discard a newer upload.
		if ( null !== $pending && self::is_current_import( $pending, $import_id ) ) {
			\delete_user_option( \get_current_user_id(), self::PENDING_OPTION );
		}

		self::redirect( array() );
	}

	/**
	 * Check if a submitted import ID matches the pending import.
	 *
	 * @param array  $pending   - Pending import.
	 * @param string $import_id - Import ID sent by the preview form.
	 *
	 * @return bool - True if the IDs match.
	 *
	 * @since 1.0.0
	 */
	public static function is_current_import( $pending, $import_id ) {
		if ( '' === $import_id ) {
			return false;
		}

		return hash_equals( $pending['id'], $import_id );
	}

	/**
	 * Reset the importable settings to their defaults. Excluded fields are kept.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function handle_reset() {
		self::check_permission();

		\check_admin_referer( self::RESET_ACTION );

		$stored = self::get_stored_options();

		foreach ( array_keys( self::get_importable_fields() ) as $id ) {
			unset( $stored[ $id ] );
		}

		\update_option( BLOATBUSTER_OPTION, $stored );
		\delete_user_option( \get_current_user_id(), self::PENDING_OPTION );

		self::redirect( array( 'bloatbuster_notice' => 'settings_reset' ) );
	}

	/**
	 * Get the Advanced tab URL.
	 *
	 * @return string - Tab URL.
	 *
	 * @since 1.0.0
	 */
	public static function get_tab_url() {
		return \admin_url( 'admin.php?page=' . BLOATBUSTER_OPTION . '&tab=' . Tab_Advanced::get_slug() );
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
				'bloatbuster_notice' => 'settings_import_error',
				'bloatbuster_error'  => $code,
			)
		);
	}

	/**
	 * Redirect to the Advanced tab with notice args.
	 *
	 * @param array $args - Notice query args.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function redirect( $args ) {
		\wp_safe_redirect( \add_query_arg( $args, self::get_tab_url() ), 303, 'BloatBuster' );
		exit;
	}

	/**
	 * Get the message of an import error.
	 *
	 * @param string $code - Error code.
	 *
	 * @return string - Error message.
	 *
	 * @since 1.0.0
	 */
	public static function get_import_error_message( $code ) {
		$messages = array(
			'upload'       => \__( 'The file could not be uploaded.', 'bloatbuster-performance-asset-cleaner' ),
			/* translators: %s: maximum file size, e.g. 1 MB. */
			'size'         => sprintf( \__( 'The file is empty or larger than %s.', 'bloatbuster-performance-asset-cleaner' ), \size_format( self::MAX_IMPORT_SIZE ) ),
			'json'         => \__( 'The file is not valid JSON.', 'bloatbuster-performance-asset-cleaner' ),
			'invalid_file' => \__( 'This is not a BloatBuster settings export file.', 'bloatbuster-performance-asset-cleaner' ),
			'format'       => \__( 'This settings file was made by a newer version of BloatBuster. Update the plugin and try again.', 'bloatbuster-performance-asset-cleaner' ),
			'expired'      => \__( 'The import expired or was already applied. Upload the file again.', 'bloatbuster-performance-asset-cleaner' ),
			'changed'      => \__( 'A newer file was uploaded after this preview. Check its preview before applying.', 'bloatbuster-performance-asset-cleaner' ),
		);

		if ( isset( $messages[ $code ] ) ) {
			return $messages[ $code ];
		}

		return \__( 'The settings could not be imported.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Render the import preview above the Advanced tab form.
	 *
	 * @param string $tab - Current tab slug.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_preview( $tab ) {
		if ( Tab_Advanced::get_slug() !== $tab || ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag set by the import redirect.
		if ( empty( $_GET[ self::PREVIEW_ARG ] ) ) {
			return;
		}

		$pending = self::get_pending();

		if ( null === $pending ) {
			return;
		}

		$changes = self::get_changes( $pending );
		$fields  = self::get_importable_fields();
		?>
		<div id="bbpac-settings-import-preview" class="bbpac-settings-transfer bbpac-settings-import-preview">
			<h3><?php \esc_html_e( 'Import preview', 'bloatbuster-performance-asset-cleaner' ); ?></h3>
			<?php if ( empty( $changes ) ) : ?>
				<p><?php \esc_html_e( 'The file matches the current settings. Nothing would change.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<?php else : ?>
				<p><?php \esc_html_e( 'These settings will change. Settings missing from the file are turned off or set to their default.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
				<table class="widefat striped bbpac-import-preview-table">
					<thead>
						<tr>
							<th scope="col"><?php \esc_html_e( 'Tab', 'bloatbuster-performance-asset-cleaner' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Setting', 'bloatbuster-performance-asset-cleaner' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Current', 'bloatbuster-performance-asset-cleaner' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'New', 'bloatbuster-performance-asset-cleaner' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $changes as $change ) : ?>
							<tr>
								<td><?php echo \esc_html( $change['tab'] ); ?></td>
								<td><?php echo \esc_html( $change['label'] ); ?></td>
								<td><?php echo \esc_html( $change['current'] ); ?></td>
								<td><strong><?php echo \esc_html( $change['new'] ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php self::render_preview_notes( $pending, $fields ); ?>
			<div class="bbpac-settings-transfer-actions">
				<?php if ( ! empty( $changes ) ) : ?>
					<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo \esc_attr( self::APPLY_ACTION ); ?>">
						<input type="hidden" name="bbpac_import_id" value="<?php echo \esc_attr( $pending['id'] ); ?>">
						<?php \wp_nonce_field( self::APPLY_ACTION ); ?>
						<button type="submit" class="button button-primary" data-bbpac-confirm="<?php \esc_attr_e( 'The settings listed above will be replaced. Export your current settings first if you may need them again.', 'bloatbuster-performance-asset-cleaner' ); ?>" data-bbpac-confirm-title="<?php \esc_attr_e( 'Apply imported settings?', 'bloatbuster-performance-asset-cleaner' ); ?>" data-bbpac-confirm-label="<?php \esc_attr_e( 'Apply settings', 'bloatbuster-performance-asset-cleaner' ); ?>"><?php \esc_html_e( 'Apply settings', 'bloatbuster-performance-asset-cleaner' ); ?></button>
					</form>
				<?php endif; ?>
				<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo \esc_attr( self::CANCEL_ACTION ); ?>">
					<input type="hidden" name="bbpac_import_id" value="<?php echo \esc_attr( $pending['id'] ); ?>">
					<?php \wp_nonce_field( self::CANCEL_ACTION ); ?>
					<?php \submit_button( \__( 'Cancel', 'bloatbuster-performance-asset-cleaner' ), 'secondary', 'bbpac_import_cancel', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the notes about ignored keys, locked fields and adjusted values of an import.
	 *
	 * @param array $pending - Pending import.
	 * @param array $fields  - Importable fields keyed by ID.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_preview_notes( $pending, $fields ) {
		$notes         = array();
		$ignored_count = isset( $pending['ignored_count'] ) ? (int) $pending['ignored_count'] : 0;

		if ( 0 < $ignored_count ) {
			$ignored = isset( $pending['ignored'] ) ? (array) $pending['ignored'] : array();
			$names   = implode( ', ', $ignored );

			if ( count( $ignored ) < $ignored_count ) {
				$names .= ', …';
			}

			/* translators: 1: number of keys, 2: list of key names. */
			$notes[] = sprintf( \_n( '%1$d unknown or site-specific key was ignored: %2$s.', '%1$d unknown or site-specific keys were ignored: %2$s.', $ignored_count, 'bloatbuster-performance-asset-cleaner' ), $ignored_count, $names );
		}

		$locked = self::get_field_labels( isset( $pending['locked'] ) ? $pending['locked'] : array(), $fields );

		if ( ! empty( $locked ) ) {
			/* translators: %s: list of setting names. */
			$notes[] = sprintf( \__( 'Not available on this site, kept as they are: %s.', 'bloatbuster-performance-asset-cleaner' ), implode( ', ', $locked ) );
		}

		$adjusted = self::get_field_labels( isset( $pending['adjusted'] ) ? $pending['adjusted'] : array(), $fields );

		if ( ! empty( $adjusted ) ) {
			/* translators: %s: list of setting names. */
			$notes[] = sprintf( \__( 'Invalid values were changed to a valid value or the default: %s.', 'bloatbuster-performance-asset-cleaner' ), implode( ', ', $adjusted ) );
		}

		if ( empty( $notes ) ) {
			return;
		}

		echo '<ul class="bbpac-import-notes">';

		foreach ( $notes as $note ) {
			echo '<li>' . \esc_html( $note ) . '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Get the labels of field IDs.
	 *
	 * @param array $ids    - Field IDs.
	 * @param array $fields - Fields keyed by ID.
	 *
	 * @return array - Field labels, or the ID when the field has no label.
	 *
	 * @since 1.0.0
	 */
	public static function get_field_labels( $ids, $fields ) {
		$labels = array();

		foreach ( (array) $ids as $id ) {
			if ( isset( $fields[ $id ]['label'] ) ) {
				$labels[] = $fields[ $id ]['label'];
			} else {
				$labels[] = (string) $id;
			}
		}

		return $labels;
	}

	/**
	 * Render the Import / Export settings section below the Advanced tab form.
	 *
	 * @param string $tab - Current tab slug.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_section( $tab ) {
		if ( Tab_Advanced::get_slug() !== $tab || ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="bbpac-settings-transfer">
			<h3><?php \esc_html_e( 'Import / Export settings', 'bloatbuster-performance-asset-cleaner' ); ?></h3>
			<p class="description"><?php \esc_html_e( 'Copy your BloatBuster settings to another site. The file holds only the settings of all tabs, no site data. "Delete plugin data on uninstall" is not included.', 'bloatbuster-performance-asset-cleaner' ); ?></p>

			<h4><?php \esc_html_e( 'Export', 'bloatbuster-performance-asset-cleaner' ); ?></h4>
			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo \esc_attr( self::EXPORT_ACTION ); ?>">
				<?php \wp_nonce_field( self::EXPORT_ACTION ); ?>
				<?php \submit_button( \__( 'Export settings', 'bloatbuster-performance-asset-cleaner' ), 'secondary', 'bbpac_settings_export', false ); ?>
			</form>

			<h4><?php \esc_html_e( 'Import', 'bloatbuster-performance-asset-cleaner' ); ?></h4>
			<p class="description"><?php \esc_html_e( 'Upload a file made with Export settings. You will see the changes before they are applied.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="<?php echo \esc_attr( self::IMPORT_ACTION ); ?>">
				<?php \wp_nonce_field( self::IMPORT_ACTION ); ?>
				<label class="screen-reader-text" for="bbpac-settings-import"><?php \esc_html_e( 'Settings file to import', 'bloatbuster-performance-asset-cleaner' ); ?></label>
				<input type="file" id="bbpac-settings-import" name="bbpac_settings_import" accept=".json,application/json" required>
				<?php \submit_button( \__( 'Upload and preview', 'bloatbuster-performance-asset-cleaner' ), 'secondary', 'bbpac_settings_import_submit', false ); ?>
			</form>

			<h4><?php \esc_html_e( 'Reset', 'bloatbuster-performance-asset-cleaner' ); ?></h4>
			<p class="description"><?php \esc_html_e( 'Turn all settings off and set them to their defaults.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo \esc_attr( self::RESET_ACTION ); ?>">
				<?php \wp_nonce_field( self::RESET_ACTION ); ?>
				<button type="submit" class="button button-secondary" data-bbpac-confirm="<?php \esc_attr_e( 'All settings on every tab are turned off or set to their default. This can not be undone. Export your settings first if you may need them again.', 'bloatbuster-performance-asset-cleaner' ); ?>" data-bbpac-confirm-title="<?php \esc_attr_e( 'Reset all settings?', 'bloatbuster-performance-asset-cleaner' ); ?>" data-bbpac-confirm-label="<?php \esc_attr_e( 'Reset settings', 'bloatbuster-performance-asset-cleaner' ); ?>"><?php \esc_html_e( 'Reset settings', 'bloatbuster-performance-asset-cleaner' ); ?></button>
			</form>
		</div>
		<?php
	}
}
