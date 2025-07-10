<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}
require_once dirname(__DIR__) . '/utils/auth.php';
require_once(dirname(__FILE__) . '/wp-load.php');

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
    $logPath = dirname(__FILE__).'/log_registros_eliminados.txt';

    if (empty($params) || !is_array($params)) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'No se recibió un JSON válido.'
        ], 400);
    }

    if(!empty($params['0'])) {
        foreach ($params['0'] as $attachment_id) {
            // Eliminar el attachment
            //if (wp_delete_attachment($attachment_id, true)) {
                file_put_contents($logPath, "Eliminado attachment ID: $attachment_id\n", FILE_APPEND);
            //} else {
                //file_put_contents($logPath, "Error al eliminar attachment ID: $attachment_id\n", FILE_APPEND);
            //}
        }
    }
    if(!empty($params['1'])) {
        foreach ($params['1'] as $full_path) {
            if (file_exists($full_path)) {
                //if (unlink($full_path)) {
                    file_put_contents($logPath, "Eliminado archivo en ruta: $full_path\n", FILE_APPEND);
                //} else {
                    //file_put_contents($logPath, "Error al eliminar archivo en ruta: $full_path\n", FILE_APPEND);
                //}
            } else {
                file_put_contents($logPath, "Archivo no encontrado en ruta: $full_path\n", FILE_APPEND);
            }
        }
    }
    file_put_contents($logPath, "Ejecución Finalizada: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    file_put_contents($logPath, "\n", FILE_APPEND); 

    return new WP_REST_Response([
        'status'   => 'success', 
        'message'  => 'Archivos Eliminados.',
        'report'   => get_site_url().'/log_registros_eliminados.txt'
    ], 200);
}