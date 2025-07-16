<?php
/**
 * Obtiene archivos paginados de WordPress,
 * incluyendo metadatos para SEO y detalles de paginación.
 * Permite filtrar por subcarpeta, opcionalmente por tipo MIME,
 * opcionalmente por un término de búsqueda de texto simple en el título,
 * y por el estado de uso/alt.
 *
 * @param int         $page         El número de página actual (por defecto 1).
 * @param int         $per_page     El número de elementos por página (por defecto 10).
 * @param string|null $folder       Filtra por subcarpeta de uploads (ej. '2024/07'). Null para no filtrar.
 * @param string|null $mime_type    Filtra por tipo MIME principal (image, audio, video, text, application).
 * Null para no filtrar por tipo MIME (traerá todos los tipos).
 * @param string|null $search_term  Término de búsqueda para texto simple en post_title. Null o vacío para no aplicar.
 * @param string      $usage_status Filtra por estado de uso/alt ('all', 'En Uso', 'Sin Uso', 'Con Alt', 'Sin Alt').
 * @return array Un array asociativo con los registros de archivos y los datos de paginación.
 */
function getPaginatedFiles( $page = 1, $per_page = 10, $folder = null, $mime_type = null, $search_term = null, $usage_status = 'all' ) {
    global $wpdb;

    require_once WP_EIA_PLUGIN_DIR . 'includes/utils/check_attachments_in_elementor.php';
    require_once WP_EIA_PLUGIN_DIR . 'includes/utils/check_attachment_in_elementor.php';
    require_once WP_EIA_PLUGIN_DIR . 'includes/utils/check_attachments_in_learnpress.php';
    require_once WP_EIA_PLUGIN_DIR . 'includes/utils/check_attachments_in_content.php';
    
    // Sanitize and validate pagination parameters
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );
    $offset = ( $page - 1 ) * $per_page;

    // Initialize WHERE conditions and query parameters
    $where_conditions = [
        "p.post_type = 'attachment'",
    ];
    $query_params = [];

    // Filter by MIME type
    if ( ! is_null( $mime_type ) && ! empty( $mime_type ) ) {
        $where_conditions[] = "p.post_mime_type LIKE %s";
        $query_params[] = $wpdb->esc_like( $mime_type ) . '/%';
    }

    // Filter by folder (relative path)
    if ( ! is_null( $folder ) && ! empty( $folder ) ) {
        $clean_folder = trailingslashit( sanitize_text_field( $folder ) );
        // We need to ensure pm_file is joined for this condition
        // The joins are already in place below for both COUNT and main query.
        $where_conditions[] = "pm_file.meta_value LIKE %s";
        $query_params[] = '%' . $wpdb->esc_like( $clean_folder ) . '%';
    }

    // Filter by search term in post_title
    if ( ! is_null( $search_term ) && ! empty( $search_term ) ) {
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

    // --- CONDITION FOR USAGE STATUS / ALT TEXT ---
    $usage_status = sanitize_text_field( $usage_status );

    if ( 'all' !== $usage_status ) {
        switch ( $usage_status ) {
            case 'En Uso':
                $where_conditions[] = "pm_in_use.meta_value = %s";
                $query_params[] = 'En Uso';
                break;
            case 'Sin Uso':
                // Crucial: Filter for 'Sin Uso' OR if the meta_value is NULL or empty
                $where_conditions[] = "pm_in_use.meta_value = %s";
                $query_params[] = 'Sin Uso'; // This %s will bind to 'Sin Uso'
                break;
            case 'Con Alt':
                $where_conditions[] = "pm_has_alt.meta_value = %s";
                $query_params[] = 'Con Alt';
                break;
            case 'Sin Alt':
                // Crucial: Filter for 'Sin Alt' OR if the meta_value is NULL or empty
                $where_conditions[] = "(pm_has_alt.meta_value = %s OR pm_has_alt.meta_value IS NULL OR pm_has_alt.meta_value = '')";
                $query_params[] = 'Sin Alt'; // This %s will bind to 'Sin Alt'
                break;
        }
    }

    // Combine all WHERE conditions
    $where_clause = '';
    if ( ! empty( $where_conditions ) ) {
        $where_clause = ' WHERE ' . implode( ' AND ', $where_conditions );
    }

    // --- 1. Query for TOTAL records (without pagination) ---
    // Ensure all necessary LEFT JOINs are present for the WHERE clauses
    $total_query_sql_template = "
        SELECT COUNT(DISTINCT p.ID)
        FROM " . $wpdb->posts . " AS p
        LEFT JOIN " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_in_use ON p.ID = pm_in_use.post_id AND pm_in_use.meta_key = '_stg_status_in_use'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_has_alt ON p.ID = pm_has_alt.post_id AND pm_has_alt.meta_key = '_stg_status_alt'"
        . $where_clause;

    $total_query = $wpdb->prepare(
        $total_query_sql_template,
        ...$query_params
    );
    
    $total_records = $wpdb->get_var( $total_query );

    // Calculate total pages
    $total_pages = ceil( $total_records / $per_page );

    // Adjust current page if it exceeds total pages for valid records, or if no records exist
    if ( $total_records > 0 && $page > $total_pages ) {
        $page = $total_pages;
        $offset = ( $page - 1 ) * $per_page;
    } elseif ( $total_records === 0 ) {
        $page = 0;
        $offset = 0;
    }

    // --- 2. Query for records on the CURRENT PAGE ---
    try {
        // Merge WHERE parameters with LIMIT and OFFSET parameters
        $attachments_query_params = array_merge([], $query_params, [$per_page, $offset]);
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
                pm_in_use.meta_value AS stg_status_in_use,
                pm_has_alt.meta_value AS stg_status_alt
            FROM " . $wpdb->posts . " AS p
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_in_use ON p.ID = pm_in_use.post_id AND pm_in_use.meta_key = '_stg_status_in_use'
            LEFT JOIN
                " . $wpdb->postmeta . " AS pm_has_alt ON p.ID = pm_has_alt.post_id AND pm_has_alt.meta_key = '_stg_status_alt'
            " . $where_clause . "
            ORDER BY
                p.post_date DESC
            LIMIT %d OFFSET %d";

        $attachments_query = $wpdb->prepare(
            $attachments_query_sql_template,
            ...$attachments_query_params
        );

        $attachments_in_folder = $wpdb->get_results( $attachments_query, ARRAY_A );

        $id_list = array();
        $path_list = array();
        
        // Loop through fetched attachments to gather basic data and prepare for usage check
        foreach ( $attachments_in_folder as &$attachment ) {
            $metadata = wp_get_attachment_metadata( $attachment['attachment_id'] );

            if ( str_starts_with( $attachment['post_mime_type'], 'image/' ) ) {
                $attachment['image_width']    = isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
                $attachment['image_height']   = isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
            }
            $attachment['file_filesize'] = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : null;

            // Only add to lists for usage check if a path exists
            if ( ! empty( $attachment['file_path_relative'] ) ) {
                $id_list[] = $attachment['attachment_id'];
                $path_list[] = $attachment['file_path_relative'];
            }
        }



        $files_to_delete = array();


        if($usage_status == null || $usage_status == 'all' || $usage_status == ''){

            //$elementor_attachments = check_attachments_in_elementor( $attachments_in_folder );
            $elementor_attachments = check_attachment_in_elementor($id_list, $path_list);
            //$learnpress_attachments = check_attachment_in_learnpress( $path_list );
            //$content_attachments = check_attachments_in_content( $path_list);

    
            foreach ($attachments_in_folder as &$attachment) {

                $attachment['in_content'] = false;
                $attachment['in_programs'] = false;
                $attachment['in_elementor'] = false;

                // if( check_attachment_in_elementor($attachment['attachment_id'], $attachment['file_path_relative']) ){
                //     $attachment['in_elementor'] = true;
                // }

                if($elementor_attachments[$attachment['attachment_id'] ] == true){
                    $attachment['in_elementor'] = true;
                }
                // if($learnpress_attachments[$attachment['attachment_id'] ] == true){
                //     $attachment['in_programs'] = true;
                // }
                // if($content_attachments[$attachment['file_path_relative'] ] == true){
                //     $attachment['in_content'] = true;
                // }

                // Determine and update 'in_use' status
                $current_in_use_status = ($attachment['in_content'] || $attachment['in_programs'] || $attachment['in_elementor']) ? 'En Uso' : 'Sin Uso';

                
                if ($current_in_use_status === 'Sin Uso') {
                    $files_to_delete[] = $attachment['attachment_id']; // Add to list for deletion
                }
                
                // Only update post meta if the status has actually changed
                if ( $current_in_use_status != $attachment['stg_status_in_use'] ) {
                    $attachment['stg_status_in_use'] = $current_in_use_status;
                    update_post_meta($attachment['attachment_id'], '_stg_status_in_use', $current_in_use_status);
                }

                // Determine and update 'alt' status
                $current_alt_status = empty($attachment['image_alt_text']) ? 'Sin Alt' : 'Con Alt';
                
                // Only update post meta if the status has actually changed
                if ( $current_alt_status != $attachment['stg_status_alt'] ) {
                    $attachment['stg_status_alt'] = $current_alt_status; // Update in the array for the current response
                    update_post_meta($attachment['attachment_id'], '_stg_status_alt', $current_alt_status);
                }
            }
        }

        unset($attachment); // Break the reference of the last element

        // --- 3. Calculate pagination data for return ---
        $current_page_count = count( $attachments_in_folder );
    
        $pagination_data = [
            'files_to_delete'         => $files_to_delete, // List of unused file IDs (for debugging/future use)
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