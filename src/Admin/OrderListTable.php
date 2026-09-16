<?php

namespace SCCommerce\Admin;

use SCCommerce\Contracts\Hookable;
use SCCommerce\Orders\Order;
use SCCommerce\PostTypes\OrderPostType;

/**
 * Adds Status/Total/Customer columns and a status filter to the
 * native Orders list table (edit.php?post_type=scc_order) rather than
 * building a bespoke admin screen — WordPress's own list table
 * already does sorting, pagination, bulk actions and search for free.
 * Also colour-codes the Status cell itself as a pill (see
 * renderColumn()) so an admin can scan the list without reading every
 * cell's text.
 */
final class OrderListTable implements Hookable
{
    public function register(): void
    {
        \add_filter('manage_'.OrderPostType::POST_TYPE.'_posts_columns', [$this, 'columns']);
        \add_action('manage_'.OrderPostType::POST_TYPE.'_posts_custom_column', [$this, 'renderColumn'], 10, 2);
        \add_action('restrict_manage_posts', [$this, 'statusFilter']);
        \add_filter('parse_query', [$this, 'filterByStatus']);
        \add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(string $hook): void
    {
        $screen = \get_current_screen();

        if ($hook === 'edit.php' && $screen && $screen->post_type === OrderPostType::POST_TYPE) {
            \wp_enqueue_style('scc-admin', SCC_URL.'assets/css/admin.css', [], SCC_VERSION);
        }
    }

    public function columns(array $columns): array
    {
        $withoutDate = \array_diff_key($columns, ['date' => true]);

        return \array_merge($withoutDate, [
            'scc_status' => 'Status',
            'scc_customer' => 'Customer',
            'scc_total' => 'Total',
            'date' => $columns['date'],
        ]);
    }

    public function renderColumn(string $column, int $postId): void
    {
        $order = Order::get($postId);

        if (! $order) {
            return;
        }

        switch ($column) {
            case 'scc_status':
                $status = $order->status();
                $label = OrderPostType::STATUSES[$status] ?? $status;
                \printf(
                    '<span class="scc-status-pill scc-status-pill-%s">%s</span>',
                    \esc_attr(\sanitize_html_class($status)),
                    \esc_html($label)
                );
                break;
            case 'scc_customer':
                echo \esc_html($order->customerName() ?: $order->customerEmail() ?: '—');
                break;
            case 'scc_total':
                echo \esc_html($order->formattedTotal());
                break;
        }
    }

    public function statusFilter(string $postType): void
    {
        if ($postType !== OrderPostType::POST_TYPE) {
            return;
        }

        $selected = isset($_GET['scc_status']) ? \sanitize_key($_GET['scc_status']) : '';
        ?>
        <select name="scc_status">
            <option value=""><?php \esc_html_e('All statuses', 'sc-commerce'); ?></option>
            <?php foreach (OrderPostType::STATUSES as $value => $label) : ?>
                <option value="<?php echo \esc_attr($value); ?>" <?php \selected($selected, $value); ?>><?php echo \esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    public function filterByStatus(\WP_Query $query): \WP_Query
    {
        if (
            ! \is_admin()
            || $query->get('post_type') !== OrderPostType::POST_TYPE
            || empty($_GET['scc_status'])
        ) {
            return $query;
        }

        $query->set('meta_key', OrderPostType::META_STATUS);
        $query->set('meta_value', \sanitize_key($_GET['scc_status']));

        return $query;
    }
}
