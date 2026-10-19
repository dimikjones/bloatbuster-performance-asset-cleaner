<?php
/**
 * Autoloaded options REST API.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route to view an option value.
 */
final class Autoload_Rest {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'bloatbuster/v1';

	/**
	 * Maximum number of bytes of a value returned.
	 *
	 * @var int
	 */
	const MAX_VALUE_LENGTH = 20480;

	/**
	 * Hook in methods.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function hooks() {
		\add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the REST routes.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function register_routes() {
		\register_rest_route(
			self::REST_NAMESPACE,
			'/autoload/value',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_value' ),
				'permission_callback' => array( __CLASS__, 'can_manage_options' ),
				'args'                => array(
					'name'   => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( __CLASS__, 'validate_option_name' ),
					),
					'reveal' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Check if the current user can manage options.
	 *
	 * @return bool - True if allowed.
	 *
	 * @since 1.0.0
	 */
	public static function can_manage_options() {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Validate an option name argument.
	 *
	 * @param mixed $value - Submitted value.
	 *
	 * @return bool - True if valid.
	 *
	 * @since 1.0.0
	 */
	public static function validate_option_name( $value ) {
		return is_string( $value ) && '' !== $value && strlen( $value ) <= 191;
	}

	/**
	 * Get the raw value of an option, formatted, truncated and masked when sensitive.
	 *
	 * @param \WP_REST_Request $request - REST request.
	 *
	 * @return \WP_REST_Response|\WP_Error - Value data or not found error.
	 *
	 * @since 1.0.0
	 */
	public static function get_value( $request ) {
		$name    = $request->get_param( 'name' );
		$options = Autoload_Options::get_raw_options( array( $name ) );

		if ( empty( $options[ $name ] ) ) {
			return new \WP_Error( 'bloatbuster_option_not_found', \__( 'Option not found.', 'bloatbuster-performance-asset-cleaner' ), array( 'status' => 404 ) );
		}

		$option    = $options[ $name ];
		$sensitive = Autoload_Options::is_sensitive( $name );
		$masked    = $sensitive && ! $request->get_param( 'reveal' );
		$format    = 'text';
		$value     = $option['value'];
		$is_binary = \wp_check_invalid_utf8( $value ) !== $value;

		if ( $is_binary ) {
			// JSON can't hold invalid UTF-8, those bytes are replaced. Export keeps the exact value.
			$format = 'binary';
			$value  = \wp_check_invalid_utf8( $value, true );
		} elseif ( \is_serialized( $value ) ) {
			// Shown raw, never unserialized.
			$format = 'serialized';
		} else {
			$decoded = json_decode( $value, true );

			if ( is_array( $decoded ) ) {
				$format = 'json';
				$value  = (string) \wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			}
		}

		$truncated = strlen( $value ) > self::MAX_VALUE_LENGTH;

		if ( $truncated ) {
			$value = \wp_check_invalid_utf8( substr( $value, 0, self::MAX_VALUE_LENGTH ), true );
		}

		return \rest_ensure_response(
			array(
				'name'      => $name,
				'size'      => strlen( $option['value'] ),
				'autoload'  => $option['autoload'],
				'format'    => $format,
				'sensitive' => $sensitive,
				'masked'    => $masked,
				'truncated' => $truncated,
				'binary'    => $is_binary,
				'value'     => $masked ? '' : $value,
			)
		);
	}
}
