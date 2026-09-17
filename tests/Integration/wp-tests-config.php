<?php

/**
 * Same job as a real wp-config.php, but for the disposable database
 * the WordPress test library installs a fresh copy of WordPress'
 * schema into for every run — never point this at the testbed's own
 * "wordpress" database. All of it is overridable via env vars so this
 * file doesn't need editing per machine; see tests/README.md for the
 * one-time setup (creating the test database, and where ABSPATH needs
 * to point).
 */

define('DB_NAME', getenv('WP_TESTS_DB_NAME') ?: 'wordpress_test');
define('DB_USER', getenv('WP_TESTS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WP_TESTS_DB_PASSWORD') ?: 'root_password');
define('DB_HOST', getenv('WP_TESTS_DB_HOST') ?: 'db');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

$table_prefix = 'wptests_';

define('WP_TESTS_DOMAIN', 'sccommercetestbed.test');
define('WP_TESTS_EMAIL', 'admin@example.org');
define('WP_TESTS_TITLE', 'SC Commerce Test Suite');

define('WP_PHP_BINARY', 'php');
define('WPLANG', '');

/**
 * The real WordPress core this test run boots against — not
 * downloaded separately, since a full install already exists inside
 * the testbed's own Docker network (the "wordpress" service's
 * wp_data volume, also mounted into the "wp-cli" service at this same
 * path). Override if running tests somewhere that install lives
 * elsewhere.
 */
define('ABSPATH', (getenv('WP_TESTS_ABSPATH') ?: '/var/www/html').'/');
