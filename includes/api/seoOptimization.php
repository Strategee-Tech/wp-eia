<?php

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

    // Imprimir todos los parámetros para depuración
    //echo "<pre>";
    //print_r($params);

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

		$update_data = array();
		$where       = array('ID' => $post->ID);

		$original_path = get_attached_file($post->ID);
    	$info          = pathinfo($original_path);
    	$miniaturas    = find_all_related_thumbnails($original_path);


    	// echo "<pre>";
    	// print_r($miniaturas);
    	// die(); 

    	// Crear archivo temporal WebP en la misma carpeta
    	$temp_webp = $info['dirname'] . '/' . $info['filename'] . '_temp.webp';

    	// Comprimir a WebP al 80%
	    // $command = escapeshellcmd("convert '$original_path' -quality 80 '$temp_webp'");
	    // exec($command, $output, $code);

	    // if ($code !== 0 || !file_exists($temp_webp)) {
	    //     return new WP_REST_Response(['status' => 'error', 'message' => 'No se pudo crear la imagen WebP'], 500);
	    // }

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
		if (!empty($params['guid'])) {
			$update_data['guid'] = $params['guid'];
		}
		if (!empty($params['slug'])) {
			$update_data['post_name'] = $params['slug'];
		} 
		//$update_data['post_mime_type'] = 'image/webp';

		// Solo hacer update si hay algo que actualizar
		if (!empty($update_data)) {
			$wpdb->update($wpdb->posts, $update_data, $where);
		}

		// Actualiza alt_text si fue enviado
		if (!empty($params['alt_text'])) {
			update_post_meta($params['post_id'], '_wp_attachment_image_alt', $params['alt_text']);
		}
		//update_post_meta($post_id, '_wp_attached_file', $folder.$slug.'.webp');

		if (!empty($params['old_url']) && !empty($params['new_url'])) {

			$old_url = $params['old_url'];
			$new_url = $params['new_url'];

			//actualizar post_content de una imagen dentro de una pagina
			//parametros: old_url, new_url
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
			 
			// Obtener el indexable_id desde wp_yoast_seo_links
			$indexable_id = $wpdb->get_var(
			    $wpdb->prepare(
			        "SELECT indexable_id FROM $tabla_yoast_seo_links 
			         WHERE post_id = %d AND type = %s LIMIT 1",
			        $post->ID,
			        'image-in'
			    )
			);

			if ($indexable_id) { 
			    $wpdb->query(
				    $wpdb->prepare(
				        "UPDATE $tabla_indexable 
				        SET open_graph_image = %s,
				        twitter_image     = %s
				        WHERE id = %d",
				        $new_url,     // %s → open_graph_image
				        $new_url,     // %s → twitter_image
				        $indexable_id // %d → id
				    )
				);

			    $wpdb->query(
				    $wpdb->prepare(
				        "UPDATE $tabla_yoast_seo_links
				        SET url = %s
				        WHERE post_id = %d AND url = %s AND type = %s",
				        $new_url,
				        $post->ID,
				        $old_url,
				        'image-in'
				    )
				);
			}
		}
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
    $dir = dirname($original_path);
    $filename = basename($original_path);
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
