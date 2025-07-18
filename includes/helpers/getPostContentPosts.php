<?php
function get_post_content_posts($path) {
    global $wpdb;

    $info     = pathinfo($path);
    $dirname  = isset($info['dirname'])  ? $info['dirname']  : '';
    $filename = isset($info['filename']) ? $info['filename'] : '';

    // Crear patrón para buscar con o sin extensión
    $relative_path = $dirname . '/' . $filename;
    $pattern       = preg_quote($relative_path, '/') . '(\\.[a-zA-Z0-9]+)?';

    // Query
    $sql = $wpdb->prepare(
        "SELECT ID
         FROM {$wpdb->posts}
         WHERE post_status IN ('publish','private','draft')
           AND post_type IN (
               'post','page','custom_post_type','lp_course','service','portfolio',
               'gva_event','gva_header','footer','team','elementskit_template',
               'elementskit_content','elementor_library'
           )
           AND post_content REGEXP %s
         LIMIT 1",
        $pattern
    );
    $result = $wpdb->get_var($sql);
    return $result ? true : false;
}



