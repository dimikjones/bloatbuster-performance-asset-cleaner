<?php
/**
 * Options list table.
 *
 * WP_List_Table works with instance methods, so this class can't follow
 * the static methods rule.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists options with size, autoload status and owner.
 */
final class Autoload_List_Table extends \WP_List_Table {

	/**
	 * Set up the table.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'bbpac-option',
				'plural'   => 'bbpac-options',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get the current request filters: view, search, transient visibility and sorting.
	 *
	 * @return array - Query args.
	 *
	 * @since 1.0.0
	 */
	public static function get_request_args() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
		$view               = isset( $_GET['view'] ) ? \sanitize_key( \wp_unslash( $_GET['view'] ) ) : 'all';
		$orderby            = isset( $_GET['orderby'] ) ? \sanitize_key( \wp_unslash( $_GET['orderby'] ) ) : 'size';
		$order              = isset( $_GET['order'] ) ? \sanitize_key( \wp_unslash( $_GET['order'] ) ) : 'desc';
		$search             = '';
		$include_transients = isset( $_GET['include_transients'] ) && is_string( $_GET['include_transients'] ) && '1' === \sanitize_key( \wp_unslash( $_GET['include_transients'] ) );

		/**
		 * Not run through sanitize_text_field(), it strips %xx from option names like 'a%2Fb'.
		 * The term is only used as a prepared LIKE value and is escaped on output.
		 */
		if ( isset( $_GET['s'] ) && is_string( $_GET['s'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- See comment above.
			$search = substr( trim( \wp_check_invalid_utf8( \wp_unslash( $_GET['s'] ) ) ), 0, 191 );
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $view, array( 'all', 'autoloaded', 'not_autoloaded', 'review' ), true ) ) {
			$view = 'all';
		}

		return array(
			'view'               => $view,
			'orderby'            => $orderby,
			'order'              => $order,
			'search'             => $search,
			'include_transients' => $include_transients,
		);
	}

	/**
	 * Get the URL of the tab with the current filters.
	 *
	 * @param array $args - Query args to add or replace.
	 *
	 * @return string - Tab URL.
	 *
	 * @since 1.0.0
	 */
	public static function get_tab_url( $args = array() ) {
		$request = self::get_request_args();
		$query   = array(
			'page'               => BLOATBUSTER_OPTION,
			'tab'                => Tab_Autoload::get_slug(),
			'view'               => 'all' !== $request['view'] ? $request['view'] : false,
			's'                  => '' !== $request['search'] ? rawurlencode( $request['search'] ) : false,
			'orderby'            => $request['orderby'],
			'order'              => $request['order'],
			'include_transients' => $request['include_transients'] ? 1 : false,
		);

		$query = array_filter( array_merge( $query, $args ), array( __CLASS__, 'is_query_value' ) );

		return \add_query_arg( $query, \admin_url( 'admin.php' ) );
	}

	/**
	 * Check if a value should be kept in a URL query. Keeps '0', a valid search term.
	 *
	 * @param mixed $value - Query value.
	 *
	 * @return bool - True to keep the value.
	 *
	 * @since 1.0.0
	 */
	public static function is_query_value( $value ) {
		return false !== $value && null !== $value && '' !== $value;
	}

	/**
	 * Get the table columns.
	 *
	 * @return array - Column slug => label.
	 *
	 * @since 1.0.0
	 */
	public function get_columns() {
		return array(
			'cb'       => '<input type="checkbox" />',
			'name'     => \__( 'Option', 'bloatbuster-performance-asset-cleaner' ),
			'size'     => \__( 'Size', 'bloatbuster-performance-asset-cleaner' ),
			'autoload' => \__( 'Autoload', 'bloatbuster-performance-asset-cleaner' ),
		);
	}

	/**
	 * Get the sortable columns.
	 *
	 * @return array - Column slug => orderby and default descending flag.
	 *
	 * @since 1.0.0
	 */
	protected function get_sortable_columns() {
		return array(
			'name'     => array( 'name', false ),
			'size'     => array( 'size', true ),
			'autoload' => array( 'autoload', false ),
		);
	}

