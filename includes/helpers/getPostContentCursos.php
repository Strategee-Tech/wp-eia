<?php
function get_post_content_cursos($path) {
    global $wpdb;

    // Crear patrón seguro para REGEXP
    $info    = pathinfo($path);
    $pattern = preg_quote($info['dirname'] . '/' . $info['filename'], '/');

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


