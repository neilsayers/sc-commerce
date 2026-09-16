<?php

namespace SCCommerce\Frontend;

use SCCommerce\Basket\Basket;
use SCCommerce\Contracts\Hookable;
use SCCommerce\Gateways\PayPal\PayPalGateway;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Settings\Settings;

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
        $invalidField = $this->firstInvalidField($customer);

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

    /**
     * The HTML form already marks these `required`, but that's only a
     * UX nicety — anyone posting straight to admin-post.php (or with
     * JS/HTML tampered with) can submit blanks, so this is the actual
     * gate. Checked in a fixed order and only the first failure is
     * reported, since the checkout page has no way to redisplay the
     * submitted values or highlight several fields at once — one
     * targeted notice per re-attempt is simpler than a multi-error
     * summary here would be worth building.
     *
     * @param array{name: string, email: string, address_line1: string, address_town: string, address_postcode: string} $customer
     */
    private function firstInvalidField(array $customer): ?string
    {
        if ($customer['name'] === '') {
            return 'name';
        }

        if ($customer['email'] === '' || ! \is_email($customer['email'])) {
            return 'email';
        }

        if ($customer['address_line1'] === '') {
            return 'address_line1';
        }

        if ($customer['address_town'] === '') {
            return 'address_town';
        }

        if ($customer['address_postcode'] === '' || ! self::isValidUkPostcode($customer['address_postcode'])) {
            return 'address_postcode';
        }

        return null;
    }

    /**
     * address_line2/address_county stay unvalidated — both are marked
     * optional on the checkout form (see scc_the_checkout()), so an
     * empty value there is correct input, not missing input.
     *
     * Standard UK postcode shape (outward code + inward code); doesn't
     * check against Royal Mail's actual allocated code list, just that
     * it's shaped like a postcode — matching the fixed GB-only address
     * this plugin stores (PostTypes\OrderPostType::META_ADDRESS_COUNTRY).
     */
    private static function isValidUkPostcode(string $postcode): bool
    {
        return \preg_match('/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', \trim($postcode)) === 1;
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
