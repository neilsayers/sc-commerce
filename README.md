# SC Commerce

A deliberately small ecommerce system for WordPress — products, a basket, PayPal checkout and orders — for sites that don't need WooCommerce's weight.

No build step, no Composer install: copy or symlink this folder into `wp-content/plugins/` and activate. See [readme.txt](readme.txt) for the full feature description (this is the file WordPress.org-style plugin readmes use), including why PayPal Standard was chosen for v1 and what a variable product currently does and doesn't do.

## Developing

This plugin is developed against the [sc-commerce-testbed](https://github.com/neilsayers) local site, which symlinks it in at `wp-content/plugins/sc-commerce`. There's no separate dev environment here — run the testbed's `docker compose up -d` and work against that.

## Status

Early scaffold (v0.2.0) — see readme.txt's changelog.
