<?php


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

function update_post_meta_elementor_data($basename, $new_url, $old_url){
    global $wpdb;
    // Extraer año/mes desde la URL vieja
    preg_match('#/uploads/(\d{4})/(\d{2})/#', $old_url, $matches);
    $year_month_path = !empty($matches[0]) ? $matches[0] : null;
    if (!$year_month_path) {
        // Evita procesar si no tienes la ruta esperada
        return;
    }
    $meta_key = '_elementor_data';
    $rows     = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value 
             FROM {$wpdb->prefix}postmeta
             WHERE meta_key = %s 
             AND meta_value LIKE %s",
            $meta_key,
            '%' . $wpdb->esc_like($basename) . '%' //nombredelarchivo.[ext]
        ),
        ARRAY_A
    );

    if(!empty($rows)) {
        foreach ($rows as $row) {
            $json_data = json_decode($row['meta_value'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                array_walk_recursive($json_data, function (&$value) use ($old_url, $new_url, $year_month_path, $basename) {
                    if (is_string($value) && strpos($value, $old_url) !== false) {
                        // Asegura que la coincidencia sea específica a la carpeta esperada
                        if (strpos($value, $year_month_path . $basename) === false) {
                            return; // No reemplazar si el path no coincide
                        }
                        $value = str_replace($old_url, $new_url, $value);
                    }
                });
                update_post_meta($row['post_id'], $meta_key, wp_slash(json_encode($json_data)));
            }
        }
    }
}

function update_elementor_css_url($new_url, $old_url) {
    global $wpdb;
    $meta_key = '_elementor_css';
    $rows     = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value 
             FROM {$wpdb->prefix}postmeta
             WHERE meta_key = %s 
             AND meta_value LIKE %s",
            $meta_key,
            '%' . $wpdb->esc_like($old_url) . '%'
        ),
        ARRAY_A
    );

    if(!empty($rows)) {   
        foreach ($rows as $row) {
            $meta_value = maybe_unserialize($row['meta_value']);
            if (!empty($meta_value['css']) && is_string($meta_value['css'])) {
                // Reemplazar la URL antigua por la nueva
                $meta_value['css'] = str_replace($old_url, $new_url, $meta_value['css']);

                // Actualizar el campo
                update_post_meta($row['post_id'], $meta_key, $meta_value);
            }
        }
    }
}

function call_compress_api($type, $file, $temp_path, $resize = false){
    $endpoint    = 'https://apicompress.strategee.us/comprimir.php';
    $post_fields = [
        'file'   => new CURLFile($file),
        'type'   => $type,
        'resize' => $resize
    ];

    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6000);

    // Ejecutar
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        throw new Exception("Error al comprimir archivo desde API externa. Código: $http_code");
    }

    // Guardar el archivo comprimido recibido
    file_put_contents($temp_path, $response);

    return $temp_path;
}