	/**
	 * Get the bulk actions.
	 *
	 * @return array - Action slug => label.
	 *
	 * @since 1.0.0
	 */
	protected function get_bulk_actions() {
		if ( ! Autoload_Actions::can_change_options() ) {
			return array(
				'export' => \__( 'Export', 'bloatbuster-performance-asset-cleaner' ),
			);
		}

		return array(
			'enable'  => \__( 'Enable autoload', 'bloatbuster-performance-asset-cleaner' ),
			'disable' => \__( 'Disable autoload', 'bloatbuster-performance-asset-cleaner' ),
			'export'  => \__( 'Export', 'bloatbuster-performance-asset-cleaner' ),
			'delete'  => \__( 'Delete', 'bloatbuster-performance-asset-cleaner' ),
		);
	}

	/**
	 * Render the bulk actions select.
	 *
	 * Uses its own field names, admin-post.php routes on the "action" field.
	 *
	 * @param string $which - Top or bottom table nav.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function bulk_actions( $which = '' ) {
		$name = 'top' === $which ? 'bbpac_bulk_action' : 'bbpac_bulk_action2';
		$id   = 'top' === $which ? 'doaction' : 'doaction2';
		?>
		<label for="<?php echo \esc_attr( $name ); ?>" class="screen-reader-text"><?php \esc_html_e( 'Select bulk action', 'bloatbuster-performance-asset-cleaner' ); ?></label>
		<select name="<?php echo \esc_attr( $name ); ?>" id="<?php echo \esc_attr( $name ); ?>">
			<option value="-1"><?php \esc_html_e( 'Bulk actions', 'bloatbuster-performance-asset-cleaner' ); ?></option>
			<?php foreach ( $this->get_bulk_actions() as $value => $label ) : ?>
				<option value="<?php echo \esc_attr( $value ); ?>"><?php echo \esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		\submit_button( \__( 'Apply', 'bloatbuster-performance-asset-cleaner' ), 'action', '', false, array( 'id' => $id ) );
	}

	/**
	 * Get the view links with counts.
	 *
	 * @return array - View slug => link HTML.
	 *
	 * @since 1.0.0
	 */
	protected function get_views() {
		$request = self::get_request_args();
		$views   = array(
			'all'            => \__( 'All', 'bloatbuster-performance-asset-cleaner' ),
			'autoloaded'     => \__( 'Autoloaded', 'bloatbuster-performance-asset-cleaner' ),
			'not_autoloaded' => \__( 'Not autoloaded', 'bloatbuster-performance-asset-cleaner' ),
			'review'         => \__( 'Needs review', 'bloatbuster-performance-asset-cleaner' ),
		);
		$links   = array();

		foreach ( $views as $view => $label ) {
			$count = Autoload_Options::count(
				array(
					'view'               => $view,
					'search'             => $request['search'],
					'include_transients' => $request['include_transients'],
				)
			);

			$links[ $view ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				\esc_url( self::get_tab_url( array( 'view' => 'all' === $view ? false : $view ) ) ),
				$view === $request['view'] ? ' class="current" aria-current="page"' : '',
				\esc_html( $label ),
				\esc_html( \number_format_i18n( $count ) )
			);
		}

		return $links;
	}

	/**
	 * Load the current page of options.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$request = self::get_request_args();

		/**
		 * Filter the number of options per page.
		 *
		 * @param int $per_page - Options per page. Default 50.
		 *
		 * @since 1.0.0
		 */
		$per_page = max( 1, (int) \apply_filters( 'bloatbuster_autoload_per_page', 50 ) );

		$result = Autoload_Options::query(
			array_merge(
				$request,
				array(
					'per_page' => $per_page,
					'page'     => $this->get_pagenum(),
				)
			)
		);

		$names             = array_column( $result['items'], 'name' );
		$blocked           = array_fill_keys( Autoload_Options::get_expiring_transient_names( $names ), true );
		$required_timeouts = array_fill_keys( Autoload_Options::get_required_timeout_names( $names ), true );

