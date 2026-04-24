<?php
/**
 * Payment adapter result value.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Payments;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result returned by post-purchase payment adapters.
 */
final class Payment_Result {
	/**
	 * Whether the action completed.
	 *
	 * @var bool
	 */
	private $success;

	/**
	 * Whether checkout confirmation is required.
	 *
	 * @var bool
	 */
	private $requires_confirmation;

	/**
	 * Stable result code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Human-readable result message.
	 *
	 * @var string
	 */
	private $message;

	/**
	 * Additional result data.
	 *
	 * @var array<string,mixed>
	 */
	private $data;

	/**
	 * Creates a result.
	 *
	 * @param bool                $success               Whether the action completed.
	 * @param bool                $requires_confirmation Whether checkout confirmation is required.
	 * @param string              $code                  Stable result code.
	 * @param string              $message               Result message.
	 * @param array<string,mixed> $data                  Additional data.
	 */
	private function __construct( $success, $requires_confirmation, $code, $message, array $data = array() ) {
		$this->success               = (bool) $success;
		$this->requires_confirmation = (bool) $requires_confirmation;
		$this->code                  = sanitize_key( $code );
		$this->message               = sanitize_text_field( $message );
		$this->data                  = $data;
	}

	/**
	 * Creates a successful result.
	 *
	 * @param string              $code    Stable code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $data    Additional data.
	 * @return self
	 */
	public static function success( $code, $message, array $data = array() ) {
		return new self( true, false, $code, $message, $data );
	}

	/**
	 * Creates a confirmation-required result.
	 *
	 * @param string              $code    Stable code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $data    Additional data.
	 * @return self
	 */
	public static function confirmation_required( $code, $message, array $data = array() ) {
		return new self( false, true, $code, $message, $data );
	}

	/**
	 * Creates a failed result.
	 *
	 * @param string              $code    Stable code.
	 * @param string              $message Message.
	 * @param array<string,mixed> $data    Additional data.
	 * @return self
	 */
	public static function failure( $code, $message, array $data = array() ) {
		return new self( false, false, $code, $message, $data );
	}

	/**
	 * Whether the action completed.
	 *
	 * @return bool
	 */
	public function is_success() {
		return $this->success;
	}

	/**
	 * Whether checkout confirmation is required.
	 *
	 * @return bool
	 */
	public function requires_confirmation() {
		return $this->requires_confirmation;
	}

	/**
	 * Gets the stable result code.
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
	 * Gets additional data.
	 *
	 * @return array<string,mixed>
	 */
	public function get_data() {
		return $this->data;
	}
}
