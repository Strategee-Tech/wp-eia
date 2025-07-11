<?php


function comprimir_imagenes($original_path, $params = []){
	$info = pathinfo($original_path);
    $ext = '.webp';

    // Generar nombre del archivo optimizado
    $temp_img = $info['dirname'] . '/' . $info['filename'] . '-opt' . $ext;

    // Obtener ancho de la imagen original
    [$width, $height] = getimagesize($original_path);

    // Decidir si redimensionar
    $shouldResize = isset($params['resize']) && $params['resize'] === true && $width > 1920;

    // Construir el comando
    if ($shouldResize) {
        $command = "convert " . escapeshellarg($original_path) . " -resize 1920x -quality 80 " . escapeshellarg($temp_img);
        error_log("Redimensionando y convirtiendo a WebP: $temp_img");
    } else {
        $command = "convert " . escapeshellarg($original_path) . " -quality 80 " . escapeshellarg($temp_img);
        error_log("Solo conversión a WebP: $temp_img");
    }

    // Ejecutar comando
    exec($command, $output, $code);

    if ($code !== 0) {
        error_log("Error en la conversión: " . implode("\n", $output));
        return false;
    }

    return true;
}