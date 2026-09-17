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
        '[scc_products exclude="2,4,5" product_type="6" search="" limit="20" layout="grid"]' => 'A grid (default) or list of products. All attributes optional: exclude (comma-separated IDs to leave out), product_type (a Product Types term ID or slug), search, limit (0 = no limit, default 20), layout ("grid" or "list" — a compact one-row-per-product table).',
        '[scc_add_to_basket id="123" variation="0" label="Add to basket"]' => 'A standalone add-to-basket button for one product. variation and label are both optional.',
        '[scc_basket]' => 'The basket table with quantity/remove controls — what the auto-created "Basket" page contains.',
        '[scc_mini_basket]' => 'A compact icon/item-count/running-total link to the full basket, for a header or sidebar.',
        '[scc_checkout]' => 'The checkout form, and (once a customer is redirected back to it) their order\'s status — what the auto-created "Checkout" page contains.',
    ];

    /**
     * @var array<string, string>
     */
    private const FUNCTIONS = [
        'scc_the_product(int $productId)' => 'Echoes a full product card — same markup as [scc_product].',
        'scc_the_products(array $args = [])' => 'Echoes a grid of product cards, or a compact list table with layout => \'list\' — same markup as [scc_products]. Args: exclude, product_type, search, limit (see Products\ProductQuery), layout.',
        'scc_the_product_buy_box(int $productId)' => 'Echoes just the price + buy box, no name/image/excerpt — what a product\'s own single-view page uses (Frontend\ProductContent), since the theme already renders those.',
        'scc_add_to_basket_button(int $productId, ?int $variation = null, string $label = \'Add to basket\')' => 'Echoes one add-to-basket form.',
        'scc_the_basket()' => 'Echoes the basket table — same markup as [scc_basket].',
        'scc_the_mini_basket()' => 'Echoes a compact icon/item-count/running-total link to the full basket — same markup as [scc_mini_basket], for a header or sidebar.',
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
            <?php $this->renderSchemaSection(); ?>
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
        <p><code><?php echo \esc_html(\rest_url('scc/v1/products')); ?>?product_type=mugs&amp;exclude=2,4,5&amp;limit=10</code></p>
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
                <tr><td><code>product_type</code></td><td>A Product Types taxonomy term ID or slug to filter by.</td></tr>
                <tr><td><code>exclude</code></td><td>Comma-separated product IDs to leave out, e.g. "2,4,5".</td></tr>
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
        <p class="description"><code>price</code> is <code>null</code> for a variable product — use <code>variations</code> (each with its own <code>price</code>/<code>price_formatted</code>/<code>image</code>/<code>description</code>, <code>image</code> <code>null</code> if that variation has none) instead.</p>
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

    private function renderSchemaSection(): void
    {
        ?>
        <h2>Structured data (schema.org)</h2>
        <p>
            Every product's own single-view page automatically gets a <code>schema.org/Product</code> block in
            <code>&lt;head&gt;</code> as JSON-LD (<code>Frontend\ProductSchema</code>) — name, description, image,
            SKU and price, built from the same <code>Products\Product</code> data everything else on this page
            reads, so it can never disagree with what's shown on the page itself. Nothing to configure; it's there
            as soon as the product is published.
        </p>
        <p class="description">
            A simple product gets a plain <code>Offer</code> (one price). A variable product gets an
            <code>AggregateOffer</code> instead — <code>lowPrice</code>/<code>highPrice</code> across its
            variations, since schema.org has no single "the" price for something sold in several variants.
            Availability is always reported as <code>InStock</code> — this plugin doesn't track stock levels, so
            there's no signal to say otherwise.
        </p>
        <?php
    }

    private function renderOrdersSection(): void
    {
        ?>
        <h2>Orders</h2>
        <p>
            An Order is created the moment a customer submits the checkout form — before any payment happens, so
            abandoned/failed attempts are captured too. Manage orders under the <strong>Orders</strong> menu;
            status only ever moves forward automatically via
            PayPal's IPN callback (see Payments below), except Dispatched, which is always set by hand once a
            parcel actually goes out. The Status column shows each order's status as a colour-coded pill (a small
            dot plus a tinted background) so the list can be scanned without reading every cell's text.
        </p>
        <table class="widefat striped" style="max-width: 500px;">
            <thead><tr><th>Status</th></tr></thead>
            <tbody>
                <?php foreach (OrderPostType::STATUSES as $label) : ?>
                    <tr><td><?php echo \esc_html($label); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            An order stuck on "Payment Pending" for a month (PayPal's IPN never arrived — see "Testing locally"
            below for the most common reason on a dev site) is automatically moved to "Cancelled" by a daily
            WP-Cron job (<code>Orders\StaleOrderCleaner</code>). It's left there for an admin to trash by hand
            whenever they're ready — nothing deletes the order itself. "Created" orders (no gateway configured)
            aren't included, since the shop owner already gets a notification email about those and is expected
            to follow up manually rather than have them expire.
        </p>

        <h3>Tracking</h3>
        <p>
            The Order Status box has a free-text <strong>Tracking code</strong> field, filled in by hand once a
            parcel goes out — there's no carrier integration to set this (or to move an order to "Complete" on
            delivery) automatically yet.
        </p>

        <h3>Checkout fields</h3>
        <p>
            The checkout form (<code>scc_the_checkout()</code>/<code>[scc_checkout]</code>) collects name, email, a
            UK delivery address (address line 1 — required, address line 2, town — required, county, postcode —
            required) and optional order notes, all stored on the order alongside its line items. There's no
            country field yet — every order gets a fixed <code>GB</code> country (<code>Order::address()</code>'s
            <code>country</code> key) rather than an empty one, ready for a real country selector to be added later
            without changing the stored shape.
        </p>
        <p class="description">
            Once redirected back from PayPal, the "Payment Pending" status shown there updates itself automatically
            (<code>assets/js/order-status.js</code> polls <code>GET /scc/v1/orders/{id}/status</code> every few
            seconds) rather than needing a manual refresh — pure progressive enhancement, since a refresh always
            shows the true current status anyway.
        </p>
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

        <h3>Testing locally</h3>
        <p>
            An IPN needs PayPal's own servers to reach this site's <code>notify_url</code> — a site only reachable
            on your own machine (a <code>.test</code>/<code>localhost</code> URL, an entry in <code>/etc/hosts</code>,
            ...) has no publicly-routable address for PayPal to POST back to, so a sandbox payment will complete on
            PayPal's side but the order here will stay on Payment Pending forever, with nothing to indicate why —
            not a bug, just nowhere for the notification to land. Expose the site with a tunnel (ngrok, Cloudflare
            Tunnel, ...) and point <code>WP_SITEURL</code>/<code>WP_HOME</code> at the tunnel's URL for the
            duration of the test to see a full sandbox payment actually flip an order to Paid. Once this plugin is
            on a real public domain, none of this applies — it works automatically.
        </p>

        <h3>What PayPal's IPN actually sends</h3>
        <p>
            An IPN is an ordinary <code>application/x-www-form-urlencoded</code> POST to
            <code>Gateways\PayPal\PayPalIpnListener</code>'s REST route, containing several dozen fields — most of
            which this plugin ignores. The ones it reads:
        </p>
        <table class="widefat striped" style="max-width: 700px;">
            <thead><tr><th>Field</th><th>What it's used for</th></tr></thead>
            <tbody>
                <tr><td><code>payment_status</code></td><td>Mapped to an order status — "Completed" → Paid, "Pending" → Payment Pending, "Denied"/"Failed"/"Voided"/"Expired" → Payment Failed. Anything else is left as Payment Pending.</td></tr>
                <tr><td><code>custom</code> / <code>invoice</code></td><td>The order ID (PayPalGateway::checkoutUrl() sets both to it) — whichever is present identifies which order this notification is about.</td></tr>
                <tr><td><code>txn_id</code></td><td>PayPal's own transaction ID, recorded on the order (<code>Order::transactionId()</code>) for support/reconciliation.</td></tr>
            </tbody>
        </table>
        <p class="description">
            Every notification is first re-posted back to PayPal with <code>cmd=_notify-validate</code> prepended —
            only a "VERIFIED" reply is trusted; anything else (including a network failure) is rejected outright and
            never touches an order. See PayPal's own IPN variable reference for the full field list if extending
            this — <code>payer_email</code>/<code>first_name</code>/<code>last_name</code>/<code>address_*</code>
            are commonly-wanted ones this plugin doesn't currently store, since it already collects its own
            delivery address on the checkout form.
        </p>
        <?php
    }
}
