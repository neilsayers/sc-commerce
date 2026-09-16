<?php

namespace SCCommerce\Admin;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\OrderPostType;

/**
 * "Documentation" — a plain reference for every way this plugin's
 * product data can be consumed (PHP function API, REST endpoint,
 * shortcode) plus the order status/payments reference, matching how
 * SC Events Manager/SC Maps document themselves. No settings live
 * here — it's read-only, so there's nothing to register_setting() or
 * admin_post_ handle.
 */
final class DocumentationPage implements Hookable
{
    private const PAGE_SLUG = 'scc-documentation';

    /**
     * @var array<string, string>
     */
    private const SHORTCODES = [
        '[scc_product id="123"]' => 'A full product card — image, name, excerpt, price and buy box.',
        '[scc_add_to_basket id="123" variation="0" label="Add to basket"]' => 'A standalone add-to-basket button for one product. variation and label are both optional.',
        '[scc_buy_now id="123" variation="0" label="Buy now"]' => 'A standalone Buy Now button for one product. variation and label are both optional.',
        '[scc_basket]' => 'The basket table with quantity/remove controls — what the auto-created "Basket" page contains.',
        '[scc_checkout]' => 'The checkout form, and (once a customer is redirected back to it) their order\'s status — what the auto-created "Checkout" page contains.',
    ];

