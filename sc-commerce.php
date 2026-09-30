<?php

/**
 * Plugin Name:       SC Commerce
 * Plugin URI:        https://github.com/neilsayers/sc-commerce
 * Description:       A deliberately small ecommerce system for WordPress — products, a basket, PayPal checkout and orders — for sites that don't need WooCommerce's weight.
 * Version:           0.11.1
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            Neil Sayers
 * Author URI:        https://screencandy.co.uk
 * License:           All Rights Reserved
 * Text Domain:       sc-commerce
 */

namespace SCCommerce;

if (! defined('ABSPATH')) {
    exit;
}

define('SCC_VERSION', '0.11.1');
define('SCC_FILE', __FILE__);
define('SCC_PATH', \plugin_dir_path(__FILE__));
define('SCC_URL', \plugin_dir_url(__FILE__));

/**
 * Minimal PSR-4-style autoloader so this plugin has zero build step
 * or Composer dependency — it just needs to be copied into any site's
 * wp-content/plugins and activated.
 */
\spl_autoload_register(function (string $class): void {
    $prefix = __NAMESPACE__.'\\';

    if (! \str_starts_with($class, $prefix)) {
        return;
    }

    $relative = \substr($class, \strlen($prefix));
    $path = SCC_PATH.'src/'.\str_replace('\\', '/', $relative).'.php';

    if (\is_file($path)) {
        require $path;
    }
});

\register_activation_hook(__FILE__, [Setup\Activator::class, 'activate']);
\register_deactivation_hook(__FILE__, [Setup\Activator::class, 'deactivate']);

require SCC_PATH.'src/Frontend/template-functions.php';

/*
 * Not on WordPress.org, so the Plugins screen's "Update available" is
 * pointed at this plugin's own GitHub Releases instead (Plugin Update
 * Checker, vendored in lib/ — no build step). A release is published
 * by .github/workflows/release.yml whenever the Version above changes;
 * enableReleaseAssets() makes sites install that workflow's zip, which
 * has the right folder name, rather than GitHub's source archive.
 */
require_once SCC_PATH.'lib/plugin-update-checker/plugin-update-checker.php';

\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker('https://github.com/neilsayers/sc-commerce/', SCC_FILE, 'sc-commerce')
    ->getVcsApi()
    ->enableReleaseAssets();

Plugin::instance()->boot();
