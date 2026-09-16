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

        \wp_localize_script('scc-basket', 'sccCommerce', [
            'restUrl' => \esc_url_raw(\rest_url('scc/v1/')),
            'nonce' => \wp_create_nonce('wp_rest'),
        ]);
    }
}
