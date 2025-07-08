<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}

add_action( 'rest_api_init', 'wp_optimization_seo_files' );

function wp_optimization_seo_files() {
    register_rest_route( 'api/v1', '/seo-optimization', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'optimization_files',
        'permission_callback' => 'basic_auth_permission_check', 
    )); 
}

function optimization_files($request) {
	// Obtener todos los parámetros de la solicitud POST
    $params = $request->get_params();

    // Validar si se envió el ID del post
	if (empty($params['post_id'])) {
		return new WP_REST_Response(array('status' => 'error', 'message' => 'El parámetro post_id es obligatorio.'), 400);
	}

	$post = get_post($params['post_id']);
	if (!$post || $post->post_type !== 'attachment') {
		return new WP_REST_Response(array('status' => 'error', 'message' => 'El post no existe o no es un attachment.'), 404);
	}
    
    try {
		global $wpdb;

		$update_data   = array();
		$where         = array('ID' => $post->ID);
		$original_path = get_attached_file($post->ID);
    	$info          = pathinfo($original_path);
    	$miniaturas    = find_all_related_thumbnails($original_path);
    	$ext           = '.webp';
    	$mimeType      = 'image/webp';
    	$old_url       = $post->guid;

    	// Crear archivo temporal WebP en la misma carpeta
    	$temp_img = $info['dirname'] . '/' . $info['filename'] . '-opt'.$ext;

    	// Comprimir a WebP al 80%
    	if($params['resize'] == true) {
    		$command = escapeshellcmd("convert '$original_path' -resize 1920x -quality 80 '$temp_img'");
    	} else {
    		$command = escapeshellcmd("convert '$original_path' -quality 80 '$temp_img'");
    	}
	    exec($command, $output, $code);

	    if ($code !== 0 || !file_exists($temp_img)) {
	        return new WP_REST_Response(['status' => 'error', 'message' => 'No se pudo crear la imagen WebP'], 500);
	    }

	    // Determinar el nuevo nombre (usando el slug)
		$slug 	  	  = sanitize_file_name($params['slug']); // limpiar para que sea válido como nombre de archivo
	    $new_filename = $slug . $ext;
		$new_path 	  = $info['dirname'] . '/' . $new_filename;

	 	// Eliminar el archivo original
	 	if(file_exists($original_path)){
    		unlink($original_path); // elimina el original
	 	}	
    	rename($temp_img, $new_path); // renombra el WebP para que quede con el nuevo nombre

    	$upload_dir    = wp_get_upload_dir();
		$relative_path = str_replace($upload_dir['basedir'], '', $original_path);
		$folder        = dirname($relative_path);
		$new_url	   = trailingslashit($upload_dir['url']) . $new_filename;

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
			$update_data['post_title'] = $params['title'];
		}
		if (!empty($params['description'])) {
			$update_data['post_content'] = $params['description'];
		} 
		if (!empty($params['legend'])) {
			$update_data['post_excerpt'] = $params['legend'];
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

    	// Actualizar post_content y Yoast
		update_yoast_info($new_url, $old_url, $post->ID);

		wp_cache_flush();

        return new WP_REST_Response(array('status' => 'success', 'message' => 'Se han actualizado los datos de SEO y se ha optimizado el archivo.'), 200);
   	} catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}

