<?php
function get_elementor_data($path) {
    global $wpdb;

    // 1. Normaliza el path (ej: "2025/05/1.4-2")
    // Escapamos para REGEXP MySQL:
    // - preg_quote() para caracteres especiales
    // - luego convertimos '/' en '\\\/' para que quede bien en MySQL
    $pattern = preg_quote($path, '/'); // Ej: 2025\/05\/1\.4\-2
    $pattern = str_replace('/', '\\\\\\/', $pattern); // Ahora: 2025\\\\/05\\\\/1\.4\-2

    // 2. Prepara el SQL con %s
    $sql = $wpdb->prepare(
        "SELECT pm.post_id
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_elementor_data'
           AND pm.meta_value REGEXP %s
           AND p.post_status IN ('publish','private','draft') 
           AND p.post_type IN (
            'post','page','custom_post_type','lp_course','service','portfolio',
            'gva_event','gva_header','footer','team','elementskit_template',
            'elementskit_content','elementor_library'
          )
        LIMIT 1",
        $pattern
    );

    // 3. Ejecuta
    $result = $wpdb->get_var($sql);
    return $result ? true : false;
}
