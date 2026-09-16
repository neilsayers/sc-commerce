<?php

namespace SCCommerce\Admin;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Settings\Settings;

final class SettingsPage implements Hookable
{
    public function register(): void
    {
        \add_action('admin_menu', [$this, 'addMenu']);
        \add_action('admin_init', [$this, 'registerSettings']);
    }

    public function addMenu(): void
    {
        \add_submenu_page(
            'edit.php?post_type=scc_product',
            'SC Commerce Settings',
            'Settings',
            'manage_options',
            'scc-settings',
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

        \add_settings_section('scc_general', 'General', '__return_false', 'scc-settings');

        $this->addField('currency', 'Currency code', 'scc_general', 'text');
        $this->addField('paypal_email', 'PayPal business email', 'scc_general', 'email');
        $this->addField('paypal_sandbox', 'Use PayPal sandbox', 'scc_general', 'checkbox');
    }

    private function addField(string $key, string $label, string $section, string $type): void
    {
        \add_settings_field($key, $label, function () use ($key, $type): void {
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
        }, 'scc-settings', $section);
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
                \do_settings_sections('scc-settings');
                \submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
