<?php
/**
 * Offer state tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\Offers\Offer_State;
use PHPUnit\Framework\TestCase;

/**
 * Tests offer action state storage.
 */
final class Offer_State_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_records_and_reads_offer_action(): void {
		$session = new Fake_Session();
		$state   = new Offer_State( $session );

		$this->assertTrue( $state->record_action( 123, 'starter-kit', 'accept' ) );

		$record = $state->get_action( 123 );

		$this->assertIsArray( $record );
		$this->assertSame( 'starter-kit', $record['offer_id'] );
		$this->assertSame( 'accept', $record['action'] );
		$this->assertGreaterThan( 0, $record['recorded_at'] );
		$this->assertTrue( $state->has_action( 123 ) );
		$this->assertTrue( $state->has_action( 123, 'accept' ) );
		$this->assertFalse( $state->has_action( 123, 'reject' ) );
	}

	/**
	 * @return void
	 */
	public function test_rejects_unknown_action(): void {
		$state = new Offer_State( new Fake_Session() );

		$this->assertFalse( $state->record_action( 123, 'starter-kit', 'maybe' ) );
		$this->assertNull( $state->get_action( 123 ) );
	}
}

/**
 * Minimal session fake.
 */
final class Fake_Session {
	/**
	 * Session data.
	 *
	 * @var array<string,mixed>
	 */
	private $data = array();

	/**
	 * Gets a session value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : $default;
	}

	/**
	 * Sets a session value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
	}
}
