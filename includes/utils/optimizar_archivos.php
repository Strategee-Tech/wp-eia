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

/**
 * Actualiza metadatos del attachment con datos de Gemini y renombra archivo
 */
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

    $image_url  = wp_get_attachment_url($attachment_id);
    $geminiData = getInfoGemini($image_url);

    if (is_array($geminiData) && isset($geminiData[0])) {
        $data = $geminiData[0];

        $title       = !empty($data['title']) ? sanitize_text_field($data['title']) : $post->post_title;
        $description = !empty($data['description']) ? wp_kses_post($data['description']) : '';
        $alt         = !empty($data['alt']) ? sanitize_text_field($data['alt']) : '';
        $slug        = !empty($data['slug']) ? sanitize_title($data['slug']) : '';

        $update_post_args = [
            'ID'           => $attachment_id,
            'post_title'   => $title,
            'post_content' => $description,
        ];

        if (!empty($slug) && $slug !== $post->post_name) {
            $update_post_args['post_name'] = $slug;
        }

        wp_update_post($update_post_args);

        if (!empty($alt)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }

        // Renombrar archivo físico
        $info         = pathinfo($current_file_path);
        $ext          = strtolower($info['extension']);
        $new_filename = $slug . '.' . $ext;
        $new_path     = $info['dirname'] . '/' . $new_filename;

        if (!empty($slug) && basename($current_file_path) !== $new_filename) {
            if (@rename($current_file_path, $new_path)) {
                update_attached_file($attachment_id, $new_path);

                $upload_dir_info = wp_upload_dir();
                $relative_path   = str_replace(trailingslashit($upload_dir_info['basedir']), '', $new_path);
                $new_url         = $upload_dir_info['baseurl'] . '/' . $relative_path;

                global $wpdb;
                $wpdb->update($wpdb->posts, ['guid' => $new_url], ['ID' => $attachment_id]);

                // ✅ Aquí programamos la regeneración para el final del request
                add_action('shutdown', function() use ($attachment_id, $new_path) {
                    if (file_exists($new_path)) {
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $metadata = wp_generate_attachment_metadata($attachment_id, $new_path);
                        if (!is_wp_error($metadata) && !empty($metadata)) {
                            wp_update_attachment_metadata($attachment_id, $metadata);
                            //error_log("Metadata regenerada correctamente para ID {$attachment_id}");
                        } else {
                            //error_log("Error regenerando metadata para {$attachment_id}");
                        }
                    } else {
                        //error_log("Archivo no encontrado para regenerar metadata: {$new_path}");
                    }
                });
            } else {
                //error_log("No se pudo renombrar el archivo adjunto de {$current_file_path} a {$new_path}");
            }
        }
    } else {
        //error_log("Los datos de Gemini no son válidos o están vacíos para el ID: " . $attachment_id);
    }
}