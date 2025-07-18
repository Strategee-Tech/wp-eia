<?php
function get_post_content_cursos($path) {
    global $wpdb;
    
    // Extraer directorio y nombre sin extensión
    $info     = pathinfo($path);
    $dirname  = isset($info['dirname'])  ? $info['dirname']  : '';
    $filename = isset($info['filename']) ? $info['filename'] : '';

    // Normalizar el patrón para REGEXP (ejemplo: 2025/05/mi-imagen)
    $relative_path = $dirname . '/' . $filename;
    $pattern  = preg_quote($relative_path, '/'); // Escapar caracteres especiales

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


