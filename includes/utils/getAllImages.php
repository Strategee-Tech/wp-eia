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


    wp_enqueue_script(
        'sendToApi', // Handle o identificador único
        plugins_url( 'sendToApi.js', __FILE__ ), // URL del script dentro de tu plugin
        array(), // Dependencias (ninguna en este caso)
        '1.0.0', // Versión
        true // Cargar en el footer
    );
    wp_enqueue_script(
        'geminiPost', // Handle o identificador único
        plugins_url( 'geminiPost.js', __FILE__ ), // URL del script dentro de tu plugin
        array(), // Dependencias (ninguna en este caso)
        '1.0.0', // Versión
        true // Cargar en el footer
    );
	
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
    $all_delete_names   = array();
    $all_scaleds_names  = array();
    $attachment_paths   = array();
    $attachment_alts    = array();
    $attachment_titles  = array();
    $attachment_contents  = array();

    //Todas la extensiones permitidas en 
    $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif' );


    //BUSCAMOS TODOS LOS ATTACHTMENT EN EL FOLDER ACTUAL Y LLENAMOS EL ARRAY attachment_paths
    $attachments_in_folder = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                p.post_id,
                p.meta_value,
                alt.meta_value as alt,
                post.post_title,
                post.post_content
            FROM 
                {$wpdb->postmeta} as p
            LEFT JOIN 
                {$wpdb->postmeta} as alt ON p.post_id = alt.post_id AND alt.meta_key = '_wp_attachment_image_alt'
            LEFT JOIN 
                {$wpdb->posts} as post ON p.post_id = post.ID
            WHERE p.meta_key = '_wp_attached_file'
                AND p.meta_value LIKE %s",
            $wpdb->esc_like($subfolder) . '%'
        ),
        ARRAY_A
    );

    foreach ( $attachments_in_folder as $attachment ) {
        $attachment_paths[ $attachment['meta_value'] ] = $attachment['post_id'];
        $attachment_alts[ $attachment['post_id'] ] = $attachment['alt'];
        $attachment_titles[ $attachment['post_id'] ] = $attachment['post_title'];
        $attachment_contents[ $attachment['post_id'] ] = $attachment['post_content'];
    }

    $AllPostsWithAttachtment = $wpdb->get_results("
		    SELECT post_id, meta_value 
		    FROM {$wpdb->prefix}postmeta AS wpostmeta
		    LEFT JOIN {$wpdb->prefix}posts AS wpost ON wpostmeta.post_id = wpost.ID
		    WHERE wpostmeta.meta_key IN('_elementor_data', '_elementor_css', '_thumbnail_id')
		    AND wpost.post_status IN('publish', 'private', 'draft')
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

        //BUSCAMOS TODOS LOS SCALED EN EL FOLDER ACTUAL Y LLENAMOS EL ARRAY all_scaleds_names
        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $filename = $file->getFilename();
				// Detectar si contiene '-scaled'
				if (strpos($filename, '-scaled') !== false) {
					$pathinfo = pathinfo($filename);
					//echo "Path antes de limpiar: " . $pathinfo['filename'] . "\n";

					// Eliminar solo '-scaled' al final del nombre (antes de la extensión)
					$name_clean = str_replace('-scaled', '', $pathinfo['filename']);

					// Reconstruir el nombre original
					$original_filename = $name_clean . '.' . $pathinfo['extension'];

					//echo "Original limpio: " . $original_filename . "\n";
					$all_scaleds_names[] = $original_filename;
				}
			}
		}
		
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

                $alt = '';
                $title = '';
                $description = '';

                //LLENAMOS EL ATTACHMENT ID BUSCANDO EN attachment_paths
                $relative_path_for_db = ltrim( $relative_path, '/' );
                if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
                    $attachment_id = $attachment_paths[ $relative_path_for_db ];
                }

                if(isset($attachment_alts[$attachment_id])){
                    $alt = $attachment_alts[$attachment_id];
                }

                if(isset($attachment_titles[$attachment_id])){
                    $title = $attachment_titles[$attachment_id];
                }

                if(isset($attachment_contents[$attachment_id])){
                    $description = $attachment_contents[$attachment_id];
                }

                $to_delete = false;

                $in_content_query = $wpdb->prepare(
					"SELECT COUNT(*) 
				 	FROM $wpdb->posts
				 	WHERE post_content LIKE %s 
				 	AND post_status IN ('publish', 'private', 'draft')
				 	AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				);

                $programas = $wpdb->prepare(
					"SELECT COUNT(*) 
					 FROM {$wpdb->prefix}learnpress_courses
					 WHERE post_content LIKE %s 
					 AND post_status IN ('publish', 'private', 'draft')",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				); 

                if(!isThumbnail($filename)){
                    $filenamewithfolder = str_replace('/', '\/', $relative_path);
                    $in_content = $wpdb->get_var($in_content_query);
                    $programas = $wpdb->get_var($programas);
                    if($in_content == 0 && $programas == 0){
                        $to_delete = true;
                        foreach ($AllPostsWithAttachtment as $post) {
                            if (strpos($post->meta_value, $filenamewithfolder) !== false || $post->meta_value == $attachment_id || strpos($post->meta_value, $relative_path) !== false) {
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
                    'title'           => $title,
                    'alt'             => $alt,
                    'description'     => $description,
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
                    if($to_delete){
                        $all_delete_names[] = $filename;
                    }
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

    
    foreach ($all_thumbnails as &$thumbnail) {
        $original_thumbnail_name = get_original_data_from_thumbnail($thumbnail['url']);
        if(in_array($original_thumbnail_name['name_clean'], $all_delete_names) && $thumbnail['to_delete'] == false){
            $thumbnail['to_delete'] = true;
        }
    }
    unset($thumbnail);


    $to_delete_with_index = array();
    $to_delete_without_index = array();

    foreach ($all_images as $image) {
        if($image['to_delete'] == true){

            if(isset($image['attachment_id']) && $image['attachment_id'] != null){
                $to_delete_with_index[] = $image['attachment_id'];
            } else {
                $to_delete_without_index[] = $image['full_path'];
            }
        }
    }

    foreach($all_thumbnails as $thumbnail){
        if($thumbnail['to_delete'] == true){
            $to_delete_without_index[] = $thumbnail['full_path'];
        }
    }


    wp_localize_script(
        'sendToApi',                    // El handle del script al que deseas adjuntar los datos
        'imagesToDelete',               // El nombre del objeto JavaScript global
        array($to_delete_with_index, $to_delete_without_index)           // Tus datos PHP
    );

    return array(
        'all_images' => $all_images,
        'all_thumbnails' => $all_thumbnails,
        'all_scaleds_names' => $all_scaleds_names,
        'all_delete_names' => $all_delete_names
    );
}


?>