<?php

namespace SCCommerce\MetaBoxes;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;
use SCCommerce\Support\Money;

/**
 * Line items, customer and totals are read-only here — an order is a
 * snapshot taken at checkout (see Orders\Order's class doc), not
 * something an admin edits line-by-line. Status and tracking code are
 * the two things genuinely editable: status e.g. moving Paid ->
 * Dispatched once a parcel goes out, or Payment Pending -> Cancelled
 * if a customer emails to say they've changed their mind; tracking
 * code filled in by hand at the same time a parcel goes out, since
 * there's no carrier integration to set it automatically yet.
 */
final class OrderDetailsMetaBox implements Hookable
{
    private const NONCE_ACTION = 'scc_save_order_status';
    private const NONCE_NAME = 'scc_order_status_nonce';

    public function register(): void
    {
        \add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        \add_action('save_post_'.OrderPostType::POST_TYPE, [$this, 'save']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        $screen = \get_current_screen();

        if (\in_array($hook, ['post.php', 'post-new.php'], true) && $screen && $screen->post_type === OrderPostType::POST_TYPE) {
            \wp_enqueue_style('scc-admin', SCC_URL.'assets/css/admin.css', [], SCC_VERSION);
        }
    }

    public function addMetaBoxes(): void
    {
        \add_meta_box('scc-order-status', 'Order Status', [$this, 'renderStatus'], OrderPostType::POST_TYPE, 'side', 'high');
        \add_meta_box('scc-order-customer', 'Customer', [$this, 'renderCustomer'], OrderPostType::POST_TYPE, 'side', 'default');
        \add_meta_box('scc-order-line-items', 'Order Items', [$this, 'renderLineItems'], OrderPostType::POST_TYPE, 'normal', 'high');
    }

    public function renderStatus(\WP_Post $post): void
    {
        $order = Order::get($post->ID);

        if (! $order) {
            return;
        }

        \wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        ?>
        <p>
            <select name="scc_status" style="width:100%;">
                <?php foreach (OrderPostType::STATUSES as $value => $label) : ?>
                    <option value="<?php echo \esc_attr($value); ?>" <?php \selected($order->status(), $value); ?>><?php echo \esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php if ($order->gateway() !== '') : ?>
            <p>
                <strong>Gateway:</strong> <?php echo \esc_html($order->gateway()); ?><br>
                <strong>Transaction ID:</strong> <?php echo \esc_html($order->transactionId() ?: '—'); ?>
            </p>
        <?php endif; ?>
        <p>
            <label for="scc-tracking-code"><strong>Tracking code</strong></label><br>
            <input type="text" id="scc-tracking-code" name="scc_tracking_code" value="<?php echo \esc_attr($order->trackingCode()); ?>" style="width:100%;">
        </p>
        <?php
    }

    public function renderCustomer(\WP_Post $post): void
    {
        $order = Order::get($post->ID);

        if (! $order) {
            return;
        }
        ?>
        <p><strong>Name:</strong> <?php echo \esc_html($order->customerName() ?: '—'); ?></p>
        <p><strong>Email:</strong> <?php echo \esc_html($order->customerEmail() ?: '—'); ?></p>
        <?php $addressLines = $order->addressLines(); ?>
        <p>
            <strong>Address:</strong><br>
            <?php if ($addressLines === []) : ?>
                —
            <?php else : ?>
                <?php echo \esc_html(\implode(', ', $addressLines)); ?>
            <?php endif; ?>
        </p>
        <?php if ($order->customerNotes() !== '') : ?>
            <p><strong>Notes:</strong><br><?php echo \nl2br(\esc_html($order->customerNotes())); ?></p>
        <?php endif; ?>
        <?php
    }

    public function renderLineItems(\WP_Post $post): void
    {
        $order = Order::get($post->ID);

        if (! $order) {
            return;
        }
        ?>
        <div id="scc-order-line-items">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Unit price</th>
                        <th>Qty</th>
                        <th>Line total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->lineItems() as $item) : ?>
                        <tr>
                            <td><?php echo \esc_html($item['name']); ?></td>
                            <td><?php echo \esc_html($item['sku'] ?: '—'); ?></td>
                            <td><?php echo \esc_html(Money::format((float) $item['unit_price'], $order->currency())); ?></td>
                            <td><?php echo \esc_html((string) $item['quantity']); ?></td>
                            <td><?php echo \esc_html(Money::format((float) $item['line_total'], $order->currency())); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="4" style="text-align:right;">Total</th>
                        <th><?php echo \esc_html($order->formattedTotal()); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    public function save(int $postId): void
    {
        if (
            ! isset($_POST[self::NONCE_NAME])
            || ! \wp_verify_nonce(\sanitize_text_field(\wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            return;
        }

        if (! \current_user_can('edit_post', $postId)) {
            return;
        }

        $order = Order::get($postId);

        if (! $order) {
            return;
        }

        if (isset($_POST['scc_status'])) {
            $order->setStatus(\sanitize_key($_POST['scc_status']));
        }

        if (isset($_POST['scc_tracking_code'])) {
            $order->setTrackingCode(\sanitize_text_field(\wp_unslash($_POST['scc_tracking_code'])));
        }
    }
}
