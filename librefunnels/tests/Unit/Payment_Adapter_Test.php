<?php
/**
 * Payment adapter tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Payments\Adapter_Registry;
use LibreFunnels\Payments\Fallback_Adapter;
use LibreFunnels\Payments\Mock_Adapter;
use LibreFunnels\Payments\Offer_Payment_Service;
use PHPUnit\Framework\TestCase;

/**
 * Tests post-purchase payment adapter resolution.
 */
final class Payment_Adapter_Test extends TestCase {
	/**
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['librefunnels_test_orders'] = array();

		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_unknown_gateway_uses_accept_and_confirm_fallback(): void {
		$order    = new Fake_Payment_Order( 101, 'cheque', 'wc_order_unknown' );
		$strategy = ( new Offer_Payment_Service() )->get_strategy_for_step( 'upsell', $order );

		$this->assertSame( 'accept_and_confirm', $strategy['mode'] );
		$this->assertSame( 'fallback', $strategy['adapterId'] );
		$this->assertFalse( $strategy['oneClick'] );
		$this->assertStringContainsString( 'WooCommerce checkout confirmation', $strategy['message'] );
	}

	/**
	 * @return void
	 */
	public function test_mock_gateway_supports_one_click_strategy(): void {
		$order    = new Fake_Payment_Order( 102, 'librefunnels_mock', 'wc_order_mock' );
		$strategy = ( new Offer_Payment_Service() )->get_strategy_for_step( 'downsell', $order );

		$this->assertSame( 'one_click', $strategy['mode'] );
		$this->assertSame( 'mock', $strategy['adapterId'] );
		$this->assertTrue( $strategy['oneClick'] );
		$this->assertSame( 102, $strategy['orderId'] );
		$this->assertSame( 'wc_order_mock', $strategy['orderKey'] );
	}

	/**
	 * @return void
	 */
	public function test_mock_adapter_never_mutates_order_on_failed_charge(): void {
		$order  = new Fake_Payment_Order( 103, 'librefunnels_mock', 'wc_order_mock' );
		$result = ( new Mock_Adapter() )->charge_offer(
			$order,
			array(
				'id'         => 'boost',
				'product_id' => 44,
			),
			array(
				'force_failure' => true,
			)
		);

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'mock_forced_failure', $result->get_code() );
		$this->assertSame( array(), $order->meta );
		$this->assertFalse( $order->saved );
	}

	/**
	 * @return void
	 */
	public function test_mock_adapter_marks_successful_charge_for_test_gateway(): void {
		$order  = new Fake_Payment_Order( 104, 'librefunnels_mock', 'wc_order_mock' );
		$result = ( new Mock_Adapter() )->charge_offer(
			$order,
			array(
				'id'         => 'boost',
				'product_id' => 44,
			)
		);

		$this->assertTrue( $result->is_success() );
		$this->assertSame( 'mock_offer_charged', $result->get_code() );
		$this->assertSame( 'yes', $order->meta['_librefunnels_mock_offer_charged'] );
		$this->assertSame( 'boost', $order->meta['_librefunnels_mock_offer_id'] );
		$this->assertTrue( $order->saved );
	}

	/**
	 * @return void
	 */
	public function test_fallback_adapter_requires_confirmation_instead_of_charging(): void {
		$result = ( new Fallback_Adapter() )->charge_offer(
			new Fake_Payment_Order( 105, 'cheque', 'wc_order_unknown' ),
			array(
				'id'         => 'boost',
				'product_id' => 44,
			)
		);

		$this->assertFalse( $result->is_success() );
		$this->assertTrue( $result->requires_confirmation() );
		$this->assertSame( 'gateway_requires_confirmation', $result->get_code() );
	}

	/**
	 * @return void
	 */
	public function test_order_request_requires_matching_order_key(): void {
		$GLOBALS['librefunnels_test_orders'] = array(
			106 => new Fake_Payment_Order( 106, 'librefunnels_mock', 'wc_order_secret' ),
		);

		$service = new Offer_Payment_Service();

		$this->assertNull(
			$service->get_order_from_request_data(
				array(
					'librefunnels_order_id'  => 106,
					'librefunnels_order_key' => 'wrong',
				)
			)
		);

		$this->assertSame(
			$GLOBALS['librefunnels_test_orders'][106],
			$service->get_order_from_request_data(
				array(
					'librefunnels_order_id'  => 106,
					'librefunnels_order_key' => 'wc_order_secret',
				)
			)
		);
	}

	/**
	 * @return void
	 */
	public function test_registry_resolves_mock_before_fallback(): void {
		$registry = new Adapter_Registry();
		$adapter  = $registry->get_adapter_for_order( new Fake_Payment_Order( 107, 'librefunnels_test', 'wc_order_test' ) );

		$this->assertSame( 'mock', $adapter->get_id() );
	}
}

/**
 * Fake WooCommerce order for adapter tests.
 */
final class Fake_Payment_Order {
	/**
	 * Order ID.
	 *
	 * @var int
	 */
	private $id;

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * Order key.
	 *
	 * @var string
	 */
	private $order_key;

	/**
	 * Metadata.
	 *
	 * @var array<string,mixed>
	 */
	public $meta = array();

	/**
	 * Whether the order was saved.
	 *
	 * @var bool
	 */
	public $saved = false;

	/**
	 * Creates an order.
	 *
	 * @param int    $id             Order ID.
	 * @param string $payment_method Payment method.
	 * @param string $order_key      Order key.
	 */
	public function __construct( $id, $payment_method, $order_key ) {
		$this->id             = absint( $id );
		$this->payment_method = sanitize_key( $payment_method );
		$this->order_key      = sanitize_text_field( $order_key );
	}

	/**
	 * Gets the order ID.
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Gets the payment method.
	 *
	 * @return string
	 */
	public function get_payment_method() {
		return $this->payment_method;
	}

	/**
	 * Gets the order key.
	 *
	 * @return string
	 */
	public function get_order_key() {
		return $this->order_key;
	}

	/**
	 * Updates metadata.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function update_meta_data( $key, $value ) {
		$this->meta[ $key ] = $value;
	}

	/**
	 * Saves the order.
	 *
	 * @return void
	 */
	public function save() {
		$this->saved = true;
	}
}
