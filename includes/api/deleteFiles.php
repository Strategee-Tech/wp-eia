<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}
require_once dirname(__DIR__) . '/utils/auth.php';

add_action( 'rest_api_init', 'wp_borrar_archivos' );

date_default_timezone_set('America/Bogota');

function wp_borrar_archivos() {
    register_rest_route( 'api/v1', '/borrar-archivos', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'borrar_archivos',
        'permission_callback' => function () {
        	require_once dirname(__DIR__) . '/utils/auth.php';
        	return basic_auth_permission_check();
    	}, 
    )); 
}

function borrar_archivos($request) {
    $params  = $request->get_json_params();
    $logPath = dirname(__FILE__, 6).'/log_registros_eliminados.txt';

    file_put_contents($logPath, "\n", FILE_APPEND); 
    file_put_contents($logPath, "Ejecución Iniciada: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

    //Para borrar todo el contenido del log y que siempre sea un solo registro
    //file_put_contents($logPath, "Inicio: " . date('Y-m-d H:i:s') . "\n");

    if (empty($params) || !is_array($params)) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'No se recibió un JSON válido.'
        ], 400);
    }

    $sheet_id = get_option('google_sheet_id');
    $sheet    = get_option('name_sheet_deleteds');

    $datos_drive = [
        'id_sheet' => $sheet_id,
        'sheet'    => $sheet.'!A1',
        'values'   => [],
    ];

    if(!empty($params['0'])) {
        foreach ($params['0'] as $attachment_id) {
            $url         = wp_get_attachment_url($attachment_id);
            $path        = get_attached_file($attachment_id);
            $peso_bytes  = file_exists($path) ? filesize($path) : 0;
            $peso_mb     = round($peso_bytes / 1048576, 2);
            $carpeta     = file_exists($path) ? dirname(str_replace(wp_upload_dir()['basedir'], '', $path)) : 'N/A';
            $fecha       = date('Y-m-d H:i:s');
            // Eliminar el attachment
            if (wp_delete_attachment($attachment_id, true)) {
                $datos_drive['values'][] = [$url, $peso_mb, $carpeta, $fecha];
                file_put_contents($logPath, "Eliminado attachment ID: $attachment_id\n", FILE_APPEND);
            } else {
                file_put_contents($logPath, "Error al eliminar attachment ID: $attachment_id\n", FILE_APPEND);
            }
        }
    }
    if(!empty($params['1'])) {
        foreach ($params['1'] as $full_path) {
            if (file_exists($full_path)) {
                $url_base    = wp_upload_dir()['baseurl'];
                $base_dir    = wp_upload_dir()['basedir'];
                $rel_path    = str_replace($base_dir, '', $full_path);
                $url         = $url_base . $rel_path;
                $peso_bytes  = file_exists($full_path) ? filesize($full_path) : 0;
                $peso_mb     = round($peso_bytes / 1048576, 2);
                $carpeta     = file_exists($full_path) ? dirname($rel_path) : 'N/A';
                $fecha       = date('Y-m-d H:i:s');
                $datos_drive['values'][] = [$url, $peso_mb, $carpeta, $fecha];
                if (unlink($full_path)) {
                    file_put_contents($logPath, "Eliminado archivo en ruta: $full_path\n", FILE_APPEND);
                } else {
                    file_put_contents($logPath, "Error al eliminar archivo en ruta: $full_path\n", FILE_APPEND);
                }
            } else {
                file_put_contents($logPath, "Archivo no encontrado en ruta: $full_path\n", FILE_APPEND);
            }
        }
    }

    file_put_contents($logPath, "Ejecución Finalizada: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    file_put_contents($logPath, "\n", FILE_APPEND); 

    // Llamar una sola vez a Google Sheets al final
    if (!empty($datos_drive['values'])) {
        if(!empty($sheet_id) && !empty($sheet)) {
            save_google_sheet($datos_drive);
        }
        return new WP_REST_Response([
            'status'   => 'success', 
            'message'  => 'Archivos Eliminados.',
            'report'   => get_site_url().'/log_registros_eliminados.txt?v=' . time()
        ], 200);
    } else {
        return new WP_REST_Response([
            'status'   => 'error', 
            'message'  => 'No se eliminó ningun archivo.',
            'report'   => get_site_url().'/log_registros_eliminados.txt?v=' . time()
        ], 400);
    } 
}