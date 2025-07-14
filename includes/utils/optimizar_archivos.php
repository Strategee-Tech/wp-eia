<?php

require_once dirname(__DIR__) . '/utils/auth.php';

add_filter('wp_handle_upload', 'compress_images');
 
function compress_images($upload) {
    set_time_limit(3600);
 
    $original_path = $upload['file']; 
    $mime          = mime_content_type($original_path);
    $info          = pathinfo($original_path);
    
    $mime_multimedia = [
        // Video
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/webm',
        'video/ogg',     // Para .ogv (Ogg Video)

        // Audio
        'audio/mpeg',    // .mp3
        'audio/wav',     // .wav
        'audio/mp4',     // .m4a
        'audio/aac',     // .aac
        'audio/ogg',     // .ogg o .oga
    ];
    $ext      = $info['extension'];
    $opt_path = $info['dirname'] . '/' . $info['filename'] . '-opt.' . $ext;
    
    if (strpos($mime, 'image/') === 0) {
        $resultado = optimizar_archivos($original_path, ['type' => 'imagen']);
        if ($resultado) {
            $opt_path = $info['dirname'] . '/' . $info['filename'] . '-opt.webp';
            $upload   = reemplazar_archivo_optimizado($upload, $original_path, $opt_path, 'image/webp');
        }
    }

    if (in_array($mime, $mime_multimedia)) {
        $resultado = optimizar_archivos($original_path, ['type' => 'multimedia']);
        if ($resultado) {
            $upload   = reemplazar_archivo_optimizado($upload, $original_path, $opt_path);
        }
    }

    if ($mime === 'application/pdf') {
        $resultado = optimizar_archivos($original_path, ['type' => 'pdf']);
        if ($resultado) {
            $upload   = reemplazar_archivo_optimizado($upload, $original_path, $opt_path);
        }
    }
    return $upload;
}

function reemplazar_archivo_optimizado($upload, $original_path, $optimized_path, $forced_mime = null) {
    if (file_exists($optimized_path)) {
        $upload['file'] = $optimized_path;
        $upload['url']  = str_replace(basename($original_path), basename($optimized_path), $upload['url']);
        $upload['type'] = $forced_mime ?: mime_content_type($optimized_path);
        @unlink($original_path);
    }
    return $upload;
}

function optimizar_archivos($original_path, $params = []) {
    set_time_limit(3600);
    $info       = pathinfo($original_path);
    $ext        = $info['extension'];
    $resize     = false;
    $webp_path  = $info['dirname'] . '/' . $info['filename'] . '-opt.' . $ext;
    if($params['type'] == 'imagen') {
        [$width, $height] = getimagesize($original_path); 
        $webp_path  = $info['dirname'] . '/' . $info['filename'] . '-opt.webp';
        if($width > 1920) {
            $resize = true;
        } 
    }
    try {
        call_compress_api($params['type'], $original_path, $webp_path, $resize);
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
