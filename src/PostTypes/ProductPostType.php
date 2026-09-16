<?php

namespace SCCommerce\PostTypes;

use SCCommerce\Contracts\Hookable;

/**
 * The "product" post type. Kept deliberately thin at this stage —
 * price/SKU/type plus a JSON-shaped variations array for provision,
 * not implementation, of variable products (see class doc on
 * MetaBoxes\ProductDetailsMetaBox for what "provision" means here).
 * Anything else a future site needs (weight, stock, downloads, ...)
 * is more postmeta on this same post type, not a new one.
 */
final class ProductPostType implements Hookable
{
    public const POST_TYPE = 'scc_product';

    public const META_PRICE = '_scc_price';
    public const META_SKU = '_scc_sku';
    public const META_TYPE = '_scc_type';
    public const META_VARIATIONS = '_scc_variations';

    public const TYPE_SIMPLE = 'simple';
    public const TYPE_VARIABLE = 'variable';

    public const TYPES = [
        self::TYPE_SIMPLE => 'Simple',
        self::TYPE_VARIABLE => 'Variable',
    ];

    public function register(): void
    {
        \add_action('init', [$this, 'registerPostType']);
        \add_action('init', [$this, 'registerMeta']);
    }

    public function registerPostType(): void
    {
        \register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Products',
                'singular_name' => 'Product',
                'add_new_item' => 'Add New Product',
                'edit_item' => 'Edit Product',
                'view_item' => 'View Product',
                'search_items' => 'Search Products',
                'not_found' => 'No products found',
                'not_found_in_trash' => 'No products found in Trash',
                'all_items' => 'Products',
                'menu_name' => 'Products',
                'name_admin_bar' => 'Product',
            ],
            'public' => true,
            'has_archive' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-cart',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
            'rewrite' => ['slug' => 'products'],
            'capability_type' => 'post',
        ]);
    }

    /**
     * Exposed via register_post_meta (rather than left as plain
     * update_post_meta calls) so price/SKU are readable through
     * show_in_rest without a custom REST field — useful for any future
     * block/editor UI, and for other plugins wanting to read a
     * product's price without needing to know this plugin's internals.
     */
    public function registerMeta(): void
    {
        \register_post_meta(self::POST_TYPE, self::META_PRICE, [
            'type' => 'number',
            'single' => true,
            'default' => 0,
            'show_in_rest' => true,
            'auth_callback' => fn (): bool => \current_user_can('edit_posts'),
        ]);

        \register_post_meta(self::POST_TYPE, self::META_SKU, [
            'type' => 'string',
            'single' => true,
            'default' => '',
            'show_in_rest' => true,
            'auth_callback' => fn (): bool => \current_user_can('edit_posts'),
        ]);

        \register_post_meta(self::POST_TYPE, self::META_TYPE, [
            'type' => 'string',
            'single' => true,
            'default' => self::TYPE_SIMPLE,
            'show_in_rest' => true,
            'auth_callback' => fn (): bool => \current_user_can('edit_posts'),
        ]);

        \register_post_meta(self::POST_TYPE, self::META_VARIATIONS, [
            'type' => 'array',
            'single' => true,
            'default' => [],
            'show_in_rest' => [
                'schema' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'label' => ['type' => 'string'],
                            'price' => ['type' => 'number'],
                            'sku' => ['type' => 'string'],
                            'image_id' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'auth_callback' => fn (): bool => \current_user_can('edit_posts'),
        ]);
    }
}
