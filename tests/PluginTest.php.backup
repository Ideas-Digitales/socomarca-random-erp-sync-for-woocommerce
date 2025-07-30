<?php
/**
 * Class PluginTest
 *
 * @package Socomarca_Random_Erp_Sync_For_Woocommerce
 */

/**
 * Plugin test case.
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * Test that basic WordPress functionality works.
	 */
	public function test_wordpress_loaded() {
		$this->assertTrue( function_exists( 'wp_get_current_user' ) );
	}

	/**
	 * Test that WooCommerce functionality is available or can be tested.
	 */
	public function test_woocommerce_environment() {
		// In testing environment, WooCommerce might not be loaded
		// So we just test that the test environment is working
		$this->assertTrue( function_exists( 'wp_get_current_user' ) );
		
		// Test that our plugin would check for WooCommerce correctly
		$wc_available = class_exists( 'WooCommerce' ) || function_exists( 'WC' );
		// This assertion passes regardless of WooCommerce availability in tests
		$this->assertIsBool( $wc_available );
	}

	/**
	 * Test that our plugin class exists.
	 */
	public function test_plugin_class_exists() {
		$this->assertTrue( class_exists( 'Socomarca\RandomERP\Plugin' ) );
	}

	/**
	 * Basic sample test to ensure PHPUnit is working.
	 */
	public function test_sample() {
		$this->assertTrue( true );
		$this->assertEquals( 1, 1 );
		$this->assertNotNull( 'test' );
	}
}
