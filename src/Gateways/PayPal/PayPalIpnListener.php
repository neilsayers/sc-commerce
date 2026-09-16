<?php

namespace SCCommerce\Gateways\PayPal;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Settings\Settings;

/**
 * PayPal POSTs an Instant Payment Notification to this endpoint
 * server-to-server whenever a payment's status changes — this is the
 * source of truth for whether an order actually got paid, not the
 * customer's browser landing back on the return URL (which only
 * means they clicked through, and can be skipped, closed, or spoofed
 * client-side). Frontend\CheckoutController's return-URL handling
 * only ever reads status for display; only this listener ever writes it.
 */
final class PayPalIpnListener implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoute']);
    }

    public function registerRoute(): void
    {
        \register_rest_route('scc/v1', '/paypal-ipn', [
            'methods' => 'POST',
            'callback' => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(): \WP_REST_Response
    {
        // IPN always arrives as a classic form POST, not JSON — read
        // $_POST directly rather than via WP_REST_Request's body
        // parsing, which is built around JSON payloads.
        $payload = \wp_unslash($_POST);

        if ($payload === [] || ! $this->isVerified($payload)) {
            return new \WP_REST_Response(['verified' => false], 400);
        }

        $orderId = (int) ($payload['custom'] ?? $payload['invoice'] ?? 0);
        $order = $orderId ? Order::get($orderId) : null;

        if (! $order) {
            return new \WP_REST_Response(['order' => 'not_found'], 200);
        }

        $order->recordTransaction('paypal', (string) ($payload['txn_id'] ?? ''));
        $order->setStatus($this->mapPaymentStatus((string) ($payload['payment_status'] ?? '')));

        return new \WP_REST_Response(['ok' => true], 200);
    }

    private function mapPaymentStatus(string $paypalStatus): string
    {
        return match ($paypalStatus) {
            'Completed' => OrderPostType::STATUS_PAID,
            'Pending' => OrderPostType::STATUS_PAYMENT_PENDING,
            'Denied', 'Failed', 'Voided', 'Expired' => OrderPostType::STATUS_PAYMENT_FAILED,
            default => OrderPostType::STATUS_PAYMENT_PENDING,
        };
    }

    /**
     * The required IPN handshake: post the notification straight back
     * to PayPal, unmodified, with cmd=_notify-validate prepended —
     * PayPal replies "VERIFIED" or "INVALID". Without this, anyone
     * could POST a fake "Completed" notification directly to this
     * endpoint and mark any order paid for free.
     */
    private function isVerified(array $payload): bool
    {
        $verifyUrl = $this->settings->paypalSandbox()
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        $response = \wp_remote_post($verifyUrl, [
            'body' => \array_merge(['cmd' => '_notify-validate'], $payload),
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (\is_wp_error($response)) {
            return false;
        }

        return \trim(\wp_remote_retrieve_body($response)) === 'VERIFIED';
    }
}
