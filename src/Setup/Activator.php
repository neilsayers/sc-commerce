<?php

namespace SCCommerce\Setup;

use SCCommerce\Settings\Settings;

final class Activator
{
    public static function activate(): void
    {
        \add_option(Settings::optionName(), Settings::defaults());

        self::createPageIfMissing('basket_page_id', 'Basket', '[scc_basket]');
        self::createPageIfMissing('checkout_page_id', 'Checkout', '[scc_checkout]');

        // Post types/taxonomies register on 'init' via Plugin::boot(), which
        // has already run by the time this fires (activation happens after
        // plugins_loaded) — flushing now picks up their rewrite rules.
        \flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        \flush_rewrite_rules();
    }

    /**
     * Creates a page containing the given shortcode and stores its ID in
     * settings, but only the first time — an admin is free to delete,
     * rename or move the shortcode to a different page afterwards
     * without this recreating it behind their back.
     */
    private static function createPageIfMissing(string $settingKey, string $title, string $shortcode): void
    {
        $settings = \get_option(Settings::optionName(), Settings::defaults());

        if (! empty($settings[$settingKey])) {
            return;
        }

        $pageId = \wp_insert_post([
            'post_title' => $title,
            'post_content' => $shortcode,
            'post_status' => 'publish',
            'post_type' => 'page',
        ], true);

        if (\is_wp_error($pageId)) {
            return;
        }

        $settings[$settingKey] = $pageId;
        \update_option(Settings::optionName(), $settings);
    }
}
