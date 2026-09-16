<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;

/**
 * Every shortcode here is a one-line wrapper — ob_start(), call the
 * matching scc_the_*()/scc_*_button() function from template-functions.php,
 * ob_get_clean() — around the same function a theme would call
 * directly. That's deliberate: there is exactly one implementation of
 * "what a product/basket/checkout looks like", not a shortcode version
 * and a separate template-tag version that could drift apart. See
 * Admin\DocumentationPage for the full reference an editor or a
 * developer would actually read.
 */
final class Shortcodes implements Hookable
{
    public function register(): void
    {
        \add_shortcode('scc_product', [$this, 'renderProduct']);
        \add_shortcode('scc_products', [$this, 'renderProducts']);
        \add_shortcode('scc_add_to_basket', [$this, 'renderAddToBasket']);
        \add_shortcode('scc_basket', [$this, 'renderBasket']);
        \add_shortcode('scc_mini_basket', [$this, 'renderMiniBasket']);
        \add_shortcode('scc_checkout', [$this, 'renderCheckout']);
    }

    /**
     * [scc_product id="123"] — a full product card. atts: id
     * (required).
     */
    public function renderProduct(array $atts): string
    {
        $atts = \shortcode_atts(['id' => 0], $atts);

        \ob_start();
        \scc_the_product((int) $atts['id']);

        return \ob_get_clean();
    }

    /**
     * [scc_products exclude="2,4,5" product_type="6" search="" limit="20" layout="grid"]
     * — a grid (default) or list of products. atts: exclude
     * (comma-separated product IDs to leave out), product_type (a
     * Product Types term ID or slug), search, limit (0 = no limit,
     * default 20), layout ("grid" or "list").
     */
    public function renderProducts(array $atts): string
    {
        $atts = \shortcode_atts([
            'exclude' => '',
            'product_type' => '',
            'search' => '',
            'limit' => 20,
            'layout' => 'grid',
        ], $atts);

        $exclude = \array_filter(\array_map('absint', \explode(',', (string) $atts['exclude'])));

        \ob_start();
        \scc_the_products([
            'exclude' => $exclude,
            'product_type' => (string) $atts['product_type'],
            'search' => (string) $atts['search'],
            'limit' => (int) $atts['limit'],
            'layout' => (string) $atts['layout'] === 'list' ? 'list' : 'grid',
        ]);

        return \ob_get_clean();
    }

    /**
     * [scc_add_to_basket id="123" variation="0" label="Add to basket"]
     * atts: id (required), variation (optional variation index for a
     * variable product), label (optional button text).
     */
    public function renderAddToBasket(array $atts): string
    {
        $atts = \shortcode_atts(['id' => 0, 'variation' => '', 'label' => 'Add to basket'], $atts);

        \ob_start();
        \scc_add_to_basket_button(
            (int) $atts['id'],
            $atts['variation'] === '' ? null : (int) $atts['variation'],
            (string) $atts['label']
        );

        return \ob_get_clean();
    }

    /**
     * [scc_basket] — no atts. What Setup\Activator's auto-created
     * "Basket" page contains.
     */
    public function renderBasket(): string
    {
        \ob_start();
        \scc_the_basket();

        return \ob_get_clean();
    }

    /**
     * [scc_mini_basket] — no atts. A compact icon/count/total link to
     * the full basket, for a header or sidebar rather than a page body.
     */
    public function renderMiniBasket(): string
    {
        \ob_start();
        \scc_the_mini_basket();

        return \ob_get_clean();
    }

    /**
     * [scc_checkout] — no atts. What Setup\Activator's auto-created
     * "Checkout" page contains; also doubles as the payment-confirmation
     * screen once a customer's been redirected back to it (see
     * scc_the_checkout()'s own doc).
     */
    public function renderCheckout(): string
    {
        \ob_start();
        \scc_the_checkout();

        return \ob_get_clean();
    }
}
