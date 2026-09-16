<?php

/**
 * Global scc_* helpers for theme templates to call directly (see
 * wp-content/themes/sc-commerce-testbed for the simplest possible
 * consumer) — deliberately not namespaced, since a theme shouldn't
 * need a `use` statement just to print a Buy Now button. Each one is a
 * thin wrapper over the real logic in src/, which is namespaced.
 */

use SCCommerce\Basket\Basket;
use SCCommerce\Products\Product;
use SCCommerce\Settings\Settings;
use SCCommerce\Support\Money;

function scc_price(float $amount, ?string $currency = null): string
{
    return Money::format($amount, $currency ?? (new Settings())->currency());
}

/**
 * A single <form> posting straight to admin-post.php?action=scc_buy_now
 * — see Frontend\CheckoutController::buyNow(). No JS required for this
 * one: a real "buy it now" has to survive a customer with JS disabled.
 */
function scc_buy_now_button(int $productId, ?int $variation = null, string $label = 'Buy now'): void
{
    $product = Product::get($productId);

    if (! $product) {
        return;
    }
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scc-buy-now-form">
        <?php wp_nonce_field('scc_buy_now'); ?>
        <input type="hidden" name="action" value="scc_buy_now">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($productId); ?>">
        <?php if ($variation !== null) : ?>
            <input type="hidden" name="variation" value="<?php echo esc_attr($variation); ?>">
        <?php endif; ?>
        <input type="number" name="quantity" value="1" min="1" style="width:4em;">
        <button type="submit" class="scc-buy-now"><?php echo esc_html($label); ?></button>
    </form>
    <?php
}

/**
 * AJAX add-to-basket (assets/js/basket.js posts to
 * scc/v1/basket/add and updates the page without a reload) with a
 * plain form fallback baked into the same markup for no-JS visitors —
 * basket.js calls preventDefault() on submit once it's loaded.
 */
function scc_add_to_basket_button(int $productId, ?int $variation = null, string $label = 'Add to basket'): void
{
    $product = Product::get($productId);

    if (! $product) {
        return;
    }
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scc-add-to-basket-form" data-scc-product="<?php echo esc_attr($productId); ?>" <?php echo $variation !== null ? 'data-scc-variation="'.esc_attr($variation).'"' : ''; ?>>
        <?php wp_nonce_field('scc_basket_add'); ?>
        <input type="hidden" name="action" value="scc_basket_add">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($productId); ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(scc_basket_url()); ?>">
        <?php if ($variation !== null) : ?>
            <input type="hidden" name="variation" value="<?php echo esc_attr($variation); ?>">
        <?php endif; ?>
        <input type="number" name="quantity" value="1" min="1" style="width:4em;">
        <button type="submit" class="scc-add-to-basket"><?php echo esc_html($label); ?></button>
    </form>
    <?php
}

function scc_basket_count(): int
{
    return Basket::forCurrentVisitor()->itemCount();
}

function scc_basket_url(): string
{
    $pageId = (new Settings())->basketPageId();

    return $pageId ? (string) get_permalink($pageId) : home_url('/');
}

function scc_checkout_url(): string
{
    $pageId = (new Settings())->checkoutPageId();

    return $pageId ? (string) get_permalink($pageId) : home_url('/');
}
