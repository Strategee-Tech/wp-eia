<?php

require_once dirname(__DIR__) . '/utils/auth.php';

function optimizar_archivos($original_path, $params = []) {
    $info       = pathinfo($original_path);
    $ext        = '.webp';
    $webp_path  = $info['dirname'] . '/' . $info['filename'] . '-opt' . $ext;

    // Obtener tipo MIME
    $mime = mime_content_type($original_path);
        // Obtener dimensiones
    [$width, $height] = getimagesize($original_path);
    $resize = false;
    if($width > 1920) {
        $resize = true;
    } 
    try {
        call_compress_api('imagen', $original_path, $webp_path, $resize);
    } catch (Exception $e) {
        error_log("Error al comprimir con API externa: " . $e->getMessage());
        return false;
    }

    if (!file_exists($webp_path)) {
        error_log("El archivo optimizado no fue generado: $webp_path");
        return false;
    }

    return $webp_path;
}
