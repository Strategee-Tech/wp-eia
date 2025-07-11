<?php

require_once dirname(__DIR__) . '/utils/auth.php';

function optimizar_archivos($original_path, $params = []) {
    $info      = pathinfo($original_path);
    $ext       = '.webp';
    $webp_path = $info['dirname'] . '/' . $info['filename'] . '-opt' . $ext;

    // Obtener dimensiones
    [$width, $height] = getimagesize($original_path);

    $resize = isset($params['resize']) && $params['resize'] === true && $width > 1920;

    // Comando ImageMagick
    if ($resize) {
        $command = "convert " . escapeshellarg($original_path) . " -resize 1920x -quality 80 " . escapeshellarg($webp_path);
        error_log("Redimensionando y convirtiendo: $webp_path");
    } else {
        $command = "convert " . escapeshellarg($original_path) . " -quality 80 " . escapeshellarg($webp_path);
        error_log("Solo conversión a WebP: $webp_path");
    }

    exec($command, $output, $code);

    if ($code !== 0 || !file_exists($webp_path)) {
        error_log("Error en la conversión a WebP: " . implode("\n", $output));
        return false;
    }

    return $webp_path;
}
