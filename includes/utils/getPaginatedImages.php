<?php

/**
 * Obtiene imágenes paginadas de TODAS las carpetas de uploads de WordPress,
 * incluyendo metadatos para SEO y detalles de paginación.
 *
 * @param int    $page      El número de página actual (por defecto 1).
 * @param int    $per_page  El número de elementos por página (por defecto 10).
 * @return array Un array asociativo con los registros de imágenes y los datos de paginación.
 */
function getPaginatedImages( $page = 1, $per_page = 10 ) {
    global $wpdb;

    // Aseguramos que $page y $per_page sean enteros positivos
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );

    // Calcular el offset para la consulta SQL
    $offset = ( $page - 1 ) * $per_page;

    // --- 1. Consulta para el TOTAL de registros (sin paginación) ---
    // Esta consulta cuenta todas las imágenes en la base de datos de WordPress
    $total_query = "
        SELECT COUNT(p.ID)
        FROM " . $wpdb->posts . " AS p
        WHERE
            p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'";
    
    $total_records = $wpdb->get_var( $total_query ); // Ejecutamos la consulta y obtenemos el conteo

    // Calcular las páginas totales
    $total_pages = ceil( $total_records / $per_page );

    // Aseguramos que la página actual no exceda el total de páginas si no hay registros
    if ( $total_records > 0 && $page > $total_pages ) {
        $page = $total_pages; // Redirigir a la última página si la solicitada es muy alta
        $offset = ( $page - 1 ) * $per_page; // Recalcular offset para la última página
    } elseif ( $total_records === 0 ) {
        $page = 0; // No hay páginas si no hay registros
    }


    // --- 2. Consulta para los registros de la PÁGINA ACTUAL ---
    try {
        $attachments_query = $wpdb->prepare(
            "SELECT
                p.ID AS attachment_id,
                p.post_title,
                p.post_name,
                p.guid AS attachment_url,
                p.post_mime_type,
                p.post_content AS image_description,
                p.post_excerpt AS image_legend,
                pm_file.meta_value AS file_path_relative,
                pm_alt.meta_value AS image_alt_text
            FROM " . $wpdb->posts . " AS p
            JOIN
                " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
            WHERE
                p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
            ORDER BY
                p.post_date DESC -- O el orden que prefieras, ej. p.post_title ASC
            LIMIT %d OFFSET %d", // Cláusulas LIMIT y OFFSET para la paginación
            $per_page,            // Parámetro para LIMIT
            $offset               // Parámetro para OFFSET
        );

        $attachments_in_folder = $wpdb->get_results( $attachments_query, ARRAY_A );

        // --- 3. Calcular los datos de paginación para retornar ---
        $current_page_count = count( $attachments_in_folder ); // Cantidad de registros en la página actual

        $pagination_data = [
            'records'        => $attachments_in_folder,        // Los registros de la página actual
            'current_page'   => $page,                          // Página actual
            'total_pages'    => $total_pages,                   // Total de páginas disponibles
            'total_records'  => (int) $total_records,           // Total de registros que cumplen el criterio
            'records_per_page' => $per_page,                    // Registros mostrados por página
            'records_on_current_page' => $current_page_count,   // Cantidad de registros en la página actual
            'prev_page'      => ( $page > 1 ) ? $page - 1 : null, // Página anterior (o null si es la primera)
            'next_page'      => ( $page < $total_pages ) ? $page + 1 : null, // Página siguiente (o null si es la última)
        ];

        return $pagination_data;

    } catch ( \Throwable $th ) {
        // Registra cualquier error que ocurra durante la consulta o el procesamiento
        error_log( 'WPIL Error fetching paginated images (no subfolder filter): ' . $th->getMessage() );
        return [
            'records'        => [],
            'current_page'   => $page,
            'total_pages'    => 0,
            'total_records'  => 0,
            'records_per_page' => $per_page,
            'records_on_current_page' => 0,
            'prev_page'      => null,
            'next_page'      => null,
        ];
    }
}