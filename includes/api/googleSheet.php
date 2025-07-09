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


function save_google_sheet($request) {
    require_once dirname(ABSPATH).'/google_api_php_client/google-api-php-client-v2.18.3-PHP8.0/vendor/autoload.php';
    $datos = $request;

    if (!$datos) {
        return new WP_REST_Response(['error' => 'No se enviaron datos'], 400);
    }

    try {
        // Configurar el cliente con la cuenta de servicio
        $client = new Google_Client();
        $client->setAuthConfig(dirname(ABSPATH).'/google_api_php_client/google-api-php-client-v2.18.3-PHP8.0/credentials/effortless-lock-294114-ae7e961598ae.json'); // ruta al archivo JSON
        $client->addScope(Google_Service_Sheets::SPREADSHEETS);

        // ID de la hoja y rango donde se insertarán datos
        $spreadsheetId = $datos["id_sheet"];
        $range   = $datos["sheet"];

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