<?php

/**
 * Global scc_* helpers for theme templates to call directly (see
 * wp-content/themes/sc-commerce-testbed for the simplest possible
 * consumer) — deliberately not namespaced, since a theme shouldn't
 * need a `use` statement just to print an add-to-basket button. Each
 * one is a thin wrapper over the real logic in src/, which is namespaced.
 *
 * Every one of these is also the *only* implementation behind the
 * matching shortcode (Frontend\Shortcodes) — [scc_product] calls
 * scc_the_product(), [scc_basket] calls scc_the_basket(), and so on.
 * A shortcode is just this function wrapped in ob_start()/ob_get_clean(),
 * so a theme using the function directly and an editor using the
 * shortcode in a page's content always render identically. See
 * Admin\DocumentationPage for the full reference.
 */

use SCCommerce\Basket\Basket;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Products\Product;
use SCCommerce\Products\ProductQuery;
use SCCommerce\Settings\Settings;
use SCCommerce\Support\Money;

function scc_price(float $amount, ?string $currency = null): string
{
    return Money::format($amount, $currency ?? (new Settings())->currency());
}

/**
 * A product card: image, name (linked to its own page), excerpt and
 * its buy box (scc_the_product_buy_box()) — enough to drop a single
 * product anywhere via [scc_product id="123"] or this function
 * directly, e.g. in a "related products" loop. The product's own
 * single-view page (Frontend\ProductContent) only needs the buy box,
 * not the whole card, since the theme already rendered the title/
 * content there.
 */
function scc_the_product(int $productId): void
{
    $product = Product::get($productId);

    if (! $product) {
        return;
    }
    ?>
    <div class="scc-product">
        <?php if (has_post_thumbnail($productId)) : ?>
            <a href="<?php echo esc_url($product->permalink()); ?>"><?php echo get_the_post_thumbnail($productId, 'medium'); ?></a>
        <?php endif; ?>
        <h2 class="scc-product-name"><a href="<?php echo esc_url($product->permalink()); ?>"><?php echo esc_html($product->name()); ?></a></h2>
        <?php
        $excerpt = get_the_excerpt($productId);

        if ($excerpt !== '') :
            ?>
            <div class="scc-product-excerpt"><?php echo wp_kses_post($excerpt); ?></div>
        <?php endif; ?>
        <?php scc_the_product_buy_box($productId); ?>
    </div>
    <?php
}

/**
 * A grid of product cards (each one scc_the_product()), or — with
 * 'layout' => 'list' — a compact one-row-per-product table
 * (scc_the_products_list()) for a denser listing than the card grid
 * suits. What [scc_products] renders either way. Filters go through
 * Products\ProductQuery, the same class the REST list endpoint uses,
 * so this and GET /scc/v1/products can never disagree about which
 * products match a given product_type/exclude/search.
 *
 * @param array{exclude?: array<int, int>, product_type?: int|string, search?: string, limit?: int, layout?: string} $args
 */
