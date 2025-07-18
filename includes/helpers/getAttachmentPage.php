<?php
/**
 * Obtiene archivos paginados de WordPress,
 * incluyendo metadatos para SEO y detalles de paginación.
 * Permite filtrar por subcarpeta, opcionalmente por tipo MIME,
 * opcionalmente por un término de búsqueda de texto simple en el título,
 * y por el estado de uso/alt utilizando las meta keys personalizadas.
 *
 * @param int         $page         El número de página actual (por defecto 1).
 * @param int         $per_page     El número de elementos por página (por defecto 20).
 * @param string|null $folder       Filtra por subcarpeta de uploads (ej. '2024/07'). Null para no filtrar.
 * @param string|null $mime_type    Filtra por tipo MIME principal (image, audio, video, text, application).
 * Null para no filtrar por tipo MIME (traerá todos los tipos).
 * @param string|null $search_term  Término de búsqueda para texto simple en post_title. Null o vacío para no aplicar.
 * @param string      $usage_status Filtra por estado de uso/alt ('all', 'scanned', 'unscanned', 'in_use', 'not_in_use', 'has_alt', 'no_alt', 'blocked', 'not_blocked').
 * @return array Un array asociativo con los registros de archivos y los datos de paginación.
 */
function getAttachmentPage( $page = 1, $per_page = 20, $folder = null, $mime_type = null, $search_term = null, $usage_status = 'all' ) {

    global $wpdb;
    
    // Define tus meta_keys personalizadas aquí (asegúrate de que coincidan con tus definiciones de plugin)
    $my_plugin_meta_keys = [
        'is_scanned'               => '_stg_is_scanned',
        'is_in_use'                => '_stg_is_in_use',
        'has_alt_text'             => '_stg_has_alt_text',
        'is_blocked_from_deletion' => '_stg_is_blocked_from_deletion',
    ];

    // Sanitize and validate pagination parameters
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );
    $offset = ( $page - 1 ) * $per_page;

    // Initialize WHERE conditions and query parameters
    $where_conditions = [
        "p.post_type = 'attachment'",
    ];
    $query_params = [];
    $files_to_delete = []; 

    // Filter by MIME type
    if ( ! empty( $mime_type ) ) { // Simplificado de is_null a empty
        $where_conditions[] = "p.post_mime_type LIKE %s";
        $query_params[] = $wpdb->esc_like( $mime_type ) . '/%';
    }

    // Filter by folder (relative path)
    if ( ! empty( $folder ) ) { // Simplificado de is_null a empty
        $clean_folder = trailingslashit( sanitize_text_field( $folder ) );
        $where_conditions[] = "pm_file.meta_value LIKE %s";
        $query_params[] = '%' . $wpdb->esc_like( $clean_folder ) . '%';
    }

    // Filter by search term in post_title
    if ( ! empty( $search_term ) ) { // Simplificado de is_null a empty
        $clean_search_term = sanitize_text_field( $search_term );
        $words = explode( ' ', $clean_search_term );
        $like_conditions = [];

        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( ! empty( $word ) ) {
                $like_conditions[] = "p.post_title LIKE %s";
                $query_params[] = '%' . $wpdb->esc_like( $word ) . '%';
            }
        }

        if ( ! empty( $like_conditions ) ) {
            $where_conditions[] = '(' . implode(' OR ', $like_conditions) . ')';
        }
    }

    // --- CONDITION FOR USAGE STATUS / ALT TEXT USING CUSTOM META KEYS ---
    $usage_status = sanitize_text_field( $usage_status );

    switch ( $usage_status ) {
        case 'scanned':
            $where_conditions[] = "pm_scanned.meta_value = '1'";
            break;
        case 'unscanned':
            // Adjunto no escaneado: meta_value es '0' O la meta_key no existe para este adjunto
            $where_conditions[] = "(pm_scanned.meta_value = '0' OR pm_scanned.post_id IS NULL)";
            break;
        case 'in_use':
            $where_conditions[] = "pm_in_use.meta_value = '1'";
            break;
        case 'not_in_use':
            // Adjunto sin uso: meta_value es '0' O la meta_key no existe para este adjunto
            $where_conditions[] = "(pm_in_use.meta_value = '0' OR pm_in_use.post_id IS NULL)";
            break;
        case 'has_alt':
            $where_conditions[] = "pm_has_alt.meta_value = '1'";
            break;
        case 'no_alt':
            // Adjunto sin alt text (de plugin): meta_value es '0' O la meta_key no existe para este adjunto
            $where_conditions[] = "(pm_has_alt.meta_value = '0' OR pm_has_alt.post_id IS NULL)";
            break;
        case 'blocked':
            $where_conditions[] = "pm_blocked.meta_value = '1'";
            break;
        case 'not_blocked':
            // Adjunto no bloqueado: meta_value es '0' O la meta_key no existe para este adjunto
            $where_conditions[] = "(pm_blocked.meta_value = '0' OR pm_blocked.post_id IS NULL)";
            break;
        case 'all':
            // No se aplica filtro por estado
            break;
    }
    
    // Combine all WHERE conditions
    $where_clause = '';
    if ( ! empty( $where_conditions ) ) {
        $where_clause = ' WHERE ' . implode( ' AND ', $where_conditions );
    }

    // --- LEFT JOINs for all custom meta keys and standard ones ---
    // Esc_sql() es importante para asegurar los nombres de las meta_keys
    $join_clauses = "
        LEFT JOIN " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_scanned ON p.ID = pm_scanned.post_id AND pm_scanned.meta_key = '" . esc_sql($my_plugin_meta_keys['is_scanned']) . "'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_in_use ON p.ID = pm_in_use.post_id AND pm_in_use.meta_key = '" . esc_sql($my_plugin_meta_keys['is_in_use']) . "'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_has_alt ON p.ID = pm_has_alt.post_id AND pm_has_alt.meta_key = '" . esc_sql($my_plugin_meta_keys['has_alt_text']) . "'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_blocked ON p.ID = pm_blocked.post_id AND pm_blocked.meta_key = '" . esc_sql($my_plugin_meta_keys['is_blocked_from_deletion']) . "'
    ";


    // --- 1. Query for TOTAL records (without pagination) ---
    $total_query_sql_template = "
        SELECT COUNT(DISTINCT p.ID)
        FROM " . $wpdb->posts . " AS p
        " . $join_clauses . "
        " . $where_clause;

    $total_query = $wpdb->prepare(
        $total_query_sql_template,
        ...$query_params
    );
    
    // --- DEPURACIÓN: Ver la consulta total ---
    error_log( 'DEBUG (Total Query): ' . $total_query );
    error_log( 'DEBUG (Total Query Params): ' . print_r($query_params, true) );

    $total_records = $wpdb->get_var( $total_query );

    // Calculate total pages
    $total_pages = ceil( $total_records / $per_page );

    // Adjust current page if it exceeds total pages for valid records, or if no records exist
    if ( $total_records > 0 && $page > $total_pages ) {
        $page = $total_pages;
        $offset = ( $page - 1 ) * $per_page;
    } elseif ( $total_records === 0 ) {
        $page = 0; // Si no hay registros, la página es 0
        $offset = 0;
    }

    // --- 2. Query for records on the CURRENT PAGE ---
    try {
        // Los parámetros de la consulta principal incluyen los de la cláusula WHERE
        // más los del LIMIT y OFFSET.
        $attachments_query_params = array_merge([], $query_params); 
        $attachments_query_params[] = $per_page; 
        $attachments_query_params[] = $offset; 

        $attachments_query_sql_template = "
            SELECT DISTINCT
                p.ID AS attachment_id,
                p.post_title,
                p.post_name,
                p.guid AS attachment_url,
                p.post_mime_type,
                p.post_content AS file_description,
                p.post_excerpt AS file_legend,
                pm_file.meta_value AS file_path_relative,
                pm_alt.meta_value AS image_alt_text,
                pm_scanned.meta_value AS is_scanned_status,
                pm_in_use.meta_value AS is_in_use_status,
                pm_has_alt.meta_value AS has_alt_text_status,
                pm_blocked.meta_value AS is_blocked_status
            FROM " . $wpdb->posts . " AS p
            " . $join_clauses . "
            " . $where_clause . "
            ORDER BY
                p.post_date DESC
            LIMIT %d OFFSET %d";

        $attachments_query = $wpdb->prepare(
            $attachments_query_sql_template,
            ...$attachments_query_params
        );

        // --- DEPURACIÓN: Ver la consulta de los registros ---
        error_log( 'DEBUG (Attachments Query): ' . $attachments_query );
        error_log( 'DEBUG (Attachments Query Params): ' . print_r($attachments_query_params, true) );

        $attachments_in_folder = $wpdb->get_results( $attachments_query, ARRAY_A );

        if(empty($attachments_in_folder)) {
            return [
                'files_to_delete'         => [],
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
        
        // Loop through fetched attachments to gather basic data and prepare for usage check
        foreach ( $attachments_in_folder as &$attachment ) {
            $metadata = wp_get_attachment_metadata( $attachment['attachment_id'] );

            // Ensure metadata exists and is an array before trying to access keys
            if ( is_array( $metadata ) ) {
                if ( str_starts_with( $attachment['post_mime_type'], 'image/' ) ) {
                    $attachment['image_width']    = isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
                    $attachment['image_height']   = isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
                }
                $attachment['file_filesize'] = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : null;
            } else {
                // Si no hay metadatos, asigna nulos para evitar errores
                $attachment['image_width']   = null;
                $attachment['image_height']  = null;
                $attachment['file_filesize'] = null;
            }


            // Convert '1'/'0' string values to boolean for easier consumption
            // Importante: pm_alt.meta_value (image_alt_text) es el alt text nativo de WP,
            // no lo conviertas a booleano aquí, ya que es una cadena de texto.
            // Solo convierte tus metas booleanas personalizadas.
            $attachment['is_scanned']             = ( '1' === $attachment['is_scanned_status'] );
            $attachment['is_in_use']              = ( '1' === $attachment['is_in_use_status'] );
            $attachment['has_alt_text_plugin']    = ( '1' === $attachment['has_alt_text_status'] ); 
            $attachment['is_blocked_from_deletion'] = ( '1' === $attachment['is_blocked_status'] );

            // Clean up raw status values if you prefer not to expose them
            unset($attachment['is_scanned_status']);
            unset($attachment['is_in_use_status']);
            unset($attachment['has_alt_text_status']);
            unset($attachment['is_blocked_status']);
        }

        // Populate files_to_delete if usage_status is 'not_in_use' (assuming this is the "unused" filter)
        // Solo incluye en files_to_delete si la condición del filtro es 'not_in_use'
        // y el adjunto cumple con que 'is_in_use' sea false después de la conversión.
        if($usage_status === 'not_in_use'){
            foreach ($attachments_in_folder as $attachment) {
                if (isset($attachment['is_in_use']) && $attachment['is_in_use'] === false) {
                    $files_to_delete[] = $attachment['attachment_id'];
                }
            }
        }
    
        // --- 3. Calculate pagination data for return ---
        $current_page_count = count( $attachments_in_folder );

        $pagination_data = [
            'files_to_delete'         => $files_to_delete, 
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
        error_log( 'STG Optimizer Error fetching paginated files: ' . $th->getMessage() );
        return [
            'files_to_delete'         => [],
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