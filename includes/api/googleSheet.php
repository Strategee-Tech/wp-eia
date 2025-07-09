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
    require_once '/var/www/html/google_api_php_client/google-api-php-client-v2.18.3-PHP8.0/vendor/autoload.php';
    $datos = $request->get_json_params();

    if (!$datos) {
        return new WP_REST_Response(['error' => 'No se enviaron datos'], 400);
    }

    try {
        // Configurar el cliente con la cuenta de servicio
        $client = new Google_Client();
        $client->setAuthConfig('/var/www/html/google_api_php_client/google-api-php-client-v2.18.3-PHP8.0/credentials/effortless-lock-294114-ae7e961598ae.json'); // ruta al archivo JSON
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);

        // ID de la hoja y rango donde se insertarán datos
        $spreadsheetId = $datos['id_sheet'];
        $range   = $datos['sheet'];
        
        $service = new Google_Service_Sheets($client);

        // Armar los datos a insertar (debes adaptar a lo que recibes)
        $valores = [
            [
                $datos['fecha'],
                $datos['new_url'],      
                $datos['peso_antes'],
                $datos['peso_despues'],
                $datos['alt_text_opt'],
                $datos['slug_opt'],
                $datos['title_opt'],
                $datos['description_opt'],
                $datos['format_opt'],
                $datos['size_opt'], 
                $datos['ia' ]
            ]
        ];

        $body   = new Google_Service_Sheets_ValueRange(['values' => $valores]);
        $params = ['valueInputOption' => 'RAW'];

        $resultado = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

        return new WP_REST_Response([
            'status' => 'ok',
            'filas_insertadas' => $resultado->getUpdates()->getUpdatedRows()
        ], 200);

    } catch (Exception $e) {
        return new WP_REST_Response(['error' => $e->getMessage()], 500);
    }
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