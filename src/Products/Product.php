<?php

namespace SCCommerce\Products;

use SCCommerce\PostTypes\ProductPostType;
use SCCommerce\Support\Money;

/**
 * Read-only view over a scc_product post's meta. Nothing here writes
 * — MetaBoxes\ProductDetailsMetaBox owns saving, this class just gives
 * the basket/checkout/templates a convenient way to read what it saved.
 */
final class Product
{
    private \WP_Post $post;

    private function __construct(\WP_Post $post)
    {
        $this->post = $post;
    }

    public static function get(int $productId): ?self
    {
        $post = \get_post($productId);

        if (! $post || $post->post_type !== ProductPostType::POST_TYPE) {
            return null;
        }

        return new self($post);
    }

    public function id(): int
    {
        return $this->post->ID;
    }

    public function name(): string
    {
        return \get_the_title($this->post);
    }

    public function permalink(): string
    {
        return (string) \get_permalink($this->post);
    }

    public function sku(): string
    {
        return (string) \get_post_meta($this->post->ID, ProductPostType::META_SKU, true);
    }

    public function type(): string
    {
        $type = \get_post_meta($this->post->ID, ProductPostType::META_TYPE, true);

        return $type ?: ProductPostType::TYPE_SIMPLE;
    }

    public function isVariable(): bool
    {
        return $this->type() === ProductPostType::TYPE_VARIABLE;
    }

    /**
     * A simple product's own price. For a variable product, use
     * variations()/variation() instead — this plugin doesn't invent a
     * fallback "base price" for variable products, since nothing
     * guarantees one of its variations is priced the same.
     */
    public function price(): float
    {
        return (float) \get_post_meta($this->post->ID, ProductPostType::META_PRICE, true);
    }

    /**
     * @return array<int, array{label: string, price: float, sku: string, image_id: int, description: string}>
     */
    public function variations(): array
    {
        $variations = \get_post_meta($this->post->ID, ProductPostType::META_VARIATIONS, true);

        if (! \is_array($variations)) {
            return [];
        }

        return \array_values(\array_map(
            fn (array $variation): array => [
                'label' => (string) ($variation['label'] ?? ''),
                'price' => (float) ($variation['price'] ?? 0),
                'sku' => (string) ($variation['sku'] ?? ''),
                'image_id' => (int) ($variation['image_id'] ?? 0),
                'description' => (string) ($variation['description'] ?? ''),
            ],
            $variations
        ));
    }

    public function variation(int $index): ?array
    {
        return $this->variations()[$index] ?? null;
    }

    /**
     * The price to charge for one unit of this product, given an
     * optional variation index — what Basket/Basket and Orders/Order
     * actually call, rather than each having to know the
     * simple/variable distinction themselves.
     */
    public function priceFor(?int $variationIndex): float
    {
        if ($variationIndex === null) {
            return $this->price();
        }

        return $this->variation($variationIndex)['price'] ?? 0.0;
    }

    /**
     * A single price for a simple product, or "From £x" for a
     * variable one (the lowest-priced variation) — display only, the
     * actual checkout price always comes from priceFor().
     */
    public function displayPrice(string $currency): string
    {
        if (! $this->isVariable()) {
            return Money::format($this->price(), $currency);
        }

        $prices = \array_column($this->variations(), 'price');

        if ($prices === []) {
            return Money::format(0, $currency);
        }

        return 'From '.Money::format(\min($prices), $currency);
    }
}
