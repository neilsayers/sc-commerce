<?php

namespace SCCommerce\Frontend;

use SCCommerce\Basket\Basket;
use SCCommerce\Contracts\Hookable;
use SCCommerce\Support\Money;

/**
 * REST endpoints under scc/v1/basket so assets/js/basket.js can
 * add/update/remove items without a full page reload — this is the
 * only thing basket.js talks to; the actual read/write logic lives in
 * Basket\Basket.
 */
final class BasketRestController implements Hookable
{
    private const NAMESPACE = 'scc/v1';

    public function register(): void
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        \register_rest_route(self::NAMESPACE, '/basket', [
            'methods' => 'GET',
            'callback' => [$this, 'getBasket'],
            'permission_callback' => '__return_true',
        ]);

        \register_rest_route(self::NAMESPACE, '/basket/add', [
            'methods' => 'POST',
            'callback' => [$this, 'addItem'],
            'permission_callback' => [$this, 'checkNonce'],
            'args' => [
                'product_id' => ['required' => true, 'type' => 'integer'],
                'variation' => ['required' => false, 'type' => 'integer'],
                'quantity' => ['required' => false, 'type' => 'integer', 'default' => 1],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/basket/update', [
            'methods' => 'POST',
            'callback' => [$this, 'updateItem'],
            'permission_callback' => [$this, 'checkNonce'],
            'args' => [
                'product_id' => ['required' => true, 'type' => 'integer'],
                'variation' => ['required' => false, 'type' => 'integer'],
                'quantity' => ['required' => true, 'type' => 'integer'],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/basket/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'removeItem'],
            'permission_callback' => [$this, 'checkNonce'],
            'args' => [
                'product_id' => ['required' => true, 'type' => 'integer'],
                'variation' => ['required' => false, 'type' => 'integer'],
            ],
        ]);

        \register_rest_route(self::NAMESPACE, '/basket/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clearBasket'],
            'permission_callback' => [$this, 'checkNonce'],
        ]);
    }

    public function checkNonce(\WP_REST_Request $request): bool
    {
        $nonce = $request->get_header('X-WP-Nonce');

        return $nonce !== null && \wp_verify_nonce($nonce, 'wp_rest');
    }

    public function getBasket(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->basketPayload(Basket::forCurrentVisitor()));
    }

    public function addItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $basket = Basket::forCurrentVisitor();
        $basket->add(
            (int) $request->get_param('product_id'),
            $this->variationParam($request),
            (int) $request->get_param('quantity')
        );

        return new \WP_REST_Response($this->basketPayload($basket));
    }

    public function updateItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $basket = Basket::forCurrentVisitor();
        $basket->update(
            (int) $request->get_param('product_id'),
            $this->variationParam($request),
            (int) $request->get_param('quantity')
        );

        return new \WP_REST_Response($this->basketPayload($basket));
    }

    public function removeItem(\WP_REST_Request $request): \WP_REST_Response
    {
        $basket = Basket::forCurrentVisitor();
        $basket->remove(
            (int) $request->get_param('product_id'),
            $this->variationParam($request)
        );

        return new \WP_REST_Response($this->basketPayload($basket));
    }

    public function clearBasket(): \WP_REST_Response
    {
        $basket = Basket::forCurrentVisitor();
        $basket->clear();

        return new \WP_REST_Response($this->basketPayload($basket));
    }

    private function variationParam(\WP_REST_Request $request): ?int
    {
        $variation = $request->get_param('variation');

        return $variation === null || $variation === '' ? null : (int) $variation;
    }

    private function basketPayload(Basket $basket): array
    {
        $currency = (new \SCCommerce\Settings\Settings())->currency();

        $items = \array_map(
            fn (array $item): array => [
                'product_id' => $item['product']->id(),
                'name' => $item['product']->name(),
                'variation' => $item['variation'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'unit_price_formatted' => Money::format($item['unit_price'], $currency),
                'line_total' => $item['line_total'],
                'line_total_formatted' => Money::format($item['line_total'], $currency),
            ],
            $basket->enrichedItems()
        );

        return [
            'items' => $items,
            'item_count' => $basket->itemCount(),
            'total' => $basket->total(),
            'total_formatted' => Money::format($basket->total(), $currency),
        ];
    }
}
