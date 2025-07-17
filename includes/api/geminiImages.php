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
    $params  = $request->get_json_params();

    //$currentPage = $params['currentPage'];
    $imageUrl = $params['imageUrl'];
    
    try{
        $metadata = generateImageMetadata($imageUrl);

    } catch (Exception $e) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'Error al escanear archivos.'
        ], 500);
    }

    if (empty($params) || !is_array($params)) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'No se recibió un JSON válido.'
        ], 400);
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
    $prompt = 'Como experto en SEO y marketing digital especializado en contenido educativo para sitios web universitarios, analiza detalladamente la siguiente imagen. Genera un objeto JSON válido, sin texto adicional, que incluya las siguientes propiedades, optimizadas para el contexto de la Universidad EIA:

    - **title**: Un título corto, atractivo y descriptivo (máx. 60 caracteres) para uso en la meta-etiqueta <title> o como encabezado H1/H2, que refleje el contenido de la imagen y sea relevante para la Universidad EIA. Incorpora palabras clave relevantes para educación superior o áreas de estudio si aplica.
    - **description**: Una meta-descripción detallada y persuasiva (máx. 160 caracteres) que resuma el contenido visual de la imagen, invite a la interacción y esté optimizada para aparecer en resultados de búsqueda, destacando aspectos únicos de la Universidad EIA o el ambiente académico.
    - **alt**: Un texto alternativo conciso y preciso (máx. 125 caracteres) que describa la imagen para usuarios con discapacidad visual y para los motores de búsqueda. Debe ser informativo y reflejar fielmente lo que se ve, incluyendo elementos clave de la Universidad EIA si son visibles (ej. \'Estudiantes en el campus EIA\', \'Laboratorio de Mecatrónica EIA\').
    - **legend**: Una leyenda o pie de foto más extenso y contextual (máx. 250 caracteres) que complemente el contenido principal de la página. Debe añadir valor informativo o narrativo, explicando el contexto de la imagen dentro de las actividades, eventos, logros o vida estudiantil de la Universidad EIA.
    - **slug**: Un slug amigable para URLs (máx. 50 caracteres) derivado del título o descripción, en minúsculas, usando guiones como separadores de palabras y sin caracteres especiales. Debe ser descriptivo y SEO-friendly, relevante para el contenido de la Universidad EIA.
    
    Asegúrate de que la salida sea un objeto JSON **estrictamente válido** y nada más. siempre responde en español, no describamos tan detalladamente a las personas, nos enfocamos mas en el contexto educativo';

    $base64Image = imageUrlToBase64($imageUrl);

    $imageExtension = pathinfo($imageUrl, PATHINFO_EXTENSION);
    $imageMimeType = 'image/jpeg'; // Valor por defecto
    if ($imageExtension === 'png') {
        $imageMimeType = 'image/png';
    } elseif ($imageExtension === 'webp') {
        $imageMimeType = 'image/webp';
    } elseif ($imageExtension === 'gif') {
        $imageMimeType = 'image/gif';
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
                            'data' => $base64Image
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

    $apiKey = 'AIzaSyCgo9gEgPrCp_81LGuxLh5rpEgdWCkmeBA'; // Reemplaza con tu clave de API de Google Gemini
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite-preview-06-17:generateContent?key=' . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        throw new Exception("Error al conectar con la API de Google Gemini.");
    }

    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        throw new Exception("Error de la API: " . ($errorData['error']['message'] ?? 'Error desconocido') . " (Código HTTP: " . $httpCode . ")");
    }

    $data = json_decode($response, true);

    // Accede a la parte de texto dentro de 'candidates'
    $result = json_decode($data['candidates'][0]['content']['parts'][0]['text'], true);

    return $result;
}

// Ejemplo de uso:
// $imageUrl = 'https://ejemplo.com/tu-imagen.jpg'; // Reemplaza con la URL de tu imagen
// try {
//     $metadata = generateImageMetadata($imageUrl);
//     echo json_encode($metadata, JSON_PRETTY_PRINT);
// } catch (Exception $e) {
//     echo "Error: " . $e->getMessage();
// }

?>