function scc_the_products(array $args = []): void
{
    $layout = ($args['layout'] ?? 'grid') === 'list' ? 'list' : 'grid';
    unset($args['layout']);

    $products = ProductQuery::get($args);

    if ($products === []) {
        echo '<p class="scc-products-empty">No products found.</p>';

        return;
    }

    if ($layout === 'list') {
        scc_the_products_list($products);

        return;
    }
    ?>
    <div class="scc-products">
        <?php foreach ($products as $product) : ?>
            <?php scc_the_product($product->id()); ?>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * The 'list' layout behind scc_the_products() — one row per product
 * (thumbnail, name, price, a compact add-to-basket) instead of a full
 * card, no excerpt, for pages that want more products visible at once
 * than the grid's card size allows. Takes already-queried Products
 * rather than query args, since scc_the_products() is the only
 * caller — not registered as its own shortcode/function, it's the
 * grid's sibling rendering, not a second listing feature.
 *
 * @param array<int, Product> $products
 */
function scc_the_products_list(array $products): void
{
    ?>
    <table class="scc-products-list">
        <tbody>
            <?php foreach ($products as $product) : ?>
                <tr>
                    <td class="scc-products-list-thumb">
                        <?php if (has_post_thumbnail($product->id())) : ?>
                            <a href="<?php echo esc_url($product->permalink()); ?>"><?php echo get_the_post_thumbnail($product->id(), 'thumbnail'); ?></a>
                        <?php endif; ?>
                    </td>
                    <td class="scc-products-list-name">
                        <a href="<?php echo esc_url($product->permalink()); ?>"><?php echo esc_html($product->name()); ?></a>
                    </td>
                    <td class="scc-products-list-price"><?php echo esc_html($product->displayPrice((new Settings())->currency())); ?></td>
                    <td class="scc-products-list-action">
                        <?php if ($product->isVariable()) : ?>
                            <a href="<?php echo esc_url($product->permalink()); ?>">Options</a>
                        <?php else : ?>
                            <?php scc_add_to_basket_button($product->id(), null, 'Add'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/**
 * Price plus Add to Basket — the part of a product's display that
 * actually depends on live data (price, stock, variations) rather
 * than editorial content, so it's kept separate from scc_the_product()
 * for pages (like the product's own single view) that already render
 * the name/image/excerpt themselves via the normal post content.
 *
 * Add to Basket is the only path to checkout (there's no "Buy Now" —
 * see CheckoutController's class doc) precisely because checkout
 * always needs the customer's name/address, which only the checkout
 * form collects; skipping straight to PayPal would mean relying on
 * PayPal's own account data for that instead, which this plugin
 * doesn't want to depend on.
 */
function scc_the_product_buy_box(int $productId): void
{
    $product = Product::get($productId);

    if (! $product) {
        return;
    }
    ?>
    <div class="scc-product-buy-box">
        <?php if ($product->isVariable()) : ?>
            <?php scc_the_variant_selector($product); ?>
        <?php else : ?>
            <p class="scc-product-price"><?php echo esc_html($product->displayPrice((new Settings())->currency())); ?></p>
            <div class="scc-product-actions">
                <?php scc_add_to_basket_button($productId); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * The variant picker for a variable product's buy box: a <select> of
 * variation labels, an image/price/description preview beneath it,
 * and the add-to-basket form for whichever variation is currently
 * selected. assets/js/product-variant-selector.js swaps the preview
 * and the form's hidden "variation" input as the selection changes —
 * every variation's data is embedded once as JSON (data-scc-variations)
 * rather than fetched per selection, since there's only ever a
 * handful of them and they're already loaded with the page.
 *
 * Requires JavaScript to actually change selection; without it (or
 * before it loads) the page still works, it just always adds the
 * first variation — the same one the preview shows on load.
 */
function scc_the_variant_selector(Product $product): void
{
    $variations = $product->variations();

    if ($variations === []) {
        echo '<p><em>This product has no variations configured yet.</em></p>';

        return;
    }

    $currency = (new Settings())->currency();
    $productId = $product->id();

    $payload = \array_map(
        static function (array $variation, int $index) use ($currency): array {
            return [
                'index' => $index,
                'price_formatted' => scc_price($variation['price'], $currency),
                'image' => $variation['image_id'] ? \wp_get_attachment_image_url($variation['image_id'], 'large') : null,
                'description' => $variation['description'],
            ];
        },
        $variations,
        \array_keys($variations)
    );
    $first = $payload[0];
    ?>
    <div class="scc-variant-selector" data-scc-variant-selector data-scc-variations="<?php echo esc_attr((string) wp_json_encode($payload)); ?>">
        <p>
            <label for="scc-variant-<?php echo esc_attr((string) $productId); ?>">Options</label><br>
            <select id="scc-variant-<?php echo esc_attr((string) $productId); ?>" data-scc-variant-select>
                <?php foreach ($variations as $index => $variation) : ?>
                    <option value="<?php echo esc_attr((string) $index); ?>"><?php echo esc_html($variation['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <img class="scc-variant-image" data-scc-variant-image src="<?php echo $first['image'] ? esc_url($first['image']) : ''; ?>" alt="" style="<?php echo $first['image'] ? '' : 'display:none;'; ?>">

        <p class="scc-product-price" data-scc-variant-price><?php echo esc_html($first['price_formatted']); ?></p>

        <?php if ($first['description'] !== '') : ?>
            <div class="scc-variant-description" data-scc-variant-description><?php echo esc_html($first['description']); ?></div>
        <?php else : ?>
            <div class="scc-variant-description" data-scc-variant-description style="display:none;"></div>
        <?php endif; ?>

        <div class="scc-product-actions">
            <?php scc_add_to_basket_button($productId, 0); ?>
        </div>
    </div>
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

/**
 * The full basket table (with per-row update/remove forms and a
 * "Proceed to checkout" link) — what [scc_basket] renders, and what
 * Setup\Activator's auto-created "Basket" page contains via that
 * shortcode. Call this directly instead if a theme wants the basket
 * on a page template rather than through the shortcode.
 */
function scc_the_basket(): void
{
    $basket = Basket::forCurrentVisitor();

    if ($basket->isEmpty()) {
        echo '<p class="scc-basket-empty">Your basket is empty.</p>';

        return;
    }
    ?>
    <table class="scc-basket-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Line total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($basket->enrichedItems() as $item) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($item['product']->permalink()); ?>"><?php echo esc_html($item['product']->name()); ?></a>
                        <?php if ($item['variation'] !== null) : $variation = $item['product']->variation($item['variation']); ?>
                            <br><small><?php echo esc_html($variation['label'] ?? ''); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scc-basket-update-form" data-scc-product="<?php echo esc_attr($item['product']->id()); ?>">
                            <?php wp_nonce_field('scc_basket_update'); ?>
                            <input type="hidden" name="action" value="scc_basket_update">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($item['product']->id()); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url(scc_basket_url()); ?>">
                            <?php if ($item['variation'] !== null) : ?>
                                <input type="hidden" name="variation" value="<?php echo esc_attr($item['variation']); ?>">
                            <?php endif; ?>
                            <input type="number" name="quantity" value="<?php echo esc_attr($item['quantity']); ?>" min="1" style="width:4em;">
                            <button type="submit">Update</button>
                        </form>
                    </td>
                    <td><?php echo esc_html(scc_price($item['line_total'])); ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scc-basket-remove-form" data-scc-product="<?php echo esc_attr($item['product']->id()); ?>">
                            <?php wp_nonce_field('scc_basket_remove'); ?>
                            <input type="hidden" name="action" value="scc_basket_remove">
                            <input type="hidden" name="product_id" value="<?php echo esc_attr($item['product']->id()); ?>">
                            <input type="hidden" name="redirect_to" value="<?php echo esc_url(scc_basket_url()); ?>">
                            <?php if ($item['variation'] !== null) : ?>
                                <input type="hidden" name="variation" value="<?php echo esc_attr($item['variation']); ?>">
                            <?php endif; ?>
                            <button type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align:right;">Total</th>
                <th><?php echo esc_html(scc_price($basket->total())); ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
    <p><a href="<?php echo esc_url(scc_checkout_url()); ?>" class="scc-proceed-to-checkout">Proceed to checkout</a></p>
    <?php
}

/**
 * A read-only "what you're actually buying" box — same line items/
 * total as scc_the_basket()'s table, minus the quantity/remove forms,
 * since by checkout the point isn't to edit the basket further, just
 * to confirm it before handing over address/payment details.
 */
function scc_the_order_summary(Basket $basket): void
{
    ?>
    <table class="scc-order-summary">
        <caption>Order summary</caption>
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Line total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($basket->enrichedItems() as $item) : ?>
                <tr>
                    <td>
                        <?php echo esc_html($item['product']->name()); ?>
                        <?php if ($item['variation'] !== null) : $variation = $item['product']->variation($item['variation']); ?>
                            <br><small><?php echo esc_html($variation['label'] ?? ''); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html((string) $item['quantity']); ?></td>
                    <td><?php echo esc_html(scc_price($item['line_total'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2" style="text-align:right;">Total</th>
                <th><?php echo esc_html(scc_price($basket->total())); ?></th>
            </tr>
        </tfoot>
    </table>
    <?php
}

/**
 * The red asterisk next to a checkout label — aria-hidden since the
 * field's own `required` attribute is what actually tells assistive
 * tech it's mandatory; this is a sighted-user visual cue only, paired
 * with the "* Required" key printed once at the top of the form.
 */
function scc_required_marker(): void
{
    echo '<span class="scc-required" aria-hidden="true">*</span>';
}

/**
 * The checkout form (order summary, address, and payment) plus, via
 * query string once a customer's been redirected here — from a
 * gateway or from CheckoutController::checkout() — order status/
 * notice messaging. The same page doubles as the "payment accepted"/
 * "we'll be in touch" confirmation screen: there's no separate
 * "complete" page, since the meaningful state to show is always this
 * order's current status, and PayPalIpnListener is what actually
 * keeps that status current in the background.
 *
 * Address fields assume a UK audience for now (no country selector —
 * Orders\Order stores a fixed 'GB' country on every order regardless).
 * Adding one later is additive: a new form field plus reading it in
 * Frontend\CheckoutController::customerFromRequest(), nothing else
 * needs to change.
 */
function scc_the_checkout(): void
{
    scc_the_checkout_notices();

    $basket = Basket::forCurrentVisitor();

    if ($basket->isEmpty() && empty($_GET['scc_order'])) {
        echo '<p class="scc-basket-empty">Your basket is empty. <a href="'.esc_url(home_url('/')).'">Continue shopping</a>.</p>';

        return;
    }

    if ($basket->isEmpty()) {
        return;
    }

    scc_the_order_summary($basket);
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="scc-checkout-form">
        <?php wp_nonce_field('scc_checkout'); ?>
        <input type="hidden" name="action" value="scc_checkout">

        <p class="scc-required-key"><?php scc_required_marker(); ?> Required</p>

        <p>
            <label>Name <?php scc_required_marker(); ?><br><input type="text" name="customer_name" required></label>
        </p>
        <p>
            <label>Email <?php scc_required_marker(); ?><br><input type="email" name="customer_email" required></label>
        </p>

        <h3>Delivery address</h3>
        <p>
            <label>Address line 1 <?php scc_required_marker(); ?><br><input type="text" name="address_line1" required></label>
        </p>
        <p>
            <label>Address line 2 <span class="scc-optional">(optional)</span><br><input type="text" name="address_line2"></label>
        </p>
        <p>
            <label>Town / city <?php scc_required_marker(); ?><br><input type="text" name="address_town" required></label>
        </p>
        <p>
            <label>County <span class="scc-optional">(optional)</span><br><input type="text" name="address_county"></label>
        </p>
        <p>
            <label>Postcode <?php scc_required_marker(); ?><br><input type="text" name="address_postcode" pattern="[A-Za-z]{1,2}\d[A-Za-z\d]?\s*\d[A-Za-z]{2}" title="Enter a valid UK postcode" required></label>
        </p>

        <p>
            <label>Order notes <span class="scc-optional">(optional)</span><br><textarea name="customer_notes" rows="3"></textarea></label>
        </p>

        <p><strong>Total: <?php echo esc_html(scc_price($basket->total())); ?></strong></p>
        <button type="submit" class="scc-place-order">Pay with PayPal</button>
    </form>
    <?php
}

function scc_the_checkout_notices(): void
{
    if (! empty($_GET['scc_notice'])) {
        $notice = sanitize_key($_GET['scc_notice']);
        $messages = [
            'empty' => 'There was nothing to check out.',
            'no_gateway' => 'Thanks — your order has been recorded, but online payment isn\'t set up on this site yet. We\'ll be in touch to arrange payment.',
            'invalid_name' => 'Please enter your name.',
            'invalid_email' => 'Please enter a valid email address.',
            'invalid_address_line1' => 'Please enter your delivery address.',
            'invalid_address_town' => 'Please enter your town or city.',
            'invalid_address_postcode' => 'Please enter a valid UK postcode.',
        ];

        if (isset($messages[$notice])) {
            echo '<p class="scc-notice">'.esc_html($messages[$notice]).'</p>';
        }
    }

    if (empty($_GET['scc_order'])) {
        return;
    }

    $order = Order::get((int) $_GET['scc_order']);

    if (! $order) {
        return;
    }

    $status = $order->status();
    $label = OrderPostType::STATUSES[$status] ?? $status;
    $isPending = $status === OrderPostType::STATUS_PAYMENT_PENDING;

    // data-scc-poll="1" only while still pending — assets/js/order-status.js
    // reads this box's data-order-id and polls
    // GET scc/v1/orders/{id}/status until the status it gets back
    // differs from data-status, then updates the text in place and
    // stops. A terminal status (Paid, Payment Failed, ...) is never
    // polled — nothing left to wait for. Progressive enhancement only:
    // without JS, or once polling gives up (see the JS file's own
    // attempt cap), a manual refresh of this same URL always shows
    // the true current status anyway, since it's read fresh from the
    // order on every request.
    ?>
    <div class="scc-order-status-box" data-scc-order-status data-order-id="<?php echo esc_attr((string) $order->id()); ?>" data-status="<?php echo esc_attr($status); ?>" data-poll="<?php echo $isPending ? '1' : '0'; ?>">
        <p class="scc-order-status">Order #<?php echo esc_html((string) $order->id()); ?> — status: <span data-scc-status-label><?php echo esc_html($label); ?></span></p>
        <?php if ($isPending && ($_GET['scc_paypal'] ?? '') === 'return') : ?>
            <p class="scc-notice" data-scc-pending-notice>We're waiting for confirmation from PayPal — this'll update automatically once that arrives, usually within a few seconds.</p>
        <?php endif; ?>
    </div>
    <?php
}

function scc_basket_count(): int
{
    return Basket::forCurrentVisitor()->itemCount();
}

/**
 * A compact "icon, item count, running total" link to the full basket
 * — for a header or sidebar, not a second way to view/edit its
 * contents (that's scc_the_basket()/[scc_basket]). The count/total
 * spans carry the same data-scc-basket-count/data-scc-basket-total
 * hooks assets/js/basket.js already updates after an AJAX
 * add-to-basket elsewhere on the page, so this stays in sync without
 * a reload — a plain refresh always shows the true state anyway,
 * since both are read fresh from the basket on every request.
 */
function scc_the_mini_basket(): void
{
    $basket = Basket::forCurrentVisitor();
    ?>
    <a href="<?php echo esc_url(scc_basket_url()); ?>" class="scc-mini-basket">
        <span class="scc-mini-basket-icon" aria-hidden="true">🛒</span>
        <span class="scc-mini-basket-count" data-scc-basket-count><?php echo esc_html((string) $basket->itemCount()); ?></span>
        <span class="scc-mini-basket-total" data-scc-basket-total><?php echo esc_html(scc_price($basket->total())); ?></span>
    </a>
    <?php
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
