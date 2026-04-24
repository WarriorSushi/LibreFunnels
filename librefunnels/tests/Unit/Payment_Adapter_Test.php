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
use LibreFunnels\Payments\Offer_Child_Order_Factory;
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
		$GLOBALS['librefunnels_test_products'] = array();

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

	/**
	 * @return void
	 */
	public function test_service_creates_child_order_before_mock_one_click_charge(): void {
		$GLOBALS['librefunnels_test_products'] = array(
			44 => new Fake_Payment_Product( 50.0 ),
		);

		$parent = new Fake_Payment_Order( 201, 'librefunnels_mock', 'wc_order_parent' );
		$child  = new Fake_Payment_Order( 301, 'librefunnels_mock', 'wc_order_child' );
		$service = new Offer_Payment_Service(
			new Adapter_Registry(),
			new Offer_Child_Order_Factory(
				null,
				static function () use ( $child ) {
					return $child;
				}
			)
		);

		$result = $service->charge_offer(
			$parent,
			array(
				'id'              => 'boost',
				'product_id'      => 44,
				'quantity'        => 2,
				'discount_type'   => 'fixed',
				'discount_amount' => 10,
			),
			array(
				'funnel_id' => 9,
				'step_id'   => 12,
			)
		);

		$this->assertTrue( $result->is_success() );
		$this->assertSame( array(), $parent->meta );
		$this->assertSame( 201, $child->parent_id );
		$this->assertSame( 123, $child->customer_id );
		$this->assertSame( 'USD', $child->currency );
		$this->assertSame( 100.0, $child->products[0]['args']['subtotal'] );
		$this->assertSame( 80.0, $child->products[0]['args']['total'] );
		$this->assertSame( 'yes', $child->meta['_librefunnels_offer_child_order'] );
		$this->assertSame( 201, $child->meta['_librefunnels_parent_order_id'] );
		$this->assertSame( 'boost', $child->meta['_librefunnels_offer_id'] );
		$this->assertSame( 12, $child->meta['_librefunnels_offer_step_id'] );
		$this->assertSame( 9, $child->meta['_librefunnels_funnel_id'] );
		$this->assertSame( 'charged', $child->meta['_librefunnels_offer_payment_status'] );
		$this->assertSame( 'mock_offer_charged', $child->meta['_librefunnels_offer_payment_result'] );
		$this->assertTrue( $child->saved );
	}

	/**
	 * @return void
	 */
	public function test_child_order_creation_fails_before_charge_when_product_is_missing(): void {
		$child = new Fake_Payment_Order( 302, 'librefunnels_mock', 'wc_order_child' );
		$service = new Offer_Payment_Service(
			new Adapter_Registry(),
			new Offer_Child_Order_Factory(
				null,
				static function () use ( $child ) {
					return $child;
				}
			)
		);

		$result = $service->charge_offer(
			new Fake_Payment_Order( 202, 'librefunnels_mock', 'wc_order_parent' ),
			array(
				'id'         => 'boost',
				'product_id' => 404,
			)
		);

		$this->assertFalse( $result->is_success() );
		$this->assertSame( 'offer_product_not_found', $result->get_code() );
		$this->assertSame( array(), $child->meta );
		$this->assertFalse( $child->saved );
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
	 * Parent order ID.
	 *
	 * @var int
	 */
	public $parent_id = 0;

	/**
	 * Customer ID.
	 *
	 * @var int
	 */
	public $customer_id = 123;

	/**
	 * Currency.
	 *
	 * @var string
	 */
	public $currency = 'USD';

	/**
	 * Added products.
	 *
	 * @var array<int,array<string,mixed>>
	 */
	public $products = array();

	/**
	 * Billing address.
	 *
	 * @var array<string,string>
	 */
	private $billing = array(
		'first_name' => 'Liberty',
		'last_name'  => 'Buyer',
	);

	/**
	 * Shipping address.
	 *
	 * @var array<string,string>
	 */
	private $shipping = array(
		'first_name' => 'Liberty',
		'last_name'  => 'Buyer',
	);

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
	 * Gets the customer ID.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return $this->customer_id;
	}

	/**
	 * Sets the customer ID.
	 *
	 * @param int $customer_id Customer ID.
	 * @return void
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = absint( $customer_id );
	}

	/**
	 * Gets the currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->currency;
	}

	/**
	 * Sets the currency.
	 *
	 * @param string $currency Currency.
	 * @return void
	 */
	public function set_currency( $currency ) {
		$this->currency = sanitize_text_field( $currency );
	}

	/**
	 * Sets the parent order ID.
	 *
	 * @param int $parent_id Parent order ID.
	 * @return void
	 */
	public function set_parent_id( $parent_id ) {
		$this->parent_id = absint( $parent_id );
	}

	/**
	 * Gets billing address.
	 *
	 * @return array<string,string>
	 */
	public function get_billing() {
		return $this->billing;
	}

	/**
	 * Sets billing address.
	 *
	 * @param array<string,string> $billing Billing address.
	 * @return void
	 */
	public function set_billing( array $billing ) {
		$this->billing = $billing;
	}

	/**
	 * Gets shipping address.
	 *
	 * @return array<string,string>
	 */
	public function get_shipping() {
		return $this->shipping;
	}

	/**
	 * Sets shipping address.
	 *
	 * @param array<string,string> $shipping Shipping address.
	 * @return void
	 */
	public function set_shipping( array $shipping ) {
		$this->shipping = $shipping;
	}

	/**
	 * Adds a product.
	 *
	 * @param object              $product  Product.
	 * @param int                 $quantity Quantity.
	 * @param array<string,mixed> $args     Line args.
	 * @return int
	 */
	public function add_product( $product, $quantity = 1, array $args = array() ) {
		$this->products[] = array(
			'product'  => $product,
			'quantity' => $quantity,
			'args'     => $args,
		);

		return count( $this->products );
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

	/**
	 * Calculates totals.
	 *
	 * @return void
	 */
	public function calculate_totals() {}
}

/**
 * Fake WooCommerce product for child order tests.
 */
final class Fake_Payment_Product {
	/**
	 * Price.
	 *
	 * @var float
	 */
	private $price;

	/**
	 * Creates a product.
	 *
	 * @param float $price Price.
	 */
	public function __construct( $price ) {
		$this->price = (float) $price;
	}

	/**
	 * Gets the product price.
	 *
	 * @param string $context Context.
	 * @return float
	 */
	public function get_price( $context = 'view' ) {
		unset( $context );

		return $this->price;
	}
}
