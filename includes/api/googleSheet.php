<?php
require_once dirname(__DIR__) . '/utils/auth.php';
add_action( 'rest_api_init', 'wp_store_google_sheet' );

function wp_store_google_sheet() {
    register_rest_route( 'api/v1', '/store-google-sheet', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'save_google_sheet',
        'permission_callback' => function () {
            require_once dirname(__DIR__) . '/utils/auth.php';
            return basic_auth_permission_check();
        }, 
    )); 
}

function save_google_sheet($datos) {
    if (empty($datos) || !isset($datos['values']) || !isset($datos['id_sheet']) || !isset($datos['sheet'])) {
        return new WP_REST_Response(['error' => 'Faltan datos necesarios'], 400);
    }

    if(empty($datos['id_sheet']) || empty($datos['sheet'])) {
        return new WP_REST_Response(['error' => 'Faltan datos necesarios'], 400);
    }

    try {
        $ruta_google_api = trailingslashit(ABSPATH . 'wp-content/google_api_php_client');
        $autoload_path   = $ruta_google_api . 'vendor/autoload.php';
        $credenciales    = $ruta_google_api . 'credentials/info_credentials.json';

        // Validaciones previas
        if (!file_exists($autoload_path)) {
            throw new Exception('No se encontró el archivo autoload.php en: ' . $autoload_path);
        }

        if (!file_exists($credenciales)) {
            throw new Exception('No se encontró el archivo de credenciales en: ' . $credenciales);
        }

        require_once $autoload_path;

        $client = new Google_Client();
        $client->setAuthConfig($credenciales);
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);

        $service       = new Google_Service_Sheets($client);
        $spreadsheetId = $datos['id_sheet'];
        $range         = $datos['sheet'];
        $values        = $datos['values']; // Este debe ser un array de arrays [[...], [...], ...]

        $body   = new Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'RAW'];

        $response = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

        return new WP_REST_Response([
            'status' => 'ok',
            'filas_insertadas' => $response->getUpdates()->getUpdatedRows()
        ], 200);

    } catch (Exception $e) {
        return new WP_REST_Response(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}