<?php

/**
 * Plugin Name:       SC Commerce
 * Plugin URI:        https://github.com/neilsayers/sc-commerce
 * Description:       A deliberately small ecommerce system for WordPress — products, a basket, PayPal checkout and orders — for sites that don't need WooCommerce's weight.
 * Version:           0.3.0
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

define('SCC_VERSION', '0.3.0');
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

Plugin::instance()->boot();
