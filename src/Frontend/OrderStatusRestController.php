<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;

/**
 * GET /wp-json/scc/v1/orders/{id}/status — what assets/js/order-status.js
 * polls from the checkout confirmation screen so "Payment Pending"
 * flips to "Paid" the moment Gateways\PayPal\PayPalIpnListener updates
 * the order, without the customer having to refresh the page
 * themselves.
 *
 * Deliberately returns only status — no name, email, address, line
 * items or total. It's public and unauthenticated (same as the
 * checkout page's own ?scc_order={id} query param already is — see
 * scc_the_checkout_notices()), so anyone who knows or guesses an
 * order ID can already see its status today; keeping this endpoint to
 * status alone means that exposure never grows to include anything
 * actually sensitive.
 */
final class OrderStatusRestController implements Hookable
{
    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route('scc/v1', '/orders/(?P<id>\d+)/status', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $order = Order::get((int) $request->get_param('id'));

        if (! $order) {
            return new \WP_REST_Response(['message' => 'Order not found.'], 404);
        }

        return new \WP_REST_Response([
            'id' => $order->id(),
            'status' => $order->status(),
            'status_label' => OrderPostType::STATUSES[$order->status()] ?? $order->status(),
        ]);
    }
}
