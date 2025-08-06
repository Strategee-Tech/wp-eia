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

    try {
        require_once dirname(ABSPATH).'/google_api_php_client/google-api-php-client-v2.18.3-PHP8.0/vendor/autoload.php';
        $client = new Google_Client();
        $client->setAuthConfig(dirname(ABSPATH).'/google_api_php_client/google-api-php-client-v2.18.3-PHP8.0/credentials/effortless-lock-294114-ae7e961598ae.json');
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