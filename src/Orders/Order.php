<?php

namespace SCCommerce\Orders;

use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Products\Product;
use SCCommerce\Settings\Settings;
use SCCommerce\Support\Money;

/**
 * Reads/writes a scc_order post. Created once, at the moment a
 * customer expresses intent to buy (see OrderPostType's class doc),
 * then only ever transitions status/records a transaction — line
 * items are fixed at creation, matching a real checkout where the
 * order is a snapshot of the basket at that moment, not a live view
 * of it (a later price change to a product shouldn't rewrite past
 * orders).
 */
final class Order
{
    private \WP_Post $post;

    private function __construct(\WP_Post $post)
    {
        $this->post = $post;
    }

    public static function get(int $orderId): ?self
    {
        $post = \get_post($orderId);

        if (! $post || $post->post_type !== OrderPostType::POST_TYPE) {
            return null;
        }

        return new self($post);
    }

    /**
     * @param array<int, array{product_id: int, variation: ?int, quantity: int}> $items
     * @param array{name?: string, email?: string, notes?: string, address_line1?: string, address_line2?: string, address_town?: string, address_county?: string, address_postcode?: string} $customer
     */
    public static function create(array $items, array $customer = []): ?self
    {
        $currency = (new Settings())->currency();
        $lineItems = [];

        foreach ($items as $item) {
            $product = Product::get((int) $item['product_id']);

            if (! $product) {
                continue;
            }

            $quantity = \max(1, (int) $item['quantity']);
            $variationIndex = isset($item['variation']) ? (int) $item['variation'] : null;
            $unitPrice = $product->priceFor($variationIndex);
            $variation = $variationIndex !== null ? $product->variation($variationIndex) : null;

            $lineItems[] = [
                'product_id' => $product->id(),
                'variation' => $variationIndex,
                'name' => $product->name().($variation ? ' — '.$variation['label'] : ''),
                'sku' => $variation['sku'] ?? $product->sku(),
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => Money::round($unitPrice * $quantity),
            ];
        }

        if ($lineItems === []) {
            return null;
        }

        $total = Money::round(\array_sum(\array_column($lineItems, 'line_total')));

        // Two-step insert: the title needs the post's own ID, which
        // only exists once it's inserted. Setting post_name explicitly
        // on the second call too, rather than leaving WordPress to
        // slugify the first "Order (pending)" title — otherwise every
        // order keeps that same auto-generated slug, forcing WordPress
        // to keep appending -2/-3/... to keep it unique.
        $postId = \wp_insert_post([
            'post_type' => OrderPostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Order (pending)',
        ], true);

        if (\is_wp_error($postId)) {
            return null;
        }

        \wp_update_post([
            'ID' => $postId,
            'post_title' => \sprintf('Order #%d', $postId),
            'post_name' => \sprintf('order-%d', $postId),
        ]);

        \update_post_meta($postId, OrderPostType::META_STATUS, OrderPostType::STATUS_CREATED);
        \update_post_meta($postId, OrderPostType::META_STATUS_CHANGED_AT, \time());
        \update_post_meta($postId, OrderPostType::META_LINE_ITEMS, $lineItems);
        \update_post_meta($postId, OrderPostType::META_CURRENCY, $currency);
        \update_post_meta($postId, OrderPostType::META_TOTAL, $total);
        \update_post_meta($postId, OrderPostType::META_CUSTOMER_NAME, \sanitize_text_field($customer['name'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_CUSTOMER_EMAIL, \sanitize_email($customer['email'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_CUSTOMER_NOTES, \sanitize_textarea_field($customer['notes'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_ADDRESS_LINE1, \sanitize_text_field($customer['address_line1'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_ADDRESS_LINE2, \sanitize_text_field($customer['address_line2'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_ADDRESS_TOWN, \sanitize_text_field($customer['address_town'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_ADDRESS_COUNTY, \sanitize_text_field($customer['address_county'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_ADDRESS_POSTCODE, \sanitize_text_field($customer['address_postcode'] ?? ''));
        \update_post_meta($postId, OrderPostType::META_ADDRESS_COUNTRY, 'GB');

        $order = self::get($postId);

        if ($order) {
            /**
             * Fires right after an order is created (status "created"),
             * before any redirect to a payment gateway — the earliest
             * point a site can hook in, e.g. to log intent-to-buy
             * analytics regardless of what happens next.
             */
            \do_action('scc_order_created', $order);
        }

        return $order;
    }

    public function id(): int
    {
        return $this->post->ID;
    }

    public function status(): string
    {
        $status = \get_post_meta($this->post->ID, OrderPostType::META_STATUS, true);

        return $status ?: OrderPostType::STATUS_CREATED;
    }

    /**
     * When the current status was set — used by Orders\StaleOrderCleaner
     * to tell "just went to Payment Pending" from "been there a month".
     * Falls back to the post's own creation time for an order that
     * predates META_STATUS_CHANGED_AT existing, rather than 0 (which
     * would make every pre-existing order look infinitely stale).
     */
    public function statusChangedAt(): int
    {
        $timestamp = \get_post_meta($this->post->ID, OrderPostType::META_STATUS_CHANGED_AT, true);

        return $timestamp !== '' ? (int) $timestamp : (int) \get_post_time('U', true, $this->post);
    }

    public function setStatus(string $status): void
    {
        if (! isset(OrderPostType::STATUSES[$status])) {
            return;
        }

        $previous = $this->status();

        if ($previous === $status) {
            return;
        }

        \update_post_meta($this->post->ID, OrderPostType::META_STATUS, $status);
        \update_post_meta($this->post->ID, OrderPostType::META_STATUS_CHANGED_AT, \time());

        \do_action('scc_order_status_changed', $this, $status, $previous);
    }

    /**
     * @return array<int, array{product_id: int, variation: ?int, name: string, sku: string, unit_price: float, quantity: int, line_total: float}>
     */
    public function lineItems(): array
    {
        $items = \get_post_meta($this->post->ID, OrderPostType::META_LINE_ITEMS, true);

        return \is_array($items) ? $items : [];
    }

    public function total(): float
    {
        return (float) \get_post_meta($this->post->ID, OrderPostType::META_TOTAL, true);
    }

    public function currency(): string
    {
        $currency = \get_post_meta($this->post->ID, OrderPostType::META_CURRENCY, true);

        return $currency ?: (new Settings())->currency();
    }

    public function formattedTotal(): string
    {
        return Money::format($this->total(), $this->currency());
    }

    public function customerName(): string
    {
        return (string) \get_post_meta($this->post->ID, OrderPostType::META_CUSTOMER_NAME, true);
    }

    public function customerEmail(): string
    {
        return (string) \get_post_meta($this->post->ID, OrderPostType::META_CUSTOMER_EMAIL, true);
    }

    public function customerNotes(): string
    {
        return (string) \get_post_meta($this->post->ID, OrderPostType::META_CUSTOMER_NOTES, true);
    }

    /**
     * @return array{line1: string, line2: string, town: string, county: string, postcode: string, country: string}
     */
    public function address(): array
    {
        return [
            'line1' => (string) \get_post_meta($this->post->ID, OrderPostType::META_ADDRESS_LINE1, true),
            'line2' => (string) \get_post_meta($this->post->ID, OrderPostType::META_ADDRESS_LINE2, true),
            'town' => (string) \get_post_meta($this->post->ID, OrderPostType::META_ADDRESS_TOWN, true),
            'county' => (string) \get_post_meta($this->post->ID, OrderPostType::META_ADDRESS_COUNTY, true),
            'postcode' => (string) \get_post_meta($this->post->ID, OrderPostType::META_ADDRESS_POSTCODE, true),
            'country' => (string) \get_post_meta($this->post->ID, OrderPostType::META_ADDRESS_COUNTRY, true),
        ];
    }

    /**
     * The address as printable lines, blank fields (address_line2/
     * county are both optional on the checkout form) simply omitted
     * rather than left as empty lines.
     *
     * @return array<int, string>
     */
    public function addressLines(): array
    {
        $address = $this->address();

        return \array_values(\array_filter([
            $address['line1'],
            $address['line2'],
            $address['town'],
            $address['county'],
            $address['postcode'],
        ], static fn (string $line): bool => $line !== ''));
    }

    public function recordTransaction(string $gateway, string $transactionId): void
    {
        \update_post_meta($this->post->ID, OrderPostType::META_GATEWAY, \sanitize_key($gateway));
        \update_post_meta($this->post->ID, OrderPostType::META_TRANSACTION_ID, \sanitize_text_field($transactionId));
    }

    public function gateway(): string
    {
        return (string) \get_post_meta($this->post->ID, OrderPostType::META_GATEWAY, true);
    }

    public function transactionId(): string
    {
        return (string) \get_post_meta($this->post->ID, OrderPostType::META_TRANSACTION_ID, true);
    }

    /**
     * A parcel tracking code — filled in by hand by whoever ships the
     * order (MetaBoxes\OrderDetailsMetaBox), there's no carrier
     * integration setting this automatically yet.
     */
    public function trackingCode(): string
    {
        return (string) \get_post_meta($this->post->ID, OrderPostType::META_TRACKING_CODE, true);
    }

    public function setTrackingCode(string $trackingCode): void
    {
        \update_post_meta($this->post->ID, OrderPostType::META_TRACKING_CODE, \sanitize_text_field($trackingCode));
    }
}
