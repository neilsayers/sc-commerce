<?php

namespace SCCommerce\Tests\Integration;

use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\PostTypes\ProductPostType;

final class OrderTest extends \WP_UnitTestCase
{
    private function createSimpleProduct(float $price, string $sku = 'TEST-SKU'): int
    {
        return self::factory()->post->create([
            'post_type' => ProductPostType::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => 'Test product',
            'meta_input' => [
                ProductPostType::META_PRICE => $price,
                ProductPostType::META_SKU => $sku,
                ProductPostType::META_TYPE => ProductPostType::TYPE_SIMPLE,
            ],
        ]);
    }

    public function test_create_builds_line_items_and_total_from_real_products(): void
    {
        $productId = $this->createSimpleProduct(9.99, 'MUG-001');

        $order = Order::create(
            [['product_id' => $productId, 'variation' => null, 'quantity' => 3]],
            ['name' => 'Jane Smith', 'email' => 'jane@example.com']
        );

        $this->assertNotNull($order);
        $this->assertSame(29.97, $order->total());
        $this->assertSame('Jane Smith', $order->customerName());
        $this->assertSame('jane@example.com', $order->customerEmail());

        $lineItems = $order->lineItems();
        $this->assertCount(1, $lineItems);
        $this->assertSame($productId, $lineItems[0]['product_id']);
        $this->assertSame('MUG-001', $lineItems[0]['sku']);
        $this->assertSame(3, $lineItems[0]['quantity']);
        $this->assertSame(29.97, $lineItems[0]['line_total']);
    }

    public function test_create_skips_line_items_for_a_product_that_no_longer_exists(): void
    {
        $productId = $this->createSimpleProduct(10.0);
        $deletedId = $productId + 1000; // deliberately never created

        $order = Order::create([
            ['product_id' => $productId, 'variation' => null, 'quantity' => 1],
            ['product_id' => $deletedId, 'variation' => null, 'quantity' => 1],
        ]);

        $this->assertNotNull($order);
        $this->assertCount(1, $order->lineItems());
        $this->assertSame(10.0, $order->total());
    }

    public function test_create_returns_null_when_no_items_resolve_to_a_real_product(): void
    {
        $order = Order::create([['product_id' => 999999, 'variation' => null, 'quantity' => 1]]);

        $this->assertNull($order);
    }

    public function test_create_sets_initial_status_to_created(): void
    {
        $productId = $this->createSimpleProduct(5.0);

        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        $this->assertSame(OrderPostType::STATUS_CREATED, $order->status());
    }

    public function test_create_fires_scc_order_created(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $firedWith = null;

        add_action('scc_order_created', function (Order $order) use (&$firedWith): void {
            $firedWith = $order;
        });

        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        $this->assertNotNull($firedWith);
        $this->assertSame($order->id(), $firedWith->id());
    }

    public function test_set_status_updates_status_and_fires_scc_order_status_changed(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        $seen = [];
        add_action('scc_order_status_changed', function (Order $changedOrder, string $newStatus, string $previousStatus) use (&$seen): void {
            $seen = [$newStatus, $previousStatus];
        }, 10, 3);

        $order->setStatus(OrderPostType::STATUS_PAID);

        $this->assertSame(OrderPostType::STATUS_PAID, $order->status());
        $this->assertSame([OrderPostType::STATUS_PAID, OrderPostType::STATUS_CREATED], $seen);
    }

    public function test_set_status_is_a_no_op_for_an_unknown_status(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        $order->setStatus('not_a_real_status');

        $this->assertSame(OrderPostType::STATUS_CREATED, $order->status());
    }

    public function test_set_status_does_not_fire_the_hook_when_status_is_unchanged(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        $fired = false;
        add_action('scc_order_status_changed', function () use (&$fired): void {
            $fired = true;
        });

        $order->setStatus(OrderPostType::STATUS_CREATED);

        $this->assertFalse($fired);
    }

    public function test_status_changed_at_is_refreshed_on_every_transition(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        update_post_meta($order->id(), OrderPostType::META_STATUS_CHANGED_AT, time() - 100);

        $order->setStatus(OrderPostType::STATUS_PAYMENT_PENDING);

        $this->assertGreaterThan(time() - 10, $order->statusChangedAt());
    }

    public function test_status_changed_at_falls_back_to_post_creation_time_when_meta_is_missing(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        delete_post_meta($order->id(), OrderPostType::META_STATUS_CHANGED_AT);

        $this->assertGreaterThan(0, $order->statusChangedAt());
    }

    public function test_tracking_code_round_trips_and_is_sanitized(): void
    {
        $productId = $this->createSimpleProduct(5.0);
        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);

        $order->setTrackingCode(' <b>ABC123</b> ');

        $this->assertSame('ABC123', $order->trackingCode());
    }
}
