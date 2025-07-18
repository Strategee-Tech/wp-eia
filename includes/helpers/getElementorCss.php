<?php
function get_elemetor_css($path) {
    global $wpdb;

    // Extraer directorio y nombre sin extensión
    $info     = pathinfo($path);
    $dirname  = isset($info['dirname'])  ? $info['dirname']  : '';
    $filename = isset($info['filename']) ? $info['filename'] : '';

    // Normalizar el patrón para REGEXP (ejemplo: 2025/05/mi-imagen)
    $relative_path = $dirname . '/' . $filename;
    $pattern  = preg_quote($relative_path, '/'); // Escapar caracteres especiales

    // Generar REGEXP para buscar esa cadena exacta sin importar la extensión
    // Ejemplo final: 2025/05/mi\-imagen
    $sql = $wpdb->prepare(
        "SELECT pm.post_id 
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_elementor_css'
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

    $result = $wpdb->get_var($sql); 
    return $result ? true : false;
}
