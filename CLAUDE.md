# SC Commerce

A small ecommerce plugin for WordPress: products, a basket, PayPal checkout, orders. Deliberately not a WooCommerce
competitor — for client sites that don't need that much machinery. Developed/exercised against the
`sc-commerce-testbed` site, which symlinks this folder into its `wp-content/plugins/`.

## House rules

1. **WordPress best practices, always.** Escape/sanitize output and input, use WP APIs (register_post_meta, nonces,
   capability checks, the REST API, enqueue) rather than reinventing them, i18n every user-facing string with the
   `sc-commerce` text domain. Follow the existing Hookable-feature pattern (`src/Contracts/Hookable.php`,
   composed in `src/Plugin.php::boot()`) for any new functionality rather than hooking straight into the main
   plugin file.
2. **Bump the version number before every commit that touches the plugin, then commit.** Update all three of:
   `sc-commerce.php`'s `Version:` header and `SCC_VERSION` constant, and `readme.txt`'s `Stable tag` (with a
   changelog entry). Use semver judgement — patch for fixes/small tweaks, minor for new features, major for
   breaking changes. This is a standing instruction, not a one-off: do it every time, without being asked again.
3. **No build step, no Composer dependency.** The whole point of this plugin (and its house style, shared with
   `sc-events-manager`/`sc-room-bookings`) is that it can be copied straight into `wp-content/plugins/` on any site
   and just work. Don't introduce a bundler, autoload via Composer, or add a runtime PHP/JS dependency without
   discussing it first. `composer.json` is dev-only (the test suite, see below) — it's never loaded at runtime and
   doesn't change this.
4. **Orders are a record of intent, not just successful payment.** Order::create() always runs before any gateway
   redirect (see its class doc) — don't "optimise" this by only creating an order after payment succeeds, that's a
   deliberate requirement, not an oversight.

## Tests

`tests/README.md` has the full setup, but in short: `vendor/bin/phpunit` runs the WP-independent unit tier
(`Support\Money`, `Support\CustomerValidator`) with just `composer install` — no WordPress or database needed.
`vendor/bin/phpunit -c phpunit-integration.xml.dist` runs the `Orders\Order`/`Orders\StaleOrderCleaner` tier, which
does need a real (disposable) WordPress test database — see the README for the one-time setup against this site's
own Docker stack. Pure, WP-independent logic (like `Support\Money`/`Support\CustomerValidator`) is deliberately
factored out specifically so it's unit-testable without a WP bootstrap — keep that pattern for new logic where it
fits, rather than leaving everything entangled in classes that also do WordPress I/O.

## Repo

- GitHub: `git@github.com:neilsayers/sc-commerce.git`