		foreach ( $result['items'] as &$item ) {
			$item['can_enable_autoload'] = ! isset( $blocked[ $item['name'] ] );
			$item['can_delete']          = ! isset( $required_timeouts[ $item['name'] ] );
		}
		unset( $item );

		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );

		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Message when no options match.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function no_items() {
		\esc_html_e( 'No options found.', 'bloatbuster-performance-asset-cleaner' );
	}

	/**
	 * Render the checkbox column. Protected options can't be selected.
	 *
	 * @param array $item - Option row.
	 *
	 * @return string - Column HTML.
	 *
	 * @since 1.0.0
	 */
	protected function column_cb( $item ) {
		if ( Autoload_Options::is_protected( $item['name'] ) ) {
			return '';
		}

		return sprintf(
			'<input type="checkbox" name="option_names[]" value="%s" />',
			\esc_attr( $item['name'] )
		);
	}

	/**
	 * Render the option name column with owner and row actions.
	 *
	 * @param array $item - Option row.
	 *
	 * @return string - Column HTML.
	 *
	 * @since 1.0.0
	 */
	protected function column_name( $item ) {
		$owner        = Autoload_Owners::get_owner( $item['name'] );
		$is_protected = Autoload_Options::is_protected( $item['name'] );

		$html  = '<code class="bbpac-option-name">' . \esc_html( $item['name'] ) . '</code>';
		$html .= '<span class="bbpac-owner bbpac-owner-' . \esc_attr( $owner['status'] ) . '">' . \esc_html( $owner['label'] ) . '</span>';

		if ( $is_protected ) {
			$html .= ' <span class="bbpac-badge bbpac-badge-protected">' . \esc_html__( 'Protected', 'bloatbuster-performance-asset-cleaner' ) . '</span>';
		}

		$actions = array(
			'view' => sprintf(
				'<button type="button" class="button-link bbpac-view-value" data-option="%1$s">%2$s</button>',
				\esc_attr( $item['name'] ),
				\esc_html__( 'View value', 'bloatbuster-performance-asset-cleaner' )
			),
		);

		if ( ! $is_protected && Autoload_Actions::can_change_options() ) {
			$is_autoloaded = Autoload_Options::is_autoloaded( $item['autoload'] );

			if ( $is_autoloaded || $item['can_enable_autoload'] ) {
				$actions['toggle'] = sprintf(
					'<a href="%1$s">%2$s</a>',
					\esc_url( Autoload_Actions::get_row_action_url( $is_autoloaded ? 'disable' : 'enable', $item['name'] ) ),
					$is_autoloaded ? \esc_html__( 'Disable autoload', 'bloatbuster-performance-asset-cleaner' ) : \esc_html__( 'Enable autoload', 'bloatbuster-performance-asset-cleaner' )
				);
			}

			if ( $item['can_delete'] ) {
				$actions['delete'] = sprintf(
					'<a href="%1$s" class="bbpac-delete-option">%2$s</a>',
					\esc_url( Autoload_Actions::get_row_action_url( 'delete', $item['name'] ) ),
					\esc_html__( 'Delete', 'bloatbuster-performance-asset-cleaner' )
				);
			}
		}

		return $html . $this->row_actions( $actions );
	}

	/**
	 * Render the size column. Large autoloaded options are highlighted.
	 *
	 * @param array $item - Option row.
	 *
	 * @return string - Column HTML.
	 *
	 * @since 1.0.0
	 */
	protected function column_size( $item ) {
		$is_large = Autoload_Options::is_autoloaded( $item['autoload'] ) && $item['size'] >= Autoload_Options::get_large_threshold();

		return sprintf(
			'<span class="%1$s">%2$s</span>',
			$is_large ? 'bbpac-size bbpac-size-large' : 'bbpac-size',
			\esc_html( \size_format( $item['size'], 1 ) )
		);
	}

	/**
	 * Render the autoload status column.
	 *
	 * @param array $item - Option row.
	 *
	 * @return string - Column HTML.
	 *
	 * @since 1.0.0
	 */
	protected function column_autoload( $item ) {
		$is_autoloaded = Autoload_Options::is_autoloaded( $item['autoload'] );
		$is_auto       = 0 === strpos( $item['autoload'], 'auto' );

		if ( $is_autoloaded ) {
			$label = $is_auto ? \__( 'Auto (on)', 'bloatbuster-performance-asset-cleaner' ) : \__( 'On', 'bloatbuster-performance-asset-cleaner' );
		} else {
			$label = $is_auto ? \__( 'Auto (off)', 'bloatbuster-performance-asset-cleaner' ) : \__( 'Off', 'bloatbuster-performance-asset-cleaner' );
		}

		return sprintf(
			'<span class="bbpac-badge %1$s" title="%2$s">%3$s</span>',
			$is_autoloaded ? 'bbpac-badge-on' : 'bbpac-badge-off',
			\esc_attr( $item['autoload'] ),
			\esc_html( $label )
		);
	}
}
