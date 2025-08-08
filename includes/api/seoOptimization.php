<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}
require_once dirname(__DIR__) . '/utils/auth.php';

add_action( 'rest_api_init', 'wp_optimization_seo_files' );

date_default_timezone_set('America/Bogota');

function wp_optimization_seo_files() {
    register_rest_route( 'api/v1', '/seo-optimization', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'optimization_files',
        'permission_callback' => function () {
        	require_once dirname(__DIR__) . '/utils/auth.php';
        	return basic_auth_permission_check();
    	}, 
    )); 
}

function optimization_files($request) {
    $params = $request->get_params();

	if (empty($params['post_id'])) {
		return new WP_REST_Response(array('status' => 'error', 'message' => 'El parámetro post_id es obligatorio.'), 400);
	}

	$post = get_post($params['post_id']);
	if (!$post || $post->post_type !== 'attachment') {
		return new WP_REST_Response(array('status' => 'error', 'message' => 'El post no existe o no es un attachment.'), 404);
	}

	$original_path = get_attached_file( $params['post_id'] );
    if (!file_exists($original_path)) {
        return new WP_REST_Response( array( 'error' => 'Archivo no encontrado.' ), 404 );
    }
    
    try {
    	$info      = pathinfo($original_path);
    	$extension = strtolower($info['extension']);

    	// minúsculas por seguridad
		$extensiones_imagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif', 'heic'];

		if (!in_array($extension, $extensiones_imagen)) {
		    return new WP_REST_Response([
		        'status'  => 'error',
		        'message' => 'El archivo a optimizar no es una imagen.',
		    ], 500);
		} 

		global $wpdb;
    	if($params['fast_edit'] == 1) {
			actualizar_post_postmeta($params, $wpdb);
			return new WP_REST_Response([
				'status'        => 'success',
				'message'       => 'Se ha actualizado la información.',
			], 200);
		} else {

			// Obtener la base de uploads
			$wp_uploads_basedir = wp_get_upload_dir()['basedir'];
			$wp_uploads_baseurl = wp_get_upload_dir()['baseurl'];

			if (preg_match('/(-scaled|-\d+x\d+)(?=\.[^.]+$)/i', $original_path)) {
			    $original_base_path = preg_replace('/(-scaled|-\d+x\d+)+(?=\.[^.]+$)/', '', $original_path);
				$miniaturas         = find_all_related_thumbnails($original_path);
				$relative_path      = str_replace($wp_uploads_basedir, '', $original_base_path);
			} else {
				$miniaturas 	= find_all_related_thumbnails($original_path);
				$relative_path  = str_replace($wp_uploads_basedir, '', $original_path);  // /2025/06/Banner-Web2-intento-1.webp
			}
			$old_min_urls    = get_related_urls($original_path, $miniaturas);
			$mins_to_replace = array_merge([$relative_path], $old_min_urls);
	    	$ext             = '.webp';
	    	$mimeType        = 'image/webp';
	    	$old_url         = $post->guid;
	    	
	    	// Crear archivo temporal WebP en la misma carpeta
	    	$temp_img = $info['dirname'] . '/' . $info['filename'] . '-opt'.$ext;

	    	if(!isset($params['resize'])) {
	    		$params['resize'] = false;
	    	}

	    	try {
		    	$compress_file = call_compress_api('imagen', $original_path, $temp_img, $params['resize']);
			    if (!file_exists($compress_file) || filesize($compress_file) === 0) {
			    	return new WP_REST_Response([
				        'status'  => 'error',
				        'message' => 'El archivo comprimido no se recibió correctamente.',
				    ], 500);
			    } 

		  	} catch (Exception $e) {
			    return new WP_REST_Response([
			        'status'  => 'error',
			        'message' => 'Falló la compresión de la imagen.',
			        'detalle' => $e->getMessage()
			    ], 500);
			}

			$params['slug'] = slug_unico(
			    sanitize_file_name($params['slug']),
			    $params['post_id']
			);

		    $new_filename = $params['slug'] . $ext;
			$new_path 	  = $info['dirname'] . '/' . $new_filename;

			$file_size_bytes_before = filesize($original_path) / 1024;

		 	// Eliminar el archivo original
		 	if(file_exists($original_path)){
	    		unlink($original_path); // elimina el original
		 	}	
	    	rename($compress_file, $new_path); // renombra el WebP para que quede con el nuevo nombre

	    	$dimensions = 'N/A';
	        $image_info = @getimagesize( $new_path );
	        if ( $image_info !== false ) {
	            $dimensions = $image_info[0] . 'x' . $image_info[1];
	        }
	        $file_size_bytes_after = filesize($new_path) / 1024;


			// Obtener la subcarpeta donde está el archivo original
			$folder        = dirname($relative_path); // /2025/06
			$old_rel_path  = '/wp-content/uploads'.$folder.'/'.$info['basename'];
			$new_rel_path  = $folder.'/'.$new_filename;

			// Construir la nueva URL en la misma carpeta del archivo original
			$new_url = trailingslashit($wp_uploads_baseurl . $folder) . $new_filename;
			$new_url = esc_url_raw($new_url);

			// Eliminar miniaturas
			if(!empty($miniaturas)) {
	    		foreach ($miniaturas as $key => $path) {
	    			if(file_exists($path)) {
	    				unlink($path);
	    			}
	    		}
	    	}
	    	$params['post_name']      = $params['slug'];
			$params['guid']           = esc_url_raw($new_url); 
			$params['post_mime_type'] = $mimeType;
	    	actualizar_post_postmeta($params, $wpdb, true);

			// Actualizar derivados del metadata
	    	update_post_meta($post->ID, '_wp_attached_file', ltrim($folder, '/').'/'.$new_filename);

	    	// Regenerar metadatos
	    	regenerate_metadata($post->ID);
 
	    	$news_miniaturas      = find_all_related_thumbnails($new_path);
	    	$new_mins_urls        = get_related_urls($new_path, $news_miniaturas);
			$news_mins_to_replace = array_merge([$new_rel_path], $new_mins_urls);

			foreach ($mins_to_replace as $i => $old_miniature_rel_path) {
				if (isset($news_mins_to_replace[$i])) {
			  		update_urls(
					    $old_miniature_rel_path,
					    $news_mins_to_replace[$i],
					    $post->ID
					); 
		    	}
			}
			
			wp_cache_flush();
			clean_post_cache($post->ID);

			$sheet_id = get_option('google_sheet_id');
			$sheet    = get_option('name_sheet_images');

			if(!empty($sheet_id) && !empty($sheet)) {
				$datos_drive = array(
				    'id_sheet' => $sheet_id,
				    'sheet'    => $sheet.'!A1',
				    'values'   => [[
				        date('Y-m-d H:i:s'),
				        $old_url,
				        $new_url,
				        number_format($file_size_bytes_before),
				        number_format($file_size_bytes_after),
				        isset($params['alt_text']) ? $params['alt_text'] : '',
				        isset($params['slug']) ? $params['slug'] : '',
				        isset($params['title']) ? $params['title'] : '',
				        isset($params['description']) ? $params['description'] : '',
				        $mimeType,
				        $dimensions,
				        (isset($params['ia']) && $params['ia'] == true) ? 'Si' : 'No',
				    ]]
				);
				$respuesta = save_google_sheet($datos_drive); // Llamada directa
			}

	        return new WP_REST_Response([
        		'status'        => 'success', 
        		'message'       => 'Se han actualizado los datos de SEO y se ha optimizado el archivo.',
        		'new_url'       => $new_url,
        		'size'          => number_format($file_size_bytes_after),
        		'new_name_file' => $new_filename,
        		'dimensions'    => $dimensions,
        	], 200);

		}
   	} catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}

