<?php

namespace SCCommerce;

use SCCommerce\Admin\DocumentationPage;
use SCCommerce\Admin\OrderListTable;
use SCCommerce\Admin\SettingsMenu;
use SCCommerce\Basket\BasketMerger;
use SCCommerce\Frontend\Assets;
use SCCommerce\Frontend\BasketFormController;
use SCCommerce\Frontend\BasketRestController;
use SCCommerce\Frontend\CheckoutController;
use SCCommerce\Frontend\ProductContent;
use SCCommerce\Frontend\ProductsRestController;
use SCCommerce\Frontend\Shortcodes;
use SCCommerce\Gateways\PayPal\PayPalGateway;
use SCCommerce\Gateways\PayPal\PayPalIpnListener;
use SCCommerce\MetaBoxes\OrderDetailsMetaBox;
use SCCommerce\MetaBoxes\ProductDetailsMetaBox;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\PostTypes\ProductPostType;
use SCCommerce\Settings\Settings;
use SCCommerce\Taxonomies\ProductTypeTaxonomy;

/**
 * Composes the plugin's features and wires them into WordPress.
 *
 * To grow the plugin (a second payment gateway, stock levels, a real
 * variable-product attribute matrix, ...) write a class implementing
 * Contracts\Hookable and add it to the list in boot().
 */
final class Plugin
{
    private static ?self $instance = null;

    private Settings $settings;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        $this->settings = new Settings();
    }

    public function boot(): void
    {
        $paypal = new PayPalGateway($this->settings);

        $features = [
            new ProductPostType(),
            new ProductTypeTaxonomy(),
            new ProductDetailsMetaBox(),
            new OrderPostType(),
            new OrderDetailsMetaBox(),
            new OrderListTable(),
            new BasketMerger(),
            new BasketRestController(),
            new BasketFormController(),
            $paypal,
            new CheckoutController($this->settings, $paypal),
            new PayPalIpnListener($this->settings),
            new ProductsRestController(),
            new Shortcodes(),
            new ProductContent(),
            new Assets(),
            new SettingsMenu(),
            new DocumentationPage(),
        ];

        foreach ($features as $feature) {
            $feature->register();
        }
    }

    public function settings(): Settings
    {
        return $this->settings;
    }
}
