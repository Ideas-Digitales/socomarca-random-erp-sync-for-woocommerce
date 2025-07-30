<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class)->in('Unit', 'Feature');
uses(Tests\Integration\IntegrationTestCase::class)->in('Integration');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Helper function to mock WordPress functions for testing
 */
function mockWordPressFunctions()
{
    if (!function_exists('get_option')) {
        function get_option($option, $default = false) {
            global $wp_options;
            return isset($wp_options[$option]) ? $wp_options[$option] : $default;
        }
    }
    
    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            global $wp_options;
            $wp_options[$option] = $value;
            return true;
        }
    }
    
    if (!function_exists('delete_option')) {
        function delete_option($option) {
            global $wp_options;
            unset($wp_options[$option]);
            return true;
        }
    }
    
    if (!function_exists('error_log')) {
        function error_log($message) {
            // Mock error_log for testing
            return true;
        }
    }
}
