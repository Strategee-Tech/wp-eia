<?php
function get_elementor_data($path) {
    global $wpdb;

    // Escapar las barras para el REGEXP en MySQL
    $pattern = str_replace('/', '\\\\/', $path);

    // Construir el query con prepare
    $sql = $wpdb->prepare("
        SELECT pm.post_id, pm.meta_key, pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_status IN ('publish','private','draft')
          AND p.post_type IN (
            'post','page','custom_post_type','lp_course','service','portfolio',
            'gva_event','gva_header','footer','team','elementskit_template',
            'elementskit_content','elementor_library'
          )
          AND pm.meta_key = '_elementor_data'
          AND pm.meta_value REGEXP %s
        LIMIT 1
    ", $pattern);

    // 3. Ejecuta
    $result = $wpdb->get_var($sql);
    return $result ? true : false;
}


