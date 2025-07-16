<?php

function check_attachment_in_content( $relative_paths = [], $post_ids = [] ) {
    global $wpdb;

    if (empty($relative_paths)) {
        return [];
    }

    // Post types permitidos
    $post_types_to_check = ['post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content', 'elementor_library'];

    // Generar placeholders para post types
    $post_types_placeholders = implode(',', array_fill(0, count($post_types_to_check), '%s'));

    // Construir patrones REGEXP para archivos en _elementor_data y _elementor_css
    if (!empty($relative_paths)) {
        // Escapamos los nombres de archivo para REGEXP
        $escaped_files = array_map(function($path) {
            return preg_quote($path, '/'); // Ej: 2025/03/descarga\.png
        }, $relative_paths);

        // Para _elementor_css y enclosure
        $elementor_css_regex = implode('|', $escaped_files);
    } else {
        $elementor_css_regex = '';
    }

    // Armar query dinámica
    $sql = "
        SELECT
               p.ID AS post_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type IN ($post_types_placeholders)
            AND p.post_status IN('publish','private','draft')
            AND (
                pm.meta_key IN('_elementor_css','enclosure')
                AND " . (!empty($elementor_css_regex) ? "p.post_content REGEXP %s" : "1=0") . "
            )
    ";

    // Preparar parámetros
    $params = $post_types_to_check;
    if (!empty($elementor_css_regex)) {
        $params[] = $elementor_css_regex;
    }
    if (!empty($relative_paths)) {
        $params[] = $relative_paths;
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
                if(!empty($relative_paths)){
                    foreach ($relative_paths as $path) {
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

    $res = [];
    foreach($post_ids as $id){
        foreach($processed_results as $row){
            if($row['post_id'] == $id){
                $res[$id] = 1;
                break;
            } else {
                $res[$id] = 0;
            }
        }
    }
    
    return $res;
}