// Función de validación de permisos con autenticación básica
function basic_auth_permission_check($request) {
	// Ruta absoluta al archivo credentials.php
    $path_to_credentials = dirname(ABSPATH) . '/credentials.php';

    // Verificación si el archivo existe antes de incluirlo
    if (file_exists($path_to_credentials)) {
        require_once($path_to_credentials);
    } else {
        return new WP_Error('server_error', 'No se encontró el archivo de credenciales.', array('status' => 500));
    }

    // Verificar si el encabezado 'Authorization' está presente
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

    if (!$auth_header || strpos($auth_header, 'Basic ') !== 0) {
        return new WP_Error('unauthorized', 'Authorization header missing or incorrect.', array('status' => 401));
    }

    // Extraer las credenciales de la cabecera 'Authorization'
    $encoded_credentials = substr($auth_header, 6); // Eliminar 'Basic '
    $decoded_credentials = base64_decode($encoded_credentials);

    // Separar las credenciales en usuario y contraseña
    list($username, $password) = explode(':', $decoded_credentials);

    // Validar las credenciales con las que se han enviado
    if ($username !== AUTH_USER_BASIC || $password !== AUTH_PASSWORD_BASIC) {
        return new WP_Error('forbidden', 'Invalid credentials.', array('status' => 403));
    }

    // Si las credenciales son correctas, permitir el acceso
    return true;
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

function regenerate_metadata($attachment_id){
	$attachment = get_post( $attachment_id );
    try {
        $attachment = get_post( $attachment_id );
        if ( $attachment && $attachment->post_type === 'attachment' ) {
            $metadata = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
            update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
            return new WP_REST_Response(array('status' => 'success', 'message' => 'Metadata regenerada correctamente'), 200);
        } else {
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Attachment no encontrado'), 404);
        }
    } catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}

function update_yoast_info($new_url, $old_url, $post_id) {
	global $wpdb;
	//actualizar post_content de una imagen dentro de una pagina
	$wpdb->query(
	    $wpdb->prepare(
	        "UPDATE {$wpdb->posts} 
	        SET post_content = REPLACE(post_content, %s, %s) 
	        WHERE post_content LIKE %s AND post_status IN ('publish', 'private', 'draft') AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
	        $old_url,
	        $new_url,
	        '%' . basename($old_url) . '%'
	    )
	);

	//actualizar post_content de una imagen dentro de un programa
	$wpdb->query(
	    $wpdb->prepare(
	        "UPDATE {$wpdb->prefix}learnpress_courses 
	        SET post_content = REPLACE(post_content, %s, %s) 
	        WHERE post_content LIKE %s AND post_status IN ('publish', 'private', 'draft')",
	        $old_url,
	        $new_url,
	        '%' . basename($old_url) . '%'
	    )
	);

	// Tabla de Yoast SEO
	$tabla_yoast_seo_links = $wpdb->prefix . 'yoast_seo_links';
	$tabla_indexable 	   = $wpdb->prefix . 'yoast_indexable';
	
    // Actualizar tabla yoast_indexable (open graph y twitter image)
	$wpdb->query(
	    $wpdb->prepare(
	        "UPDATE $tabla_indexable 
	        SET open_graph_image = %s, twitter_image = %s
	        WHERE open_graph_image = %s AND twitter_image = %s",
	        $new_url,     // nuevo open_graph_image
	        $new_url,     // nuevo twitter_image
	        $old_url,     // viejo open_graph_image
	        $old_url      // viejo twitter_image
	    )
	);

	// Actualizar tabla yoast_seo_links (url general)
	$wpdb->query(
	    $wpdb->prepare(
	        "UPDATE $tabla_yoast_seo_links
	         SET url = %s
	         WHERE url = %s",
	        $new_url,
	        $old_url
	    )
	);

	// 1. Buscar todas las filas que contienen la URL antigua
	$filas = $wpdb->get_results(
	    $wpdb->prepare(
	        "SELECT id, open_graph_image_meta FROM $tabla_indexable 
	         WHERE open_graph_image_meta LIKE %s",
	        '%' . $old_url . '%'
	    ),
	    ARRAY_A
	);


	// echo "<pre>";
	// print_r($old_url);
	// echo "<br>";

 // 	print_r($filas);
	// echo "<br>"; 


	// 2. Iterar sobre cada fila y actualizar si aplica
	if(!empty($filas)) {
		foreach ($filas as $fila) {
		    $json = $fila['open_graph_image_meta'];
		    $id   = $fila['id'];

		    $meta = json_decode($json, true); // Convertir a array asociativo

		 //    print_r($meta);
			// die(); 

		    if (json_last_error() === JSON_ERROR_NONE && is_array($meta)) {
		        // 3. Reemplazar solo la clave "url" si coincide
		        if (isset($meta['url']) && $meta['url'] == $old_url) {
		            $meta['url'] = $new_url;

		            // 4. Codificar de nuevo el JSON
		            $nuevo_json = wp_json_encode($meta);

		            // 5. Actualizar en base de datos
		            $wpdb->update(
		                $tabla_indexable,
		                ['open_graph_image_meta' => $nuevo_json],
		                ['id' => $id]
		            );
		        }
		    } 
		}
	}
}
