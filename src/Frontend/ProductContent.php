<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;
use SCCommerce\PostTypes\ProductPostType;
use SCCommerce\Products\Product;
use SCCommerce\Settings\Settings;

/**
 * Prepends price + Buy Now + Add to Basket to a product's content on
 * its single view, so a product is immediately usable on any theme
 * (including this plugin's own testbed) with zero template work.
 * A real client theme would more likely override this — via
 * remove_filter on scc_render_product_buttons, or its own
 * single-scc_product.php template — than rely on it long-term.
 */
final class ProductContent implements Hookable
{
    public function register(): void
    {
        \add_filter('the_content', [$this, 'appendBuyButtons']);
    }

    public function appendBuyButtons(string $content): string
    {
        if (! \is_singular(ProductPostType::POST_TYPE) || ! \in_the_loop() || ! \is_main_query()) {
            return $content;
        }

        $product = Product::get(\get_the_ID());

        if (! $product) {
            return $content;
        }

        \ob_start();
        ?>
        <div class="scc-product-buy-box">
            <p class="scc-product-price"><?php echo \esc_html($product->displayPrice((new Settings())->currency())); ?></p>
            <?php if (! $product->isVariable()) : ?>
                <div class="scc-product-actions">
                    <?php scc_add_to_basket_button($product->id()); ?>
                    <?php scc_buy_now_button($product->id()); ?>
                </div>
            <?php else : ?>
                <p><em>This product has options — variant selection isn't built into the testbed theme yet; see the plugin README's "Variable products" section.</em></p>
            <?php endif; ?>
        </div>
        <?php
        $buyBox = \ob_get_clean();

        return $content.$buyBox;
    }
}
