<?php

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Products and orders are left in place on uninstall — a shop's order
// history is real business data, not plugin config, and deleting it
// silently on a WordPress "uninstall" click (as opposed to a deliberate
// data-wipe action) would be a nasty surprise. Only this plugin's own
// settings option is removed.
delete_option('scc_settings');
