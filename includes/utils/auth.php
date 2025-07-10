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

function update_yoast_info($new_url, $old_url, $post_id) {
    global $wpdb;
    //actualizar post_content de una imagen dentro de una pagina
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->posts} 
            SET post_content = REPLACE(post_content, %s, %s) 
            WHERE post_content LIKE %s AND post_status IN ('publish', 'private', 'draft') AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
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
            WHERE post_content LIKE %s AND post_status IN ('publish', 'private', 'draft')",
            $old_url,
            $new_url,
            '%' . basename($old_url) . '%'
        )
    );

    // Tabla de Yoast SEO
    $tabla_yoast_seo_links = $wpdb->prefix . 'yoast_seo_links';
    $tabla_indexable       = $wpdb->prefix . 'yoast_indexable';
    
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

