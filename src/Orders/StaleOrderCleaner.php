<?php

namespace SCCommerce\Orders;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\OrderPostType;

/**
 * Auto-cancels an order that's been stuck on Payment Pending for a
 * month — PayPal's IPN never arrived (the customer abandoned at
 * PayPal, or this is a local/non-public site with nothing for IPN to
 * reach, see Admin\DocumentationPage's "Testing locally" section), so
 * nothing else would ever move it off Payment Pending on its own.
 *
 * Deliberately scoped to Payment Pending only, not Created — a
 * Created order with no gateway configured (CheckoutController's
 * "no_gateway" path) is a manual order the shop owner already got
 * notified about (Notifications\OrderNotifier) and is expected to
 * follow up on themselves in their own time, not something stuck
 * waiting on an external service that's never going to answer.
 *
 * Runs once a day via WP-Cron. A Cancelled order is left for an admin
 * to trash by hand whenever they're ready — this only stops it
 * silently sitting as "Payment Pending" forever, it doesn't delete
 * anything itself.
 */
final class StaleOrderCleaner implements Hookable
{
    public const CRON_HOOK = 'scc_cancel_stale_orders';
    public const STALE_AFTER = \MONTH_IN_SECONDS;

    public function register(): void
    {
        \add_action('init', [$this, 'scheduleEvent']);
        \add_action(self::CRON_HOOK, [$this, 'cancelStaleOrders']);
    }

    public function scheduleEvent(): void
    {
        if (! \wp_next_scheduled(self::CRON_HOOK)) {
            \wp_schedule_event(\time(), 'daily', self::CRON_HOOK);
        }
    }

    public function cancelStaleOrders(): void
    {
        $orderIds = \get_posts([
            'post_type' => OrderPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => OrderPostType::META_STATUS,
                    'value' => OrderPostType::STATUS_PAYMENT_PENDING,
                ],
                [
                    'key' => OrderPostType::META_STATUS_CHANGED_AT,
                    'value' => \time() - self::STALE_AFTER,
                    'compare' => '<=',
                    'type' => 'NUMERIC',
                ],
            ],
        ]);

        foreach ($orderIds as $orderId) {
            $order = Order::get((int) $orderId);

            if ($order) {
                $order->setStatus(OrderPostType::STATUS_CANCELLED);
            }
        }
    }
}
