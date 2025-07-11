<?php
/**
 * Obtiene archivos paginados de WordPress,
 * incluyendo metadatos para SEO y detalles de paginación.
 * Permite filtrar por subcarpeta.
 *
 * @param int         $page       El número de página actual (por defecto 1).
 * @param int         $per_page   El número de elementos por página (por defecto 10).
 * @param string|null $folder     Filtra por subcarpeta de uploads (ej. '2024/07'). Null para no filtrar.
 * @param string      $mime_type  Filtra por tipo MIME principal (image, audio, video, text, application).
 * Por defecto 'image'.
 * @return array Un array asociativo con los registros de archivos y los datos de paginación.
 */
function getPaginatedFiles( $page = 1, $per_page = 10, $folder = null, $mime_type = 'image' ) {
    global $wpdb;

    // Aseguramos que $page y $per_page sean enteros positivos
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );

    // Calcular el offset para la consulta SQL
    $offset = ( $page - 1 ) * $per_page;

    // --- Preparación de las cláusulas WHERE dinámicas ---
    $where_conditions = [
        "p.post_type = 'attachment'",
        $wpdb->prepare( "p.post_mime_type LIKE %s", $wpdb->esc_like( $mime_type ) . '/%' )
    ];
    $query_params = []; // Array para almacenar los parámetros de prepare

    // Condición para el filtro de carpeta (folder)
    if ( ! is_null( $folder ) && ! empty( $folder ) ) {
        $clean_folder = trailingslashit( sanitize_text_field( $folder ) );
        $where_conditions[] = "pm_file.meta_value LIKE %s";
        $query_params[] = '%' . $wpdb->esc_like( $clean_folder ) . '%';
    }

    // Unir todas las condiciones WHERE
    $where_clause = implode( ' AND ', $where_conditions );

    // --- 1. Consulta para el TOTAL de registros (sin paginación) ---
    $total_query_sql_template = "
        SELECT COUNT(DISTINCT p.ID)
        FROM " . $wpdb->posts . " AS p
        JOIN " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        WHERE " . $where_clause;

    $total_query = $wpdb->prepare(
        $total_query_sql_template,
        ...$query_params
    );
    
    $total_records = $wpdb->get_var( $total_query );

    // Calcular las páginas totales
    $total_pages = ceil( $total_records / $per_page );

    // Aseguramos que la página actual no exceda el total de páginas si no hay registros
    // Y ajustamos el offset en consecuencia
    if ( $total_records > 0 && $page > $total_pages ) {
        $page = $total_pages;
        $offset = ( $page - 1 ) * $per_page;
    } elseif ( $total_records === 0 ) {
        $page = 0;
        $offset = 0; // Asegurarse de que el offset sea 0 si no hay registros
    }

    // --- 2. Consulta para los registros de la PÁGINA ACTUAL ---
    try {
        // Los parámetros para la consulta principal incluyen primero los de WHERE, luego LIMIT y OFFSET.
        $attachments_query_params = array_merge($query_params, [$per_page, $offset]);

        $attachments_query_sql_template = "
            SELECT
                p.ID AS attachment_id,
                p.post_title,
                p.post_name,
                p.guid AS attachment_url,
                p.post_mime_type,
                p.post_content AS file_description,
                p.post_excerpt AS file_legend,
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
            LIMIT %d OFFSET %d";

        $attachments_query = $wpdb->prepare(
            $attachments_query_sql_template,
            ...$attachments_query_params
        );

        $attachments_in_folder = $wpdb->get_results( $attachments_query, ARRAY_A );

        // Añadir metadatos adicionales y el estado de optimización (siempre se trae, pero no se filtra por él)
        foreach ( $attachments_in_folder as &$attachment ) {
            $metadata = wp_get_attachment_metadata( $attachment['attachment_id'] );
            
            // Metadatos específicos de imagen
            if ( str_starts_with( $attachment['post_mime_type'], 'image/' ) ) {
                $attachment['image_width']    = isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
                $attachment['image_height']   = isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
            }
            
            // Metadatos generales de archivo (tamaño)
            $attachment['file_filesize'] = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : null;
            
            // El estado de optimización ya viene de la consulta, pero puedes asegurarte si quieres
            // $attachment['optimization_status'] = get_post_meta($attachment['attachment_id'], '_stg_optimized_status', true);
        }

        // --- 3. Calcular los datos de paginación para retornar ---
        $current_page_count = count( $attachments_in_folder );

        $pagination_data = [
            'records'                 => $attachments_in_folder,
            'current_page'            => $page,
            'total_pages'             => $total_pages,
            'total_records'           => (int) $total_records,
            'records_per_page'        => $per_page,
            'records_on_current_page' => $current_page_count,
            'prev_page'               => ( $page > 1 ) ? $page - 1 : null,
            'next_page'               => ( $page < $total_pages ) ? $page + 1 : null,
        ];

        return $pagination_data;

    } catch ( \Throwable $th ) {
        error_log( 'WPIL Error fetching paginated files: ' . $th->getMessage() );
        return [
            'records'                 => [],
            'current_page'            => $page,
            'total_pages'             => 0,
            'total_records'           => 0,
            'records_per_page'        => $per_page,
            'records_on_current_page' => 0,
            'prev_page'               => null,
            'next_page'               => null,
        ];
    }
}