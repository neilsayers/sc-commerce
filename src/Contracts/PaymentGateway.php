<?php

namespace SCCommerce\Contracts;

use SCCommerce\Orders\Order;

/**
 * Anything that can take an Order and get it paid. PayPal (Gateways\PayPal\PayPalGateway)
 * is the only implementation today, but Order/CheckoutController never
 * talk to PayPal directly — they only know this contract, so a
 * Stripe/GoCardless/whatever gateway is a second class implementing
 * this, registered in Plugin::boot(), with nothing else to change.
 */
interface PaymentGateway
{
    /**
     * Unique slug stored against the order (Order::PAYMENT_GATEWAY_META)
     * so an IPN/webhook handler knows which gateway an incoming
     * notification belongs to.
     */
    public function id(): string;

    /**
     * Where to send the customer to actually pay for $order.
     */
    public function checkoutUrl(Order $order): string;
}
