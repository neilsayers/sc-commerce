<?php

namespace SCCommerce\Notifications;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Settings\Settings;
use SCCommerce\Support\Money;

/**
 * Emails the shop owner whenever an order needs a human to look at
 * it — either it's just been paid (from PayPalIpnListener's status
 * change, never the customer's spoofable return-URL landing), or it
 * never will be paid online because no gateway is configured, in
 * which case Order::create() firing scc_order_created is the only
 * signal this order will ever produce without someone following up
 * manually.
 *
 * Recipient is Settings::notificationEmail() if the shop owner set
 * one, falling back to the site's own admin_email — "email the site
 * administrator, or the shop owner's address first if there is one".
 */
final class OrderNotifier implements Hookable
{
    public function __construct(private Settings $settings)
    {
    }

    public function register(): void
    {
        \add_action('scc_order_created', [$this, 'notifyIfUnpaidByDesign']);
        \add_action('scc_order_status_changed', [$this, 'notifyIfPaid'], 10, 3);
    }

    /**
     * A created order only ever gets here without a further status
     * change if there's no gateway to take it to (CheckoutController's
     * "no_gateway" path) — anything else transitions to Payment
     * Pending next, handled by notifyIfPaid instead.
     */
    public function notifyIfUnpaidByDesign(Order $order): void
    {
        if ($this->settings->isPaypalConfigured()) {
            return;
        }

        $this->send($order, \sprintf(
            /* translators: %d: order number */
            \__('New order #%d (no online payment configured)', 'sc-commerce'),
            $order->id()
        ));
    }

    public function notifyIfPaid(Order $order, string $status, string $previous): void
    {
        if ($status !== OrderPostType::STATUS_PAID) {
            return;
        }

        $this->send($order, \sprintf(
            /* translators: %d: order number */
            \__('Order #%d paid', 'sc-commerce'),
            $order->id()
        ));
    }

    private function send(Order $order, string $subject): void
    {
        $to = $this->settings->notificationEmail() ?: (string) \get_option('admin_email');

        if ($to === '') {
            return;
        }

        \wp_mail($to, $subject, $this->body($order));
    }

    private function body(Order $order): string
    {
        $lines = [
            \sprintf(
                /* translators: 1: order number, 2: formatted total */
                \__('Order #%1$d — %2$s', 'sc-commerce'),
                $order->id(),
                $order->formattedTotal()
            ),
            '',
            \sprintf(
                /* translators: 1: customer name, 2: customer email */
                \__('Customer: %1$s <%2$s>', 'sc-commerce'),
                $order->customerName(),
                $order->customerEmail()
            ),
            '',
        ];

        foreach ($order->lineItems() as $item) {
            $lines[] = \sprintf(
                '- %s x%d — %s',
                $item['name'],
                $item['quantity'],
                Money::format((float) $item['line_total'], $order->currency())
            );
        }

        $address = $order->addressLines();

        if ($address !== []) {
            $lines[] = '';
            $lines[] = \__('Delivery address:', 'sc-commerce');
            $lines[] = \implode(', ', $address);
        }

        if ($order->customerNotes() !== '') {
            $lines[] = '';
            $lines[] = \__('Notes:', 'sc-commerce').' '.$order->customerNotes();
        }

        $lines[] = '';
        $lines[] = \admin_url(\sprintf('post.php?post=%d&action=edit', $order->id()));

        return \implode("\n", $lines);
    }
}