function find_all_related_thumbnails($original_path) {
	$original_path_clean = preg_replace('/(-scaled|-\d+x\d+)+(?=\.[^.]+$)/', '', $original_path);

    $dir 		   = dirname($original_path_clean);
    $filename 	   = basename($original_path_clean);
    $filename_base = preg_replace('/\.[^.]+$/', '', $filename); // sin extensión
    $related_files = [];

    foreach (scandir($dir) as $file) {
        $full_path = $dir . '/' . $file;
        // Detecta:
        // - nombre-300x200.jpg
        // - nombre-scaled.jpg
        // - nombre-scaled-300x200.webp
        if (
            preg_match('/^' . preg_quote($filename_base, '/') . '(-(scaled|\d+x\d+)){0,2}(\.[a-z0-9]+){1,2}$/i', $file)
            //preg_match('/^' . preg_quote($filename_base, '/') . '(-scaled)?(-\d+x\d+)?(\.[a-z0-9]+){1,2}$/i', $file)
            //preg_match('/^' . preg_quote($filename_base, '/') . '(-scaled)?(-\d+x\d+)?\.[a-z0-9]+$/i', $file)
            && $full_path !== $original_path_clean // no borres el original
        ) {
            $related_files[] = $full_path;
        }
    }

    // Ordenar por tamaño (extraer ancho y alto del nombre)
    usort($related_files, function($a, $b) {
        preg_match('/-(\d+)x(\d+)/', $a, $matchA);
        preg_match('/-(\d+)x(\d+)/', $b, $matchB);

        $widthA = isset($matchA[1]) ? (int)$matchA[1] : 0;
        $widthB = isset($matchB[1]) ? (int)$matchB[1] : 0;
        $heightA = isset($matchA[2]) ? (int)$matchA[2] : 0;
        $heightB = isset($matchB[2]) ? (int)$matchB[2] : 0;

        // Orden por ancho primero, luego alto
        return ($widthA <=> $widthB) ?: ($heightA <=> $heightB);
    });

    return $related_files;
}

function get_related_urls($original_path, $miniaturas) {
    $upload_dir   = wp_get_upload_dir();
    // $base_url     = $upload_dir['baseurl'];
    $base_dir     = $upload_dir['basedir'];
    $related_urls = [];
    if(!empty($miniaturas)) {
    	foreach ($miniaturas as $path) {
        	$relative = str_replace($base_dir, '', $path);
        	$related_urls[] = $relative;
    	}
    }
    return $related_urls;
}