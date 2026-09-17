<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\ProductPostType;
use SCCommerce\Products\Product;
use SCCommerce\Settings\Settings;

/**
 * Product structured data (schema.org/Product as JSON-LD) on a
 * product's own single-view page — lets search engines show
 * price/availability directly in results with no work from the shop
 * owner, since it's built from the same Products\Product data the
 * page itself already renders, never a second source of truth. Only
 * the canonical singular view gets a block, not the products grid/list
 * — schema.org's own guidance is one Product block per page describing
 * that page's subject, not a listing of several.
 */
final class ProductSchema implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('wp_head', [$this, 'render']);
    }

    public function render(): void
    {
        if (! \is_singular(ProductPostType::POST_TYPE)) {
            return;
        }

        $product = Product::get(\get_the_ID());

        if (! $product) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name(),
            'url' => $product->permalink(),
            'description' => \wp_strip_all_tags((string) \get_the_excerpt($product->id())),
        ];

        $imageUrl = \get_the_post_thumbnail_url($product->id(), 'large');

        if ($imageUrl) {
            $schema['image'] = $imageUrl;
        }

        if ($product->sku() !== '') {
            $schema['sku'] = $product->sku();
        }

        $schema['offers'] = $this->offers($product);

        // Slashes deliberately left escaped (json_encode's default) —
        // that's what stops a stray "</script>" inside a product's own
        // name/description from breaking out of this tag.
        echo '<script type="application/ld+json">'.\wp_json_encode($schema, \JSON_UNESCAPED_UNICODE)."</script>\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function offers(Product $product): array
    {
        $currency = $this->settings->currency();

        if (! $product->isVariable()) {
            return [
                '@type' => 'Offer',
                'url' => $product->permalink(),
                'priceCurrency' => $currency,
                'price' => \number_format($product->price(), 2, '.', ''),
                // No stock tracking in this plugin (see Product's own
                // class doc) — always reported in stock rather than
                // guessing, since there's no signal here to say
                // otherwise.
                'availability' => 'https://schema.org/InStock',
            ];
        }

        $prices = \array_column($product->variations(), 'price');

        if ($prices === []) {
            $prices = [0.0];
        }

        return [
            '@type' => 'AggregateOffer',
            'url' => $product->permalink(),
            'priceCurrency' => $currency,
            'lowPrice' => \number_format(\min($prices), 2, '.', ''),
            'highPrice' => \number_format(\max($prices), 2, '.', ''),
            'offerCount' => \count($prices),
            'availability' => 'https://schema.org/InStock',
        ];
    }
}
