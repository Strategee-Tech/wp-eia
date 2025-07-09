<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}
require_once dirname(__DIR__) . '/utils/auth.php';
add_action( 'rest_api_init', 'wp_optimization_files' );

date_default_timezone_set('America/Bogota');

function wp_optimization_files() {
    register_rest_route( 'api/v1', '/optimization-file', array(
        'methods'             => 'POST', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'optimization',
        'permission_callback' => function () {
        	require_once dirname(__DIR__) . '/utils/auth.php';
        	return basic_auth_permission_check();
    	}, 
    )); 
}

function optimization($request) {
	// Obtener todos los parámetros de la solicitud POST
    $params = $request->get_params();

    if ( empty( $params['post_id'] ) || ! is_numeric( $params['post_id'] ) ) {
        return new WP_REST_Response( array( 'error' => 'ID inválido.' ), 400 );
    }

    $file_path = get_attached_file( $params['post_id'] );
    if ( ! file_exists( $file_path ) ) {
        return new WP_REST_Response( array( 'error' => 'Archivo no encontrado.' ), 404 );
    }
    
    try {
    	echo "<pre>";
    	print_r($params);
    	echo "<br>";
    	echo "</pre>";
      
    	// Obtener datos del archivo
	    $pathinfo      = pathinfo( $file_path );
	    $upload_dir    = wp_upload_dir();
	    $ext           = strtolower( $pathinfo['extension'] );
	    $dir           = $pathinfo['dirname'];
	    $base_filename = $new_name ? $new_name : $pathinfo['filename'];
	    $new_filename  = $base_filename . '-compressed.' . $ext;
	    $new_path      = $dir . '/' . $new_filename;

	    echo "<pre>";
    	print_r($pathinfo);
    	echo "<br>";
    	echo "</pre>";

    	echo "<pre>";
    	print_r($upload_dir);
    	echo "<br>";
    	echo "</pre>";

    	echo "<pre>";
    	print_r($ext);
    	echo "<br>";
    	echo "</pre>";

    	echo "<pre>";
    	print_r($dir);
    	echo "<br>";
    	echo "</pre>";

    	echo "<pre>";
    	print_r($base_filename);
    	echo "<br>";
    	echo "</pre>";

		echo "<pre>";
    	print_r($new_filename);
    	echo "<br>";
    	echo "</pre>";

		echo "<pre>";
    	print_r($new_path);
    	echo "<br>";
    	echo "</pre>";

	    $ffmpeg_path   = '/var/www/html/ffmpeg/ffmpeg'; // Ruta absoluta a tu ejecutable

	    // Comprimir con FFmpeg
	    $ffmpeg_cmd = sprintf(
	        'ffmpeg -i %s -vcodec libx264 -crf 28 %s 2>&1',
	        escapeshellarg( $ffmpeg_path ),
	        escapeshellarg( $file_path ),
	        escapeshellarg( $new_path )
	    );
	    exec( $ffmpeg_cmd, $output, $return_var );

	    if ( $return_var !== 0 || ! file_exists( $new_path ) ) {
	        return new WP_REST_Response( array(
	            'error'  => 'Error al comprimir con FFmpeg.',
	            'output' => $output
	        ), 500 );
	    }

	    // Eliminar original si se desea (aquí lo hacemos por defecto)
    	// unlink( $file_path );

    	// Actualizar la metadata del attachment con el nuevo archivo
    	// update_attached_file( $attachment_id, $new_path );
		
        return new WP_REST_Response(
    	array(
    		'status'        => 'success', 
    		'message'       => 'Se han actualizado los datos y se ha optimizado el archivo.',
    	), 200);
   	} catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}
