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

        $geminiData = getInfoGemini($upload['url']);

        if (is_array($geminiData) && isset($geminiData[0])) { 
            if(!empty($geminiData[0])) { 

                $slug = sanitize_file_name($geminiData[0]['slug']) . '.webp';

                //Ruta actual del archivo
                $current_path = $upload['file']; // Ej: /var/www/.../uploads/2025/07/original.webp

                //Directorio actual (donde está el archivo)
                $dir = dirname($current_path); // Ej: /var/www/.../uploads/2025/07

                //Nueva ruta física
                $new_path = $dir . '/' . $slug;

                //Renombrar el archivo en el servidor
                if (rename($current_path, $new_path)) {
                    //Construir nueva URL
                    $upload['url']  = trailingslashit(dirname($upload['url'])) . $slug;

                    //Actualizar file y type
                    $upload['file'] = $new_path; 
                } else {
                    error_log('Error al renombrar el archivo a: ' . $new_path);
                } 

                $gemini_data = [
                    'alt_temp'         => $geminiData['0']['alt'],
                    'slug_temp'        => $geminiData['0']['slug'],
                    'description_temp' => $geminiData['0']['description'],
                    'title_temp'       => $geminiData['0']['title'],
                ]; 
                $unique_key = 'gemini_' . md5($upload['url']); 
                set_transient($unique_key, $gemini_data, 5 * MINUTE_IN_SECONDS);
            }
        } 
        @unlink($original_path);
    }
    return $upload;
}

add_action('add_attachment', 'update_attachment_with_gemini_data');

function update_attachment_with_gemini_data($attachment_id) {
    $post = get_post($attachment_id);
    if ($post->post_type !== 'attachment') return;

    $mime = get_post_mime_type($attachment_id);
    if (strpos($mime, 'image/') !== 0) return;

    $current_file_path = get_attached_file($attachment_id);
    if (!$current_file_path || !file_exists($current_file_path)) {
        //error_log("Archivo adjunto no encontrado en: " . $current_file_path);
        return;
    }
    $url         = wp_get_attachment_url($attachment_id);
    $unique_key  = 'gemini_' . md5($url);
    $gemini_data = get_transient($unique_key); 

    if ($gemini_data) {

        $update_post_args = [
            'ID'           => $attachment_id,
            'post_title'   => $gemini_data['title_temp'],
            'post_content' => $gemini_data['description_temp'],
        ]; 
        wp_update_post($update_post_args);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $gemini_data['alt_temp']);
        
        // Luego bórralo para no dejar basura
        delete_transient($unique_key);
    } 
}

function getInfoGemini($url){
    require_once(dirname(ABSPATH) . '/credentials.php');

    $endpoint = site_url('/wp-json/api/v1/gemini');
    $username = AUTH_USER_BASIC;
    $password = AUTH_PASSWORD_BASIC;

    $data = ['imageUrl' => $url];
    $ch   = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");

    $response = curl_exec($ch);

    curl_close($ch);

    return json_decode($response, true); 
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
