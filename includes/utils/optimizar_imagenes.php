<?php


function comprimir_imagenes($original_path){
	$temp_img = $original_path;

    // Obtener dimensiones de la imagen
    [$width, $height] = getimagesize($original_path);

    // Verificar si necesita redimensionarse
    if ($width > 1920) {
        $command = "convert " . escapeshellarg($original_path) . " -resize 1920x -quality 80 " . escapeshellarg($temp_img);
    } else {
        $command = "convert " . escapeshellarg($original_path) . " -quality 80 " . escapeshellarg($temp_img);
    }

 	exec($command, $output, $code);
 	if ($code !== 0) {
        return false
    } else {
        return true;
    }
}