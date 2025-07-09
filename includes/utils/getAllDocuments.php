<?php

/**
 * Función para obtener todos las documentos del directorio de uploads o de una subcarpeta específica.
 * Excluye miniaturas y filtra opcionalmente por si están vinculadas o no a attachments.
 *
 * @param string $subfolder        Carpeta relativa dentro de uploads (ej: '2024/06' o 'mis-documentos-api').
 * @param string $orderby          Columna por la que ordenar (size_bytes, is_attachment, modified_date).
 * @param string $order            Dirección de ordenación (asc o desc).
 * @return array Lista de arrays de documentos.
 */
function wpil_get_all_documents_in_uploads( $subfolder = '', $orderby = 'size_bytes', $order = 'desc' ) {
    global $wpdb;

    wp_enqueue_script(
        'sendToApi',
        plugins_url( 'sendToApi.js', __FILE__ ),
        array(),
        '1.0.0',
        true
    );

    $upload_dir_info  = wp_upload_dir();
    $base_upload_path = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url  = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path .= trailingslashit( $subfolder );
    }

    $all_documents            = array();
    $to_delete_with_index     = array();
    $to_delete_without_index  = array();
    $not_allowed_extensions   = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif', 'json', 'heic');
    $attachment_paths         = array();

    // Buscar archivos adjuntos en la base de datos
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'
               AND meta_value LIKE %s",
            $wpdb->esc_like($subfolder) . '%'
        ),
        ARRAY_A
    );
    foreach ( $results as $row ) {
        $attachment_paths[ $row['meta_value'] ] = $row['post_id'];
    }

    if ( ! is_dir( $start_path ) ) {
        return array();
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $start_path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $posts = $wpdb->get_results("
            SELECT post_id, meta_value 
            FROM {$wpdb->prefix}postmeta AS wpostmeta
            LEFT JOIN {$wpdb->prefix}posts AS wpost ON wpostmeta.post_id = wpost.ID
            WHERE wpostmeta.meta_key IN('_elementor_data','enclosure')
            AND wpost.post_status IN('publish','private','draft')
        ");

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $ext = strtolower( $file->getExtension() );
                if ( in_array( $ext, $not_allowed_extensions ) ) continue;

                $filename        = $file->getFilename();
                $relative_path   = str_replace( $base_upload_path, '', $file->getPathname() );
                $file_size_bytes = $file->getSize();
                $is_attachment   = false;
                $attachment_id   = null;

                $relative_path_for_db = ltrim( $relative_path, '/' );
                if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
                    $is_attachment = true;
                    $attachment_id = $attachment_paths[ $relative_path_for_db ];
                }

                $query = $wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM $wpdb->posts
                     WHERE post_content LIKE %s 
                       AND post_status IN('publish','private','draft')
                       AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
                    '%' . $wpdb->esc_like( $base_upload_url . $relative_path ) . '%'
                );

                $programas = $wpdb->prepare(
                    "SELECT COUNT(*) 
                     FROM {$wpdb->prefix}learnpress_courses
                     WHERE post_content LIKE %s 
                       AND post_status IN('publish','private','draft')",
                    '%' . $wpdb->esc_like( $base_upload_url . $relative_path ) . '%'
                );

                $en_contenido = $wpdb->get_var( $query );
                $en_programa  = $wpdb->get_var( $programas );

                // Búsqueda en meta_value
                $filenamewithfolder = str_replace( '/', '\/', $relative_path );
                $es_usado_en_postmeta = false;
                foreach ( $posts as $post ) {
                    if ( strpos( $post->meta_value, $filenamewithfolder ) !== false ) {
                        $es_usado_en_postmeta = true;
                        break;
                    }
                }

                $esta_en_uso = ($en_contenido || $en_programa || $es_usado_en_postmeta);

                $all_documents[] = array(
                    'full_path'       => $file->getPathname(),
                    'relative_path'   => $relative_path,
                    'filename'        => $filename,
                    'size_bytes'      => $file_size_bytes,
                    'size_kb'         => round($file_size_bytes / 1024, 2),
                    'is_attachment'   => $is_attachment,
                    'attachment_id'   => $attachment_id,
                    'modified_date'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
                    'url'             => $base_upload_url . $relative_path,
                    'en_contenido'    => $en_contenido,
                    'en_programa'     => $en_programa,
                    'en_postmeta'     => $es_usado_en_postmeta,
                    'esta_en_uso'     => $esta_en_uso ? 1 : 0
                );

                if ( !$esta_en_uso && $is_attachment ) {
                    $to_delete_with_index[] = $attachment_id;
                }
                if ( !$esta_en_uso && !$is_attachment ) {
                    $to_delete_without_index[] = $file->getPathname();
                }
            }
        }
    } catch ( UnexpectedValueException $e ) {
        error_log( 'WPIL Error iterating directory: ' . $e->getMessage() . ' for path: ' . $start_path );
        return array();
    }

    // Ordenar resultados si se solicita
    if ( ! empty( $orderby ) && ! empty( $all_documents ) ) {
        usort( $all_documents, function( $a, $b ) use ( $orderby, $order ) {
            $value_a = null;
            $value_b = null;

            switch ( $orderby ) {
                case 'size_bytes':
                    $value_a = $a['size_bytes'];
                    $value_b = $b['size_bytes'];
                    break;
                case 'is_attachment':
                    $value_a = (int) $a['is_attachment'];
                    $value_b = (int) $b['is_attachment'];
                    break;
                case 'modified_date':
                    $value_a = strtotime( $a['modified_date'] );
                    $value_b = strtotime( $b['modified_date'] );
                    break;
                default:
                    return 0;
            }

            if ( $value_a == $value_b ) return 0;
            return ( $order === 'asc' ) ? ( $value_a < $value_b ? -1 : 1 ) : ( $value_a > $value_b ? -1 : 1 );
        });
    }

    wp_localize_script(
        'sendToApi',
        'imagesToDelete',
        array($to_delete_with_index, $to_delete_without_index)
    );

    return $all_documents;
}
