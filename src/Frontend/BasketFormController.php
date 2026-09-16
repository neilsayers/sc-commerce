<?php

namespace SCCommerce\Frontend;

use SCCommerce\Basket\Basket;
use SCCommerce\Contracts\Hookable;

/**
 * Plain-form fallback for add/update/remove-from-basket, for visitors
 * without JS — assets/js/basket.js intercepts these same forms
 * (marked with data-scc-* attributes, see template-functions.php) and
 * calls BasketRestController instead so JS visitors get no full-page
 * reload. Both paths write through the same Basket class, so they can
 * never disagree about what's actually in the basket.
 */
final class BasketFormController implements Hookable
{
    public function register(): void
    {
        foreach (['add', 'update', 'remove'] as $action) {
            \add_action("admin_post_scc_basket_{$action}", [$this, $action]);
            \add_action("admin_post_nopriv_scc_basket_{$action}", [$this, $action]);
        }
    }

    public function add(): void
    {
        \check_admin_referer('scc_basket_add');

        Basket::forCurrentVisitor()->add(
            (int) ($_POST['product_id'] ?? 0),
            $this->variationField(),
            \max(1, (int) ($_POST['quantity'] ?? 1))
        );

        $this->redirectBack();
    }

    public function update(): void
    {
        \check_admin_referer('scc_basket_update');

        Basket::forCurrentVisitor()->update(
            (int) ($_POST['product_id'] ?? 0),
            $this->variationField(),
            (int) ($_POST['quantity'] ?? 0)
        );

        $this->redirectBack();
    }

    public function remove(): void
    {
        \check_admin_referer('scc_basket_remove');

        Basket::forCurrentVisitor()->remove(
            (int) ($_POST['product_id'] ?? 0),
            $this->variationField()
        );

        $this->redirectBack();
    }

    private function variationField(): ?int
    {
        return isset($_POST['variation']) && $_POST['variation'] !== '' ? (int) $_POST['variation'] : null;
    }

    private function redirectBack(): void
    {
        $redirectTo = isset($_POST['redirect_to']) ? \esc_url_raw(\wp_unslash($_POST['redirect_to'])) : \home_url('/');

        \wp_safe_redirect($redirectTo ?: \home_url('/'));
        exit;
    }
}
