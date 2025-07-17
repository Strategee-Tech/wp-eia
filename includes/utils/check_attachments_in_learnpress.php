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

    // ✅ Normaliza rutas (decodifica %xx)
    $relative_paths = array_map('urldecode', $relative_paths);

    // Inicializa mapa de resultados
    $usage_map = array_fill_keys($relative_paths, false);

    // ✅ Patrón SQL básico (dirname + filename)
    $sql_pattern = implode('|', array_map(function($path) {
        $info = pathinfo($path);
        return preg_quote($info['dirname'] . '/' . $info['filename'], '/');
    }, $relative_paths));

    // ✅ Consulta LearnPress en tabla personalizada
    $query_sql = "
        SELECT ID, post_content
        FROM {$wpdb->prefix}learnpress_courses
        WHERE post_status IN ('publish', 'private', 'draft')
        AND post_content REGEXP %s
    ";

    $found_posts = $wpdb->get_results($wpdb->prepare($query_sql, $sql_pattern), ARRAY_A);

    if (empty($found_posts)) {
        return $usage_map;
    }

    // ✅ Patrón exacto para coincidencias (imágenes con variaciones + otros archivos)
    $combinedPattern = '/' . implode('|', array_map(function($path) {
        $info = pathinfo($path);
        $escaped = preg_quote($info['dirname'] . '/' . $info['filename'], '/');
        $ext = preg_quote($info['extension'], '/');

        // Para imágenes: permitir variaciones (-150x150, -scaled)
        if (preg_match('/^(jpg|jpeg|png|gif|webp|avif|svg|heic|tiff|bmp)$/i', $info['extension'])) {
            return $escaped . '(?:-(?:[0-9]+x[0-9]+|scaled))?\.' . $ext;
        }

        // Para otros archivos: coincidencia exacta
        return $escaped . '\.' . $ext;
    }, $relative_paths)) . '/i';

    // ✅ Analiza el contenido real de los cursos
    foreach ($found_posts as $post) {
        $content = urldecode($post['post_content']); // decodifica caracteres %xx
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
