<?php
/**
 * Rule evaluation result.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Rules;

defined( 'ABSPATH' ) || exit;

/**
 * Carries explicit rule evaluation state.
 */
final class Rule_Result {
	/**
	 * Whether the rule matched.
	 *
	 * @var bool
	 */
	private $matched;

	/**
	 * Machine-readable result code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Human-readable message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Creates a result.
	 *
	 * @param bool   $matched Whether the rule matched.
	 * @param string $code    Result code.
	 * @param string $message Result message.
	 */
	private function __construct( $matched, $code, $message ) {
		$this->matched = (bool) $matched;
		$this->code    = sanitize_key( (string) $code );
		$this->message = (string) $message;
	}

	/**
	 * Creates a matched result.
	 *
	 * @param string $code    Result code.
	 * @param string $message Result message.
	 * @return Rule_Result
	 */
	public static function match( $code = 'matched', $message = '' ) {
		return new self( true, $code, $message );
	}

	/**
	 * Creates a non-matched result.
	 *
	 * @param string $code    Result code.
	 * @param string $message Result message.
	 * @return Rule_Result
	 */
	public static function no_match( $code = 'not_matched', $message = '' ) {
		return new self( false, $code, $message );
	}

	/**
	 * Whether the rule matched.
	 *
	 * @return bool
	 */
	public function is_match() {
		return $this->matched;
	}

	/**
	 * Gets the result code.
	 *
	 * @return string
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Gets the message.
	 *
	 * @return string
	 */
	public function get_message() {
		return $this->message;
	}

	/**
	 * Returns an array representation.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'matched' => $this->matched,
			'code'    => $this->code,
			'message' => $this->message,
		);
	}
}
