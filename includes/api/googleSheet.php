<?php

add_action( 'rest_api_init', 'wp_store_google_sheet' );

function wp_store_google_sheet() {
    register_rest_route( 'api/v1', '/store-google-sheet', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'save_google_sheet',
        'permission_callback' => 'basic_auth_permission_check2', 
    )); 
}


function save_google_sheet($request) {
    require_once dirname(__FILE__) . '/../utils/google_sheet_2.0.0/vendor/autoload.php';
    $client = new Google_Client();
    $client->setAuthConfig(ROOT . DS .'client_secret_1065046989376-tbket75qsb90vg21lejeercjhot7i90t.apps.googleusercontent.com.json');
    $client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline'); // Acceso offline para obtener tokens de actualización
    $client->setDeveloperKey("AIzaSyD8B9Ff8DG-sNI_iYZvN-i2IHuzcUipUik"); //cuenta notificaciones@strategee.us, password mercadeo93

    

    echo "<pre>";
    print_r($client);
    die(); 

}

// Función de validación de permisos con autenticación básica
function basic_auth_permission_check2($request) {
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