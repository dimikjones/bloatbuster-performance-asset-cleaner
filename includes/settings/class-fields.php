<?php
/**
 * Settings field rendering and sanitizing.
 *
 * @package BloatBuster
 */

namespace BloatBuster;

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and sanitizes settings fields by type.
 *
 * Built-in types: toggle, checkbox, select, number, action and report.
 * Other types use the bloatbuster_render_field_{type} action and the
 * bloatbuster_sanitize_field_{type} filter.
 */
final class Fields {

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
	 * Sanitize a submitted field value.
	 *
	 * @param array $field - Field data.
	 * @param array $input - Raw submitted options.
	 *
	 * @return mixed - Sanitized value, or null to remove the stored value.
	 *
	 * @since 1.0.0
	 */
	public static function sanitize_field( $field, $input ) {
		$raw = isset( $input[ $field['id'] ] ) ? $input[ $field['id'] ] : null;

		if ( 'toggle' === $field['type'] || 'checkbox' === $field['type'] ) {
			if ( ! empty( $raw ) ) {
				return 1;
			}

			return null;
		}

		if ( 'select' === $field['type'] ) {
			$value = is_scalar( $raw ) ? \sanitize_key( $raw ) : '';

			if ( '' !== $value && isset( $field['options'][ $value ] ) ) {
				return $value;
			}

			return null;
		}

		if ( 'number' === $field['type'] ) {
			$value = is_scalar( $raw ) && '' !== $raw ? \absint( $raw ) : $field['default'];

			return min( $field['max'], max( $field['min'], $value ) );
		}

		/**
		 * Sanitize the value of a custom field type.
		 *
		 * @param mixed $value - Sanitized value, null removes the stored value.
		 * @param mixed $raw   - Raw submitted value.
		 * @param array $field - Field data.
		 *
		 * @since 1.0.0
		 */
		return \apply_filters( 'bloatbuster_sanitize_field_' . $field['type'], null, $raw, $field );
	}

	/**
	 * Render a field, its description, note and locked notice.
	 *
	 * @param array $args - Field data with type, id, label, description and optional options, locked, locked_message, note, button, parent, min, max, step, default and render_callback.
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
		} elseif ( 'report' === $args['type'] ) {
			if ( ! empty( $args['render_callback'] ) && is_callable( $args['render_callback'] ) ) {
				call_user_func( $args['render_callback'], $args );
			}
		} elseif ( 'select' === $args['type'] ) {
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
				<input type="number" name="<?php echo \esc_attr( $option_name ); ?>" value="<?php echo \esc_attr( $number ); ?>" min="<?php echo \esc_attr( $args['min'] ); ?>" max="<?php echo \esc_attr( $args['max'] ); ?>" step="<?php echo \esc_attr( $args['step'] ); ?>" <?php \disabled( $is_locked ); ?>>
			</div>
			<?php
		} elseif ( 'checkbox' === $args['type'] ) {
			?>
			<div class="bbpac-field checkbox">
				<input type="checkbox" name="<?php echo \esc_attr( $option_name ); ?>" value="1" <?php \checked( 1, $value ); ?> <?php \disabled( $is_locked ); ?>>
			</div>
			<?php
		} elseif ( 'toggle' === $args['type'] ) {
			?>
			<div class="bbpac-field toggle">
				<label>
					<input type="checkbox" id="<?php echo \esc_attr( 'bbpac-' . $args['id'] ); ?>" name="<?php echo \esc_attr( $option_name ); ?>" value="1" <?php \checked( 1, $value ); ?> <?php \disabled( $is_locked ); ?>>
					<span class="slider round"></span>
				</label>
			</div>
			<?php
		} else {
			/**
			 * Render a custom field type.
			 *
			 * @param array  $args        - Field data.
			 * @param mixed  $value       - Stored value, empty string if not set.
			 * @param string $option_name - Input name attribute.
			 *
			 * @since 1.0.0
			 */
			\do_action( 'bloatbuster_render_field_' . $args['type'], $args, $value, $option_name );
		}

		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . \esc_html( $args['description'] ) . '</p>';
		}

		if ( ! empty( $args['note'] ) ) {
			echo '<p class="description">' . \esc_html( $args['note'] ) . '</p>';
		}

		if ( $is_locked && ! empty( $args['locked_message'] ) ) {
			echo '<p class="description bbpac-locked">' . \wp_kses_post( $args['locked_message'] ) . '</p>';
		}
	}

	/**
	 * Render an action field with its status and submit button.
	 *
	 * The render_callback prints the status and returns false when there
	 * is nothing to do, which disables the button.
	 *
	 * @param array $args - Field data with id, button and optional render_callback.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public static function render_action_field( $args ) {
		$has_items = true;

		if ( ! empty( $args['render_callback'] ) && is_callable( $args['render_callback'] ) ) {
			$has_items = false !== call_user_func( $args['render_callback'], $args );
		}
		?>
		<p>
			<button type="submit" name="bloatbuster_action" value="<?php echo \esc_attr( $args['id'] ); ?>" class="button button-secondary" <?php echo ! empty( $args['confirm'] ) ? 'data-bbpac-confirm="' . \esc_attr( $args['confirm'] ) . '"' : ''; ?> <?php \disabled( ! $has_items ); ?>><?php echo \esc_html( $args['button'] ); ?></button>
		</p>
		<?php
	}
}
