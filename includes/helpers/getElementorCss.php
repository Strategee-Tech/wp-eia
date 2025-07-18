<?php
function get_elementor_css($path) {
    global $wpdb;

    // Construir el patrón REGEXP dinámicamente
    $pattern = preg_quote($path, '/'); 
    $pattern .= '(\\.(jpg|jpeg|png|webp|gif|svg|heic|avif|bmp|tiff))?';

    // Consulta preparada
    $sql = $wpdb->prepare(
        "SELECT COUNT(*) 
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE p.post_status IN ('publish','private','draft')
           AND p.post_type IN (
                'post','page','custom_post_type','lp_course','service','portfolio',
                'gva_event','gva_header','footer','team','elementskit_template',
                'elementskit_content','elementor_library'
           )
           AND pm.meta_key = '_elementor_css'
           AND pm.meta_value REGEXP %s",
           LIMIT 1
        $pattern 
    );
    $result = $wpdb->get_var($sql);
    return $result ? true : false;
}
