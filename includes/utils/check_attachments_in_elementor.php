<?php
/**
 * Realiza una consulta optimizada para verificar el uso de múltiples adjuntos
 * en la meta de Elementor y otros.
 *
 * @param array $attachments_data Array de arrays/objetos, cada uno con 'attachment_id' y 'file_path_relative'.
 * @param array $meta_keys_to_check Array de meta_keys a buscar (ej. ['_elementor_data', '_elementor_css', '_thumbnail_id']).
 * @return array Un array asociativo donde la clave es 'attachment_id' y el valor es true/false si se encontró uso.
 */
function check_attachments_in_elementor( $attachments_data ) {
    global $wpdb;

    $meta_keys_to_check = ['_elementor_data', '_elementor_css', '_thumbnail_id', 'enclosure'];

    if ( empty( $attachments_data ) || empty( $meta_keys_to_check ) ) {
        return [];
    }

    $attachment_ids_to_check = [];
    $basenames_to_check = [];
    $search_params = [];
    $where_or_conditions = [];

    // 1. Recolectar todos los IDs y basenames únicos, y construir las condiciones LIKE/Equality
    foreach ( $attachments_data as $attachment ) {
        $attachment_id = (int) $attachment['attachment_id'];
        $file_path_relative = $attachment['file_path_relative'];
        $basename = basename( $file_path_relative ); // Obtener solo el nombre del archivo

        // Evitar duplicados y asegurar que solo procesamos archivos con rutas válidas
        if ( ! in_array( $attachment_id, $attachment_ids_to_check ) && $attachment_id > 0 ) {
            $attachment_ids_to_check[] = $attachment_id;
            // Añadir la condición de igualdad por ID (para _thumbnail_id, etc.)
            $where_or_conditions[] = "pm.meta_value = %d";
            $search_params[] = $attachment_id;
        }

        if ( ! in_array( $basename, $basenames_to_check ) && ! empty( $basename ) ) {
            $basenames_to_check[] = $basename;
            // Añadir la condición LIKE para el basename
            $where_or_conditions[] = "pm.meta_value LIKE %s";
            $search_params[] = '%' . $wpdb->esc_like( $basename ) . '%';
        }

        // Añadir la condición LIKE para el ID en formato JSON (para Elementor _elementor_data)
        // Solo si no es un ID duplicado
        if ( ! in_array( '%"id":' . $attachment_id . '%', $search_params ) && $attachment_id > 0 ) {
             $where_or_conditions[] = "pm.meta_value LIKE %s";
             $search_params[] = '%' . $wpdb->esc_like( '"id":' . $attachment_id ) . '%';
        }
    }

    if ( empty( $where_or_conditions ) || empty( $attachment_ids_to_check ) ) {
        return []; // No hay nada que buscar
    }

    // Preparar la lista de meta_keys para la cláusula IN
    $meta_key_placeholders = implode(', ', array_fill(0, count($meta_keys_to_check), '%s'));
    $meta_key_list = '(' . $meta_key_placeholders . ')';

    // Construir la cláusula OR para las condiciones de búsqueda de valores
    $value_conditions_sql = '(' . implode(' OR ', $where_or_conditions) . ')';

    // 2. Construir la consulta SQL
    $query_sql = "
        SELECT DISTINCT pm.post_id AS content_post_id, pm.meta_value, p.ID AS attachment_id_in_query
        FROM {$wpdb->prefix}postmeta pm
        JOIN {$wpdb->prefix}posts p ON pm.post_id = p.ID
        WHERE pm.meta_key IN {$meta_key_list}
        AND {$value_conditions_sql}
        AND p.post_status IN ('publish', 'private', 'draft', 'revision')
    ";

    // 3. Preparar los parámetros para wpdb->prepare
    // Primero, los meta_keys
    $final_params = $meta_keys_to_check;
    // Luego, los search_params (LIKEs e IDs)
    $final_params = array_merge($final_params, $search_params);

    // Ejecutar la consulta preparada
    $prepared_query = $wpdb->prepare(
        $query_sql,
        ...$final_params
    );

    $results = $wpdb->get_results($prepared_query, ARRAY_A);

    // 4. Procesar los resultados para determinar el uso de cada adjunto original
    $in_use_status = [];
    foreach ( $attachments_data as $attachment ) {
        $attachment_id = (int) $attachment['attachment_id'];
        $file_path_relative = $attachment['file_path_relative'];
        $basename = basename( $file_path_relative );
        $is_in_use = false;

        foreach ( $results as $row ) {
            // Check by ID (for _thumbnail_id or direct ID references)
            if ( $row['meta_value'] == $attachment_id ) {
                $is_in_use = true;
                break;
            }
            // Check in css
            if ( str_contains( $row['meta_value'], $file_path_relative ) ) {
                $is_in_use = true;
                break;
            }

            $file_path_relative_decoded = str_replace('/', '\/', $file_path_relative);
            if ( str_contains( $row['meta_value'], $file_path_relative_decoded ) ) {
                $is_in_use = true;
                break;
            }
            // Check by JSON ID (for Elementor data)
            if ( str_contains( $row['meta_value'], '"id":' . $attachment_id ) ) {
                $is_in_use = true;
                break;
            }
        }
        $in_use_status[ $attachment_id ] = $is_in_use;
    }

    return $in_use_status;
}



// --- Ejemplo de uso ---
// Supongamos que $attachments_in_folder es el array que recibes de tu consulta principal
/*
$attachments_in_folder = [
    ['attachment_id' => 101, 'file_path_relative' => '2024/07/image1.jpg'],
    ['attachment_id' => 102, 'file_path_relative' => '2024/07/doc.pdf'],
    ['attachment_id' => 103, 'file_path_relative' => '2024/07/image2.png'],
    // ... hasta 20 o más registros
];
*/

// Meta keys que quieres buscar para referencias a adjuntos
//$meta_keys = ['_elementor_data', '_elementor_css', '_thumbnail_id'];

// Llamar a la función optimizada
// En tu función getPaginatedFiles, justo antes de empezar el bucle foreach para $attachments_in_folder,
// podrías llamar a esta función:

/*
$files_data_for_check = [];
foreach ($attachments_in_folder as $att) {
    $files_data_for_check[] = [
        'attachment_id' => $att['attachment_id'],
        'file_path_relative' => $att['file_path_relative']
    ];
}

$elementor_usage_map = check_attachments_usage_optimized($files_data_for_check, $meta_keys);

// Luego, en tu bucle foreach para $attachments_in_folder:
foreach ($attachments_in_folder as &$attachment) {
    // ... tu lógica existente ...

    $attachment_id = $attachment['attachment_id'];
    // $attachment['in_elementor'] = true; // Elimina la lógica anterior del bucle aquí
    // Consulta el mapa de uso que generaste con la única consulta
    $attachment['in_elementor'] = isset($elementor_usage_map[$attachment_id]) ? $elementor_usage_map[$attachment_id] : false;

    // ... el resto de tu lógica para stg_status_in_use, etc.
}
*/