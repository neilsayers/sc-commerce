<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\ProductPostType;

/**
 * Prepends the buy box (template-functions.php's scc_the_product_buy_box())
 * to a product's content on its single view, so a product is
 * immediately usable on any theme (including this plugin's own
 * testbed) with zero template work. A real client theme would more
 * likely override this — via remove_filter on 'the_content' with this
 * class's appendBuyBox(), or its own single-scc_product.php template
 * calling scc_the_product_buy_box() wherever it wants — than rely on
 * it long-term.
 */
final class ProductContent implements Hookable
{
    public function register(): void
    {
        \add_filter('the_content', [$this, 'appendBuyBox']);
    }

    public function appendBuyBox(string $content): string
    {
        if (! \is_singular(ProductPostType::POST_TYPE) || ! \in_the_loop() || ! \is_main_query()) {
            return $content;
        }

        \ob_start();
        \scc_the_product_buy_box(\get_the_ID());
        $buyBox = \ob_get_clean();

        return $content.$buyBox;
    }
}
