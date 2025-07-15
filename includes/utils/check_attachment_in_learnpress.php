<?php
/**
 * Realiza una consulta optimizada para verificar si múltiples adjuntos están
 * en uso dentro del post_content de cursos de LearnPress.
 *
 * @param array  $relative_paths      Array de rutas relativas de archivos (ej. '2024/07/imagen.jpg').
 * @return array Un array asociativo donde la clave es la ruta relativa del archivo y el valor es un booleano (true si está en uso).
 */
function check_attachment_in_learnpress( $relative_paths ) {
    global $wpdb;

    if ( empty( $relative_paths ) ) {
        return [];
    }

    $content_like_conditions = [];
    $query_params = [];
    $usage_map = array_fill_keys( $relative_paths, false ); // Inicializa todos como no usados

    // Construir las condiciones LIKE para cada ruta relativa
    foreach ( $relative_paths as $path ) {
        $content_like_conditions[] = "post_content LIKE %s";
        $query_params[] = '%' . $wpdb->esc_like( $path ) . '%';
    }

    // Unir las condiciones LIKE con OR
    $where_content_clause = implode( ' OR ', $content_like_conditions );

    // Construir la consulta SQL principal
    $query_sql = "
        SELECT ID, post_content
        FROM {$wpdb->prefix}learnpress_courses
        WHERE ({$where_content_clause})
        AND post_status IN ('publish', 'private', 'draft', 'revision)
    ";

    // Preparar y ejecutar la consulta
    $prepared_query = $wpdb->prepare(
        $query_sql,
        ...$query_params
    );

    $found_courses = $wpdb->get_results( $prepared_query, ARRAY_A );

    // Recorrer los resultados para determinar el uso de cada archivo
    foreach ( $found_courses as $course ) {
        foreach ( $relative_paths as $path ) {
            if ( str_contains( $course['post_content'], $path ) ) {
                $usage_map[ $path ] = true;
                // Una vez que encontramos que una ruta está en uso, no necesitamos seguir buscando para esa ruta
                // dentro de los resultados de esta consulta.
            }
        }
    }

    return $usage_map;
}

// --- Ejemplo de uso y cómo integrarlo en tu `getPaginatedFiles` ---

// Asegúrate de que `wp_upload_dir()['baseurl']` esté disponible en tu contexto.
// Por ejemplo, al inicio de `getPaginatedFiles`:
// $current_base_upload_url = wp_upload_dir()['baseurl'];

/*
// 1. Recolectar las rutas relativas de los archivos que se quieren verificar en LearnPress
$paths_to_check_in_learnpress = [];
foreach ($attachments_in_folder as $att_item) {
    if (!empty($att_item['file_path_relative'])) {
        $paths_to_check_in_learnpress[] = $att_item['file_path_relative'];
    }
}

// 2. Llamar a la función optimizada para LearnPress
$learnpress_usage_map = check_learnpress_usage_optimized(
    $paths_to_check_in_learnpress,
    $current_base_upload_url
);

// ... (El resto de tu código getPaginatedFiles) ...

// Luego, en tu bucle `foreach ($attachments_in_folder as &$attachment)`:
foreach ($attachments_in_folder as &$attachment) {
    // ... tu lógica existente ...

    $file_path_relative_decoded = str_replace('/', '/', $attachment['file_path_relative']);

    // Reemplaza la lógica anterior para 'in_programs'
    $attachment['in_programs'] = isset($learnpress_usage_map[$file_path_relative_decoded]) ? $learnpress_usage_map[$file_path_relative_decoded] : false;

    // ... el resto de tu lógica para 'in_content', 'in_elementor', 'stg_status_in_use', etc.
    
    // Y la condición final para current_in_use_status:
    // $current_in_use_status = ($attachment['in_content'] || $attachment['in_programs'] || $attachment['in_elementor']) ? 'En Uso' : 'Sin Uso';
}
*/