<?php

require_once dirname(__DIR__) . '/utils/auth.php';

function optimizar_archivos($original_path, $params = []) {
    $info      = pathinfo($original_path);
    $ext       = '.webp';
    $webp_path = $info['dirname'] . '/' . $info['filename'] . '-opt' . $ext;

    // Obtener dimensiones
    [$width, $height] = getimagesize($original_path);

    $resize        = isset($params['resize']) && $params['resize'] === true && $width > 1920;
    $compress_file = call_compress_api('imagen', $original_path, $webp_path, $params['resize']);
    if (!file_exists($compress_file) || filesize($compress_file) === 0) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'El archivo comprimido no se recibió correctamente.',
            'detalle' => $e->getMessage()
        ], 500);
        $compress_file = $webp_path;
    }

    // Comando ImageMagick
    // if ($resize) {
    //     $command = "convert " . escapeshellarg($original_path) . " -resize 1920x -quality 80 " . escapeshellarg($webp_path);
    //     error_log("Redimensionando y convirtiendo: $webp_path");
    // } else {
    //     $command = "convert " . escapeshellarg($original_path) . " -quality 80 " . escapeshellarg($webp_path);
    //     error_log("Solo conversión a WebP: $webp_path");
    // }

    // exec($command, $output, $code);

    // if ($code !== 0 || !file_exists($webp_path)) {
    //     error_log("Error en la conversión a WebP: " . implode("\n", $output));
    //     return false;
    // }

    return $compress_file;
}
