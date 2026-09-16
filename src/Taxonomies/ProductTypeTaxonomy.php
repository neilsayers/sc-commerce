<?php

namespace SCCommerce\Taxonomies;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\ProductPostType;

/**
 * "Product Types" — a hierarchical taxonomy for grouping products
 * (e.g. "T-Shirts", "Mugs", "Digital Downloads"), analogous to
 * WooCommerce's product_cat. Deliberately not named the same as
 * ProductPostType::META_TYPE ("simple"/"variable", stored in
 * postmeta) — that's a structural distinction about how a product's
 * pricing works, this is an editorial one about what it is, and the
 * two are independent (a variable product can belong to any type).
 */
final class ProductTypeTaxonomy implements Hookable
{
    public const TAXONOMY = 'scc_product_type';

    public function register(): void
    {
        \add_action('init', [$this, 'registerTaxonomy']);
    }

    public function registerTaxonomy(): void
    {
        \register_taxonomy(self::TAXONOMY, [ProductPostType::POST_TYPE], [
            'labels' => [
                'name' => 'Product Types',
                'singular_name' => 'Product Type',
                'search_items' => 'Search Product Types',
                'all_items' => 'All Product Types',
                'edit_item' => 'Edit Product Type',
                'add_new_item' => 'Add New Product Type',
                'menu_name' => 'Product Types',
            ],
            'hierarchical' => true,
            'public' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'product-type'],
        ]);
    }
}
