<?php
function get_post_content_posts($path) {
    global $wpdb;

    // Extraer directorio y nombre sin extensión
    $info     = pathinfo($path);
    $dirname  = isset($info['dirname'])  ? $info['dirname']  : '';
    $filename = isset($info['filename']) ? $info['filename'] : '';

    // Normalizar el patrón para REGEXP (ejemplo: 2025/05/mi-imagen)
    $relative_path = $dirname . '/' . $filename;
    $pattern  = preg_quote($relative_path, '/'); // Escapar caracteres especiales

    // Query optimizada
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



