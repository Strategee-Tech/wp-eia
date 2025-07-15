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
 * @param string      $usage_status Filtra por estado de uso/alt ('all', 'in_use', 'not_in_use', 'has_alt', 'no_alt').
 * @return array Un array asociativo con los registros de archivos y los datos de paginación.
 */
function getPaginatedFiles( $page = 1, $per_page = 10, $folder = null, $mime_type = null, $search_term = null, $usage_status = 'all' ) {
    global $wpdb;

    // Aseguramos que $page y $per_page sean enteros positivos
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );

    // Calcular el offset para la consulta SQL
    $offset = ( $page - 1 ) * $per_page;

    // --- Preparación de las cláusulas WHERE dinámicas ---
    $where_conditions = [
        "p.post_type = 'attachment'",
    ];
    $query_params = []; // Array para almacenar los parámetros de prepare

    // Condición para el filtro de tipo MIME
    if ( ! is_null( $mime_type ) && ! empty( $mime_type ) ) {
        $where_conditions[] = "p.post_mime_type LIKE %s";
        $query_params[] = $wpdb->esc_like( $mime_type ) . '/%';
    }

    // Condición para el filtro de carpeta (folder)
    if ( ! is_null( $folder ) && ! empty( $folder ) ) {
        $clean_folder = trailingslashit( sanitize_text_field( $folder ) );
        $where_conditions[] = "pm_file.meta_value LIKE %s";
        $query_params[] = '%' . $wpdb->esc_like( $clean_folder ) . '%';
    }

    // Condición para el filtro de búsqueda de texto simple en post_title (usando LIKE)
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

    // --- CONDICIÓN PARA EL ESTADO DE USO / ALT TEXT ---
    $usage_status = sanitize_text_field( $usage_status );

    if ( 'all' !== $usage_status ) {
        switch ( $usage_status ) {
            case 'in_use':
                $where_conditions[] = "pm_in_use.meta_value = %s";
                $query_params[] = 'En Uso';
                break;
            case 'not_in_use':
                $where_conditions[] = "pm_in_use.meta_value = %s";
                $query_params[] = 'Sin Uso';
                break;
            case 'has_alt':
                $where_conditions[] = "pm_has_alt.meta_value = %s";
                $query_params[] = 'Con Alt';
                break;
            case 'no_alt':
                $where_conditions[] = "pm_has_alt.meta_value = %s";
                $query_params[] = 'Sin Alt';
                break;
        }
    }

    // Unir todas las condiciones WHERE
    $where_clause = '';
    if ( ! empty( $where_conditions ) ) {
        $where_clause = ' WHERE ' . implode( ' AND ', $where_conditions );
    }

    // --- 1. Consulta para el TOTAL de registros (sin paginación) ---
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

    // Calcular las páginas totales
    $total_pages = ceil( $total_records / $per_page );

    // Aseguramos que la página actual no exceda el total de páginas si no hay registros
    if ( $total_records > 0 && $page > $total_pages ) {
        $page = $total_pages;
        $offset = ( $page - 1 ) * $per_page;
    } elseif ( $total_records === 0 ) {
        $page = 0;
        $offset = 0;
    }

    // --- 2. Consulta para los registros de la PÁGINA ACTUAL ---
    try {
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
        
        foreach ( $attachments_in_folder as &$attachment ) {
            $metadata = wp_get_attachment_metadata( $attachment['attachment_id'] );

            if ( str_starts_with( $attachment['post_mime_type'], 'image/' ) ) {
                $attachment['image_width']    = isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
                $attachment['image_height']   = isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
            }
            $attachment['file_filesize'] = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : null;

            $id_list[] = $attachment['attachment_id'];
            $path_list[] = $attachment['file_path_relative'];
        }

        // --- Lógica para determinar el uso de las imágenes y actualizar metadatos ---
        $where_clauses = [];
        $prepare_args = [];
        foreach ($path_list as $path) {
            $where_clauses[] = "post_content LIKE %s";
            $prepare_args[] = '%' . $wpdb->esc_like($path) . '%';
        }

        $where_clause_content = implode(' OR ', $where_clauses);

        $programas_sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}learnpress_courses
             WHERE ({$where_clause_content}) 
             AND post_status IN ('publish', 'private', 'draft')",
            ...$prepare_args
        );
        $programas = $wpdb->get_results($programas_sql);

        $post_types = [
            'post', 'page', 'custom_post_type', 'lp_course', 'service',
            'portfolio', 'gva_event', 'gva_header', 'footer', 'team',
            'elementskit_template', 'elementskit_content', 'elementor_library'
        ];

        $post_type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
        $post_type_args = $post_types;

        $in_content_query_sql = $wpdb->prepare(
            "SELECT ID, post_title, post_content, post_type 
             FROM $wpdb->posts
             WHERE ({$where_clause_content}) 
             AND post_status IN ('publish', 'private', 'draft')
             AND post_type IN ({$post_type_placeholders})",
            ...array_merge($prepare_args, $post_type_args)
        );
        $found_posts = $wpdb->get_results($in_content_query_sql);

        $in_elementor_query_sql = $wpdb->prepare(
            "SELECT wpostmeta.post_id, wpostmeta.meta_value 
            FROM {$wpdb->prefix}postmeta AS wpostmeta
            LEFT JOIN {$wpdb->prefix}posts AS wpost ON wpostmeta.post_id = wpost.ID
            WHERE wpostmeta.meta_key IN('_elementor_data', '_elementor_css', '_thumbnail_id')
            AND wpost.post_status IN('publish', 'private', 'draft') "
        );
        $elementor_posts = $wpdb->get_results($in_elementor_query_sql);

        $files_to_delete = array();

        foreach ($attachments_in_folder as &$attachment) {
            $attachment['in_content'] = false;
            $attachment['in_programs'] = false;
            $attachment['in_elementor'] = false;
            
            $file_path_relative_decoded = str_replace('/', '/', $attachment['file_path_relative']);
            $attachment_id = $attachment['attachment_id'];

            foreach ($found_posts as $post) {
                if (strpos($post->post_content, $file_path_relative_decoded) !== false) {
                    $attachment['in_content'] = true;
                    break;
                }
            }

            foreach ($programas as $programa) {
                if (strpos($programa->post_content, $file_path_relative_decoded) !== false) {
                    $attachment['in_programs'] = true;
                    break;
                }
            }
            
            foreach ($elementor_posts as $elementor_post) {
                if (strpos($elementor_post->meta_value, $file_path_relative_decoded) !== false || $elementor_post->meta_value == $attachment_id) {
                    $attachment['in_elementor'] = true;
                    break;
                } 
            }

            $current_in_use_status = ($attachment['in_content'] || $attachment['in_programs'] || $attachment['in_elementor']) ? 'En Uso' : 'Sin Uso';
            
            if ($current_in_use_status === 'Sin Uso') {
                $files_to_delete[] = $attachment_id;
            }
            
            if ($current_in_use_status !== $attachment['stg_status_in_use'] ) {
                $attachment['stg_status_in_use'] = $current_in_use_status;
                update_post_meta($attachment_id, '_stg_status_in_use', $current_in_use_status);
            }

            $current_alt_status = empty($attachment['image_alt_text']) ? 'Sin Alt' : 'Con Alt';
            
            if ($current_alt_status !== $attachment['stg_status_alt'] ) {
                $attachment['stg_status_alt'] = $current_alt_status;
                update_post_meta($attachment_id, '_stg_status_alt', $current_alt_status);
            }
        }
        unset($attachment);

        // --- 3. Calcular los datos de paginación para retornar ---
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