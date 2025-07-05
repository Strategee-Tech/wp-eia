<?php
/**
 * Función para obtener todas las imágenes del directorio de uploads o de una subcarpeta específica.
 * Excluye miniaturas y filtra opcionalmente por si están vinculadas o no a attachments .
 *
 * @param string $subfolder        Carpeta relativa dentro de uploads (ej: '2024/06' o 'mis-imagenes-api').
 * @param string $orderby          Columna por la que ordenar (size_bytes, is_attachment, modified_date).
 * @param string $order            Dirección de ordenación (asc o desc).
 * @return array Lista de arrays de imágenes.
 */
function get_all_images_in_uploads( $subfolder = '', $orderby = 'size_bytes', $order = 'desc' ) {

    require_once WP_EIA_PLUGIN_DIR . 'includes/utils/imageNames.php';
	
    global $wpdb;

    $upload_dir_info    = wp_upload_dir();
    $base_upload_path   = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url    = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path         = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path    .= trailingslashit( $subfolder );
    }

    $all_images         = array();
    $all_thumbnails     = array();
    $attachment_paths   = array();

    //Todas la extensiones permitidas en 
    $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif' );


    //BUSCAMOS TODOS LOS ATTACHTMENT EN EL FOLDER ACTUAL Y LLENAMOS EL ARRAY attachment_paths
    $attachments_in_folder = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_wp_attached_file'
                AND meta_value LIKE %s",
            $wpdb->esc_like($subfolder) . '%'
        ),
        ARRAY_A
    );
    foreach ( $attachments_in_folder as $attachment ) {
        $attachment_paths[ $attachment['meta_value'] ] = $attachment['post_id'];
    }

    $AllPostsWithAttachtment = $wpdb->get_results("
		    SELECT post_id, meta_value 
		    FROM {$wpdb->prefix}postmeta AS wpostmeta
		    LEFT JOIN {$wpdb->prefix}posts AS wpost ON wpostmeta.post_id = wpost.ID
		    WHERE wpostmeta.meta_key IN('_elementor_data', '_elementor_css', '_thumbnail_id')
		    AND wpost.post_status IN('publish', 'private')
		");

    // echo '<pre>' . htmlspecialchars(print_r($posts, true)) . '</pre>';

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

                //SI NO ES UNA IMAGEN, LO DESCARTAMOS
        		if (!in_array($ext, $allowed_extensions)) continue;

                $filename        = $file->getFilename();
				$relative_path   = str_replace( $base_upload_path, '', $file->getPathname() );
				$file_size_bytes = $file->getSize();
				$attachment_id   = null;
				
                //OBTENEMOS LAS DIMENSIONES DE LA IMAGEN
                $dimensions = 'N/A';
                $image_info = @getimagesize( $file->getPathname() );
                if ( $image_info !== false ) {
                    $dimensions = $image_info[0] . 'x' . $image_info[1];
                }

                //LLENAMOS EL ATTACHMENT ID BUSCANDO EN attachment_paths
                $relative_path_for_db = ltrim( $relative_path, '/' );
                if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
                    $attachment_id = $attachment_paths[ $relative_path_for_db ];
                }

                $to_delete = false;

                $in_content_query = $wpdb->prepare(
					"SELECT COUNT(*) 
				 	FROM $wpdb->posts
				 	WHERE post_content LIKE %s 
				 	AND post_status IN ('publish', 'private')
				 	AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				);

                $programas = $wpdb->prepare(
					"SELECT COUNT(*) 
					 FROM {$wpdb->prefix}learnpress_courses
					 WHERE post_content LIKE %s 
					 AND post_status = 'publish'",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				); 

                if(!isThumbnail($filename)){
                    $filenamewithfolder = str_replace('/', '\/', $relative_path);
                    $in_content = $wpdb->get_var($in_content_query);
                    $programas = $wpdb->get_var($programas);
                    if($in_content == 0 && $programas == 0){
                        $to_delete = true;
                        foreach ($AllPostsWithAttachtment as $post) {
                            if (strpos($post->meta_value, $filenamewithfolder) !== false || $post->meta_value == $attachment_id) {
                                $to_delete = false;
                                break;
                            } 
                        }
                    }
                }

                $newImage = array(
					'full_path'       => $file->getPathname(),
					'relative_path'   => $relative_path,
					'filename'        => $filename,
                    'dimensions'      => $dimensions,
					'size_bytes'      => $file_size_bytes,
					'size_kb'         => $file_size_bytes / 1024,
					'attachment_id'   => $attachment_id,
					'modified_date'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
					'url'             => $base_upload_url . $relative_path,
                    'to_delete'       => $to_delete,
                    'is_thumbnail'    => isThumbnail($filename)
				);

                if(!isThumbnail($filename)){
                    $all_images[] = $newImage;
                } else {
                    $all_thumbnails[] = $newImage;
                }
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
    return array($all_images, $all_thumbnails);
}
?>