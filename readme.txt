=== SC Commerce ===
Contributors: screencandy
Tags: ecommerce, shop, basket, paypal, orders
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 0.8.0
License: All Rights Reserved

A deliberately small ecommerce system for WordPress — products, a basket, PayPal checkout and orders — for sites that don't need WooCommerce's weight.

== Description ==

SC Commerce is built to be dropped into any WordPress site as-is — no build step, no Composer install. It is not a WooCommerce
replacement for stores that need one; it's for the smaller client sites that don't, where WooCommerce's data model, admin
screens and plugin ecosystem are more than the site will ever use.

**Products.** A "Product" post type with a price, SKU and simple/variable type. Variable products currently store a flat
list of variations (label, price, SKU) as provision for a future attribute-matrix UI — see "Variable products" below.
Products belong to a hierarchical "Product Types" taxonomy for browsing/grouping. `[scc_products]` lists them as a
card grid by default, or `layout="list"` for a compact one-row-per-product table.

**Basket.** A logged-in visitor's basket lives in their own user meta; a guest's lives entirely in a cookie (the basket
itself, JSON-encoded, not a server-side session token) — see Basket\Basket's class doc. Adding an item is AJAX
(Frontend\BasketRestController) with a working no-JS form fallback (Frontend\BasketFormController) behind the same
markup. A guest's basket merges into their account automatically on login (Basket\BasketMerger). `[scc_mini_basket]`
gives a header/sidebar a compact icon + item count + running total linking to the full basket, staying in sync with
AJAX add-to-basket elsewhere on the page without a reload.

**Checkout & payments.** There's no "Buy Now" shortcut — every purchase goes "Add to basket" then "Proceed to
checkout" from the basket, since checkout is the only point that collects the customer's name and delivery address,
which every order needs regardless of what's being bought. Checkout (Frontend\CheckoutController) creates an Order
(see "Orders" below) and redirects to PayPal — PayPal Standard's hosted checkout (`cgi-bin/webscr`), needing only a
business email address in Settings, no API keys or SDK. Payment confirmation comes from PayPal's IPN callback
(Gateways\PayPal\PayPalIpnListener), never from the customer's browser landing back on the site, since that can be
skipped, closed or spoofed. Adding a second gateway (Stripe Checkout, GoCardless, PayPal's own Smart Buttons/Orders
v2 API) means writing a class implementing Contracts\PaymentGateway — nothing else in the checkout flow needs to
change.

**Orders.** An "Order" post is created the moment a customer submits the checkout form — *before* any payment
happens, so abandoned and failed attempts are captured too, not just successful ones. Status lives in postmeta
rather than WordPress's own post_status, with a fixed set: Created, Payment Pending,
Cancelled, Paid, Payment Failed, Complete, Dispatched. Orders aren't created manually in wp-admin (create_posts is
locked out for the type) — only ever via Orders\Order::create(). The checkout form collects a UK delivery address
(line 1, line 2, town, county, postcode — no country field yet, every order gets a fixed "GB") plus optional order
notes, all stored on the order alongside its line items. Name, email, address line 1, town and postcode are required
and validated server-side (postcode against a UK postcode shape), not just via the form's own HTML attributes. The
Orders list colour-codes each row by status (a subtle left accent), and an order stuck on "Payment Pending" for a
month is automatically moved to "Cancelled" by a daily WP-Cron job (Orders\StaleOrderCleaner) — left for an admin
to trash by hand, nothing deletes the order itself. A free-text tracking code field on the order can be filled in
by hand once a parcel goes out; there's no carrier integration (or automatic "delivered" detection) yet.

