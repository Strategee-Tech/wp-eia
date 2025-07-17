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
    $logPath = dirname(__FILE__, 6).'/log_registros_eliminados.txt';

    if (empty($params) || !is_array($params)) {
        return new WP_REST_Response([
            'status'  => 'error',
            'message' => 'No se recibió un JSON válido.'
        ], 400);
    }
    
    
    return new WP_REST_Response([
        'status'   => 'success', 
        'message'  => 'Archivos Eliminados.',
        'report'   => get_site_url().'/log_registros_eliminados.txt?v=' . time()
    ], 200);
}