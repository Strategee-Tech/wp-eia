<?php

/**
 * Obtiene imágenes paginadas de TODAS las carpetas de uploads de WordPress,
 * incluyendo metadatos para SEO y detalles de paginación.
 * También permite filtrar por estado de optimización y estado de uso.
 *
 * @param int    $page       El número de página actual (por defecto 1).
 * @param int    $per_page   El número de elementos por página (por defecto 10).
 * @param string|null $status  Filtra por estado de optimización (ej. 'optimized', 'pending'). Null para no filtrar.
 * @return array Un array asociativo con los registros de imágenes y los datos de paginación.
 */
function getPaginatedImages( $page = 1, $per_page = 10, $status = null, $folder = null, $scan = null, $delete = null, $optimize = null ) {
    global $wpdb;

    // Aseguramos que $page y $per_page sean enteros positivos
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );

    // Calcular el offset para la consulta SQL
    $offset = ( $page - 1 ) * $per_page;

    // --- Preparación de las cláusulas WHERE dinámicas ---
    $where_conditions = [
        "p.post_type = 'attachment'",
        "p.post_mime_type LIKE 'image/%'"
    ];
    $query_params = []; // Array para almacenar los parámetros de prepare

    // Condición para el estado de optimización
    if ( ! is_null( $status ) && ! empty( $status ) ) {
        $where_conditions[] = "pm_optimized.meta_value = %s";
        $query_params[] = sanitize_text_field( $status );
    }

    // Unir todas las condiciones WHERE
    $where_clause = implode( ' AND ', $where_conditions );


    // --- 1. Consulta para el TOTAL de registros (sin paginación) ---
    // Combine all parameters for the total query here
    $total_query_params = $query_params; // Start with WHERE clause parameters

    $total_query = $wpdb->prepare(
        "SELECT COUNT(p.ID)
        FROM " . $wpdb->posts . " AS p
        JOIN " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_optimized ON p.ID = pm_optimized.post_id AND pm_optimized.meta_key = '_stg_optimization_status'
        WHERE " . $where_clause,
        ...$total_query_params // Now this is the ONLY argument after the format string
    );
    
    $total_records = $wpdb->get_var( $total_query );

    // Calcular las páginas totales
    $total_pages = ceil( $total_records / $per_page );

    // Aseguramos que la página actual no exceda el total de páginas si no hay registros
    if ( $total_records > 0 && $page > $total_pages ) {
        $page = $total_pages;
        $offset = ( $page - 1 ) * $per_page;
    } elseif ( $total_records === 0 ) {
        $page = 0;
    }


    // --- 2. Consulta para los registros de la PÁGINA ACTUAL ---
    try {
        // Combine all parameters for the main attachments query
        $attachments_query_params = array_merge($query_params, [$per_page, $offset]); // Merge WHERE params with LIMIT/OFFSET params

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
                pm_alt.meta_value AS image_alt_text,
                pm_optimized.meta_value AS optimization_status
            FROM " . $wpdb->posts . " AS p
            JOIN
                " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_optimized ON p.ID = pm_optimized.post_id AND pm_optimized.meta_key = '_stg_optimized_status'
            WHERE " . $where_clause . "
            ORDER BY
                p.post_date DESC
            LIMIT %d OFFSET %d",
            // The combined array is now the only argument after the format string
            ...$attachments_query_params
        );

        $attachments_in_folder = $wpdb->get_results( $attachments_query, ARRAY_A );

        // --- 3. Calcular los datos de paginación para retornar ---
        $current_page_count = count( $attachments_in_folder );

        $pagination_data = [
            'records'        => $attachments_in_folder,
            'current_page'   => $page,
            'total_pages'    => $total_pages,
            'total_records'  => (int) $total_records,
            'records_per_page' => $per_page,
            'records_on_current_page' => $current_page_count,
            'prev_page'      => ( $page > 1 ) ? $page - 1 : null,
            'next_page'      => ( $page < $total_pages ) ? $page + 1 : null,
        ];

        return $pagination_data;

    } catch ( \Throwable $th ) {
        error_log( 'WPIL Error fetching paginated images (filtered): ' . $th->getMessage() );
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