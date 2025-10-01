<?php

require_once dirname(__DIR__) . '/utils/auth.php';

// El hook wp_handle_upload se ejecuta antes de que WordPress cree el registro de attachment.
add_filter('wp_handle_upload', 'compress_images');
 
function compress_images($upload) {
    set_time_limit(3600);

    $original_path = $upload['file']; 
    $mime          = mime_content_type($original_path);
    $info          = pathinfo($original_path);
    
    $mime_multimedia = [
        // Video
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska', 
        'video/webm', 'video/ogg', // Para .ogv (Ogg Video)

        // Audio
        'audio/mpeg', 'audio/wav', 'audio/mp4', 'audio/aac', 'audio/ogg',
    ];
    $ext     = $info['extension'];
    
    // Ruta temporal por defecto (para multimedia y PDF)
    $opt_path = $info['dirname'] . '/' . $info['filename'] . '-opt.' . $ext;
    
    if (strpos($mime, 'image/') === 0) {
        $resultado = optimizar_archivos($original_path, ['type' => 'imagen']);
        if ($resultado) {
            // RUTA TEMPORAL DE IMAGEN (SIEMPRE WEBP)
            $temp_path = $info['dirname'] . '/' . $info['filename'] . '-opt.webp';
            
            // 1. OBTENEMOS Y RENOMBRAMOS EL ARCHIVO FÍSICO AQUÍ
            // Usamos la URL temporal para la llamada a Gemini
            $temp_url = trailingslashit(dirname($upload['url'])) . basename($temp_path); 
            $geminiData = getInfoGemini($temp_url); 
            
            if (is_array($geminiData) && isset($geminiData[0]) && !empty($geminiData[0])) {
                $final_filename = sanitize_file_name($geminiData[0]['slug']) . '.webp';
                $final_path = $info['dirname'] . '/' . $final_filename;

                // 🚨 RENOMBRADO CLAVE: Renombramos el archivo físico antes de que WP lo registre
                if (rename($temp_path, $final_path)) {
                    // 2. Guardamos los datos de Gemini, usando el NOMBRE DEL ARCHIVO FINAL como clave
                    $unique_key = 'gemini_' . md5(basename($final_path)); 
                    $gemini_data = [
                        'alt_temp'           => $geminiData[0]['alt'],
                        'slug_temp'          => $geminiData[0]['slug'],
                        'description_temp'   => $geminiData[0]['description'],
                        'title_temp'         => $geminiData[0]['title'],
                        'original_slug_temp' => $info['filename'], 
                    ];
                    set_transient($unique_key, $gemini_data, 5 * MINUTE_IN_SECONDS);

                    // 3. Modificamos el array $upload para que apunte al NOMBRE FINAL (file y url)
                    // ESTO ES LO QUE ARREGLA EL 'No such file or directory'
                    $upload = reemplazar_archivo_optimizado($upload, $original_path, $final_path, 'image/webp');
                    
                } else {
                    error_log('Error al renombrar el archivo de ' . $temp_path . ' a ' . $final_path);
                    // Si falla el rename, usamos el nombre temporal
                    $upload = reemplazar_archivo_optimizado($upload, $original_path, $temp_path, 'image/webp');
                }
            } else {
                // Si Gemini falla, usamos el nombre temporal
                $upload = reemplazar_archivo_optimizado($upload, $original_path, $temp_path, 'image/webp');
            }
        }
    }
    
    // -----------------------------------------------------------------
    // LÓGICA DE MULTIMEDIA Y PDF (AQUÍ SE MANTIENE EL CÓDIGO ORIGINAL)
    // -----------------------------------------------------------------

    // Multimedia
    if (in_array($mime, $mime_multimedia)) {
        $resultado = optimizar_archivos($original_path, ['type' => 'multimedia']);
        if ($resultado) {
            $upload    = reemplazar_archivo_optimizado($upload, $original_path, $opt_path);
        }
    }

    // PDF
    if ($mime === 'application/pdf') {
        $resultado = optimizar_archivos($original_path, ['type' => 'pdf']);
        if ($resultado) {
            $upload    = reemplazar_archivo_optimizado($upload, $original_path, $opt_path);
        }
    }
    
    return $upload;
}

function reemplazar_archivo_optimizado($upload, $original_path, $optimized_path, $forced_mime = null) {
    if (file_exists($optimized_path)) {
        // La ruta del archivo y URL se actualizan al nombre optimizado 
        $upload['file'] = $optimized_path;
        $upload['url']  = str_replace(basename($original_path), basename($optimized_path), $upload['url']);
        $upload['type'] = $forced_mime ?: mime_content_type($optimized_path);

        // Borramos el archivo físico original
        @unlink($original_path);
    }
    return $upload;
}

// El hook add_attachment se ejecuta después de que WordPress registra el attachment en la base de datos.
add_action('add_attachment', 'update_attachment_with_gemini_data');

function update_attachment_with_gemini_data($attachment_id) {
    $post = get_post($attachment_id);
    if (!$post || $post->post_type !== 'attachment') return;

    $mime = get_post_mime_type($attachment_id);
    // Solo aplica la lógica de Gemini a las imágenes
    if (strpos($mime, 'image/') !== 0) return;

    $file_path = get_attached_file($attachment_id);
    $url = wp_get_attachment_url($attachment_id);
    
    // Usamos el nombre del archivo (que ya es el SLUG FINAL si Gemini funcionó)
    $unique_key  = 'gemini_' . md5(basename($file_path));
    $gemini_data = get_transient($unique_key); 

    if ($gemini_data) {
        // 1. ACTUALIZAR LOS METADATOS DEL ATTACHMENT
        $new_slug = sanitize_title($gemini_data['slug_temp']); // Slug para el post
        
        $update_post_args = [
            'ID'             => $attachment_id,
            'post_title'     => $gemini_data['title_temp'],
            'post_content'   => $gemini_data['description_temp'],
            'post_name'      => $new_slug,
            'guid'           => $url, // Ya debe ser el URL final
        ]; 
        wp_update_post($update_post_args);

        // 2. FORZAR LA GENERACIÓN CORRECTA DE METADATOS/MINIATURAS
        // Esto corrige el error de "no se visualiza la imagen" y el Warning de 'No such file or directory'
        $attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
        // Usamos la función nativa para guardar los metadatos generados.
        wp_update_attachment_metadata( $attachment_id, $attach_data );

        // 3. Actualizamos el alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $gemini_data['alt_temp']);
        
        // 4. BORRAR EL TRANSITORIO
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
