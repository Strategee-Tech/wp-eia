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
		global $wpdb;

		$update_data   = array();
		$where         = array('ID' => $post->ID);
    	$info          = pathinfo($original_path);
    	$miniaturas    = find_all_related_thumbnails($original_path);
    	$ext           = '.webp';
    	$mimeType      = 'image/webp';
    	$old_url       = $post->guid;

    	$extension 		    = strtolower($info['extension']); // minúsculas por seguridad
		$extensiones_imagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif', 'heic'];

		if (!in_array($extension, $extensiones_imagen)) {
		   return new WP_REST_Response([
		        'status'  => 'error',
		        'message' => 'El archivo a optimizar no es una imagen.',
		    ], 500);
		} 

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

	    // Determinar el nuevo nombre (usando el slug)
		$slug 		    = sanitize_file_name($params['slug']); // limpiar para que sea válido como nombre de archivo
		$slug_unico     = slug_unico($slug, $params['post_id']);
		$params['slug'] = $slug_unico;
		$slug = $slug_unico;

	    $new_filename = $slug . $ext;
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

		// Obtener la base de uploads
		$wp_uploads_basedir = wp_get_upload_dir()['basedir'];
		$wp_uploads_baseurl = wp_get_upload_dir()['baseurl'];

		// Obtener la subcarpeta donde está el archivo original
		$relative_path = str_replace($wp_uploads_basedir, '', $original_path);  // /2025/06/Banner-Web2-intento-1.webp
		$folder        = dirname($relative_path);                               // /2025/06
		$old_rel_path  = '/wp-content/uploads'.$folder.'/'.$info['basename'];

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

		// Agrega solo si no está vacío
		if (!empty($params['title'])) {
			$update_data['post_title'] = sanitize_text_field($params['title']);
		}
		if (!empty($params['description'])) {
			$update_data['post_content'] = sanitize_textarea_field($params['description']);
		} 
		if (!empty($params['legend'])) {
			$update_data['post_excerpt'] = sanitize_text_field($params['legend']);
		} 
		if (!empty($params['slug'])) {
			$update_data['post_name'] = $params['slug'];
		} 
		$update_data['post_mime_type'] = $mimeType;
		$update_data['guid'] = $new_url;

		// Solo hacer update si hay algo que actualizar
		if (!empty($update_data)) {
			$wpdb->update($wpdb->posts, $update_data, $where);
		}

		// Actualiza texto alternativo si fue enviado
		if (!empty($params['alt_text'])) {
			update_post_meta($params['post_id'], '_wp_attachment_image_alt', $params['alt_text']);
		}

		// Actualizar derivados del metadata
    	update_post_meta($post->ID, '_wp_attached_file', ltrim($folder, '/').'/'.$new_filename);

    	// Regenerar metadatos
    	regenerate_metadata($post->ID);

    	// Actualizar los _elementor_data
		update_post_meta_elementor_data($info['basename'], $new_url, $old_url);

    	//Actualizar elementor_css_url
    	update_elementor_css_url($new_url, $old_url);

    	// Actualizar post_content y Yoast
		update_yoast_info($new_url, $old_url, $post->ID, $old_rel_path);

		wp_cache_flush();

		$datos_drive = array(
		    'id_sheet' => '1r1WXkd812cJPu4BUvIeGDGYXfSsnebSAgOvDSvIEQyM',
		    'sheet'    => 'Imagenes!A1',
		    'values'   => [[
		        date('Y-m-d H:i:s'),
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

        return new WP_REST_Response(
        	array(
        		'status'        => 'success', 
        		'message'       => 'Se han actualizado los datos de SEO y se ha optimizado el archivo.',
        		'new_url'       => $new_url,
        		'size'          => number_format($file_size_bytes_after),
        		'new_name_file' => $new_filename,
        		'dimensions'    => $dimensions,
        	), 200);
   	} catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}

function find_all_related_thumbnails($original_path) {
    $dir 		   = dirname($original_path);
    $filename 	   = basename($original_path);
    $filename_base = preg_replace('/\.[^.]+$/', '', $filename); // sin extensión
    $related_files = [];

    foreach (scandir($dir) as $file) {
        $full_path = $dir . '/' . $file;
        // Detecta:
        // - nombre-300x200.jpg
        // - nombre-scaled.jpg
        // - nombre-scaled-300x200.webp
        if (
            preg_match('/^' . preg_quote($filename_base, '/') . '(-scaled)?(-\d+x\d+)?(\.[a-z0-9]+){1,2}$/i', $file)
            //preg_match('/^' . preg_quote($filename_base, '/') . '(-scaled)?(-\d+x\d+)?\.[a-z0-9]+$/i', $file)
            && $full_path !== $original_path // no borres el original
        ) {
            $related_files[] = $full_path;
        }
    }
    return $related_files;
}