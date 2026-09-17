<?php

namespace SCCommerce\Tests\Integration;

use SCCommerce\Orders\Order;
use SCCommerce\Orders\StaleOrderCleaner;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\PostTypes\ProductPostType;

final class StaleOrderCleanerTest extends \WP_UnitTestCase
{
    private function createOrderWithStatusSince(string $status, int $statusChangedAt): Order
    {
        $productId = self::factory()->post->create([
            'post_type' => ProductPostType::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => [
                ProductPostType::META_PRICE => 5.0,
                ProductPostType::META_TYPE => ProductPostType::TYPE_SIMPLE,
            ],
        ]);

        $order = Order::create([['product_id' => $productId, 'variation' => null, 'quantity' => 1]]);
        $order->setStatus($status);

        // setStatus() already stamped "now" on top of whatever create()
        // set — overwrite it directly to simulate an order that's
        // actually been sitting in this status for a while, which is
        // the whole thing being tested here.
        update_post_meta($order->id(), OrderPostType::META_STATUS_CHANGED_AT, $statusChangedAt);

        return $order;
    }

    public function test_cancels_an_order_stuck_pending_for_over_a_month(): void
    {
        $order = $this->createOrderWithStatusSince(
            OrderPostType::STATUS_PAYMENT_PENDING,
            time() - (StaleOrderCleaner::STALE_AFTER + DAY_IN_SECONDS)
        );

        (new StaleOrderCleaner())->cancelStaleOrders();

        $this->assertSame(OrderPostType::STATUS_CANCELLED, Order::get($order->id())->status());
    }

    public function test_leaves_a_recently_pending_order_alone(): void
    {
        $order = $this->createOrderWithStatusSince(OrderPostType::STATUS_PAYMENT_PENDING, time() - DAY_IN_SECONDS);

        (new StaleOrderCleaner())->cancelStaleOrders();

        $this->assertSame(OrderPostType::STATUS_PAYMENT_PENDING, Order::get($order->id())->status());
    }

    /**
     * A "Created" order (no gateway configured) is deliberately never
     * swept up here, however old — see StaleOrderCleaner's own class
     * doc for why: the shop owner's already been notified about it
     * and is expected to follow up by hand, on their own timeline.
     */
    public function test_leaves_an_old_created_order_alone(): void
    {
        $order = $this->createOrderWithStatusSince(
            OrderPostType::STATUS_CREATED,
            time() - (StaleOrderCleaner::STALE_AFTER + DAY_IN_SECONDS)
        );

        (new StaleOrderCleaner())->cancelStaleOrders();

        $this->assertSame(OrderPostType::STATUS_CREATED, Order::get($order->id())->status());
    }

    public function test_leaves_an_already_paid_order_alone_even_if_old(): void
    {
        $order = $this->createOrderWithStatusSince(
            OrderPostType::STATUS_PAID,
            time() - (StaleOrderCleaner::STALE_AFTER + DAY_IN_SECONDS)
        );

        (new StaleOrderCleaner())->cancelStaleOrders();

        $this->assertSame(OrderPostType::STATUS_PAID, Order::get($order->id())->status());
    }
}
