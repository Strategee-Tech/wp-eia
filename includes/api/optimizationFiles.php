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
	$params = $request->get_params();

	if (empty($params['post_id']) || !is_numeric($params['post_id'])) {
		return new WP_REST_Response(['error' => 'ID inválido.'], 400);
	}

	$post = get_post($params['post_id']);
	if (!$post || $post->post_type !== 'attachment') {
		return new WP_REST_Response(['status' => 'error', 'message' => 'El post no existe o no es un attachment.'], 404);
	}

	$original_path = get_attached_file($params['post_id']);
	if (!file_exists($original_path)) {
		return new WP_REST_Response(['error' => 'Archivo no encontrado.'], 404);
	}

	try {
		$info = pathinfo($original_path);
		$ext  = strtolower($info['extension']);
		$extensiones_imagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif', 'heic'];
		if (in_array($ext, $extensiones_imagen)) {
		   return new WP_REST_Response([
		        'status'  => 'error',
		        'message' => 'El archivo a optimizar no es un archivo multimedia.',
		    ], 500);
		} 

		global $wpdb;
		if($params['fast_edit'] == 1) {
			actualizar_post_postmeta($params, $wpdb);
			return new WP_REST_Response([
				'status'        => 'success',
				'message'       => 'Se ha actualizado la información.',
			], 200);

		} else {
			$params['slug'] = slug_unico(
			    sanitize_file_name($params['slug']),
			    $params['post_id']
			);
			$dir          = $info['dirname'];
			$new_filename = $params['slug'] . '.' . $ext;
			$new_path     = $dir . '/' . $new_filename;
			$old_url      = $post->guid;
			$file_size_bytes_before = filesize($original_path);

			// Ruta completa a ffmpeg
			$ext_multimedia        = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'm4a', 'aac', 'ogg', 'mpeg'];
			$ext_documentos        = ['pdf'];
			$temp_path 		       = $dir . '/' . uniqid('-compressed', true) . '.' . $ext;

			//compresion
			$regenerate_metadata   = init_compress_file($ext, $ext_multimedia, $ext_documentos, $original_path, $temp_path, $new_path);
			$file_size_bytes_after = filesize($new_path);

			// Obtener ruta relativa y URL pública
			$upload_dir    = wp_upload_dir();
			$relative_path = str_replace(trailingslashit($upload_dir['basedir']), '', $new_path);
			$folder        = dirname($relative_path);                               // /2025/06
			$new_url       = trailingslashit($upload_dir['baseurl']) . $relative_path;
			$old_rel_path  = $folder.'/'.$info['basename'];
			$new_rel_path  = $folder.'/'.$new_filename;

			$params['post_name'] = $params['slug'];
			$params['guid']      = esc_url_raw($new_url);      
			actualizar_post_postmeta($params, $wpdb, true);

			// Actualizar derivados del metadata
    		update_post_meta($post->ID, '_wp_attached_file', $new_rel_path);

    		// Regenerar metadatos
	    	if($regenerate_metadata != false){
	    		regenerate_metadata($post->ID, $regenerate_metadata);
	    	}

	    	update_urls(
			    $old_rel_path,
			    $new_rel_path,  
			    ['post_content', 'meta_value', 'open_graph_image', 'twitter_image', 'open_graph_image_meta', 'url', 'action_data'],
				$post->ID
			); 

			wp_cache_flush();
			clean_post_cache($post->ID);

			$sheet_id = get_option('google_sheet_id');
			$sheet    = get_option('name_sheet_files');

			if(!empty($sheet_id) && !empty($sheet)) {
				$datos_drive = array(
					'id_sheet' => $sheet_id,
					'sheet'    => $sheet.'!A1',
					'values'   => [[
						date('Y-m-d H:i:s'),
						$old_url,
						$new_url,
						number_format($file_size_bytes_before),
						number_format($file_size_bytes_after),
						isset($params['alt_text']) ? $params['alt_text'] : '',
						isset($params['slug']) ? $params['slug'] : '',
						isset($params['title']) ? $params['title'] : '',
						isset($params['description']) ? $params['description'] : '',
						$ext,
						'N/A',
						(isset($params['ia']) && $params['ia'] == true) ? 'Si' : 'No',
					]]
				);
				$respuesta = save_google_sheet($datos_drive); // Llamada directa
			}

			return new WP_REST_Response([
				'status'        => 'success',
				'message'       => 'Se han actualizado los datos y se ha optimizado el archivo.',
				'new_name_file' => $new_filename,
				'old_url'       => $old_url,
				'new_url'       => $new_url,
				'new_path'      => $new_path,
				'relative_path' => $relative_path,
				'size'          => number_format($file_size_bytes_after),
			], 200);
		} 
	} catch (\Throwable $th) {
		return new WP_REST_Response(['status' => 'error', 'message' => $th->getMessage()], 500);
	}
}

function init_compress_file($ext, $ext_multimedia, $ext_documentos, $original_path, $temp_path, $new_path){
	$regenerate_metadata = false;
	// Si NO es PDF → Comprimir con FFmpeg
	if (in_array($ext, $ext_multimedia)) {
		try {
		    $compress_file = call_compress_api('multimedia', $original_path, $temp_path);

		    if (!file_exists($compress_file) || filesize($compress_file) === 0) {
		    	return new WP_REST_Response([
			        'status'  => 'error',
			        'message' => 'El archivo comprimido no se recibió correctamente.',
			    ], 500);
		    } 
		    @unlink($original_path);
		    rename($compress_file, $new_path);
		    $regenerate_metadata = 'multimedia';

		} catch (Exception $e) {
		    return new WP_REST_Response([
		        'status'  => 'error',
		        'message' => 'Falló la compresión del archivo multimedia.',
		        'detalle' => $e->getMessage()
		    ], 500);
		}

	} elseif(in_array($ext, $ext_documentos)) {
		try {
		    $compress_file = call_compress_api('pdf', $original_path, $temp_path);

		    if (!file_exists($compress_file) || filesize($compress_file) === 0) {
		        return new WP_REST_Response([
			        'status'  => 'error',
			        'message' => 'El archivo comprimido no se recibió correctamente.',
			    ], 500);
		    } 
		    @unlink($original_path);
		    rename($compress_file, $new_path);
		    $regenerate_metadata = 'pdf';

		} catch (Exception $e) {
		    return new WP_REST_Response([
		        'status'  => 'error',
		        'message' => 'Falló la compresión del archivo pdf.',
		        'detalle' => $e->getMessage()
		    ], 500);
		}
	} else {
		// Solo renombrar el archivo si no se puede comprimir
	    if ($original_path != $new_path) {
	        rename($original_path, $new_path);
	    }
	} 
	return $regenerate_metadata;
}