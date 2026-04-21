<?php
/**
 * Checkout field customizer.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Checkout;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Applies checkout field rules while a LibreFunnels checkout step renders.
 */
final class Checkout_Field_Customizer {
	/**
	 * Active checkout step ID for the current render pass.
	 *
	 * @var int
	 */
	private static $active_step_id = 0;

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function register() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce checkout field filter.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ) );
	}

	/**
	 * Sets the active checkout step.
	 *
	 * @param int $step_id Step ID.
	 * @return void
	 */
	public static function set_active_step_id( $step_id ) {
		self::$active_step_id = absint( $step_id );
	}

	/**
	 * Clears the active checkout step.
	 *
	 * @return void
	 */
	public static function clear_active_step_id() {
		self::$active_step_id = 0;
	}

	/**
	 * Applies field rules to WooCommerce checkout fields.
	 *
	 * @param array<string,mixed> $fields Checkout fields.
	 * @return array<string,mixed>
	 */
	public function filter_checkout_fields( $fields ) {
		if ( 0 === self::$active_step_id ) {
			return $fields;
		}

		$rules = Registered_Meta::sanitize_checkout_fields(
			get_post_meta( self::$active_step_id, LIBREFUNNELS_CHECKOUT_FIELDS_META, true )
		);

		foreach ( $rules as $rule ) {
			$section = $rule['section'];
			$key     = $rule['key'];

			if ( ! isset( $fields[ $section ][ $key ] ) ) {
				continue;
			}

			if ( $rule['hidden'] ) {
				unset( $fields[ $section ][ $key ] );
				continue;
			}

			if ( '' !== $rule['label'] ) {
				$fields[ $section ][ $key ]['label'] = $rule['label'];
			}

			if ( '' !== $rule['placeholder'] ) {
				$fields[ $section ][ $key ]['placeholder'] = $rule['placeholder'];
			}

			$fields[ $section ][ $key ]['required'] = $rule['required'];
		}

		return $fields;
	}
}
