<?php

namespace SCCommerce\Setup;

use SCCommerce\Contracts\Hookable;

/**
 * Self-heals stale rewrite rules whenever this plugin's version
 * changes. Setup\Activator's activation-time flush only runs on an
 * actual deactivate/reactivate cycle — it does nothing for a site
 * (like this testbed, via its live symlink) where the plugin folder
 * is simply updated in place, so a rewrite-relevant change (e.g.
 * ProductPostType's 'rewrite' slug, or any new post type/taxonomy)
 * can leave the site's cached rewrite_rules option stale until
 * someone happens to resave Settings -> Permalinks by hand. Comparing
 * against SCC_VERSION means the actual flush_rewrite_rules() call —
 * a genuinely expensive operation — runs at most once per version
 * bump, not on every request.
 */
final class RewriteFlusher implements Hookable
{
    private const OPTION = 'scc_rewrite_flushed_version';

    public function register(): void
    {
        // Priority 20: after ProductPostType/OrderPostType/ProductTypeTaxonomy's
        // own 'init' registration (default priority 10), so the flush
        // this triggers reflects their current rewrite rules, not the
        // previous request's.
        \add_action('init', [$this, 'maybeFlush'], 20);
    }

    public function maybeFlush(): void
    {
        if (\get_option(self::OPTION) === SCC_VERSION) {
            return;
        }

        \flush_rewrite_rules();
        \update_option(self::OPTION, SCC_VERSION);
    }
}
