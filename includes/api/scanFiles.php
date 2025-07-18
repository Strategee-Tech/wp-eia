<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}
require_once dirname(__DIR__) . '/utils/auth.php';

add_action( 'rest_api_init', 'wp_scan_files' );

date_default_timezone_set('America/Bogota');

function wp_scan_files() {
    register_rest_route( 'api/v1', '/scan-files', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'scan_files',
        'permission_callback' => function () {
            require_once dirname(__DIR__) . '/utils/auth.php';
            return basic_auth_permission_check();
        }, 
    )); 
}

function scan_files($request) {
    $params  = $request->get_json_params();

    global $wpdb;

    $result = [];

    if(empty($params)) {
        $sql = "
            SELECT pm_file.meta_id, pm_file.post_id, pm_file.meta_value
            FROM {$wpdb->posts} AS p
            LEFT JOIN {$wpdb->postmeta} AS pm_file 
                ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
            LEFT JOIN {$wpdb->postmeta} AS pm_in_use 
                ON p.ID = pm_in_use.post_id AND pm_in_use.meta_key = '_stg_is_scanned'
            WHERE pm_in_use.meta_value = %s
            LIMIT 1
        ";

        $query  = $wpdb->prepare($sql, 0); // %s se reemplaza con '0'
        $result = $wpdb->get_row($query);
    }

    if (!empty($result) || !empty($params)) {
        // Accede a los datos
        $meta_id    = empty($params) ? $result->meta_id : '';
        $post_id    = isset($params['attachment_id']) ? $params['attachment_id'] : $result->post_id;
        $file_value = isset($params['path']) ? $params['path'] : $result->meta_value;

        // Lista de funciones a evaluar en orden
        $checks = [
            function() use ($file_value) { return get_elementor_data($file_value); },
            function() use ($file_value) { return get_elementor_css($file_value); },
            function() use ($file_value) { return get_post_content_posts($file_value); },
            function() use ($file_value) { return get_post_content_cursos($file_value); },
            function() use ($file_value) { return get_enclosure($file_value); },
            function() use ($post_id) { return get_thumbnail($post_id); }
        ];

        $found = false;
        foreach ($checks as $check) {
            $resp = $check();
            if ($resp == true) {
                $found = true;
                break; // Detenemos el bucle en la primera coincidencia
            }
        }

        // Actualizamos el meta_key con el estado final
        if ($found) {
            stg_set_attachment_in_use($post_id, 1); 
        } else {
            stg_set_attachment_in_use($post_id, 0); 
        }

        stg_set_attachment_scanned($post_id, 1); 

        return new WP_REST_Response([
            'status'           => 'success', 
            'message'          => 'Archivo Escaneado.',
            'total_escaneados' => contar_escaneados($wpdb),
        ], 200);
         
    } else {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'No se encontraron registros.'
        ], 400);
    }
}

function contar_escaneados($wpdb){
    $sql = "
        SELECT COUNT(*) 
        FROM {$wpdb->posts} AS p
        LEFT JOIN {$wpdb->postmeta} AS pm_file 
            ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
        LEFT JOIN {$wpdb->postmeta} AS pm_in_use 
            ON p.ID = pm_in_use.post_id AND pm_in_use.meta_key = '_stg_is_scanned'
        WHERE pm_in_use.meta_value = %s
    ";

    $query = $wpdb->prepare($sql, '1'); // Filtra por no escaneados (0)
    $total = $wpdb->get_var($query);

    // Resultado
    return $total;
}