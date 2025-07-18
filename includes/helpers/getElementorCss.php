<?php
function get_elemetor_css($path) {
    global $wpdb;

    // Escapar la cadena para REGEXP
    $pattern = preg_quote($path, '/'); // ejemplo: 2025\/03\/mi\-imagen\.jpg

    $sql = $wpdb->prepare(
        "SELECT pm.post_id 
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_elementor_css'
           AND pm.meta_value REGEXP %s
           AND p.post_status IN ('publish','private','draft')
         LIMIT 1",
        $pattern
    );
    $result = $wpdb->get_var($sql); 
    return $result ? true : false;
}
