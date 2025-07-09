<?php


// Función de validación de permisos con autenticación básica
function basic_auth_permission_check() {
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


function slug_unico($slug_deseado, $id_actual = 0) {
    global $wpdb;

    $slug_deseado = sanitize_title($slug_deseado);

    // Verifica si ese slug ya está en uso por otro attachment
    $sql = $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts 
         WHERE post_name = %s 
           AND post_type = 'attachment'
           AND post_status != 'trash'
           AND ID != %d
         LIMIT 1",
        $slug_deseado,
        $id_actual
    );

    $existe_id = $wpdb->get_var($sql);

    if (!$existe_id) {
        // Slug libre o es suyo mismo
        return $slug_deseado;
    }

    // Slug ya usado por otro → generar uno único
    return wp_unique_post_slug($slug_deseado, $id_actual, 'inherit', 'attachment', 0);
}
