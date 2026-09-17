<?php

/**
 * Bootstrap for the unit tier — no WordPress, no database, no
 * Composer autoload even needed beyond PHPUnit itself. Loads
 * Support\Money and Support\CustomerValidator directly (bypassing
 * sc-commerce.php entirely, since that file exits immediately if
 * ABSPATH isn't defined) because both classes are deliberately
 * WP-independent — see their own class docs.
 *
 * CustomerValidator calls WordPress's is_email(); the shim below is a
 * deliberately simplified stand-in (good enough to tell "looks like
 * an email" from "clearly isn't" for exercising this plugin's own
 * validation branching) — it is not a claim of matching every edge
 * case of WordPress core's real implementation. The integration tier
 * (tests/Integration) runs against real WordPress, real is_email()
 * included, if that fidelity ever matters for a specific case.
 */

require dirname(__DIR__, 2).'/src/Support/Money.php';
require dirname(__DIR__, 2).'/src/Support/CustomerValidator.php';

if (! function_exists('is_email')) {
    function is_email(string $email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
