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
    echo "<pre>";
    print_r($params);

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

		// Agrega solo si no está vacío
		if (!empty($params['guid'])) {
			$update_data['guid'] = $params['guid'];
		}
		if (!empty($params['title'])) {
			$update_data['post_title'] = $params['title'];
		}
		if (!empty($params['slug'])) {
			$update_data['post_name'] = $params['slug'];
		} 
		//$update_data['post_mime_type'] = 'image/webp';


		// echo "<pre>";
		// print_r($update_data);
		// die(); 

		// Solo hacer update si hay algo que actualizar
		if (!empty($update_data)) {
			$wpdb->update($wpdb->posts, $update_data, $where);
		}

		// Actualiza alt_text si fue enviado
		if (!empty($params['alt_text'])) {
			update_post_meta($params['post_id'], '_wp_attachment_image_alt', $params['alt_text']);
		}
		//update_post_meta($post_id, '_wp_attached_file', $folder.$slug.'.webp');
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