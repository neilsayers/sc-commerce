<?php

namespace SCCommerce\Products;

use SCCommerce\PostTypes\ProductPostType;
use SCCommerce\Taxonomies\ProductTypeTaxonomy;

/**
 * The one place "which published products match these filters" is
 * decided — scc_the_products()/[scc_products] and
 * Frontend\ProductsRestController's list endpoint both call this
 * rather than each building their own WP_Query args, so a listing
 * shortcode and the REST API can never quietly disagree about which
 * products come back for the same filters.
 */
final class ProductQuery
{
    /**
     * @param array{exclude?: array<int, int>, product_type?: int|string, search?: string, limit?: int} $args
     * @return array<int, Product>
     */
    public static function get(array $args = []): array
    {
        $args = \wp_parse_args($args, [
            'exclude' => [],
            'product_type' => '',
            'search' => '',
            'limit' => 20,
        ]);

        $limit = (int) $args['limit'];
        $limit = $limit === 0 ? -1 : \min(100, $limit);

        $query = [
            'post_type' => ProductPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            's' => (string) $args['search'],
        ];

        if ($args['exclude'] !== []) {
            $query['post__not_in'] = \array_map('intval', (array) $args['exclude']);
        }

        $productType = $args['product_type'];

        if ($productType !== '' && $productType !== null) {
            $query['tax_query'] = [[
                'taxonomy' => ProductTypeTaxonomy::TAXONOMY,
                // A shortcode/query-string value arrives as a string
                // either way ("6" or "mugs") — is_numeric tells a
                // term ID apart from a slug, since a slug could
                // coincidentally look like one otherwise.
                'field' => \is_numeric($productType) ? 'term_id' : 'slug',
                'terms' => $productType,
            ]];
        }

        $posts = \get_posts($query);

        return \array_values(\array_filter(\array_map(
            static fn (\WP_Post $post): ?Product => Product::get($post->ID),
            $posts
        )));
    }
}
