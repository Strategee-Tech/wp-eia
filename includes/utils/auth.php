<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

// Función de validación de permisos con autenticación básica
function basic_auth_permission_check() {
    // Ruta absoluta al archivo credentials.php
    $path_to_credentials = dirname(ABSPATH) . '/credentials.php';

    // Verificación si el archivo existe antes de incluirlo
    if (file_exists($path_to_credentials)) {
        require_once($path_to_credentials);
    } else {
        return new WP_Error('server_error', 'No se encontró el archivo de credenciales.', array('status' => 500));
    }

    // Verificar si el encabezado 'Authorization' está presente
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;

    if (!$auth_header || strpos($auth_header, 'Basic ') !== 0) {
        return new WP_Error('unauthorized', 'Authorization header missing or incorrect.', array('status' => 401));
    }

    // Extraer las credenciales de la cabecera 'Authorization'
    $encoded_credentials = substr($auth_header, 6); // Eliminar 'Basic '
    $decoded_credentials = base64_decode($encoded_credentials);

    // Separar las credenciales en usuario y contraseña
    list($username, $password) = explode(':', $decoded_credentials);

    // Validar las credenciales con las que se han enviado
    if ($username !== AUTH_USER_BASIC || $password !== AUTH_PASSWORD_BASIC) {
        return new WP_Error('forbidden', 'Invalid credentials.', array('status' => 403));
    }

    // Si las credenciales son correctas, permitir el acceso
    return true;
}


function slug_unico($slug_deseado, $id_actual = 0) {
    global $wpdb;

    $slug_deseado = sanitize_title($slug_deseado);

    // Verifica si ese slug ya está en uso por otro attachment
    $sql = $wpdb->prepare(
        "SELECT ID FROM $wpdb->posts 
         WHERE post_name = %s 
           AND post_type = 'attachment'
           AND post_status != 'trash'
           AND ID != %d
         LIMIT 1",
        $slug_deseado,
        $id_actual
    );

    $existe_id = $wpdb->get_var($sql);

    if (!$existe_id) {
        // Slug libre o es suyo mismo
        return $slug_deseado;
    }

    // Slug ya usado por otro → generar uno único
    return wp_unique_post_slug($slug_deseado, $id_actual, 'inherit', 'attachment', 0);
}

function update_yoast_info($new_url, $old_url, $post_id, $old_partial) {
    global $wpdb;
    //actualizar post_content de una imagen dentro de una pagina
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->posts} 
            SET post_content = REPLACE(post_content, %s, %s) 
            WHERE post_content LIKE %s AND post_status IN ('publish', 'private', 'draft', 'revision') AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
            $old_url,
            $new_url,
            '%' . basename($old_url) . '%'
        )
    );

    //actualizar post_content de una imagen dentro de un programa
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->prefix}learnpress_courses 
            SET post_content = REPLACE(post_content, %s, %s) 
            WHERE post_content LIKE %s AND post_status IN ('publish', 'private', 'draft', 'revision')",
            $old_url,
            $new_url,
            '%' . basename($old_url) . '%'
        )
    );

    // Tabla de Yoast SEO
    $tabla_yoast_seo_links = $wpdb->prefix . 'yoast_seo_links';
    $tabla_indexable       = $wpdb->prefix . 'yoast_indexable';
    $tabla_redirection     = $wpdb->prefix . 'redirection_items';

    // Actualiza la fila cuyo match_url contenga la ruta parcial de la tabla de redirecciones
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $tabla_redirection SET action_data = %s WHERE match_url LIKE %s",
            $new_url,
            '%' . $wpdb->esc_like($old_partial) . '%'
        )
    );
    
    // Actualizar tabla yoast_indexable (open graph y twitter image)
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $tabla_indexable 
            SET open_graph_image = %s, twitter_image = %s
            WHERE open_graph_image = %s AND twitter_image = %s",
            $new_url,     // nuevo open_graph_image
            $new_url,     // nuevo twitter_image
            $old_url,     // viejo open_graph_image
            $old_url      // viejo twitter_image
        )
    );

    // Actualizar tabla yoast_seo_links (url general)
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE $tabla_yoast_seo_links
             SET url = %s
             WHERE url = %s",
            $new_url,
            $old_url
        )
    );

    // 1. Buscar todas las filas que contienen la URL antigua
    $filas = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, open_graph_image_meta FROM $tabla_indexable 
             WHERE open_graph_image LIKE %s",
            '%' . $new_url . '%'
        ),
        ARRAY_A
    );

    if(!empty($filas)) {
        foreach ($filas as $fila) {
            $json = $fila['open_graph_image_meta'];
            $id   = $fila['id'];
            $meta = json_decode($json, true); // Convertir a array asociativo
            if (json_last_error() == JSON_ERROR_NONE && is_array($meta)) {
                // 3. Reemplazar solo la clave "id" si coincide
                if (isset($meta['url']) && $post_id == $meta['id']) {
                    $meta['url'] = $new_url;

                    // 4. Codificar de nuevo el JSON
                    $nuevo_json = wp_json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                    // 5. Actualizar en base de datos
                    $wpdb->update(
                        $tabla_indexable,
                        ['open_graph_image_meta' => $nuevo_json],
                        ['id' => $id]
                    );
                }
            } 
        }
    }
}

