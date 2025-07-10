<?php
/**
 * Obtiene imágenes paginadas de WordPress,
 * incluyendo metadatos para SEO y detalles de paginación.
 * Permite filtrar por estado de optimización y por subcarpeta.
 *
 * @param int         $page       El número de página actual (por defecto 1).
 * @param int         $per_page   El número de elementos por página (por defecto 10).
 * @param string|null $status     Filtra por estado de optimización (ej. 'optimized', 'failed').
 * Use 'pendiente' o 'unprocessed' para filtrar por imágenes sin el metadato.
 * Null para no filtrar por estado.
 * @param string|null $folder     Filtra por subcarpeta de uploads (ej. '2024/07'). Null para no filtrar.
 * @return array Un array asociativo con los registros de imágenes y los datos de paginación.
 */
function getPaginatedImages( $page = 1, $per_page = 10, $status = null, $folder = null, $scan = 0 ) {
    global $wpdb;

    // Aseguramos que $page y $per_page sean enteros positivos
    $page = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );

    // Calcular el offset para la consulta SQL
    $offset = ( $page - 1 ) * $per_page;

    $pending_delete = 0;
    $pending_optimize = 0;


    $AllPostsWithAttachment = array();

    if($scan == 1){
        $AllPostsWithAttachment = $wpdb->get_results("
                SELECT post_id, meta_value 
                FROM {$wpdb->prefix}postmeta AS wpostmeta
                LEFT JOIN {$wpdb->prefix}posts AS wpost ON wpostmeta.post_id = wpost.ID
                WHERE wpostmeta.meta_key IN('_elementor_data', '_elementor_css', '_thumbnail_id', 'enclosure')
                AND wpost.post_status IN('publish', 'private', 'draft')
            ");
    }

    // --- Preparación de las cláusulas WHERE dinámicas ---
    $where_conditions = [
        "p.post_type = 'attachment'",
        "p.post_mime_type LIKE 'image/%'"
    ];
    $query_params = []; // Array para almacenar los parámetros de prepare

    // Condición para el estado de optimización
    if ( ! is_null( $status ) && ! empty( $status ) ) {
        if ( $status === 'pendiente' || $status === 'unprocessed' ) {
            $where_conditions[] = "pm_optimized.meta_value = 'pendiente'";
        } else {
            $where_conditions[] = "pm_optimized.meta_value = %s";
            $query_params[] = sanitize_text_field( $status );
        }
    }

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
        SELECT COUNT(p.ID)
        FROM " . $wpdb->posts . " AS p
        JOIN " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
        LEFT JOIN " . $wpdb->postmeta . " AS pm_optimized ON p.ID = pm_optimized.post_id AND pm_optimized.meta_key = '_stg_optimized_status'
        WHERE " . $where_clause;
    
    // DEBUG: Imprimir la consulta total y sus parámetros
    // error_log('Total Query SQL (Template): ' . $total_query_sql_template);
    // error_log('Total Query Parameters: ' . print_r($query_params, true));

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
        // Combinamos todos los parámetros para la consulta principal de adjuntos
        // El orden es CRÍTICO: Primero los de WHERE, luego LIMIT, luego OFFSET
        $attachments_query_params = array_merge($query_params, [$per_page, $offset]);

        $attachments_query_sql_template = "
            SELECT
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
            LIMIT %d OFFSET %d";
        
        // DEBUG: Imprimir la consulta principal y sus parámetros
        // error_log('Attachments Query SQL (Template): ' . $attachments_query_sql_template);
        // error_log('Attachments Query Parameters (Merged): ' . print_r($attachments_query_params, true));


        $attachments_query = $wpdb->prepare(
            $attachments_query_sql_template,
            ...$attachments_query_params
        );

        $attachments_in_folder = $wpdb->get_results( $attachments_query, ARRAY_A );

        // --- Opcional: Añadir datos de tamaño/dimensiones después de obtener los resultados ---
        foreach ( $attachments_in_folder as &$attachment ) {
            $metadata = wp_get_attachment_metadata( $attachment['attachment_id'] );
            $attachment['image_width']  = isset( $metadata['width'] ) ? (int) $metadata['width'] : null;
            $attachment['image_height'] = isset( $metadata['height'] ) ? (int) $metadata['height'] : null;
            $attachment['image_filesize'] = isset( $metadata['filesize'] ) ? (int) $metadata['filesize'] : null;
            $attachment['optimization_status'] = get_post_meta($attachment['attachment_id'], '_stg_optimized_status', true);
            if($attachment['optimization_status'] == 'eliminar'){
                $pending_delete++;
            } else if($attachment['optimization_status'] == 'por optimizar'){
                $pending_optimize++;
            }
        }


        // --- 3. Calcular los datos de paginación para retornar ---
        $current_page_count = count( $attachments_in_folder );

        if($scan == 1){
            
            foreach ($attachments_in_folder as &$attachment) {
                
                $in_content_query = $wpdb->prepare(
					"SELECT COUNT(*) 
				 	FROM $wpdb->posts
				 	WHERE post_content LIKE %s 
				 	AND post_status IN ('publish', 'private', 'draft')
				 	AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
					'%' . $wpdb->esc_like($attachment['file_path_relative']) . '%'
				);

                $programas = $wpdb->prepare(
					"SELECT COUNT(*) 
					 FROM {$wpdb->prefix}learnpress_courses
					 WHERE post_content LIKE %s 
					 AND post_status IN ('publish', 'private', 'draft')",
					'%' . $wpdb->esc_like($attachment['file_path_relative']) . '%'
				); 

                if($attachment['optimization_status'] == 'optimizada'){
                    continue;
                }

                $filenamewithfolder = str_replace('/', '\/', $attachment['file_path_relative']);
                $in_content = $wpdb->get_var($in_content_query);
                $programas = $wpdb->get_var($programas);


                if($in_content == 0 && $programas == 0){
                    $attachment['optimization_status'] = 'eliminar';
                    update_post_meta($attachment['attachment_id'], '_stg_optimized_status', 'eliminar');
                    foreach ($AllPostsWithAttachment as $post) {
                        if (strpos($post->meta_value, $filenamewithfolder) !== false || $post->meta_value == $attachment['attachment_id'] || strpos($post->meta_value, $attachment['file_path_relative']) !== false) {
                            $attachment['optimization_status'] = 'por optimizar';
                            update_post_meta($attachment['attachment_id'], '_stg_optimized_status', 'por optimizar');
                            break;
                        } 
                    }
                } else {
                    $attachment['optimization_status'] = 'por optimizar';
                    update_post_meta($attachment['attachment_id'], '_stg_optimized_status', 'por optimizar');
                }


            }
            unset($attachment);



        }

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
            'delete'           => $pending_delete,
            'optimize'         => $pending_optimize,
            'records'          => [],
            'current_page'     => $page,
            'total_pages'      => 0,
            'total_records'    => 0,
            'records_per_page' => $per_page,
            'records_on_current_page' => 0,
            'prev_page'        => null,
            'next_page'        => null,
        ];
    }
}