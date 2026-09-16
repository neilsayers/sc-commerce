<?php

namespace SCCommerce\Basket;

use SCCommerce\Products\Product;
use SCCommerce\Support\Money;

/**
 * A logged-in visitor's basket lives in their own user meta; a guest's
 * lives entirely in a cookie (the basket itself, JSON-encoded — not a
 * token pointing at server-side storage). That keeps a guest basket
 * fully stateless: no transient/session cleanup to worry about, and it
 * survives across any number of front-end servers with no shared
 * storage. The trade-off is a ~4KB cookie size ceiling, which a
 * basket of a few dozen line items is nowhere near.
 *
 * See Basket\BasketMerger for what happens to a guest basket when
 * that visitor logs in.
 */
final class Basket
{
    private const COOKIE_NAME = 'scc_basket';
    private const USER_META_KEY = '_scc_basket';
    private const COOKIE_TTL = 30 * DAY_IN_SECONDS;

    private ?int $userId;

    /**
     * @param array<int, array{product_id: int, variation: ?int, quantity: int}> $items
     */
    private array $items;

    private function __construct(?int $userId, array $items)
    {
        $this->userId = $userId;
        $this->items = $items;
    }

    public static function forCurrentVisitor(): self
    {
        $userId = \get_current_user_id();

        return new self($userId ?: null, self::read($userId ?: null));
    }

    /**
     * @return array<int, array{product_id: int, variation: ?int, quantity: int}>
     */
    private static function read(?int $userId): array
    {
        if ($userId) {
            $items = \get_user_meta($userId, self::USER_META_KEY, true);

            return self::sanitizeItems(\is_array($items) ? $items : []);
        }

        if (! isset($_COOKIE[self::COOKIE_NAME])) {
            return [];
        }

        $decoded = \json_decode(\stripslashes($_COOKIE[self::COOKIE_NAME]), true);

        return self::sanitizeItems(\is_array($decoded) ? $decoded : []);
    }

    private static function sanitizeItems(array $items): array
    {
        $clean = [];

        foreach ($items as $item) {
            if (! isset($item['product_id'], $item['quantity'])) {
                continue;
            }

            $clean[] = [
                'product_id' => (int) $item['product_id'],
                'variation' => isset($item['variation']) && $item['variation'] !== null ? (int) $item['variation'] : null,
                'quantity' => \max(1, (int) $item['quantity']),
            ];
        }

        return $clean;
    }

    private function persist(): void
    {
        if ($this->userId) {
            \update_user_meta($this->userId, self::USER_META_KEY, $this->items);

            return;
        }

        $value = \wp_json_encode($this->items);
        \setcookie(self::COOKIE_NAME, $value, [
            'expires' => \time() + self::COOKIE_TTL,
            'path' => '/',
            'secure' => \is_ssl(),
            'httponly' => false, // read/written client-side by assets/js/basket.js for optimistic UI updates
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $value; // so the rest of this request sees the change immediately
    }

    public function add(int $productId, ?int $variation, int $quantity): void
    {
        foreach ($this->items as &$item) {
            if ($item['product_id'] === $productId && $item['variation'] === $variation) {
                $item['quantity'] += \max(1, $quantity);
                $this->persist();

                return;
            }
        }
        unset($item);

        $this->items[] = [
            'product_id' => $productId,
            'variation' => $variation,
            'quantity' => \max(1, $quantity),
        ];

        $this->persist();
    }

    public function update(int $productId, ?int $variation, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($productId, $variation);

            return;
        }

        foreach ($this->items as &$item) {
            if ($item['product_id'] === $productId && $item['variation'] === $variation) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        unset($item);

        $this->persist();
    }

    public function remove(int $productId, ?int $variation): void
    {
        $this->items = \array_values(\array_filter(
            $this->items,
            fn (array $item): bool => ! ($item['product_id'] === $productId && $item['variation'] === $variation)
        ));

        $this->persist();
    }

    public function clear(): void
    {
        $this->items = [];
        $this->persist();
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @return array<int, array{product_id: int, variation: ?int, quantity: int}>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function itemCount(): int
    {
        return \array_sum(\array_column($this->items, 'quantity'));
    }

    /**
     * Items enriched with live product data for rendering — a
     * deleted/unpublished product is silently dropped rather than
     * shown as a broken row.
     *
     * @return array<int, array{product: Product, variation: ?int, quantity: int, unit_price: float, line_total: float}>
     */
    public function enrichedItems(): array
    {
        $enriched = [];

        foreach ($this->items as $item) {
            $product = Product::get($item['product_id']);

            if (! $product) {
                continue;
            }

            $unitPrice = $product->priceFor($item['variation']);

            $enriched[] = [
                'product' => $product,
                'variation' => $item['variation'],
                'quantity' => $item['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => Money::round($unitPrice * $item['quantity']),
            ];
        }

        return $enriched;
    }

    public function total(): float
    {
        return Money::round(\array_sum(\array_column($this->enrichedItems(), 'line_total')));
    }
}
