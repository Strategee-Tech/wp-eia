<?php

function check_attachment_in_elementor($attachment_ids = [], $file_paths = []) {
    global $wpdb;

    if (empty($file_paths) && empty($attachment_ids)) {
        return [];
    }

    // Post types Elementor
    $post_types = [
        'post','page','custom_post_type','lp_course','service','portfolio',
        'gva_event','gva_header','footer','team','elementskit_template',
        'elementskit_content','elementor_library'
    ];
    $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    // --- Construir patrones REGEXP ---
    $elementor_data_regex = '';
    $elementor_css_regex  = '';

    if (!empty($file_paths)) {
        // Normaliza paths
        $file_paths = array_map('urldecode', $file_paths);

        // Patrón para JSON (_elementor_data) -> con doble backslash
        $elementor_data_regex = implode('|', array_map(function($path) {
            $info = pathinfo($path);
            $escaped = str_replace('/', '\\\\/', preg_quote($info['dirname'] . '/' . $info['filename'], '/'));
            return $escaped . '(?:-(?:[0-9]+x[0-9]+|scaled))?\\.' . preg_quote($info['extension'], '/');
        }, $file_paths));

        // Patrón para CSS (_elementor_css)
        $elementor_css_regex = implode('|', array_map(function($path) {
            $info = pathinfo($path);
            $escaped = preg_quote($info['dirname'] . '/' . $info['filename'], '/');
            return $escaped . '(?:-(?:[0-9]+x[0-9]+|scaled))?\\.' . preg_quote($info['extension'], '/');
        }, $file_paths));
    }

    // Patrón para IDs en JSON ("id":12345)
    $regex_attachment_ids_json = '';
    if (!empty($attachment_ids)) {
        $regex_attachment_ids_json = '"id":(' . implode('|', array_map('intval', $attachment_ids)) . ')';
    }

    // IN para _thumbnail_id
    $thumbnail_in = !empty($attachment_ids) ? implode(',', array_map('intval', $attachment_ids)) : '';

    // --- Query ---
    $sql = "
        SELECT pm.meta_key, pm.meta_id, pm.post_id, pm.meta_value
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE p.post_type IN ($placeholders)
        AND p.post_status IN('publish','private','draft')
        AND (
            (
                pm.meta_key = '_elementor_data'
                AND (
                    " . (!empty($elementor_data_regex) ? "pm.meta_value REGEXP %s" : "1=0") . "
                    " . (!empty($regex_attachment_ids_json) ? "OR pm.meta_value REGEXP %s" : "") . "
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

    // Parámetros dinámicos
    $params = $post_types;
    if (!empty($elementor_data_regex)) {
        $params[] = $elementor_data_regex;
    }
    if (!empty($regex_attachment_ids_json)) {
        $params[] = $regex_attachment_ids_json;
    }
    if (!empty($elementor_css_regex)) {
        $params[] = $elementor_css_regex;
    }

    $query   = $wpdb->prepare($sql, $params);
    $results = $wpdb->get_results($query, ARRAY_A);

    // --- Procesar resultados ---
    foreach ($results as $row) {

        $attachment_id = null; // Inicializar en null

        switch ($row['meta_key']) {

            case '_thumbnail_id' :
                // Para _thumbnail_id, el meta_value es el ID directamente
                $attachment_id = (int) $row['meta_value'];
                break;
                
            case '_elementor_data':
                // Para _elementor_data, buscar el ID en el JSON
                // Esto requiere un análisis más profundo del JSON.
                // Aquí se muestra una forma simplificada de buscar IDs pasados.

                if (!empty($attachment_ids)) {
                    foreach ($attachment_ids as $id_to_find) {
                        // Buscar el patrón "id":<ID> en el JSON
                        if (strpos($row['meta_value'], '"id":' . intval($id_to_find)) !== false) {
                            $attachment_id = intval($id_to_find);
                            break; // Encontramos uno, no necesitamos buscar más en este meta_value
                        }
                    }
                    if($attachment_id){
                        break;
                    }
                }
                if(!empty($file_paths)){
                    foreach ($file_paths as $path) {
                        $attachment_id = get_attachment_id_by_path($path);

                        if ($attachment_id) {
                            $attachment_id = (int) $attachment_id;
                            break;
                        }
                    }
                }
                break;
            default:

                // Si también se buscaron por file_paths en elementor_data
                if (!empty($file_paths)) {
                    
                    foreach ($file_paths as $path) {
                        $attachment_id = get_attachment_id_by_path($path);

                        if ($attachment_id) {
                            $attachment_id = (int) $attachment_id;
                            break;
                        }
                    }
                }
                break;
        }

        // Añadir el ID del attachment encontrado a la fila
        $row['attachment_id'] = $attachment_id;
        $processed_results[] = $row;

        
    }

    $res = array_fill_keys($attachment_ids, 0);
    
    // 2. Marcar como encontrados los IDs que realmente aparecieron en los resultados de la consulta
    foreach ($processed_results as $row) {
        if (isset($row['attachment_id']) && in_array($row['attachment_id'], $attachment_ids)) {
            $res[$row['attachment_id']] = 1;
        }
    }
    return $res; 
}