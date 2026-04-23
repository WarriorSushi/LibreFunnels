<?php
/**
 * Bundled template library tests.
 *
 * @package LibreFunnels\Tests
 */

namespace LibreFunnels\Tests\Unit;

use LibreFunnels\ImportExport\Bundled_Template_Library;
use LibreFunnels\ImportExport\Package_Validator;
use PHPUnit\Framework\TestCase;

/**
 * Tests bundled template metadata and packages.
 */
final class Bundled_Template_Library_Test extends TestCase {
	/**
	 * @return void
	 */
	public function test_template_list_exposes_recommended_starter(): void {
		$library   = new Bundled_Template_Library();
		$templates = $library->get_templates();

		$this->assertNotEmpty( $templates );
		$this->assertSame( 'starter_checkout', $templates[0]['slug'] );
		$this->assertTrue( $templates[0]['isRecommended'] );
		$this->assertGreaterThanOrEqual( 3, $templates[0]['stepCount'] );
	}

	/**
	 * @return void
	 */
	public function test_template_package_is_normalized(): void {
		$library = new Bundled_Template_Library();
		$package = $library->get_template_package( 'starter_checkout' );

		$this->assertIsArray( $package );
		$this->assertSame( Package_Validator::FORMAT, $package['format'] );
		$this->assertSame( Package_Validator::VERSION, $package['version'] );
		$this->assertSame( 'landing', $package['steps'][0]['type'] );
		$this->assertSame( 'checkout', $package['steps'][1]['type'] );
		$this->assertSame( 101, $package['funnel']['startStepId'] );
	}

	/**
	 * @return void
	 */
	public function test_missing_template_returns_error(): void {
		$library = new Bundled_Template_Library();
		$result  = $library->get_template_package( 'not-real' );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'librefunnels_template_not_found', $result->get_error_code() );
	}
}
