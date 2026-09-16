=== SC Commerce ===
Contributors: screencandy
Tags: ecommerce, shop, basket, paypal, orders
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.3.0
License: All Rights Reserved

A deliberately small ecommerce system for WordPress — products, a basket, PayPal checkout and orders — for sites that don't need WooCommerce's weight.

== Description ==

SC Commerce is built to be dropped into any WordPress site as-is — no build step, no Composer install. It is not a WooCommerce
replacement for stores that need one; it's for the smaller client sites that don't, where WooCommerce's data model, admin
screens and plugin ecosystem are more than the site will ever use.

**Products.** A "Product" post type with a price, SKU and simple/variable type. Variable products currently store a flat
list of variations (label, price, SKU) as provision for a future attribute-matrix UI — see "Variable products" below.
Products belong to a hierarchical "Product Types" taxonomy for browsing/grouping.

**Basket.** A logged-in visitor's basket lives in their own user meta; a guest's lives entirely in a cookie (the basket
itself, JSON-encoded, not a server-side session token) — see Basket\Basket's class doc. Adding an item is AJAX
(Frontend\BasketRestController) with a working no-JS form fallback (Frontend\BasketFormController) behind the same
markup. A guest's basket merges into their account automatically on login (Basket\BasketMerger).

**Checkout & payments.** "Buy Now" on a product, or "Proceed to checkout" from the basket, both end up at
Frontend\CheckoutController, which creates an Order (see "Orders" below) and redirects to PayPal — PayPal Standard's
hosted checkout (`cgi-bin/webscr`), needing only a business email address in Settings, no API keys or SDK. Payment
confirmation comes from PayPal's IPN callback (Gateways\PayPal\PayPalIpnListener), never from the customer's browser
landing back on the site, since that can be skipped, closed or spoofed. Adding a second gateway (Stripe Checkout, GoCardless,
PayPal's own Smart Buttons/Orders v2 API) means writing a class implementing Contracts\PaymentGateway — nothing else in
the checkout flow needs to change.

**Orders.** An "Order" post is created the moment a customer expresses intent to buy — a Buy Now click, or submitting the
checkout form — *before* any payment happens, so abandoned and failed attempts are captured too, not just successful
ones. Status lives in postmeta rather than WordPress's own post_status, with a fixed set: Created, Payment Pending,
Cancelled, Paid, Payment Failed, Complete, Dispatched. Orders aren't created manually in wp-admin (create_posts is
locked out for the type) — only ever via Orders\Order::create(). The checkout form collects a UK delivery address
(line 1, line 2, town, county, postcode — no country field yet, every order gets a fixed "GB") plus optional order
notes, all stored on the order alongside its line items.

== Variable products ==

The "Variable" product type and its variations repeater (label/price/SKU per row) are provision, not a full
implementation — there's no attribute system (e.g. Size × Colour generating rows automatically), and the testbed
theme's product page doesn't yet render a variant picker (Frontend\ProductContent shows a placeholder note instead
of Buy Now/Add to Basket for variable products). The meta shape (`_scc_variations`, a flat array of rows) is chosen
so a future attribute-matrix UI can write to the same field without a data migration.

== Payments: why PayPal Standard, and what else is free ==

PayPal Standard was chosen for v1 because it needs nothing but a business email address — no API keys, no OAuth
handshake, no SDK — while still being a real, working checkout. The trade-offs: an older/less polished checkout UI
than PayPal's own Smart Buttons, and IPN (a POST-and-verify callback) rather than modern signed webhooks.

Other genuinely free-to-integrate options worth considering as the plugin grows, each as its own
Contracts\PaymentGateway implementation:

* **PayPal Smart Buttons / Orders v2 API** — PayPal's current recommended integration; no PayPal fees beyond the
  usual per-transaction rate, but needs a REST API app (client ID/secret) and some JS SDK wiring.
* **Stripe Checkout** — a hosted page like PayPal Standard's, free to integrate (Stripe only takes its usual
  per-transaction cut), arguably the more modern/reliable option today, and a common client ask.
* **GoCardless** — direct debit rather than card/PayPal balance; free to integrate, better suited to
  recurring/subscription products than one-off orders.

== Product data: three ways in ==

* **PHP template functions** (`scc_the_product()`, `scc_the_products()`, `scc_the_product_buy_box()`,
  `scc_add_to_basket_button()`, `scc_buy_now_button()`, `scc_the_basket()`, `scc_the_checkout()`, ...) — for theme
  code on this same site.
* **Shortcodes** (`[scc_product]`, `[scc_products]`, `[scc_add_to_basket]`, `[scc_buy_now]`, `[scc_basket]`,
  `[scc_checkout]`) — for post/page content. Each one is a one-line wrapper around the matching function above, not
  a second implementation, so the two can never render differently.
* **REST API** (`GET /wp-json/scc/v1/products`, `GET /wp-json/scc/v1/products/{id}`) — for anything outside this
  site's own PHP. Public, read-only, versioned. `[scc_products]`/`scc_the_products()` and the REST list endpoint are
  both built on `Products\ProductQuery`, so filtering (`exclude`, `product_type` by ID or slug, `search`) behaves
  identically whichever one you use.

Full reference (attributes, query params, response shape) lives on the in-dashboard SC Commerce → Documentation
screen, not just here.

== Installation ==

1. Copy (or symlink) this plugin's folder into `wp-content/plugins/`.
2. Activate it. This creates a Basket and Checkout page (each with the relevant shortcode) and a default settings row.
3. Go to SC Commerce → Settings and enter a PayPal business email address (and toggle sandbox mode for testing).
4. Add some products, then visit one on the front end.

== Changelog ==

= 0.3.0 =
* Added `[scc_products]`/`scc_the_products()` — a filterable product listing (`exclude`, `product_type` by term ID
  or slug, `search`, `limit`), backed by a new `Products\ProductQuery` shared by the REST list endpoint too, which
  gained matching `exclude`/term-ID support.
* Checkout now collects a UK delivery address and optional order notes, stored on the order.
* Checkout screen shows an order summary (line items + total) alongside the address/payment form.

= 0.2.0 =
* SC Commerce settings moved out from under Products into their own top-level admin menu, near the bottom of the
  menu list — General and PayPal are now separate settings sections.
* Added an in-dashboard Documentation screen (SC Commerce → Documentation).
* Added `[scc_product]`, `[scc_add_to_basket]` and `[scc_buy_now]` shortcodes, and their matching
  `scc_the_product()`/`scc_the_product_buy_box()` template functions.
* Added a public products REST API (`GET /scc/v1/products`, `GET /scc/v1/products/{id}`).

= 0.1.0 =
* Initial scaffold: Product/Order post types, Product Types taxonomy, basket, checkout, PayPal Standard gateway, orders admin.
