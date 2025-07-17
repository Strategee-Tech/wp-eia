<?php
/**
 * Realiza una consulta optimizada para verificar si múltiples adjuntos están
 * en uso dentro del post_content de varios tipos de posts.
 *
 * @param array  $relative_paths      Array de rutas relativas de archivos (ej. '2024/07/imagen.jpg').
 * @return array Un array asociativo donde la clave es la ruta relativa del archivo y el valor es un booleano (true si está en uso).
 */
// function check_attachments_in_content( $relative_paths ) {
//     global $wpdb; 
//     $usage_map      = array_fill_keys( $relative_paths, false );
//     $pattern        = implode('|', array_map(function($path) {
//         $path_parts = pathinfo($path);
//         return preg_quote($path_parts['dirname'] . '/' . $path_parts['filename'], '/');
//     }, $relative_paths));
 
//     $query_sql = "
//         SELECT ID, post_content
//         FROM {$wpdb->posts}
//         WHERE post_status IN ('publish', 'private', 'draft')
//         AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content', 'elementor_library')
//         AND post_content REGEXP %s
//     ";
 
//     $prepared_query = $wpdb->prepare($query_sql, $pattern);
//     $found_posts    = $wpdb->get_results($prepared_query, ARRAY_A);

//     $combinedPattern = '/' . implode('|', array_map(function($path) {
//     $path_parts  = pathinfo($path);
//     return preg_quote($path_parts['dirname'] . '/' . $path_parts['filename'], '/')
//             . '(?:-(?:[0-9]+x[0-9]+|[0-9\.]+))?\.[a-zA-Z]{2,5}';
//     }, $relative_paths)) . '/';

//     foreach ($found_posts as $post) {
//         if (preg_match_all($combinedPattern, $post['post_content'], $matches)) {
//             foreach ($matches[0] as $match) {
//                 foreach ($relative_paths as $path) {
//                     if (strpos($match, pathinfo($path, PATHINFO_FILENAME)) !== false) {
//                         $usage_map[$path] = true;
//                     }
//                 }
//             }
//         }
//     }
//     return $usage_map;
// }

/**
 * Verifica si múltiples archivos están en uso dentro del post_content de WordPress.
 * Compatible con imágenes (con variaciones) y documentos (PDF, DOC, etc.).
 *
 * @param array $relative_paths Array de rutas relativas (ej. '2025/06/archivo.pdf' o '2024/07/imagen.jpg').
 * @return array Array asociativo: [ 'archivo' => true|false ] indicando si está en uso.
 */
function check_attachments_in_content( $relative_paths ) {
    global $wpdb;

    // Normaliza rutas para manejar caracteres especiales codificados (%20, %E0%B8%82, etc.)
    $relative_paths = array_map('urldecode', $relative_paths);

    // Mapa de resultados
    $usage_map = array_fill_keys($relative_paths, false);

    // ✅ Patrón para consulta SQL (ignora parámetros, solo busca dirname/filename)
    $sql_pattern = implode('|', array_map(function($path) {
        $info = pathinfo($path);
        return preg_quote($info['dirname'] . '/' . $info['filename'], '/');
    }, $relative_paths));

    // Consulta posts que potencialmente contienen los archivos
    $query_sql = "
        SELECT ID, post_content
        FROM {$wpdb->posts}
        WHERE post_status IN ('publish','private','draft')
        AND post_type IN ('post','page','custom_post_type','lp_course','service','portfolio','gva_event','gva_header','footer','team','elementskit_template','elementskit_content','elementor_library')
        AND post_content REGEXP %s
    ";

    $found_posts = $wpdb->get_results($wpdb->prepare($query_sql, $sql_pattern), ARRAY_A);

    if (empty($found_posts)) {
        return $usage_map;
    }

    // ✅ Patrón exacto para coincidencias en el contenido (soporta imágenes con tamaños)
    $combinedPattern = '/' . implode('|', array_map(function($path) {
        $info = pathinfo($path);
        $escaped = preg_quote($info['dirname'] . '/' . $info['filename'], '/');
        $ext = preg_quote($info['extension'], '/');

        // Para imágenes (jpg|jpeg|png|gif|webp|avif) -> permitir variaciones tipo -150x150 o -scaled
        if (preg_match('/^(jpg|jpeg|png|gif|webp|avif)$/i', $info['extension'])) {
            return $escaped . '(?:-(?:[0-9]+x[0-9]+|scaled))?\.' . $ext;
        }

        // Para otros archivos (pdf, doc, etc.) -> coincidencia exacta
        return $escaped . '\.' . $ext;
    }, $relative_paths)) . '/i';

    // ✅ Buscar coincidencias reales en los posts
    foreach ($found_posts as $post) {
        $content = urldecode($post['post_content']); // Decodifica contenido
        if (preg_match_all($combinedPattern, $content, $matches)) {
            foreach ($matches[0] as $match) {
                foreach ($relative_paths as $path) {
                    if (strpos($match, basename($path)) !== false) {
                        $usage_map[$path] = true;
                    }
                }
            }
        }
    }

    return $usage_map;
}