**Notifications.** The shop owner is emailed when an order needs attention — paid (confirmed by PayPal's IPN), or
placed with no payment gateway configured, since that order will never reach "Paid" on its own. Sent to the
"Order notification email" set in SC Commerce → Settings, or the site's admin email if that's left blank.

== Variable products ==

The "Variable" product type and its variations repeater (label/price/SKU/image per row) are provision, not a full
implementation — there's no attribute system (e.g. Size × Colour generating rows automatically), and the testbed
theme's product page doesn't yet render a variant picker (Frontend\ProductContent shows a placeholder note instead
of an Add to Basket form for variable products). The meta shape (`_scc_variations`, a flat array of rows) is chosen
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
  `scc_add_to_basket_button()`, `scc_the_basket()`, `scc_the_mini_basket()`, `scc_the_checkout()`, ...) — for theme
  code on this same site.
* **Shortcodes** (`[scc_product]`, `[scc_products]`, `[scc_add_to_basket]`, `[scc_basket]`, `[scc_mini_basket]`,
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

= 0.8.0 =
* Fixed product permalinks 404ing ("Page not found") after the product post type's registration changed without an
  actual deactivate/reactivate cycle to trigger WordPress's own rewrite-rules flush. Added `Setup\RewriteFlusher`,
  which self-heals this going forward by flushing once whenever the plugin's version changes — no need to manually
  resave Settings -> Permalinks after an update again.
* Each variation in the variable-product repeater can now have its own image — a small (140x79, 16:9) thumbnail
  picked via the core media library, kept small so the repeater stays scannable with several rows on screen.
  Exposed as `image` on the REST API's `variations` (`null` if a variation has none).

= 0.7.0 =
* An order stuck on "Payment Pending" for a month is now automatically moved to "Cancelled" by a daily WP-Cron job
  (`Orders\StaleOrderCleaner`) — left for an admin to trash by hand, nothing deletes the order itself. Scoped to
  Payment Pending only; a "Created" order with no gateway configured is a manual order the shop owner's already
  been emailed about, not something waiting on an external service.
* The Orders list now colour-codes each row by status — a subtle 10px left accent (dark red Cancelled, red Payment
  Failed, yellow Created/Payment Pending, light green Paid, dark green Dispatched, purple Complete).
* Added a free-text tracking code field to the Order Status box, filled in by hand once a parcel goes out. No
  carrier integration yet (so no automatic "delivered" detection) — a deliberate "manual for now".

= 0.6.0 =
* **Breaking:** removed "Buy Now" entirely (`[scc_buy_now]`, `scc_buy_now_button()`) — every purchase now goes
  through "Add to basket" then checkout, since checkout is the only point that collects the customer's name and
  delivery address, which every order needs regardless of what's being bought.
* `[scc_products]`/`scc_the_products()` gained a `layout` option — the existing card grid (default), or `"list"`
  for a compact one-row-per-product table (thumbnail, name, price, a small Add button).
* Added `[scc_mini_basket]`/`scc_the_mini_basket()` — a compact icon + item count + running total link to the full
  basket, for a header or sidebar. Stays in sync with an AJAX add-to-basket elsewhere on the page (assets/js/basket.js
  now also updates a running total, not just the count).

= 0.5.1 =
* Checkout's required fields (name, email, address line 1, town, postcode) now show a red asterisk, with a
  "* Required" key at the top of the form — a visual cue only; the fields' own `required` attribute is still what
  tells assistive tech they're mandatory.

= 0.5.0 =
* Shop owner now gets a notification email for orders that need attention — a paid order (from PayPal's IPN, not
  the customer's return-URL landing), or an order placed with no gateway configured, which will never reach a paid
  status on its own. Sent to a new "Order notification email" setting if set, otherwise the site's admin email.
* Checkout now validates name, email, delivery address line 1, town and postcode server-side (not just via the
  form's `required`/`pattern` attributes, which a direct POST can bypass) — postcode is checked against a UK
  postcode shape. Missing/invalid submissions redirect back to checkout with a specific notice instead of creating
  an incomplete order.

= 0.4.0 =
* The checkout confirmation screen's "Payment Pending" status now updates itself automatically (polls
  `GET /scc/v1/orders/{id}/status` every few seconds) instead of needing a manual page refresh once PayPal's IPN
  arrives.
* Documentation page now explains why IPN won't complete an order locally without a public tunnel, and what to do
  about it.

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
