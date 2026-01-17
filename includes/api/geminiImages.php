<?php

require_once dirname(__DIR__) . '/utils/auth.php';

add_action( 'rest_api_init', 'wp_gemini' );

date_default_timezone_set('America/Bogota');

function wp_gemini() {
    register_rest_route( 'api/v1', '/gemini', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'gemini',
        'permission_callback' => function () {
        	require_once dirname(__DIR__) . '/utils/auth.php';
        	return basic_auth_permission_check();
    	}, 
    )); 
}

function gemini($request) {
    $params   = $request->get_json_params(); 
    $imageUrl = $params['imageUrl'];

    if (empty($params) || !is_array($params)) {
        return new WP_REST_Response([
            'status'  => 'error',
            'error'   => 'Error en el servicio. Intenta nuevamente.',
            'message' => 'No se recibió un JSON válido.'
        ], 400);
    }
    
    try{
        $metadata = generateImageMetadata($imageUrl);
    } catch (Exception $e) {
        return new WP_REST_Response([
            'status'  => 'error',
            'error'   => $e->getMessage(),
            'message' => 'Error al generar la información con Gemini.'
        ], 500);
    } 

    return new WP_REST_Response([
        $metadata
    ], 200);
}

function imageUrlToBase64(string $imageUrl): string {
    $imageData = file_get_contents($imageUrl);
    if ($imageData === false) {
        throw new Exception("No se pudo obtener la imagen de la URL: " . $imageUrl);
    }
    return base64_encode($imageData);
}

function generateImageMetadata(string $imageUrl): array {

    $prompt      = get_option('gemini_prompt');
    $base64Image = imageUrlToBase64($imageUrl);

    if(empty($prompt)) {
        throw new Exception("Error al conectar con la API de Google Gemini. Verifica si se ha configurado la Apikey, la url de la Api o el prompt.");
    }

    $imageExtension = pathinfo($imageUrl, PATHINFO_EXTENSION);
    $imageMimeType = 'image/jpeg'; // Valor por defecto
    if ($imageExtension === 'png') {
        $imageMimeType = 'image/png';
    } elseif ($imageExtension === 'webp') {
        $imageMimeType = 'image/webp';
    } elseif ($imageExtension === 'gif') {
        $imageMimeType = 'image/gif';
    } elseif ($imageExtension === 'svg') {
        $imageMimeType = 'image/svg+xml';
    } elseif ($imageExtension === 'avif') {
        $imageMimeType = 'image/avif';
    } elseif ($imageExtension === 'heic') {
        $imageMimeType = 'image/heic';
    } elseif ($imageExtension === 'bmp') {
        $imageMimeType = 'image/bmp';
    } elseif ($imageExtension === 'tiff') {
        $imageMimeType = 'image/tiff';
    } elseif ($imageExtension === 'heif') {
        $imageMimeType = 'image/heif';
    } 

    $requestBody = [
        'contents' => [
            [
                'parts' => [
                    [
                        'text' => $prompt
                    ],
                    [
                        'inline_data' => [
                            'mime_type' => $imageMimeType,
                            'data'      => $base64Image
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'title' => ['type' => 'STRING', 'description' => 'Título conciso y descriptivo de la imagen.'],
                    'description' => ['type' => 'STRING', 'description' => 'Descripción detallada de la imagen.'],
                    'alt' => ['type' => 'STRING', 'description' => 'Texto alternativo para la imagen, optimizado para SEO y accesibilidad.'],
                    'slug' => ['type' => 'STRING', 'description' => 'Slug amigable para URL, en minúsculas y con guiones.']
                ],
                'required' => ['title', 'description', 'alt', 'slug']
            ]
        ]
    ];

     // OJO: el central espera { requestBody: ... }
    $payload = array(
        'requestBody' => $requestBody, 
    );

    $centralUrl = 'https://apicompressv2.strategee.us/gemini.php';

    $args = array(
        'headers' => array(
            'Content-Type' => 'application/json', 
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 300,
    );

    $res = wp_remote_post($centralUrl, $args);

    if (is_wp_error($res)) {
        throw new Exception('Error conectando al servidor central: ' . $res->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    $data = json_decode($body, true);

    if ($code !== 200) {
        $msg = is_array($data) && isset($data['error']) ? $data['error'] : 'Error desconocido';
        throw new Exception("Central API error: {$msg} (HTTP {$code})");
    }

    if (!is_array($data)) {
        throw new Exception('Respuesta del central no es JSON válido: ' . substr($body, 0, 300));
    }

    return $data; 
}
?>