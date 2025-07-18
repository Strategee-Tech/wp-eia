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
 * NUEVO: Después de subir el archivo, obtener datos de Gemini y actualizar metadatos
 */
add_action('add_attachment', 'update_attachment_with_gemini_data');

function update_attachment_with_gemini_data($attachment_id) {
    // Asegurarse de que esto se ejecuta *después* de la carga y optimización inicial del archivo
    // Un ligero retraso podría ser beneficioso si la optimización lleva tiempo,
    // aunque 'add_attachment' se dispara típicamente después de que el archivo se escribe.

    $post = get_post($attachment_id);

    if ($post->post_type !== 'attachment') return;

    $mime = get_post_mime_type($attachment_id);
    if (strpos($mime, 'image/') !== 0) return;

    // Obtener la ruta actual del adjunto
    $current_file_path = get_attached_file($attachment_id);
    if (!$current_file_path || !file_exists($current_file_path)) {
        error_log("Archivo adjunto no encontrado en: " . $current_file_path);
        return;
    }

    // URL de la imagen (usar la URL actual del adjunto para Gemini)
    $image_url  = wp_get_attachment_url($attachment_id);

    // Llamar a Gemini
    $geminiData = getInfoGemini($image_url);

    if (is_array($geminiData) && isset($geminiData[0])) {
        $data = $geminiData[0];

        $title       = !empty($data['title']) ? sanitize_text_field($data['title']) : $post->post_title;
        $description = !empty($data['description']) ? wp_kses_post($data['description']) : '';
        $alt         = !empty($data['alt']) ? sanitize_text_field($data['alt']) : '';
        $slug        = !empty($data['slug']) ? sanitize_title($data['slug']) : '';

        // Preparar datos de la publicación para la actualización
        $update_post_args = [
            'ID'           => $attachment_id,
            'post_title'   => $title,
            'post_content' => $description,
        ];

        // Solo actualizar post_name si se proporciona un nuevo slug y es diferente
        if (!empty($slug) && $slug !== $post->post_name) {
            $update_post_args['post_name'] = $slug;
        }

        // Actualizar los detalles de la publicación
        wp_update_post($update_post_args);

        // Actualizar el texto alternativo
        if (!empty($alt)) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
        }

        // --- Renombrar el archivo físico y actualizar metadatos ---
        if (!empty($slug) && $slug !== sanitize_title(pathinfo($current_file_path, PATHINFO_FILENAME))) {
            $current_path  = $current_file_path;
            $info          = pathinfo($current_path);
            $ext           = strtolower($info['extension']);
            $new_filename  = $slug . '.' . $ext;
            $new_path      = dirname($current_path) . '/' . $new_filename;

            // Comprobar si el nuevo nombre de archivo es diferente del actual
            if (basename($current_path) !== $new_filename) {
                // Intentar renombrar el archivo
                if (@rename($current_path, $new_path)) {
                    // Actualizar la ruta del archivo adjunto en la base de datos
                    update_attached_file($attachment_id, $new_path);

                    // Crucialmente, el GUID debe actualizarse para reflejar la nueva URL.
                    // Obtener información del directorio de carga para construir la nueva URL.
                    $upload_dir_info = wp_upload_dir();
                    $relative_path   = str_replace(trailingslashit($upload_dir_info['basedir']), '', $new_path);
                    $new_url         = $upload_dir_info['baseurl'] . '/' . $relative_path;

                    global $wpdb;
                    $wpdb->update($wpdb->posts, ['guid' => $new_url], ['ID' => $attachment_id]);

                    // Regenerar metadatos usando la *nueva* ruta del archivo.
                    // Aquí es donde ocurre la magia para todos los tamaños.
                    require_once(ABSPATH . 'wp-admin/includes/image.php'); // Asegurarse de que esto esté cargado
                    $metadata = wp_generate_attachment_metadata($attachment_id, $new_path);
                    if (is_wp_error($metadata)) {
                        error_log("Error al generar metadatos del adjunto para ID " . $attachment_id . ": " . $metadata->get_error_message());
                    } else {
                        wp_update_attachment_metadata($attachment_id, $metadata);
                    }
                } else {
                    error_log("No se pudo renombrar el archivo adjunto de {$current_path} a {$new_path} para el ID de adjunto {$attachment_id}.");
                }
            }
        }
    } else {
        error_log("Los datos de Gemini no son válidos o están vacíos para el ID de adjunto: " . $attachment_id);
    }
}