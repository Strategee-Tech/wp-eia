<?php

function check_attachment_in_elementor($attachment_ids = [], $file_paths = [] ) {
    global $wpdb;

    if (empty($file_paths) && empty($attachment_ids)) {
        return [];
    }

    // Post types permitidos
    $post_types = [
        'post','page','custom_post_type','lp_course','service','portfolio',
        'gva_event','gva_header','footer','team','elementskit_template',
        'elementskit_content','elementor_library'
    ];

    // Generar placeholders para post types
    $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    // Construir patrones REGEXP para archivos en _elementor_data y _elementor_css
    $regex_files_elementor = '';
    if (!empty($file_paths)) {
        // Escapamos los nombres de archivo para REGEXP
        $escaped_files = array_map(function($path) {
            return preg_quote($path, '/'); // Ej: 2025/03/descarga\.png
        }, $file_paths);

        // Para _elementor_data (dobles backslashes)
        $elementor_data_regex = implode('|', array_map(function($path) {
            return str_replace('/', '\\\\/',$path); // \/2025\/03\/descarga\.png
        }, $escaped_files));

        // Para _elementor_css y enclosure
        $elementor_css_regex = implode('|', $escaped_files);
    } else {
        $elementor_data_regex = '';
        $elementor_css_regex = '';
    }

    // Construir patrón para IDs en JSON ("id":12345)
    $regex_attachment_ids = '';
    if (!empty($attachment_ids)) {
        $regex_attachment_ids = implode('|', array_map('intval', $attachment_ids));
    }

    // Construir IN() para _thumbnail_id
    $thumbnail_in = '';
    if (!empty($attachment_ids)) {
        $thumbnail_in = implode(',', array_map('intval', $attachment_ids));
    }

    // Armar query dinámica
    $sql = "
        SELECT pm.meta_key,
               pm.meta_id AS meta_id,
               pm.post_id AS post_id,
               pm.meta_value AS meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type IN ($post_types_placeholders)
        AND p.post_status IN('publish','private','draft')
        AND (
            (
                pm.meta_key IN('_elementor_data') 
                AND (
                    " . (!empty($elementor_data_regex) ? "pm.meta_value REGEXP %s" : "1=0") . "
                    " . (!empty($regex_attachment_ids) ? "OR pm.meta_value REGEXP %s" : "") . "
                )
            )
            OR
            (
                pm.meta_key IN('_elementor_css','enclosure')
                AND " . (!empty($elementor_css_regex) ? "pm.meta_value REGEXP %s" : "1=0") . "
            )
            " . (!empty($thumbnail_in) ? "OR (pm.meta_key = '_thumbnail_id' AND pm.meta_value IN ($thumbnail_in))" : "") . "
        )
    ";

    // Preparar parámetros
    $params = $post_types;
    if (!empty($elementor_data_regex)) {
        $params[] = $elementor_data_regex;
    }
    if (!empty($regex_attachment_ids)) {
        $params[] = '"id":(' . $regex_attachment_ids . ')';
    }
    if (!empty($elementor_css_regex)) {
        $params[] = $elementor_css_regex;
    }

    // Preparar query
    $query = $wpdb->prepare($sql, $params);

    // Ejecutar y devolver resultados
    return $wpdb->get_results($query, ARRAY_A);
}
