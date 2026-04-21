<?php
/**
 * Routing result value object.
 *
 * @package LibreFunnels
 */

namespace LibreFunnels\Routing;

defined( 'ABSPATH' ) || exit;

/**
 * Carries explicit success or failure state for funnel routing.
 */
final class Routing_Result {
	/**
	 * Whether routing succeeded.
	 *
	 * @var bool
	 */
	private $success;

	/**
	 * Resolved step ID.
	 *
	 * @var int
	 */
	private $step_id;

	/**
	 * Machine-readable result code.
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
	 * Creates a result.
	 *
	 * @param bool   $success Whether routing succeeded.
	 * @param int    $step_id Resolved step ID.
	 * @param string $code    Machine-readable result code.
	 * @param string $message Human-readable result message.
	 */
	private function __construct( $success, $step_id, $code, $message ) {
		$this->success = (bool) $success;
		$this->step_id = absint( $step_id );
		$this->code    = sanitize_key( $code );
		$this->message = (string) $message;
	}

	/**
	 * Creates a successful result.
	 *
	 * @param int    $step_id Resolved step ID.
	 * @param string $code    Machine-readable result code.
	 * @param string $message Human-readable result message.
	 * @return Routing_Result
	 */
	public static function success( $step_id, $code = 'ok', $message = '' ) {
		return new self( true, $step_id, $code, $message );
	}

	/**
	 * Creates a failed result.
	 *
	 * @param string $code    Machine-readable result code.
	 * @param string $message Human-readable result message.
	 * @return Routing_Result
	 */
	public static function failure( $code, $message ) {
		return new self( false, 0, $code, $message );
	}

	/**
	 * Checks whether routing succeeded.
	 *
	 * @return bool
	 */
	public function is_success() {
		return $this->success;
	}

	/**
	 * Returns the resolved step ID.
	 *
	 * @return int
	 */
	public function get_step_id() {
		return $this->step_id;
	}

	/**
	 * Returns the result code.
	 *
	 * @return string
	 */
	public function get_code() {
		return $this->code;
	}

	/**
	 * Returns the result message.
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
			'success' => $this->success,
			'step_id' => $this->step_id,
			'code'    => $this->code,
			'message' => $this->message,
		);
	}
}
