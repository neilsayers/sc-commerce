<?php

/**
 * Bootstrap for the integration tier — boots real WordPress (via the
 * wp-phpunit/wp-phpunit Composer package's test library) against a
 * disposable test database, then loads this plugin the same way
 * sc-commerce.php's own require does. Needs a working test database
 * already reachable — see tests/README.md; this file doesn't create
 * one, it just wires everything, up to and including that connection,
 * together.
 *
 * WP_TESTS_CONFIG_FILE_PATH is set here (rather than left for the
 * person running the suite to remember) so `vendor/bin/phpunit -c
 * phpunit-integration.xml.dist` works with zero env setup beyond the
 * database itself existing — see wp-tests-config.php for the DB_*
 * defaults that assumes.
 */

if (! getenv('WP_TESTS_CONFIG_FILE_PATH')) {
    putenv('WP_TESTS_CONFIG_FILE_PATH='.__DIR__.'/wp-tests-config.php');
}

$_tests_dir = dirname(__DIR__, 2).'/vendor/wp-phpunit/wp-phpunit';

require "{$_tests_dir}/includes/functions.php";

/**
 * Loads the plugin under test the same way WordPress would via
 * sc-commerce.php — tests_add_filter('muplugins_loaded', ...) runs
 * this after WP's own bootstrap but before any test runs, exactly
 * like the classic wp-cli-generated test bootstrap does.
 */
function _manually_load_sc_commerce_plugin(): void
{
    require dirname(__DIR__, 2).'/sc-commerce.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_sc_commerce_plugin');

require "{$_tests_dir}/includes/bootstrap.php";
