<?php
/**
 * Funnel package validation.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\ImportExport;

use LibreFunnels\Domain\Registered_Meta;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes and validates import/export packages.
 */
final class Package_Validator {
	/**
	 * Current package format identifier.
	 */
	const FORMAT = 'librefunnels.funnel';

	/**
	 * Current package schema version.
	 */
	const VERSION = 1;

	/**
	 * Normalizes a decoded package or JSON string.
	 *
	 * @param mixed $package Package data or JSON string.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function normalize( $package ) {
		if ( is_string( $package ) ) {
			$package = json_decode( $package, true );
		}

		if ( is_object( $package ) ) {
			$package = (array) $package;
		}

		if ( ! is_array( $package ) ) {
			return new \WP_Error( 'invalid_package', __( 'The funnel package is not valid JSON data.', 'librefunnels' ) );
		}

		if ( ! isset( $package['format'] ) || self::FORMAT !== (string) $package['format'] ) {
			return new \WP_Error( 'invalid_package_format', __( 'The funnel package format is not supported.', 'librefunnels' ) );
		}

		$version = isset( $package['version'] ) ? absint( $package['version'] ) : 0;

		if ( self::VERSION !== $version ) {
			return new \WP_Error( 'unsupported_package_version', __( 'The funnel package version is not supported.', 'librefunnels' ) );
		}

		if ( ! isset( $package['funnel'] ) || ! is_array( $package['funnel'] ) ) {
			return new \WP_Error( 'missing_funnel', __( 'The funnel package does not include funnel data.', 'librefunnels' ) );
		}

		return array(
			'format'      => self::FORMAT,
			'version'     => self::VERSION,
			'generatedBy' => isset( $package['generatedBy'] ) ? sanitize_text_field( (string) $package['generatedBy'] ) : '',
			'funnel'      => $this->normalize_funnel( $package['funnel'] ),
			'steps'       => $this->normalize_steps( isset( $package['steps'] ) && is_array( $package['steps'] ) ? $package['steps'] : array() ),
		);
	}

	/**
	 * Normalizes funnel package data.
	 *
	 * @param array<string,mixed> $funnel Funnel data.
	 * @return array<string,mixed>
	 */
	private function normalize_funnel( array $funnel ) {
		return array(
			'title'       => isset( $funnel['title'] ) ? sanitize_text_field( (string) $funnel['title'] ) : '',
			'status'      => isset( $funnel['status'] ) ? sanitize_key( (string) $funnel['status'] ) : 'draft',
			'startStepId' => isset( $funnel['startStepId'] ) ? absint( $funnel['startStepId'] ) : 0,
			'graph'       => Registered_Meta::sanitize_graph( isset( $funnel['graph'] ) ? $funnel['graph'] : array() ),
		);
	}

	/**
	 * Normalizes step package data.
	 *
	 * @param array<int,mixed> $steps Raw step list.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_steps( array $steps ) {
		$normalized = array();

		foreach ( $steps as $step ) {
			if ( is_object( $step ) ) {
				$step = (array) $step;
			}

			if ( ! is_array( $step ) ) {
				continue;
			}

			$normalized[] = array(
				'originalId'       => isset( $step['originalId'] ) ? absint( $step['originalId'] ) : 0,
				'title'            => isset( $step['title'] ) ? sanitize_text_field( (string) $step['title'] ) : '',
				'content'          => isset( $step['content'] ) ? wp_kses_post( (string) $step['content'] ) : '',
				'excerpt'          => isset( $step['excerpt'] ) ? wp_kses_post( (string) $step['excerpt'] ) : '',
				'status'           => isset( $step['status'] ) ? sanitize_key( (string) $step['status'] ) : 'draft',
				'type'             => isset( $step['type'] ) ? Registered_Meta::sanitize_step_type( $step['type'] ) : 'landing',
				'order'            => isset( $step['order'] ) ? absint( $step['order'] ) : 0,
				'template'         => isset( $step['template'] ) ? sanitize_key( (string) $step['template'] ) : '',
				'pageId'           => isset( $step['pageId'] ) ? absint( $step['pageId'] ) : 0,
				'checkoutProducts' => Registered_Meta::sanitize_checkout_products( isset( $step['checkoutProducts'] ) ? $step['checkoutProducts'] : array() ),
				'checkoutCoupons'  => Registered_Meta::sanitize_coupon_codes( isset( $step['checkoutCoupons'] ) ? $step['checkoutCoupons'] : array() ),
				'checkoutFields'   => Registered_Meta::sanitize_checkout_fields( isset( $step['checkoutFields'] ) ? $step['checkoutFields'] : array() ),
				'orderBumps'       => Registered_Meta::sanitize_order_bumps( isset( $step['orderBumps'] ) ? $step['orderBumps'] : array() ),
				'offer'            => Registered_Meta::sanitize_step_offer( isset( $step['offer'] ) ? $step['offer'] : array() ),
			);
		}

		return $normalized;
	}
}
