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
        // Obtenemos la información del path original ANTES de hacer cambios
        $original_info = pathinfo($original_path); 

        $upload['file'] = $optimized_path;
        $upload['url']  = str_replace(basename($original_path), basename($optimized_path), $upload['url']);
        $upload['type'] = $forced_mime ?: mime_content_type($optimized_path);

        if($forced_mime == 'image/webp') {  
            $geminiData = getInfoGemini($upload['url']);
            if (is_array($geminiData) && isset($geminiData[0]) && !empty($geminiData[0])) { 
                
                $slug = sanitize_file_name($geminiData[0]['slug']) . '.webp';

                // Usamos un filtro temporal para el nombre, como ya haces
                add_filter('wp_unique_filename', function($filename) use ($slug) {
                    return $slug;
                }, 10, 1);

                $current_path = $upload['file'];
                $dir = dirname($current_path);
                $new_path = $dir . '/' . $slug;

                if (rename($current_path, $new_path)) {
                    $upload['url']  = trailingslashit(dirname($upload['url'])) . $slug;
                    $upload['file'] = $new_path; 
                } else {
                    error_log('Error al renombrar el archivo a: ' . $new_path);
                } 

                remove_all_filters('wp_unique_filename');

                $gemini_data = [
                    'alt_temp'           => $geminiData['0']['alt'],
                    'slug_temp'          => $geminiData['0']['slug'],
                    'description_temp'   => $geminiData['0']['description'],
                    'title_temp'         => $geminiData['0']['title'],
                    // ✅ AÑADIMOS ESTO: Guardamos el nombre del archivo original (sin extensión)
                    'original_slug_temp' => $original_info['filename'], 
                ]; 
                $unique_key = 'gemini_' . md5($upload['url']); 
                set_transient($unique_key, $gemini_data, 5 * MINUTE_IN_SECONDS);
            }
        }  

        // ❌ ELIMINAMOS EL BLOQUE DE BORRADO DE AQUÍ
        // Ya no intentamos borrar el attachment en este punto.

        // Borramos el archivo físico original, eso está bien
        @unlink($original_path);
    }
    return $upload;
}

add_action('add_attachment', 'update_attachment_with_gemini_data');

function update_attachment_with_gemini_data($attachment_id) {
    $post = get_post($attachment_id);
    if (!$post || $post->post_type !== 'attachment') return;

    $mime = get_post_mime_type($attachment_id);
    if (strpos($mime, 'image/') !== 0) return;

    $url         = wp_get_attachment_url($attachment_id);
    $unique_key  = 'gemini_' . md5($url);
    $gemini_data = get_transient($unique_key); 

    if ($gemini_data) {
        $slug = sanitize_title($gemini_data['slug_temp']);

        $update_post_args = [
            'ID'           => $attachment_id,
            'post_title'   => $gemini_data['title_temp'],
            'post_content' => $gemini_data['description_temp'],
            'post_name'    => $slug,
            'guid'         => $url, 
        ]; 
        wp_update_post($update_post_args);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $gemini_data['alt_temp']);
        // stg_set_attachment_has_alt_text( $attachment_id, true ); // Asegúrate que esta función exista.

        // ✅ LÓGICA DE BORRADO DEL ATTACHMENT BASURA
        if (!empty($gemini_data['original_slug_temp'])) {
            $junk_slug = $gemini_data['original_slug_temp'];
            
            // Buscamos un attachment que coincida con el slug original
            $junk_attachment = get_page_by_path($junk_slug, OBJECT, 'attachment');

            // Si lo encontramos y NO es el attachment que acabamos de procesar
            if ($junk_attachment && $junk_attachment->ID !== $attachment_id) {
                // Borramos el attachment basura de forma permanente
                wp_delete_attachment($junk_attachment->ID, true);
            }
        }
        
        // Luego bórralo para no dejar basura
        delete_transient($unique_key);
    } 
}

function getInfoGemini($url){
    $AUTH_USER_BASIC     = get_option('user_auth');
    $AUTH_PASSWORD_BASIC = get_option('pass_auth');

    $endpoint = site_url('/wp-json/api/v1/gemini');

    $data = ['imageUrl' => $url];
    $ch   = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, "$AUTH_USER_BASIC:$AUTH_PASSWORD_BASIC");

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
