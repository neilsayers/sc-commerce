<?php

namespace SCCommerce\Basket;

use SCCommerce\Contracts\Hookable;

/**
 * Folds a guest's cookie basket into their account the moment they
 * log in, so adding items while browsing as a guest and then signing
 * in at checkout doesn't silently lose them.
 */
final class BasketMerger implements Hookable
{
    private const COOKIE_NAME = 'scc_basket';

    public function register(): void
    {
        \add_action('wp_login', [$this, 'mergeOnLogin'], 10, 2);
    }

    public function mergeOnLogin(string $userLogin, \WP_User $user): void
    {
        if (! isset($_COOKIE[self::COOKIE_NAME])) {
            return;
        }

        $guestItems = \json_decode(\stripslashes($_COOKIE[self::COOKIE_NAME]), true);

        if (! \is_array($guestItems) || $guestItems === []) {
            return;
        }

        $basket = Basket::forCurrentVisitor(); // wp_login fires after wp_set_current_user, so this already reads the user's own basket

        foreach ($guestItems as $item) {
            if (! isset($item['product_id'], $item['quantity'])) {
                continue;
            }

            $basket->add(
                (int) $item['product_id'],
                isset($item['variation']) && $item['variation'] !== null ? (int) $item['variation'] : null,
                (int) $item['quantity']
            );
        }

        \setcookie(self::COOKIE_NAME, '', [
            'expires' => \time() - \YEAR_IN_SECONDS,
            'path' => '/',
        ]);
    }
}
