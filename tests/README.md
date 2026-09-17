# SC Commerce test suite

Dev-only tooling (`composer.json`'s `require-dev`) — none of this ships with the
plugin or is needed to run it; see the root `CLAUDE.md` for why the plugin
itself stays Composer-free.

Two tiers:

## Unit — `Support\Money`, `Support\CustomerValidator`

Plain PHP logic with zero WordPress dependency, so this tier needs nothing but
PHP and Composer — no database, no WordPress install, runs anywhere:

```sh
composer install
vendor/bin/phpunit
```

## Integration — `Orders\Order`, `Orders\StaleOrderCleaner`

These touch real posts/postmeta, so they run against an actual (disposable)
WordPress database via `WP_UnitTestCase` — each test runs in a transaction
that's rolled back afterward, so nothing here touches the testbed's real
`wordpress` database or its content.

One-time setup, run from inside the `wp-cli` container (`docker compose exec
wp-cli bash`, from the testbed's own directory) since that's what already has
a real WordPress install on disk (`/var/www/html`, the same volume the
`wordpress` service serves) and network access to the `db` service:

1. **Composer.** The official `wordpress:cli` image doesn't bundle Composer.
   Simplest one-off: from the plugin's directory on the host,
   `docker run --rm -v "$PWD":/app composer:2 install` — populates `vendor/`
   on the shared volume, so the `wp-cli` container sees it too.
2. **Create the test database** (once):
   ```sh
   docker compose exec db mysql -uroot -proot_password \
     -e "CREATE DATABASE IF NOT EXISTS wordpress_test"
   ```
3. **Run it:**
   ```sh
   docker compose exec wp-cli \
     vendor/bin/phpunit -c phpunit-integration.xml.dist
   ```

`tests/Integration/wp-tests-config.php` defaults to exactly this setup
(`root`/`root_password`/host `db`/database `wordpress_test`, `ABSPATH` at
`/var/www/html`) — override any of it with `WP_TESTS_DB_NAME`,
`WP_TESTS_DB_USER`, `WP_TESTS_DB_PASSWORD`, `WP_TESTS_DB_HOST` or
`WP_TESTS_ABSPATH` env vars if running somewhere else.

**Note on this file:** written from established WordPress-plugin-testing
conventions (`wp-phpunit/wp-phpunit` + `WP_UnitTestCase`), but not actually
executed against a live environment while writing it — no PHP/Composer/Docker
CLI was reachable from that session. The unit tier's plain-PHP logic is safe
either way; if the integration tier's bootstrap needs a tweak the first time
it's actually run, the error will point at what (usually a DB connection or
an `ABSPATH` mismatch).
