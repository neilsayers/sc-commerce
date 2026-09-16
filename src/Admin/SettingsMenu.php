<?php

namespace SCCommerce\Admin;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Settings\Settings;

/**
 * A top-level "SC Commerce" menu, separate from the Products post
 * type's own menu — settings aren't a kind of product, so they don't
 * belong nested under Products -> Settings. Sits near the bottom of
 * the admin menu (position 94) alongside this site's other sc-*
 * plugins (Event Types 90, Room Bookings 91, Maps 92, SEO 93).
 *
 * Two settings sections rather than one flat list: "General"
 * (currency, order notification email) and "PayPal" (business email,
 * sandbox mode) — the section title is what actually draws the visual
 * divider between them (WordPress's own Settings API renders each
 * add_settings_section() as its own <h2> + fields table), not a
 * custom <hr>.
 */
final class SettingsMenu implements Hookable
{
    public const PAGE_SLUG = 'scc-settings';

    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addMenu']);
        \add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenu(): void
    {
        \add_menu_page(
            'SC Commerce',
            'SC Commerce',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            'dashicons-store',
            94
        );

        \add_submenu_page(
            self::PAGE_SLUG,
            'SC Commerce Settings',
            'Settings',
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render']
        );
    }

    public function registerSettings(): void
    {
        \register_setting('scc_settings_group', Settings::optionName(), [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
            'default' => Settings::defaults(),
        ]);

        \add_settings_section('scc_general', 'General', '__return_false', self::PAGE_SLUG);
        $this->addField('currency', 'Currency code', 'scc_general', 'text');
        $this->addField(
            'notification_email',
            'Order notification email',
            'scc_general',
            'email',
            'Where new-order emails are sent. Leave blank to use this site\'s admin email instead.'
        );

        \add_settings_section(
            'scc_paypal',
            'PayPal',
            function (): void {
                // do_settings_sections() calls this and prints nothing
                // itself — it expects the callback to echo directly,
                // not return a string.
                echo '<p>PayPal Standard — no API keys needed, just a business email address. See SC Commerce -> Documentation for other payment options.</p>';
            },
            self::PAGE_SLUG
        );
        $this->addField('paypal_email', 'Business email', 'scc_paypal', 'email');
        $this->addField('paypal_sandbox', 'Use sandbox mode', 'scc_paypal', 'checkbox');
    }

    private function addField(string $key, string $label, string $section, string $type, string $description = ''): void
    {
        \add_settings_field($key, $label, function () use ($key, $type, $description): void {
            $settings = \get_option(Settings::optionName(), Settings::defaults());
            $value = $settings[$key] ?? '';
            $name = Settings::optionName()."[{$key}]";

            if ($type === 'checkbox') {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s>',
                    \esc_attr($name),
                    \checked((bool) $value, true, false)
                );

                return;
            }

            printf(
                '<input type="%s" name="%s" value="%s" class="regular-text">',
                \esc_attr($type),
                \esc_attr($name),
                \esc_attr((string) $value)
            );

            if ($description !== '') {
                printf('<p class="description">%s</p>', \esc_html($description));
            }
        }, self::PAGE_SLUG, $section);
    }

    /**
     * basket_page_id/checkout_page_id have no fields on this form
     * (Setup\Activator sets them once, on activation) — carried
     * forward from the existing option rather than reset to 0 on
     * every save, since register_setting() replaces the whole option.
     */
    public function sanitize(array $input): array
    {
        $existing = \get_option(Settings::optionName(), Settings::defaults());

        return [
            'currency' => isset($input['currency']) ? \strtoupper(\sanitize_text_field($input['currency'])) : 'GBP',
            'notification_email' => isset($input['notification_email']) ? \sanitize_email($input['notification_email']) : '',
            'paypal_email' => isset($input['paypal_email']) ? \sanitize_email($input['paypal_email']) : '',
            'paypal_sandbox' => ! empty($input['paypal_sandbox']),
            'basket_page_id' => (int) $existing['basket_page_id'],
            'checkout_page_id' => (int) $existing['checkout_page_id'],
        ];
    }

    public function render(): void
    {
        if (! \current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>SC Commerce Settings</h1>
            <form method="post" action="options.php">
                <?php
                \settings_fields('scc_settings_group');
                \do_settings_sections(self::PAGE_SLUG);
                \submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
