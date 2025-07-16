<?php



function check_attachment_in_elementor($attachment_id, $file_path_relative){
 
    global $wpdb;

    // Variables dinámicas
    $thumbnail_id       = $attachment_id; // ID opcional del attachment (Elementor)

    // 1. Escapar el patrón para REGEXP
    $pattern_escaped = preg_quote($file_path_relative, '/'); // "2025/03/descarga\.png"

    // 2. Para _elementor_data (dobles backslashes para JSON)
    $elementor_data_pattern = '\\\\/' . str_replace('/', '\\\\/', $file_path_relative); // "\/2025\/03\/descarga\.png"

    // 3. Para _elementor_css y otros (slash normal)
    $elementor_css_pattern = $pattern_escaped; // "2025/03/descarga\.png"

    // 4. Para buscar por ID en JSON (opcional)
    $elementor_id_pattern = '"id":' . intval($attachment_id);

    // Post types
    $post_types = [
        'post','page','custom_post_type','lp_course','service','portfolio',
        'gva_event','gva_header','footer','team','elementskit_template',
        'elementskit_content','elementor_library'
    ];
    $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    // Construcción de la query
    $sql = "
        SELECT pm.meta_key,
            MIN(pm.meta_id) AS meta_id,
            MIN(pm.post_id) AS post_id,
            MIN(pm.meta_value) AS meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type IN ($post_types_placeholders)
        AND p.post_status IN('publish','private','draft')
        AND (
            (
                pm.meta_key IN('_elementor_data') 
                AND (
                    pm.meta_value REGEXP %s 
                    OR pm.meta_value REGEXP %s
                )
            )
            OR
            (pm.meta_key IN('_elementor_css','enclosure') AND pm.meta_value REGEXP %s)
            OR
            (pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d)
        )
        GROUP BY pm.meta_key
    ";

    // Preparar la query con parámetros dinámicos
    $query = $wpdb->prepare(
        $sql,
        array_merge($post_types, [$elementor_data_pattern, $elementor_id_pattern, $elementor_css_pattern, $thumbnail_id])
    );

    // Ejecutar y obtener resultados

    $results = $wpdb->get_results($query, ARRAY_A);

    return count($results) > 0 ? true : false;

    // Ver resultados
    // echo '<pre>'; print_r($results); echo '</pre>';

        
    // echo "<pre>";
    // print_r($results);

    // die;

}