<?php
/**
 * Autoloaded Options settings tab.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Browse, change autoload, delete, export and restore options.
 */
final class Tab_Autoload extends Settings_Tab {

	/**
	 * Get the tab slug.
	 *
	 * @return string - Tab slug.
	 *
	 * @since 1.0.0
	 */
	public static function get_slug() {
		return 'autoload';
	}

	/**
	 * Get the tab label.
	 *
	 * @return string - Tab label.
	 *
	 * @since 1.0.0
	 */
	public static function get_label() {
		return \__( 'Autoloaded Options', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab description.
	 *
	 * @return string - Tab description.
	 *
	 * @since 1.0.0
	 */
	public static function get_description() {
		return \__( 'Options WordPress loads on every page request. Large values here slow down the whole site. Disable autoload for options that are not needed on every page, and delete options left behind by removed plugins.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Get the tab position in the navigation.
	 *
	 * @return int - Tab priority.
	 *
	 * @since 1.0.0
	 */
	public static function get_priority() {
		return 35;
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
	 * Add the result notices of the tab actions.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function add_notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only flags set by the action redirect.
		$notice  = isset( $_GET['bloatbuster_notice'] ) ? \sanitize_key( \wp_unslash( $_GET['bloatbuster_notice'] ) ) : '';
		$count   = isset( $_GET['bloatbuster_count'] ) ? \absint( $_GET['bloatbuster_count'] ) : 0;
		$skipped = isset( $_GET['bloatbuster_skipped'] ) ? \absint( $_GET['bloatbuster_skipped'] ) : 0;
		$created = isset( $_GET['bloatbuster_created'] ) ? \absint( $_GET['bloatbuster_created'] ) : 0;
		$updated = isset( $_GET['bloatbuster_updated'] ) ? \absint( $_GET['bloatbuster_updated'] ) : 0;
		$error   = isset( $_GET['bloatbuster_error'] ) ? \sanitize_key( \wp_unslash( $_GET['bloatbuster_error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$message = '';
		$type    = 'updated';

		if ( 'autoload_enable' === $notice ) {
			/* translators: %d: number of options. */
			$message = sprintf( \_n( 'Autoload enabled for %d option.', 'Autoload enabled for %d options.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'autoload_disable' === $notice ) {
			/* translators: %d: number of options. */
			$message = sprintf( \_n( 'Autoload disabled for %d option.', 'Autoload disabled for %d options.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'autoload_delete' === $notice ) {
			/* translators: %d: number of options. */
			$message = sprintf( \_n( 'Deleted %d option.', 'Deleted %d options.', $count, 'bloatbuster-performance-asset-cleaner' ), $count );
		} elseif ( 'autoload_none' === $notice ) {
			$message = \__( 'No options were changed. Select at least one option that is not protected.', 'bloatbuster-performance-asset-cleaner' );
			$type    = 'warning';
		} elseif ( 'autoload_imported' === $notice ) {
			/* translators: 1: number of created options, 2: number of options with restored autoload. */
			$message = sprintf( \__( 'Restore finished: %1$d options created, autoload restored for %2$d options. Existing option values were not changed.', 'bloatbuster-performance-asset-cleaner' ), $created, $updated );
		} elseif ( 'autoload_import_error' === $notice ) {
			$message = self::get_import_error_message( $error );
			$type    = 'error';
		}

		if ( '' === $message ) {
			return;
		}

		if ( $skipped > 0 ) {
			/* translators: %d: number of skipped options. */
			$message .= ' ' . sprintf( \_n( '%d protected or invalid option was skipped.', '%d protected or invalid options were skipped.', $skipped, 'bloatbuster-performance-asset-cleaner' ), $skipped );
		}

		\add_settings_error( 'messages', 'bloatbuster_notice', \esc_html( $message ), $type );
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
			/* translators: %s: maximum file size, e.g. 10 MB. */
			'size'         => sprintf( \__( 'The file is empty or larger than %s.', 'bloatbuster-performance-asset-cleaner' ), \size_format( Autoload_Actions::MAX_IMPORT_SIZE ) ),
			'json'         => \__( 'The file is not valid JSON.', 'bloatbuster-performance-asset-cleaner' ),
			'invalid_file' => \__( 'This is not a BloatBuster options export file.', 'bloatbuster-performance-asset-cleaner' ),
			'no_options'   => \__( 'The file has no valid options to restore.', 'bloatbuster-performance-asset-cleaner' ),
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : \__( 'The options could not be restored.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Render the summary cards, options table, import form and value dialog.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_content() {
		require_once BLOATBUSTER_DIR . '/includes/autoload/class-autoload-list-table.php';

		$table   = new Autoload_List_Table();
		$request = Autoload_List_Table::get_request_args();

		$table->prepare_items();

		self::render_summary();
		?>
		<div class="bbpac-autoload-manager">
			<?php $table->views(); ?>
			<form method="get" action="<?php echo \esc_url( \admin_url( 'admin.php' ) ); ?>" class="bbpac-autoload-search">
				<input type="hidden" name="page" value="<?php echo \esc_attr( BLOATBUSTER_OPTION ); ?>">
				<input type="hidden" name="tab" value="<?php echo \esc_attr( self::get_slug() ); ?>">
				<?php if ( 'all' !== $request['view'] ) : ?>
					<input type="hidden" name="view" value="<?php echo \esc_attr( $request['view'] ); ?>">
				<?php endif; ?>
				<label class="bbpac-include-transients">
					<input type="checkbox" name="transients" value="1" <?php \checked( $request['transients'] ); ?>>
					<?php \esc_html_e( 'Include transients', 'bloatbuster-performance-asset-cleaner' ); ?>
				</label>
				<?php $table->search_box( \__( 'Search options', 'bloatbuster-performance-asset-cleaner' ), 'bbpac-option' ); ?>
			</form>
			<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" id="bbpac-autoload-form">
				<input type="hidden" name="action" value="<?php echo \esc_attr( Autoload_Actions::BULK_ACTION ); ?>">
				<?php // display() prints the nonce field, see Autoload_Actions::BULK_NONCE. ?>
				<?php $table->display(); ?>
			</form>
			<div class="bbpac-autoload-import">
				<h3><?php \esc_html_e( 'Restore from backup', 'bloatbuster-performance-asset-cleaner' ); ?></h3>
				<p class="description"><?php \esc_html_e( 'Upload a file created with the Export bulk action. Missing options are created again, existing options only get their autoload state restored. Protected options are skipped.', 'bloatbuster-performance-asset-cleaner' ); ?></p>
				<form method="post" action="<?php echo \esc_url( \admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
					<input type="hidden" name="action" value="<?php echo \esc_attr( Autoload_Actions::IMPORT_ACTION ); ?>">
					<?php \wp_nonce_field( Autoload_Actions::IMPORT_ACTION ); ?>
					<input type="file" name="bbpac_import" accept=".json,application/json" required>
					<?php \submit_button( \__( 'Restore options', 'bloatbuster-performance-asset-cleaner' ), 'secondary', 'bbpac_import_submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
		self::render_value_dialog();
	}

	/**
	 * Render the summary cards and Site Health status.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_summary() {
		$summary    = Autoload_Options::get_summary();
		$too_big    = $summary['size'] > $summary['limit'];
		$limit_size = \size_format( $summary['limit'] );
		$review_url = Autoload_List_Table::get_tab_url( array( 'view' => 'review' ) );
		?>
		<div class="bbpac-cards">
			<div class="bbpac-card<?php echo $too_big ? ' bbpac-card-warning' : ''; ?>">
				<span class="bbpac-card-label"><?php \esc_html_e( 'Total autoloaded size', 'bloatbuster-performance-asset-cleaner' ); ?></span>
				<span class="bbpac-card-value"><?php echo \esc_html( \size_format( $summary['size'], 2 ) ); ?></span>
			</div>
			<div class="bbpac-card">
				<span class="bbpac-card-label"><?php \esc_html_e( 'Autoloaded options', 'bloatbuster-performance-asset-cleaner' ); ?></span>
				<span class="bbpac-card-value"><?php echo \esc_html( \number_format_i18n( $summary['count'] ) ); ?></span>
			</div>
			<div class="bbpac-card">
				<span class="bbpac-card-label"><?php \esc_html_e( 'Largest option', 'bloatbuster-performance-asset-cleaner' ); ?></span>
				<span class="bbpac-card-value"><?php echo \esc_html( \size_format( $summary['largest_size'], 1 ) ); ?></span>
				<?php if ( '' !== $summary['largest_name'] ) : ?>
					<code class="bbpac-card-meta"><?php echo \esc_html( $summary['largest_name'] ); ?></code>
				<?php endif; ?>
			</div>
			<a class="bbpac-card<?php echo $summary['review'] > 0 ? ' bbpac-card-warning' : ''; ?>" href="<?php echo \esc_url( $review_url ); ?>">
				<span class="bbpac-card-label"><?php \esc_html_e( 'Needs review', 'bloatbuster-performance-asset-cleaner' ); ?></span>
				<span class="bbpac-card-value"><?php echo \esc_html( \number_format_i18n( $summary['review'] ) ); ?></span>
				<span class="bbpac-card-meta">
					<?php
					/* translators: %s: size, e.g. 10 KB. */
					echo \esc_html( sprintf( \__( 'Autoloaded and %s or larger', 'bloatbuster-performance-asset-cleaner' ), \size_format( Autoload_Options::get_large_threshold() ) ) );
					?>
				</span>
			</a>
		</div>
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
			<?php
		endif;
	}

	/**
	 * Render the dialog used to view an option value.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_value_dialog() {
		?>
		<dialog id="bbpac-value-dialog" class="bbpac-value-dialog">
			<div class="bbpac-value-dialog-header">
				<code class="bbpac-value-name"></code>
				<span class="bbpac-value-meta"></span>
			</div>
			<p class="bbpac-value-notice" hidden></p>
			<pre class="bbpac-value-content"></pre>
			<div class="bbpac-value-dialog-footer">
				<button type="button" class="button bbpac-value-reveal" hidden><?php \esc_html_e( 'Reveal value', 'bloatbuster-performance-asset-cleaner' ); ?></button>
				<button type="button" class="button bbpac-value-copy"><?php \esc_html_e( 'Copy', 'bloatbuster-performance-asset-cleaner' ); ?></button>
				<button type="button" class="button button-primary bbpac-value-close"><?php \esc_html_e( 'Close', 'bloatbuster-performance-asset-cleaner' ); ?></button>
			</div>
		</dialog>
		<?php
	}
}
