<?php
/**
 * Función para obtener todas las imágenes del directorio de uploads o de una subcarpeta específica.
 * Excluye miniaturas y filtra opcionalmente por si están vinculadas o no a attachments .
 *
 * @param string $subfolder        Carpeta relativa dentro de uploads (ej: '2024/06' o 'mis-imagenes-api').
 * @param bool|null $check_attachments Si es true, muestra solo imágenes vinculadas a un attachment.
 * @param string $orderby          Columna por la que ordenar (size_bytes, is_attachment, modified_date).
 * @param string $order            Dirección de ordenación (asc o desc).
 * @param bool $show_miniatures    Si es true, muestra miniaturas.
 * @return array Lista de arrays de imágenes.
 */
function get_all_images_in_uploads( $subfolder = '', $orderby = 'size_bytes', $order = 'desc', $show_miniatures = 0 ) {
	
    global $wpdb;


	
    $upload_dir_info    = wp_upload_dir();
    $base_upload_path   = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url    = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path         = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path    .= trailingslashit( $subfolder );
    }

    $all_images         = array();
    $not_allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif' );
    $attachment_paths   = array();

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


    echo '<pre>';
    print_r($results);
    echo '</pre>';
    


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
		
        foreach ( $iterator as $file ) {

            if ( $file->isFile() ) {
				$ext = strtolower($file->getExtension());

        		if (!in_array($ext, $not_allowed_extensions)) continue;

                $filename        = $file->getFilename();
                $extension       = strtolower( $file->getExtension() );
				$relative_path   = str_replace( $base_upload_path, '', $file->getPathname() );
				$file_size_bytes = $file->getSize();
				$attachment_id   = null;

				// Realizar la verificación de attachment solo si es necesario (cuando $check_attachments no es null)
                $relative_path_for_db = ltrim( $relative_path, '/' );
                if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
                    $attachment_id = $attachment_paths[ $relative_path_for_db ];
                }
				
				$query = $wpdb->prepare(
                    "SELECT COUNT(*) 
                        FROM $wpdb->posts
                        WHERE post_content LIKE %s 
                        AND post_status = 'publish'
                        AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
                    '%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
                );

                $all_images[] = array(
					'full_path'       => $file->getPathname(),
					'relative_path'   => $relative_path,
					'filename'        => $filename,
					'size_bytes'      => $file_size_bytes,
					'size_kb'         => $file_size_bytes / 1024,
					'attachment_id'   => $attachment_id,
					'modified_date'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
					'url'             => $base_upload_url . $relative_path,
					//'en_contenido'	  => $en_contenido
				);
            }
        }
    } catch ( UnexpectedValueException $e ) {
        error_log( 'WPIL Error iterating directory: ' . $e->getMessage() . ' for path: ' . $start_path );
        return array();
    }

    if ( ! empty( $orderby ) && ! empty( $all_images ) ) {
        usort( $all_images, function( $a, $b ) use ( $orderby, $order ) {
            $value_a = null;
            $value_b = null;

            switch ( $orderby ) {
                case 'size_bytes':
                    $value_a = $a['size_bytes'];
                    $value_b = $b['size_bytes'];
                    break;
                case 'modified_date':
                    $value_a = strtotime( $a['modified_date'] );
                    $value_b = strtotime( $b['modified_date'] );
                    break;
                default:
                    return 0;
            }

            if ( $value_a == $value_b ) {
                return 0;
            }

            if ( $order === 'asc' ) {
                return ( $value_a < $value_b ) ? -1 : 1;
            } else { // 'desc'
                return ( $value_a > $value_b ) ? -1 : 1;
            }
        });
    }
    return $all_images;
}
?>