function update_post_meta_elementor_data($wpdb, $attachment_id, $old_path, $new_path){

    // UPDATE wp_postmeta
    // SET meta_value = REPLACE(
    //     meta_value,
    //     '/2025\\/07\\/Sin-titulo-3.webp',
    //     '/2025\\/07\\/Sin-titulo-3.png'
    // )
    // WHERE meta_key = '_elementor_data'
    // AND meta_value LIKE '%"id":177837%';

    // Definir las cadenas a reemplazar
    $old_path = str_replace('/', '\\/', $old_path);
    $new_path = str_replace('/', '\\/', $new_path);

    $sql = $wpdb->prepare(
        "UPDATE {$wpdb->postmeta}
         SET meta_value = REPLACE(meta_value, %s, %s)
         WHERE meta_key = '_elementor_data'
         AND meta_value LIKE %s",
        $old_path,   // valor actual que quieres reemplazar
        $new_path,   // nuevo valor
        '%"id":' . $attachment_id . '%' // condición para asegurar que coincide con ese ID
    );

    $rows_affected = $wpdb->query($sql);

    //echo "Filas actualizadas: " . $rows_affected;
} 

function update_meta_value_urls($old_path, $new_path) {
    $wp_cli_path = '/usr/local/bin/wp'; // Ruta a WP-CLI
    $wp_path     = ABSPATH; // Ruta a WP 
    $table       = 'wp_postmeta'; 

    // Escapar parámetros para seguridad
    $old_esc     = escapeshellarg($old_path);
    $new_esc     = escapeshellarg($new_path);
    $wp_path_esc = escapeshellarg($wp_path); 

    // Construir el comando dinámicamente
    $command = "$wp_cli_path search-replace $old_esc $new_esc $table --include-columns=meta_value --precise --allow-root --path=$wp_path_esc";

    // Ejecutar WP-CLI
    $output  = shell_exec($command . " 2>&1"); 

    echo "<pre>$output</pre>";
}

