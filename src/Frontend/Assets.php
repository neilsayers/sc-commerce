<?php

namespace SCCommerce\Frontend;

use SCCommerce\Contracts\Hookable;

final class Assets implements Hookable
{
    public function register(): void
    {
        \add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(): void
    {
        \wp_enqueue_style('scc-frontend', SCC_URL.'assets/css/frontend.css', [], SCC_VERSION);

        \wp_enqueue_script('scc-basket', SCC_URL.'assets/js/basket.js', [], SCC_VERSION, true);
        // Depends on scc-basket purely so its wp_localize_script() data
        // below (window.sccCommerce) is guaranteed to print first,
        // regardless of enqueue order — both scripts read it.
        \wp_enqueue_script('scc-order-status', SCC_URL.'assets/js/order-status.js', ['scc-basket'], SCC_VERSION, true);
        // No dependency on scc-basket: this one only reads the variant
        // data already embedded in the page's own markup and never
        // touches window.sccCommerce/REST, unlike the two above.
        \wp_enqueue_script('scc-product-variant-selector', SCC_URL.'assets/js/product-variant-selector.js', [], SCC_VERSION, true);

        $sccCommerce = [
            'restUrl' => \esc_url_raw(\rest_url('scc/v1/')),
            'nonce' => \wp_create_nonce('wp_rest'),
        ];

        // Both scripts read window.sccCommerce; wp_localize_script()
        // only needs to attach the data once, on either handle — it's
        // a plain inline <script> defining a global, not something
        // tied to the specific handle it's attached to.
        \wp_localize_script('scc-basket', 'sccCommerce', $sccCommerce);
    }
}
