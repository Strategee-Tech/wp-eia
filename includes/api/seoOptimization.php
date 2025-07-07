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
    die();  // Detener la ejecución para que solo se imprima una vez


    // $attachment_id = $request->get_param('attachment_id');
    // try {
    //     $attachment = get_post( $attachment_id );
    //     if ( $attachment && $attachment->post_type === 'attachment' ) {
    //         $metadata = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
    //         update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
    //         return new WP_REST_Response(array('status' => 'success', 'message' => 'Metadata regenerada correctamente'), 200);
    //     } else {
    //         return new WP_REST_Response(array('status' => 'error', 'message' => 'Attachment no encontrado'), 404);
    //     }
    // } catch (\Throwable $th) {
    //     return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    // }
}

// Función de validación de permisos con autenticación básica
function basic_auth_permission_check($request) {
	$site_folder 		 = basename(dirname(ABSPATH));  // Obtener el nombre de la carpeta
	$path_to_credentials = dirname(ABSPATH) . "/$site_folder/credentials.php";  // Construir la ruta al archivo

	require_once($path_to_credentials);

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