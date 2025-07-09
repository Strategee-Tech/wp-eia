<?php



function is_eia_plugin_page () {
    if(!is_admin() ) {
        return;
    }

    $currentPage = isset($_GET['page']) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

    if($currentPage === 'wp-eia' || $currentPage === 'images' || $currentPage === 'documents') {
        return true;
    }
    return false;
}

function disable_admin_notices() {
    if(is_eia_plugin_page()) {
        return;
    }

    remove_all_actions('admin_notices');
    remove_all_actions('all_admin_notices');
    remove_all_actions('user_admin_notices');

    if( is_multisite() ) {
        remove_all_actions('network_admin_notices');
    }
}

add_action('admin_head', 'disable_admin_notices');