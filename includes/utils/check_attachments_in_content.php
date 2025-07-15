<?php
/**
 * Realiza una consulta optimizada para verificar si múltiples adjuntos están
 * en uso dentro del post_content de varios tipos de posts.
 *
 * @param array  $relative_paths      Array de rutas relativas de archivos (ej. '2024/07/imagen.jpg').
 * @param array  $post_types_to_check Array de tipos de posts a buscar.
 * @return array Un array asociativo donde la clave es la ruta relativa del archivo y el valor es un booleano (true si está en uso).
 */
function check_attachments_in_content( $relative_paths ) {
    global $wpdb;

    $post_types_to_check = ['post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content', 'elementor_library'];

    if ( empty( $relative_paths ) || empty( $post_types_to_check ) ) {
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

    // Preparar los placeholders para los tipos de post
    $post_type_placeholders = implode(', ', array_fill(0, count($post_types_to_check), '%s'));

    // Construir la consulta SQL principal
    $query_sql = "
        SELECT ID, post_content
        FROM {$wpdb->posts}
        WHERE ({$where_content_clause})
        AND post_status IN ('publish', 'private', 'draft')
        AND post_type IN ({$post_type_placeholders})
    ";

    // Unir los parámetros de post_content y los de post_type
    $final_params = array_merge( $query_params, $post_types_to_check );

    // Preparar y ejecutar la consulta
    $prepared_query = $wpdb->prepare(
        $query_sql,
        ...$final_params
    );

    $found_posts = $wpdb->get_results( $prepared_query, ARRAY_A );

    // Recorrer los resultados para determinar el uso de cada archivo
    foreach ( $found_posts as $post ) {
        foreach ( $relative_paths as $path ) {
            if ( str_contains( $post['post_content'], $path ) ) {
                $usage_map[ $path ] = true; 
                // Si encuentras el uso, puedes romper aquí para esta ruta
                // pero si una ruta puede aparecer en varios posts, querrás continuar el bucle externo.
                // Para el propósito de solo saber si "está en uso", esto es suficiente.
            }
        }
    }

    return $usage_map;
}

// --- Ejemplo de uso y cómo integrarlo en tu getPaginatedFiles ---

// Necesitarás obtener la base_upload_url (que generalmente es la URL del directorio uploads)
// puedes obtenerla con: wp_upload_dir()['baseurl']
// Esto debería estar disponible en el contexto de tu función.
// global $wpdb; // Ya definida en tu función

// Dentro de getPaginatedFiles, antes del bucle foreach($attachments_in_folder as &$attachment):

// 1. Recolectar las rutas relativas de los archivos que se quieren verificar
/*
$current_base_upload_url = wp_upload_dir()['baseurl']; // Asegúrate de obtener esto una vez
$paths_to_check_in_content = [];
$attachment_id_to_path_map = []; // Para mapear ID a path para el bucle final

foreach ($attachments_in_folder as $att_item) {
    if (!empty($att_item['file_path_relative'])) {
        $paths_to_check_in_content[] = $att_item['file_path_relative'];
        $attachment_id_to_path_map[$att_item['attachment_id']] = $att_item['file_path_relative'];
    }
}

$post_types_for_content_check = [
    'post', 'page', 'custom_post_type', 'lp_course', 'service',
    'portfolio', 'gva_event', 'gva_header', 'footer', 'team',
    'elementskit_template', 'elementskit_content', 'elementor_library'
];

// 2. Llamar a la función optimizada
$content_usage_map = check_content_usage_optimized(
    $paths_to_check_in_content,
    $current_base_upload_url,
    $post_types_for_content_check
);

// ... (El resto de tu código getPaginatedFiles) ...

// Luego, en tu bucle foreach ($attachments_in_folder as &$attachment):
foreach ($attachments_in_folder as &$attachment) {
    // ... tu lógica existente ...

    $attachment_id = $attachment['attachment_id'];
    $file_path_relative_decoded = str_replace('/', '/', $attachment['file_path_relative']);

    // Reemplaza la lógica anterior para 'in_content' y 'in_programs'
    // ya que esta nueva consulta lo cubre.
    // Solo necesitarás el mapeo para LearnPress si es una tabla separada como 'learnpress_courses'.
    // Si LearnPress usa wp_posts con 'lp_course' post_type, entonces esta nueva función lo cubre todo.

    // Si LearnPress usa una tabla separada (como en tu código original con $programas_sql),
    // mantén la consulta de $programas_sql y su bucle.
    // Si 'lp_course' en post_types_for_content_check es suficiente, entonces esto basta.
    
    // Asumiendo que 'lp_course' está en post_types_for_content_check,
    // y tu logic para $programas_sql ya no es necesaria aquí si los programas
    // también almacenan el contenido de la misma forma que otros posts.

    // Consulta el mapa de uso generado por la única consulta para 'in_content'
    $attachment['in_content'] = isset($content_usage_map[$file_path_relative_decoded]) ? $content_usage_map[$file_path_relative_decoded] : false;
    // Si $programas sigue siendo necesario (tabla separada), mantenlo
    // $attachment['in_programs'] = // ... tu lógica de programas ...

    // $attachment['in_elementor'] = // ... del ejemplo anterior con check_attachments_usage_optimized para meta ...

    $current_in_use_status = ($attachment['in_content'] || $attachment['in_programs'] || $attachment['in_elementor']) ? 'En Uso' : 'Sin Uso';
    
    // ... el resto de tu lógica para actualizar metadatos, etc.
}
*/