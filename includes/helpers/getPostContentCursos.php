<?php
function get_post_content_cursos($path) {
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
         FROM {$wpdb->prefix}learnpress_courses
         WHERE post_status IN ('publish', 'private', 'draft')
           AND post_content REGEXP %s
         LIMIT 1",
        $pattern
    );

    $result = $wpdb->get_var($sql);

    return $result ? true : false;
}


