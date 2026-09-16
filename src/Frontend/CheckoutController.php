<?php

namespace SCCommerce\Frontend;

use SCCommerce\Basket\Basket;
use SCCommerce\Contracts\Hookable;
use SCCommerce\Gateways\PayPal\PayPalGateway;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Settings\Settings;

/**
 * Handles the two ways an Order gets created — a single-product "Buy
 * Now" click, and "Proceed to checkout" from the basket — via classic
 * admin-post.php form posts rather than REST. Both end in a redirect
 * (to PayPal, or back to the checkout page), which is what
 * admin-post.php is built for; the basket's own add/remove/update
 * stays on BasketRestController since those are AJAX, not navigations.
 *
 * Order::create() runs before any gateway is involved (see its class
 * doc) — that's requirement #5's "regardless of whether the payment
 * goes through" satisfied at the point this class calls it, not later.
 */
final class CheckoutController implements Hookable
{
    public function __construct(private Settings $settings, private PayPalGateway $paypal)
    {
    }

    public function register(): void
    {
        \add_action('admin_post_scc_buy_now', [$this, 'buyNow']);
        \add_action('admin_post_nopriv_scc_buy_now', [$this, 'buyNow']);
        \add_action('admin_post_scc_checkout', [$this, 'checkout']);
        \add_action('admin_post_nopriv_scc_checkout', [$this, 'checkout']);
        \add_action('template_redirect', [$this, 'handlePayPalReturn']);
    }

    public function buyNow(): void
    {
        \check_admin_referer('scc_buy_now');

        $productId = (int) ($_POST['product_id'] ?? 0);
        $variation = isset($_POST['variation']) && $_POST['variation'] !== '' ? (int) $_POST['variation'] : null;
        $quantity = \max(1, (int) ($_POST['quantity'] ?? 1));

        $order = Order::create(
            [['product_id' => $productId, 'variation' => $variation, 'quantity' => $quantity]],
            $this->customerFromRequest()
        );

        $this->redirectToPayment($order);
    }

    public function checkout(): void
    {
        \check_admin_referer('scc_checkout');

        $basket = Basket::forCurrentVisitor();
        $order = Order::create($basket->items(), $this->customerFromRequest());

        if ($order) {
            $basket->clear();
        }

        $this->redirectToPayment($order);
    }

    private function customerFromRequest(): array
    {
        return [
            'name' => \sanitize_text_field(\wp_unslash($_POST['customer_name'] ?? '')),
            'email' => \sanitize_email(\wp_unslash($_POST['customer_email'] ?? '')),
        ];
    }

    private function redirectToPayment(?Order $order): void
    {
        $checkoutPageId = $this->settings->checkoutPageId();
        $checkoutUrl = $checkoutPageId ? \get_permalink($checkoutPageId) : \home_url('/');

        if (! $order) {
            \wp_safe_redirect(\add_query_arg('scc_notice', 'empty', $checkoutUrl));
            exit;
        }

        if (! $this->paypal->isConfigured()) {
            \wp_safe_redirect(\add_query_arg(['scc_order' => $order->id(), 'scc_notice' => 'no_gateway'], $checkoutUrl));
            exit;
        }

        $order->setStatus(OrderPostType::STATUS_PAYMENT_PENDING);

        \wp_safe_redirect($this->paypal->checkoutUrl($order));
        exit;
    }

    /**
     * Only ever changes an order's status to Cancelled, and only when
     * PayPal's own cancel_return brings the customer back — a real
     * payment confirmation always comes from
     * Gateways\PayPal\PayPalIpnListener, never from this. If IPN has
     * already marked the order Paid by the time this fires (the
     * customer paid, then hit back), that status is left alone.
     */
    public function handlePayPalReturn(): void
    {
        if (empty($_GET['scc_order']) || empty($_GET['scc_paypal'])) {
            return;
        }

        if ($_GET['scc_paypal'] !== 'cancel') {
            return;
        }

        $order = Order::get((int) $_GET['scc_order']);

        if ($order && $order->status() === OrderPostType::STATUS_PAYMENT_PENDING) {
            $order->setStatus(OrderPostType::STATUS_CANCELLED);
        }
    }
}