    /**
     * @var array<string, string>
     */
    private const FUNCTIONS = [
        'scc_the_product(int $productId)' => 'Echoes a full product card — same markup as [scc_product].',
        'scc_the_product_buy_box(int $productId)' => 'Echoes just the price + buy box, no name/image/excerpt — what a product\'s own single-view page uses (Frontend\ProductContent), since the theme already renders those.',
        'scc_add_to_basket_button(int $productId, ?int $variation = null, string $label = \'Add to basket\')' => 'Echoes one add-to-basket form.',
        'scc_buy_now_button(int $productId, ?int $variation = null, string $label = \'Buy now\')' => 'Echoes one Buy Now form.',
        'scc_the_basket()' => 'Echoes the basket table — same markup as [scc_basket].',
        'scc_the_checkout()' => 'Echoes the checkout form/status — same markup as [scc_checkout].',
        'scc_price(float $amount, ?string $currency = null)' => 'Formats an amount using the site\'s configured currency (or one you pass explicitly).',
        'scc_basket_count()' => 'The current visitor\'s total basket quantity — handy for a header/cart icon badge.',
        'scc_basket_url() / scc_checkout_url()' => 'Permalinks of the auto-created Basket/Checkout pages.',
    ];

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'registerMenu']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        if (($_GET['page'] ?? '') !== self::PAGE_SLUG) {
            return;
        }

        \wp_enqueue_style('scc-admin', SCC_URL.'assets/css/admin.css', [], SCC_VERSION);
    }

    public function registerMenu(): void
    {
        \add_submenu_page(
            SettingsMenu::PAGE_SLUG,
            'Documentation',
            'Documentation',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    public function renderPage(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap scc-docs">
            <h1>SC Commerce — Documentation</h1>

            <p>
                Three ways to get product data into a theme, a page, or something outside this site entirely — all
                built on the same <code>Products\Product</code> class, so they can never disagree about a product's
                price or type.
            </p>

            <?php $this->renderFunctionSection(); ?>
            <?php $this->renderRestSection(); ?>
            <?php $this->renderShortcodeSection(); ?>
            <?php $this->renderOrdersSection(); ?>
            <?php $this->renderPaymentsSection(); ?>
        </div>
        <?php
    }

    private function renderFunctionSection(): void
    {
        ?>
        <h2>PHP: template functions</h2>
        <p>
            For theme code on this same site — no HTTP round trip, and every one of these is the actual
            implementation the matching shortcode wraps (see Shortcodes below), not a second copy of it. Global
            functions, deliberately not namespaced, so a template doesn't need a <code>use</code> statement to call one.
        </p>
        <pre class="scc-docs-code">&lt;?php if (have_posts()) : while (have_posts()) : the_post(); ?&gt;
    &lt;?php scc_the_product_buy_box(get_the_ID()); ?&gt;
&lt;?php endwhile; endif; ?&gt;</pre>

        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Function</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (self::FUNCTIONS as $signature => $description) : ?>
                    <tr>
                        <td><code><?php echo \esc_html($signature); ?></code></td>
                        <td><?php echo \esc_html($description); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderRestSection(): void
    {
        ?>
        <h2>REST: <code>GET /wp-json/scc/v1/products</code></h2>
        <p>
            For anything outside this site's own PHP — a decoupled front end, another site, a build step. Public
            and read-only; no authentication needed, since every field it returns is already visible on the
            product's own front-end page.
        </p>
        <p><code><?php echo \esc_html(\rest_url('scc/v1/products')); ?>?product_type=mugs&amp;limit=10</code></p>
        <p>Single product: <code><?php echo \esc_html(\rest_url('scc/v1/products/123')); ?></code></p>
        <p>
            <strong>v1 is a promise:</strong> existing fields won't be renamed or removed within it. A field can be
            added; a genuinely breaking change gets its own <code>scc/v2</code> route instead, so anything already
            built against v1 keeps working.
        </p>
        <p class="description">
            WordPress's own core REST controller also exposes this post type at
            <code>/wp/v2/scc_product</code> (with price/SKU/type/variations readable via its meta, since those are
            registered with <code>show_in_rest</code>) — that's the full raw post object rather than this
            purpose-shaped response, useful if you're already working with core's REST API elsewhere on the site.
        </p>

        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Query param</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><code>search</code></td><td>Free-text search against the product title/content.</td></tr>
                <tr><td><code>product_type</code></td><td>A Product Types taxonomy term slug to filter by.</td></tr>
                <tr><td><code>limit</code></td><td>Maximum products to return, capped at 100. 0 means no limit. Default 20.</td></tr>
            </tbody>
        </table>

        <p>Response shape:</p>
        <pre class="scc-docs-code">{
  "products": [
    {
      "id": 123,
      "name": "Mug",
      "permalink": "https://…/products/mug",
      "excerpt": "…",
      "image": "https://…/mug.jpg",
      "sku": "MUG-001",
      "type": "simple",
      "price": 9.99,
      "price_formatted": "£9.99",
      "variations": [],
      "product_types": [ { "id": 4, "name": "Mugs", "slug": "mugs" } ]
    }
  ],
  "total": 1
}</pre>
        <p class="description"><code>price</code> is <code>null</code> for a variable product — use <code>variations</code> (each with its own <code>price</code>/<code>price_formatted</code>) instead.</p>
        <?php
    }

    private function renderShortcodeSection(): void
    {
        ?>
        <h2>Shortcodes</h2>
        <p>For post/page content — renders the plugin's own markup directly.</p>

        <table class="widefat striped" style="max-width: 900px;">
            <thead>
                <tr>
                    <th>Shortcode</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (self::SHORTCODES as $shortcode => $description) : ?>
                    <tr>
                        <td><code><?php echo \esc_html($shortcode); ?></code></td>
                        <td><?php echo \esc_html($description); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderOrdersSection(): void
    {
        ?>
        <h2>Orders</h2>
        <p>
            An Order is created at the moment a customer expresses intent to buy (a Buy Now click, or submitting the
            checkout form) — before any payment happens, so abandoned/failed attempts are captured too. Manage
            orders under the <strong>Orders</strong> menu; status only ever moves forward automatically via
            PayPal's IPN callback (see Payments below), except Dispatched, which is always set by hand once a
            parcel actually goes out.
        </p>
        <table class="widefat striped" style="max-width: 500px;">
            <thead><tr><th>Status</th></tr></thead>
            <tbody>
                <?php foreach (OrderPostType::STATUSES as $label) : ?>
                    <tr><td><?php echo \esc_html($label); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderPaymentsSection(): void
    {
        ?>
        <h2>Payments</h2>
        <p>
            PayPal Standard (configured under SC Commerce -> Settings) is the only gateway built in — it needs
            nothing but a business email address, no API keys or SDK, so it's the simplest thing that can take a
            real payment. Confirmation always comes from PayPal's own server-to-server IPN callback, never from the
            customer's browser landing back on the site (which can be skipped, closed, or spoofed).
        </p>
        <p class="description">
            Other free-to-integrate options worth considering as a second gateway — each would be a class
            implementing <code>Contracts\PaymentGateway</code>, alongside the existing PayPal one, not a
            replacement for it: PayPal Smart Buttons/Orders v2 API (PayPal's current recommended integration, needs
            API credentials), Stripe Checkout (a similar hosted-page flow, arguably more modern), GoCardless (direct
            debit, better suited to recurring products than one-off orders).
        </p>
        <?php
    }
}
