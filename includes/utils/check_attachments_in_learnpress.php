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
 
    if ( empty( $relative_paths )) {
        return [];
    }

    $content_like_conditions = [];
    $query_params = [];
    $usage_map = array_fill_keys( $relative_paths, false );

    // Construir condiciones LIKE (usa el path base sin extensión)
    foreach ( $relative_paths as $path ) {
        $path_parts = pathinfo($path);
        $base_path = $path_parts['dirname'] . '/' . $path_parts['filename'];
        $content_like_conditions[] = "post_content LIKE %s";
        $query_params[] = '%' . $wpdb->esc_like( $base_path ) . '%'; 
    }

    // Unir las condiciones LIKE con OR
    $where_content_clause = implode( ' OR ', $content_like_conditions ); 

    // Construir la consulta SQL principal
    $query_sql = "
        SELECT ID, post_content
        FROM {$wpdb->prefix}learnpress_courses
        WHERE ({$where_content_clause})
        AND post_status IN ('publish', 'private', 'draft')
    ";

    $final_params   = array_merge( $query_params );
    $prepared_query = $wpdb->prepare($query_sql, ...$final_params);

    $found_posts = $wpdb->get_results($prepared_query, ARRAY_A);

    foreach ( $found_posts as $post ) {
        foreach ( $relative_paths as $path ) {
            $path_parts = pathinfo($path);
            $base_path = preg_quote($path_parts['dirname'] . '/' . $path_parts['filename'], '/');

            // REGEX para capturar variantes:
            // - Nombre base
            // - Opcional sufijo "-150x150" o "-2"
            // - Cualquier extensión
            $pattern = "/{$base_path}(?:-(?:[0-9]+x[0-9]+|[0-9\.]+))?\.[a-zA-Z]{2,5}/";

            if ( preg_match($pattern, $post['post_content']) ) {
                $usage_map[$path] = true;
            }
        }
    }

    return $usage_map;
}
