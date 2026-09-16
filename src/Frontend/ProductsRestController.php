<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Products\Product;
use SCCommerce\Products\ProductQuery;
use SCCommerce\Settings\Settings;
use SCCommerce\Taxonomies\ProductTypeTaxonomy;

/**
 * GET /wp-json/scc/v1/products and /products/{id} — the plugin's
 * public, versioned data contract for anything outside this site's
 * own PHP (a decoupled front end, another site, a build step, ...).
 * WordPress's own core REST controller already exposes scc_product
 * (show_in_rest is true on the post type, and its meta is registered
 * with show_in_rest — see PostTypes\ProductPostType::registerMeta())
 * at /wp/v2/scc_product, but that's the full raw post object; this is
 * a purpose-shaped response (computed display price, expanded product
 * types, no post-object noise) built from the same Products\Product
 * class the shortcodes and template functions use — not a second
 * source of truth for what a product's price or type actually is.
 *
 * "v1" is a promise: existing fields in the response won't be renamed
 * or removed within v1. A field can be added; a genuinely breaking
 * change gets a v2 route instead. See SC Commerce -> Documentation.
 */
final class ProductsRestController implements Hookable
{
    private const NAMESPACE = 'scc/v1';

    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route(self::NAMESPACE, '/products', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'index'],
            // Read-only, and every field it returns is already public on
            // a published product's own front-end page — nothing here is
            // gated behind a capability check.
            'permission_callback' => '__return_true',
            'args' => [
                'search' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description' => 'Free-text search against the product title/content.',
                ],
                'product_type' => [
                    'type' => 'string',
                    'default' => '',
                    // A closure, not the bare 'sanitize_title' string:
                    // WP calls a sanitize_callback as
                    // ($value, $request, $param), and sanitize_title()
                    // happens to accept exactly 3 params too — its
                    // 2nd, $fallback_title, silently receives the
                    // WP_REST_Request object whenever $value is empty
                    // (e.g. this param left off the query string
                    // entirely), which then blows up wherever the
                    // "sanitized" value is later treated as a string.
                    'sanitize_callback' => static fn ($value): string => \is_numeric($value) ? (string) \absint($value) : \sanitize_title((string) $value),
                    'description' => 'A Product Types taxonomy term ID or slug to filter by.',
                ],
                'exclude' => [
                    'type' => 'string',
                    'default' => '',
                    'sanitize_callback' => static fn ($value): string => (string) $value,
                    'description' => 'Comma-separated product IDs to leave out, e.g. "2,4,5".',
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 20,
                    'sanitize_callback' => 'absint',
                    'description' => 'Maximum products to return, capped at 100. 0 means no limit.',
                ],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/products/(?P<id>\d+)', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $exclude = \array_filter(\array_map(
            'absint',
            \explode(',', (string) $request->get_param('exclude'))
        ));

        $products = \array_values(\array_filter(\array_map(
            fn (Product $product): ?array => $this->toResponse($product),
            ProductQuery::get([
                'exclude' => $exclude,
                'product_type' => (string) $request->get_param('product_type'),
                'search' => (string) $request->get_param('search'),
                'limit' => (int) $request->get_param('limit'),
            ])
        )));

        return new \WP_REST_Response([
            'products' => $products,
            'total' => \count($products),
        ]);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $product = Product::get((int) $request->get_param('id'));

        if (! $product) {
            return new \WP_REST_Response(['message' => 'Product not found.'], 404);
        }

        return new \WP_REST_Response($this->toResponse($product));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function toResponse(?Product $product): ?array
    {
        if (! $product) {
            return null;
        }

        $currency = (new Settings())->currency();
        $postId = $product->id();

        $variations = \array_map(
            static fn (array $variation): array => [
                'label' => $variation['label'],
                'sku' => $variation['sku'],
                'price' => $variation['price'],
                'price_formatted' => \scc_price($variation['price'], $currency),
                'image' => $variation['image_id'] ? (\wp_get_attachment_image_url($variation['image_id'], 'medium') ?: null) : null,
            ],
            $product->variations()
        );

        $productTypes = \wp_get_post_terms($postId, ProductTypeTaxonomy::TAXONOMY);
        $productTypes = \is_array($productTypes) ? $productTypes : [];

        return [
            'id' => $postId,
            'name' => $product->name(),
            'permalink' => $product->permalink(),
            'excerpt' => \get_the_excerpt($postId),
            'image' => \get_the_post_thumbnail_url($postId, 'medium') ?: null,
            'sku' => $product->sku(),
            'type' => $product->type(),
            'price' => $product->isVariable() ? null : $product->price(),
            'price_formatted' => $product->displayPrice($currency),
            'variations' => $variations,
            'product_types' => \array_map(
                static fn (\WP_Term $term): array => ['id' => $term->term_id, 'name' => $term->name, 'slug' => $term->slug],
                $productTypes
            ),
        ];
    }
}
