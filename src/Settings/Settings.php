<?php

namespace SCCommerce\Settings;

/**
 * Everything site-specific lives in one 'scc_settings' option rather
 * than scattered add_option() calls — one row to read, one to export/
 * import if a site's config ever needs copying elsewhere.
 */
final class Settings
{
    private const OPTION = 'scc_settings';

    private const DEFAULTS = [
        'currency' => 'GBP',
        'notification_email' => '',
        'paypal_email' => '',
        'paypal_sandbox' => true,
        'basket_page_id' => 0,
        'checkout_page_id' => 0,
    ];

    private array $values;

    public function __construct()
    {
        $this->values = \wp_parse_args(\get_option(self::OPTION, []), self::DEFAULTS);
    }

    public function currency(): string
    {
        return (string) $this->values['currency'];
    }

    /**
     * Blank unless the shop owner explicitly set one — callers wanting
     * "shop owner's address, falling back to the site admin" (see
     * Notifications\OrderNotifier) apply that fallback themselves
     * rather than this class reaching into unrelated WP options.
     */
    public function notificationEmail(): string
    {
        return (string) $this->values['notification_email'];
    }

    public function paypalEmail(): string
    {
        return (string) $this->values['paypal_email'];
    }

    public function paypalSandbox(): bool
    {
        return (bool) $this->values['paypal_sandbox'];
    }

    public function basketPageId(): int
    {
        return (int) $this->values['basket_page_id'];
    }

    public function checkoutPageId(): int
    {
        return (int) $this->values['checkout_page_id'];
    }

    public function isPaypalConfigured(): bool
    {
        return $this->paypalEmail() !== '';
    }

    public static function optionName(): string
    {
        return self::OPTION;
    }

    public static function defaults(): array
    {
        return self::DEFAULTS;
    }
}
