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
        \add_shortcode('scc_add_to_basket', [$this, 'renderAddToBasket']);
        \add_shortcode('scc_buy_now', [$this, 'renderBuyNow']);
        \add_shortcode('scc_basket', [$this, 'renderBasket']);
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
     * [scc_buy_now id="123" variation="0" label="Buy now"] — same atts
     * as [scc_add_to_basket].
     */
    public function renderBuyNow(array $atts): string
    {
        $atts = \shortcode_atts(['id' => 0, 'variation' => '', 'label' => 'Buy now'], $atts);

        \ob_start();
        \scc_buy_now_button(
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
