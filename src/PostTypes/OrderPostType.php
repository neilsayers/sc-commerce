<?php

namespace SCCommerce\PostTypes;

use SCCommerce\Contracts\Hookable;

/**
 * A single "Order" post, created the moment a customer submits the
 * checkout form — before any payment happens, so it captures abandoned
 * and failed attempts too, not just successful ones. Status lives in
 * postmeta (self::META_STATUS) rather than a custom post_status, same
 * reasoning as SC Room Bookings' BookingPostType: an order is always
 * a "real" post, there's no need for WordPress's own publish/draft
 * machinery to also track this.
 *
 * Not public — an order has no front-end page of its own (the
 * customer's view of "their order" is the checkout confirmation
 * screen, rendered from Order data via a shortcode, not this post's
 * permalink). show_ui stays on so admins manage orders through
 * WordPress's native list/edit screens rather than a bespoke admin
 * page — but create_posts is locked out (see registerPostType) since
 * an order only ever comes from Orders\Order::create(), never
 * "Add New" in wp-admin.
 */
final class OrderPostType implements Hookable
{
    public const POST_TYPE = 'scc_order';

    public const META_STATUS = '_scc_status';
    // Set whenever META_STATUS changes (Orders\Order::setStatus()), not
    // just when the post itself is last modified — Orders\StaleOrderCleaner
    // needs "how long has this order actually been Payment Pending",
    // which post_modified can't answer (postmeta writes don't touch it).
    public const META_STATUS_CHANGED_AT = '_scc_status_changed_at';
    public const META_LINE_ITEMS = '_scc_line_items';
    public const META_CURRENCY = '_scc_currency';
    public const META_TOTAL = '_scc_total';
    public const META_CUSTOMER_NAME = '_scc_customer_name';
    public const META_CUSTOMER_EMAIL = '_scc_customer_email';
    public const META_CUSTOMER_NOTES = '_scc_customer_notes';
    public const META_GATEWAY = '_scc_gateway';
    public const META_TRANSACTION_ID = '_scc_transaction_id';
    public const META_TRACKING_CODE = '_scc_tracking_code';

    /**
     * UK-only for now, deliberately — no country field on the checkout
     * form, and every order gets this fixed value rather than an empty
     * one. Adding other countries later means adding a country field
     * to the form and reading it in
     * Frontend\CheckoutController::customerFromRequest() instead of
     * hardcoding META_ADDRESS_COUNTRY's value there; nothing about the
     * meta shape itself needs to change.
     */
    public const META_ADDRESS_LINE1 = '_scc_address_line1';
    public const META_ADDRESS_LINE2 = '_scc_address_line2';
    public const META_ADDRESS_TOWN = '_scc_address_town';
    public const META_ADDRESS_COUNTY = '_scc_address_county';
    public const META_ADDRESS_POSTCODE = '_scc_address_postcode';
    public const META_ADDRESS_COUNTRY = '_scc_address_country';

    public const STATUS_CREATED = 'created';
    public const STATUS_PAYMENT_PENDING = 'payment_pending';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAID = 'paid';
    public const STATUS_PAYMENT_FAILED = 'payment_failed';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUSES = [
        self::STATUS_CREATED => 'Created',
        self::STATUS_PAYMENT_PENDING => 'Payment Pending',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_PAID => 'Paid',
        self::STATUS_PAYMENT_FAILED => 'Payment Failed',
        self::STATUS_COMPLETE => 'Complete',
        self::STATUS_DISPATCHED => 'Dispatched',
    ];

    public function register(): void
    {
        \add_action('init', [$this, 'registerPostType']);
        \add_filter('map_meta_cap', [$this, 'preventManualCreation'], 10, 4);
    }

    public function registerPostType(): void
    {
        \register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Orders',
                'singular_name' => 'Order',
                'edit_item' => 'Edit Order',
                'view_item' => 'View Order',
                'search_items' => 'Search Orders',
                'not_found' => 'No orders found',
                'not_found_in_trash' => 'No orders found in Trash',
                'all_items' => 'Orders',
                'menu_name' => 'Orders',
                'name_admin_bar' => 'Order',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-clipboard',
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }

    /**
     * Orders are only ever created programmatically (Orders\Order::create()).
     * Blocking create_posts hides "Add New" and post-new.php for this
     * type while leaving read/edit/delete/list alone, so an admin can
     * still open and update an order's status.
     */
    public function preventManualCreation(array $caps, string $cap, int $userId, array $args): array
    {
        if ($cap === 'create_posts' && ($args[0] ?? null) === self::POST_TYPE) {
            return ['do_not_allow'];
        }

        return $caps;
    }
}
