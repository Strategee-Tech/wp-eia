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
    $regex_attachment_ids_json = ''; // Renombrado para evitar confusión
    if (!empty($attachment_ids)) {
        $regex_attachment_ids_json = implode('|', array_map('intval', $attachment_ids));
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

    // Preparar parámetros
    $params = $post_types;
    if (!empty($elementor_data_regex)) {
        $params[] = $elementor_data_regex;
    }
    if (!empty($regex_attachment_ids_json)) { // Usar el nuevo nombre de variable
        $params[] = '"id":(' . $regex_attachment_ids_json . ')';
    }
    if (!empty($elementor_css_regex)) {
        $params[] = $elementor_css_regex;
    }

    // Preparar query
    $query = $wpdb->prepare($sql, $params);

    // Ejecutar y obtener resultados
    $results = $wpdb->get_results($query, ARRAY_A);

    // --- PROCESAR RESULTADOS PARA EXTRAER EL ATTACHMENT ID ---
    $processed_results = [];
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
                
                }
            case '_elementor_css' || '_elementor_data':

                // Si también se buscaron por file_paths en elementor_data
                if (!empty($file_paths)) {
                    
                    foreach ($file_paths as $path) {
                        $sql = $wpdb->prepare(
                            "SELECT post_id FROM {$wpdb->postmeta} 
                                WHERE meta_key = '_wp_attached_file' 
                                AND meta_value = %s",
                                $path
                        );
                    
                        $attachment_id = $wpdb->get_var($sql);

                        if ($attachment_id) {
                            $attachment_id = (int) $attachment_id;
                            break;
                        }
                    }
                }
                break;
            case 'enclosure':
                // Para CSS o enclosure, si se busca por file_paths, es complicado obtener el ID
                // directo sin una consulta adicional a wp_posts.
                // Si el original $file_paths contenía rutas como 'uploads/2025/03/image.png'
                // se necesitaría buscar el attachment cuyo guid o post_name coincida.
                break;
        }

        // Añadir el ID del attachment encontrado a la fila
        $row['attachment_id'] = $attachment_id;
        $processed_results[] = $row;
    }

    return $processed_results;
}