function regenerate_metadata($attachment_id, $fileType = 'image'){
    try {
        $attachment = get_post( $attachment_id );
        if ( $attachment->post_type == 'attachment' ) {
            if($fileType == 'image' || $fileType == 'pdf'){
                $metadata = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
            } elseif($fileType == 'multimedia') {
                $file     = get_attached_file( $attachment_id );

                // Leer solo los metadatos del video
                $video_data = wp_read_video_metadata($file);

                // Leer los metadatos existentes completos
                $existing_data = wp_get_attachment_metadata($attachment_id);

                // Si no hay metadatos previos, creamos uno mínimo
                if (!is_array($existing_data)) {
                    $existing_data = [
                        'file' => wp_basename($file),
                    ];
                }
                //Fusionamos
                $metadata = array_merge($existing_data, $video_data);
            }
            if(!empty($metadata)) {
                wp_update_attachment_metadata($attachment_id, $metadata);
                return new WP_REST_Response(array('status' => 'success', 'message' => 'Metadata regenerada correctamente.'), 200);
            } else {
                return new WP_REST_Response(array('status' => 'error', 'message' => 'No se ha podido generar los metadata.'), 404);
            }
        } else {
            return new WP_REST_Response(array('status' => 'error', 'message' => 'Attachment no encontrado.'), 404);
        }
    } catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}

function actualizar_post_postmeta($params = array(), $wpdb, $update_slug = false){
    $where       = array('ID' => $params['post_id']);
    $update_data = array();

    if($update_slug == false){
        unset($params['post_name']);
        unset($params['guid']);
        if(isset($params['post_mime_type'])) {
            unset($params['post_mime_type']);
        }   
    }   

    // Preparar actualización de campos
    if (!empty($params['title']))      $update_data['post_title']   = sanitize_text_field($params['title']);
    if (!empty($params['description']))$update_data['post_content'] = sanitize_textarea_field($params['description']);
    if (!empty($params['legend']))     $update_data['post_excerpt'] = sanitize_text_field($params['legend']);

    // Solo hacer update si hay algo que actualizar
    if (!empty($update_data)) {
        $wpdb->update($wpdb->posts, $update_data, $where);
    }

    // Actualiza texto alternativo si fue enviado
    if (!empty($params['alt_text'])) {
        update_post_meta($params['post_id'], '_wp_attachment_image_alt', $params['alt_text']);
        stg_set_attachment_has_alt_text( $params['post_id'], true );
    }
}

function call_compress_api($type, $file, $temp_path, $resize = false) {
    // Endpoints: primario y fallback
    $primary_endpoint   = 'https://apicompressv2.strategee.us/comprimir.php';
    $secondary_endpoint = 'https://apicompress.strategee.us/comprimir.php';

    // Tamaño original para logs
    $size_original = filesize($file);

    // Datos POST
    $post_fields = [
        'file'   => new CURLFile($file),
        'type'   => $type,
        'resize' => $resize
    ];

    // Función interna para enviar request
    $send_request = function($endpoint) use ($post_fields) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 600);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'code' => $http_code,
            'body' => $response
        ];
    };

    // Intento 1: endpoint primario
    $result = $send_request($primary_endpoint);

    // Si falla o devuelve error JSON, usar fallback
    if ($result['code'] !== 200 || is_json_error($result['body'])) {
        error_log("Fallo en endpoint primario ({$primary_endpoint}). Intentando fallback...");
        $result = $send_request($secondary_endpoint);
    }

    // Validar resultado final
    if ($result['code'] !== 200) {
        throw new Exception("Error al comprimir archivo. Código HTTP: {$result['code']}");
    }

    // ¿Es JSON de error?
    if (is_json_error($result['body'])) {
        $error = json_decode($result['body'], true);
        throw new Exception("Error API: " . ($error['error'] ?? 'Desconocido'));
    }

    // Guardar archivo comprimido
    file_put_contents($temp_path, $result['body']);

    // Logs de tamaño antes y después
    $size_final = filesize($temp_path);
    //error_log("Compresión completada. Original: {$size_original} bytes → Optimizado: {$size_final} bytes");

    return $temp_path;
}

/**
 * Verifica si la respuesta es JSON con error
 */
function is_json_error($data) {
    if (empty($data)) return false;
    json_decode($data);
    if (json_last_error() === JSON_ERROR_NONE) {
        $decoded = json_decode($data, true);
        return isset($decoded['error']);
    }
    return false;
}