<?php
/**
 * Rule evaluator.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates structured rules against supplied facts.
 */
final class Rule_Evaluator {
	/**
	 * Evaluates a rule tree against facts.
	 *
	 * @param array<string,mixed> $rule  Rule tree.
	 * @param array<string,mixed> $facts Facts.
	 * @return Rule_Result
	 */
	public function evaluate( array $rule, array $facts = array() ) {
		$type = isset( $rule['type'] ) ? sanitize_key( (string) $rule['type'] ) : '';

		if ( '' === $type ) {
			return Rule_Result::no_match( 'rule_type_missing', __( 'The rule is missing a type.', 'librefunnels' ) );
		}

		if ( 'all' === $type || 'any' === $type ) {
			return $this->evaluate_group( $type, $rule, $facts );
		}

		return $this->evaluate_condition( $type, $rule, $facts );
	}

	/**
	 * Evaluates a rule group.
	 *
	 * @param string              $type  Group type.
	 * @param array<string,mixed> $rule  Rule group.
	 * @param array<string,mixed> $facts Facts.
	 * @return Rule_Result
	 */
	private function evaluate_group( $type, array $rule, array $facts ) {
		$rules = isset( $rule['rules'] ) && is_array( $rule['rules'] ) ? $rule['rules'] : array();

		if ( empty( $rules ) ) {
			return Rule_Result::no_match( 'rule_group_empty', __( 'The rule group is empty.', 'librefunnels' ) );
		}

		$matched = 0;

		foreach ( $rules as $child_rule ) {
			if ( is_object( $child_rule ) ) {
				$child_rule = (array) $child_rule;
			}

			if ( ! is_array( $child_rule ) ) {
				return Rule_Result::no_match( 'rule_child_invalid', __( 'A rule group contains an invalid child rule.', 'librefunnels' ) );
			}

			$result = $this->evaluate( $child_rule, $facts );

			if ( $result->is_match() ) {
				++$matched;
			} elseif ( 'all' === $type ) {
				return Rule_Result::no_match( 'all_rule_not_matched', __( 'At least one required condition did not match.', 'librefunnels' ) );
			}
		}

		if ( 'any' === $type && 0 === $matched ) {
			return Rule_Result::no_match( 'any_rule_not_matched', __( 'None of the optional conditions matched.', 'librefunnels' ) );
		}

		return Rule_Result::match( $type . '_rule_matched', __( 'The rule group matched.', 'librefunnels' ) );
	}

	/**
	 * Evaluates a leaf condition.
	 *
	 * @param string              $type  Condition type.
	 * @param array<string,mixed> $rule  Rule.
	 * @param array<string,mixed> $facts Facts.
	 * @return Rule_Result
	 */
	private function evaluate_condition( $type, array $rule, array $facts ) {
		switch ( $type ) {
			case 'always':
				return Rule_Result::match( 'always_matched', __( 'The always rule matched.', 'librefunnels' ) );

			case 'cart_contains_product':
				return $this->evaluate_cart_contains_product( $rule, $facts );

			case 'cart_subtotal_gte':
				return $this->evaluate_number_comparison( $rule, $facts, 'cart_subtotal', '>=', 'cart_subtotal_gte_matched', 'cart_subtotal_gte_not_matched' );

			case 'cart_subtotal_lte':
				return $this->evaluate_number_comparison( $rule, $facts, 'cart_subtotal', '<=', 'cart_subtotal_lte_matched', 'cart_subtotal_lte_not_matched' );

			case 'customer_logged_in':
				return ! empty( $facts['customer_logged_in'] )
					? Rule_Result::match( 'customer_logged_in_matched', __( 'The customer is logged in.', 'librefunnels' ) )
					: Rule_Result::no_match( 'customer_logged_in_not_matched', __( 'The customer is not logged in.', 'librefunnels' ) );
		}

		return Rule_Result::no_match( 'unknown_rule_type', __( 'The rule type is not supported.', 'librefunnels' ) );
	}

	/**
	 * Evaluates whether cart facts contain a product.
	 *
	 * @param array<string,mixed> $rule  Rule.
	 * @param array<string,mixed> $facts Facts.
	 * @return Rule_Result
	 */
	private function evaluate_cart_contains_product( array $rule, array $facts ) {
		$product_id = isset( $rule['product_id'] ) ? absint( $rule['product_id'] ) : 0;
		$products   = isset( $facts['cart_product_ids'] ) && is_array( $facts['cart_product_ids'] ) ? array_map( 'absint', $facts['cart_product_ids'] ) : array();

		if ( 0 === $product_id ) {
			return Rule_Result::no_match( 'rule_product_missing', __( 'The rule is missing a product ID.', 'librefunnels' ) );
		}

		if ( in_array( $product_id, $products, true ) ) {
			return Rule_Result::match( 'cart_contains_product_matched', __( 'The cart contains the product.', 'librefunnels' ) );
		}

		return Rule_Result::no_match( 'cart_contains_product_not_matched', __( 'The cart does not contain the product.', 'librefunnels' ) );
	}

	/**
	 * Evaluates a numeric comparison against facts.
	 *
	 * @param array<string,mixed> $rule       Rule.
	 * @param array<string,mixed> $facts      Facts.
	 * @param string              $fact_key   Fact key.
	 * @param string              $operator   Operator.
	 * @param string              $match_code Match code.
	 * @param string              $fail_code  Failure code.
	 * @return Rule_Result
	 */
	private function evaluate_number_comparison( array $rule, array $facts, $fact_key, $operator, $match_code, $fail_code ) {
		$target = isset( $rule['amount'] ) ? (float) $rule['amount'] : 0.0;
		$actual = isset( $facts[ $fact_key ] ) ? (float) $facts[ $fact_key ] : 0.0;

		if ( '>=' === $operator && $actual >= $target ) {
			return Rule_Result::match( $match_code, __( 'The numeric rule matched.', 'librefunnels' ) );
		}

		if ( '<=' === $operator && $actual <= $target ) {
			return Rule_Result::match( $match_code, __( 'The numeric rule matched.', 'librefunnels' ) );
		}

		return Rule_Result::no_match( $fail_code, __( 'The numeric rule did not match.', 'librefunnels' ) );
	}
}
