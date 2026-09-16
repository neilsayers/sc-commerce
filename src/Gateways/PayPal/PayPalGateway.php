<?php

namespace SCCommerce\Gateways\PayPal;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Contracts\PaymentGateway;
use SCCommerce\Orders\Order;
use SCCommerce\Settings\Settings;

/**
 * PayPal Standard (the hosted cgi-bin/webscr checkout + IPN) rather
 * than the Orders v2 API or Smart Buttons — it needs nothing but a
 * PayPal business email address in Settings, no API keys/OAuth/SDK,
 * so it's genuinely the simplest thing that can take a real payment.
 * The trade-off is an older, less polished checkout UI and IPN
 * (rather than webhooks) for confirmation — see
 * PayPalIpnListener and the README's "Payments" section for the
 * upgrade path (Smart Buttons/Orders v2, or a second PaymentGateway
 * implementation entirely, e.g. Stripe Checkout) once that matters.
 *
 * Implements Hookable purely to whitelist paypal.com/sandbox.paypal.com
 * for wp_safe_redirect() (Frontend\CheckoutController::redirectToPayment())
 * — without this, WordPress silently refuses to redirect there at all
 * and sends the customer to wp-admin instead, since wp_safe_redirect()
 * only ever follows the site's own host plus whatever's been added to
 * 'allowed_redirect_hosts'.
 */
final class PayPalGateway implements PaymentGateway, Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_filter('allowed_redirect_hosts', [$this, 'allowRedirectHosts']);
    }

    public function allowRedirectHosts(array $hosts): array
    {
        return \array_merge($hosts, ['www.paypal.com', 'www.sandbox.paypal.com']);
    }

    public function id(): string
    {
        return 'paypal';
    }

    public function isConfigured(): bool
    {
        return $this->settings->isPaypalConfigured();
    }

    public function checkoutUrl(Order $order): string
    {
        $base = $this->settings->paypalSandbox()
            ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://www.paypal.com/cgi-bin/webscr';

        $params = [
            'cmd' => '_cart',
            'upload' => '1',
            'business' => $this->settings->paypalEmail(),
            'currency_code' => $order->currency(),
            'custom' => (string) $order->id(),
            'invoice' => (string) $order->id(),
            'no_shipping' => '1',
            'return' => $this->returnUrl($order, 'return'),
            'cancel_return' => $this->returnUrl($order, 'cancel'),
            'notify_url' => \rest_url('scc/v1/paypal-ipn'),
        ];

        foreach (\array_values($order->lineItems()) as $index => $item) {
            $n = $index + 1;
            $params["item_name_{$n}"] = $item['name'];
            $params["amount_{$n}"] = \number_format((float) $item['unit_price'], 2, '.', '');
            $params["quantity_{$n}"] = (string) $item['quantity'];
        }

        return $base.'?'.\http_build_query($params);
    }

    private function returnUrl(Order $order, string $outcome): string
    {
        $checkoutPageId = $this->settings->checkoutPageId();
        $base = $checkoutPageId ? \get_permalink($checkoutPageId) : \home_url('/');

        return \add_query_arg([
            'scc_order' => $order->id(),
            'scc_paypal' => $outcome,
        ], $base);
    }
}
