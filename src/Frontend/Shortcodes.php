<?php

namespace SCCommerce\Frontend;

use SCCommerce\Basket\Basket;
use SCCommerce\Contracts\Hookable;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;

/**
 * [scc_basket] and [scc_checkout] — the two pages Setup\Activator
 * creates on activation. Kept as shortcodes rather than page templates
 * so they work on whatever page/theme an admin drops them into, not
 * just this plugin's own testbed theme.
 */
final class Shortcodes implements Hookable
{
    public function register(): void
    {
        \add_shortcode('scc_basket', [$this, 'renderBasket']);
        \add_shortcode('scc_checkout', [$this, 'renderCheckout']);
    }

    public function renderBasket(): string
    {
        $basket = Basket::forCurrentVisitor();

        if ($basket->isEmpty()) {
            return '<p class="scc-basket-empty">Your basket is empty.</p>';
        }

        \ob_start();
        ?>
        <table class="scc-basket-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Line total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($basket->enrichedItems() as $item) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo \esc_url($item['product']->permalink()); ?>"><?php echo \esc_html($item['product']->name()); ?></a>
                            <?php if ($item['variation'] !== null) : $variation = $item['product']->variation($item['variation']); ?>
                                <br><small><?php echo \esc_html($variation['label'] ?? ''); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" class="scc-basket-update-form" data-scc-product="<?php echo \esc_attr($item['product']->id()); ?>">
                                <?php \wp_nonce_field('scc_basket_update'); ?>
                                <input type="hidden" name="action" value="scc_basket_update">
                                <input type="hidden" name="product_id" value="<?php echo \esc_attr($item['product']->id()); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo \esc_url(scc_basket_url()); ?>">
                                <?php if ($item['variation'] !== null) : ?>
                                    <input type="hidden" name="variation" value="<?php echo \esc_attr($item['variation']); ?>">
                                <?php endif; ?>
                                <input type="number" name="quantity" value="<?php echo \esc_attr($item['quantity']); ?>" min="1" style="width:4em;">
                                <button type="submit">Update</button>
                            </form>
                        </td>
                        <td><?php echo \esc_html(scc_price($item['line_total'])); ?></td>
                        <td>
                            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" class="scc-basket-remove-form" data-scc-product="<?php echo \esc_attr($item['product']->id()); ?>">
                                <?php \wp_nonce_field('scc_basket_remove'); ?>
                                <input type="hidden" name="action" value="scc_basket_remove">
                                <input type="hidden" name="product_id" value="<?php echo \esc_attr($item['product']->id()); ?>">
                                <input type="hidden" name="redirect_to" value="<?php echo \esc_url(scc_basket_url()); ?>">
                                <?php if ($item['variation'] !== null) : ?>
                                    <input type="hidden" name="variation" value="<?php echo \esc_attr($item['variation']); ?>">
                                <?php endif; ?>
                                <button type="submit">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align:right;">Total</th>
                    <th><?php echo \esc_html(scc_price($basket->total())); ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
        <p><a href="<?php echo \esc_url(scc_checkout_url()); ?>" class="scc-proceed-to-checkout">Proceed to checkout</a></p>
        <?php
        return \ob_get_clean();
    }

    public function renderCheckout(): string
    {
        \ob_start();

        $this->renderNotices();

        $basket = Basket::forCurrentVisitor();

        if ($basket->isEmpty() && empty($_GET['scc_order'])) {
            echo '<p class="scc-basket-empty">Your basket is empty. <a href="'.\esc_url(\home_url('/')).'">Continue shopping</a>.</p>';

            return \ob_get_clean();
        }

        if (! $basket->isEmpty()) :
            ?>
            <form method="post" action="<?php echo \esc_url(\admin_url('admin-post.php')); ?>" class="scc-checkout-form">
                <?php \wp_nonce_field('scc_checkout'); ?>
                <input type="hidden" name="action" value="scc_checkout">
                <p>
                    <label>Name<br><input type="text" name="customer_name" required></label>
                </p>
                <p>
                    <label>Email<br><input type="email" name="customer_email" required></label>
                </p>
                <p><strong>Total: <?php echo \esc_html(scc_price($basket->total())); ?></strong></p>
                <button type="submit" class="scc-place-order">Pay with PayPal</button>
            </form>
            <?php
        endif;

        return \ob_get_clean();
    }

    private function renderNotices(): void
    {
        if (! empty($_GET['scc_notice'])) {
            $notice = \sanitize_key($_GET['scc_notice']);
            $messages = [
                'empty' => 'There was nothing to check out.',
                'no_gateway' => 'Thanks — your order has been recorded, but online payment isn\'t set up on this site yet. We\'ll be in touch to arrange payment.',
            ];

            if (isset($messages[$notice])) {
                echo '<p class="scc-notice">'.\esc_html($messages[$notice]).'</p>';
            }
        }

        if (empty($_GET['scc_order'])) {
            return;
        }

        $order = Order::get((int) $_GET['scc_order']);

        if (! $order) {
            return;
        }

        $label = OrderPostType::STATUSES[$order->status()] ?? $order->status();
        echo '<p class="scc-order-status">Order #'.\esc_html((string) $order->id()).' — status: '.\esc_html($label).'</p>';

        if ($order->status() === OrderPostType::STATUS_PAYMENT_PENDING && ($_GET['scc_paypal'] ?? '') === 'return') {
            echo '<p class="scc-notice">We\'re waiting for confirmation from PayPal — this page will show "Paid" once that arrives, usually within a few seconds.</p>';
        }
    }
}
