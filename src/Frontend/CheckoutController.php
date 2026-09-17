<?php

namespace SCCommerce\Frontend;

use SCCommerce\Basket\Basket;
use SCCommerce\Contracts\Hookable;
use SCCommerce\Gateways\PayPal\PayPalGateway;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Settings\Settings;
use SCCommerce\Support\CustomerValidator;

/**
 * Handles "Proceed to checkout" from the basket via a classic
 * admin-post.php form post rather than REST, since it ends in a
 * redirect (to PayPal, or back to the checkout page), which is what
 * admin-post.php is built for; the basket's own add/remove/update
 * stays on BasketRestController since those are AJAX, not navigations.
 *
 * There's deliberately no "Buy Now" shortcut straight from a product
 * page — every checkout needs the customer's name and delivery
 * address, which only this form collects, and skipping it would mean
 * either not having that data or relying on PayPal's own account data
 * for it instead, which this plugin doesn't want to depend on.
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
        \add_action('admin_post_scc_checkout', [$this, 'checkout']);
        \add_action('admin_post_nopriv_scc_checkout', [$this, 'checkout']);
        \add_action('template_redirect', [$this, 'handlePayPalReturn']);
    }

    public function checkout(): void
    {
        \check_admin_referer('scc_checkout');

        $customer = $this->customerFromRequest();

        // The HTML form already marks these `required`, but that's only
        // a UX nicety — anyone posting straight to admin-post.php (or
        // with JS/HTML tampered with) can submit blanks, so this is the
        // actual gate.
        $invalidField = CustomerValidator::firstInvalidField($customer);

        if ($invalidField) {
            $this->redirectToCheckoutWithError($invalidField);

            return;
        }

        $basket = Basket::forCurrentVisitor();
        $order = Order::create($basket->items(), $customer);

        if ($order) {
            $basket->clear();
        }

        $this->redirectToPayment($order);
    }

    /**
     * The address/notes fields always come from the checkout form
     * (scc_the_checkout()) — checkout() is the only caller.
     */
    private function customerFromRequest(): array
    {
        return [
            'name' => \sanitize_text_field(\wp_unslash($_POST['customer_name'] ?? '')),
            'email' => \sanitize_email(\wp_unslash($_POST['customer_email'] ?? '')),
            'notes' => \sanitize_textarea_field(\wp_unslash($_POST['customer_notes'] ?? '')),
            'address_line1' => \sanitize_text_field(\wp_unslash($_POST['address_line1'] ?? '')),
            'address_line2' => \sanitize_text_field(\wp_unslash($_POST['address_line2'] ?? '')),
            'address_town' => \sanitize_text_field(\wp_unslash($_POST['address_town'] ?? '')),
            'address_county' => \sanitize_text_field(\wp_unslash($_POST['address_county'] ?? '')),
            'address_postcode' => \sanitize_text_field(\wp_unslash($_POST['address_postcode'] ?? '')),
        ];
    }

    private function redirectToCheckoutWithError(string $field): void
    {
        $checkoutPageId = $this->settings->checkoutPageId();
        $checkoutUrl = $checkoutPageId ? \get_permalink($checkoutPageId) : \home_url('/');

        \wp_safe_redirect(\add_query_arg('scc_notice', 'invalid_'.$field, $checkoutUrl));
        exit;
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
