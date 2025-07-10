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
		global $wpdb;

		$where          = array('ID' => $post->ID);
		$slug           = sanitize_file_name($params['slug']);
		$slug_unico     = slug_unico($slug, $params['post_id']);
		$slug           = $slug_unico;
		$params['slug'] = $slug;
		$info           = pathinfo($original_path);
		$ext            = strtolower($info['extension']);
		$dir            = $info['dirname'];
		$new_filename   = $slug . '.' . $ext;
		$new_path       = $dir . '/' . $new_filename;
		$old_url        = $post->guid;
		$file_size_bytes_before = filesize($original_path);

		// Ruta completa a ffmpeg
		$ffmpeg_exe     = dirname(ABSPATH) . '/ffmpeg/ffmpeg';
		$ext_multimedia = ['mp4', 'mov', 'avi', 'mkv', 'webm', 'mp3', 'wav', 'm4a', 'aac'];
		$ext_documentos = ['pdf'];

		// Si NO es PDF → Comprimir con FFmpeg
		if (in_array($ext, $ext_multimedia)) {

			$temp_path  = $dir . '/' . uniqid('-compressed', true) . '.' . $ext;

			$ffmpeg_cmd = sprintf(
			    '/bin/bash -c "%s -i %s -vcodec libx264 -crf 28 %s"',
			    escapeshellcmd($ffmpeg_exe),
			    escapeshellarg($original_path),
			    escapeshellarg($temp_path)
			);

			exec($ffmpeg_cmd . ' 2>&1', $output, $return_code);

			if ($return_code !== 0 || !file_exists($temp_path)) {
				return new WP_REST_Response([
					'message'   => 'Error al comprimir con FFmpeg.',
					'cmd'       => $ffmpeg_cmd,
					'output'    => $output,
					'exit_code' => $return_code,
				], 500);
			}

		 	if (file_exists($temp_path)) {
    			unlink($original_path);
    			rename($temp_path, $new_path);
			}
			$file_size_bytes_after = filesize($new_path);

		} elseif(in_array($ext, $ext_documentos)) {

		 	$temp_path = $dir . '/' . uniqid('-compressed', true) . '.' . $ext;

		    $gs_cmd = sprintf(
		        'gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen ' .
		        '-dNOPAUSE -dQUIET -dBATCH -sOutputFile=%s %s',
		        escapeshellarg($temp_path),
		        escapeshellarg($original_path)
		    );

		    exec($gs_cmd . ' 2>&1', $output, $return_code);

		    if ($return_code !== 0 || !file_exists($temp_path)) {
		        return new WP_REST_Response([
		            'message'   => 'Error al comprimir con Ghostscript.',
		            'cmd'       => $gs_cmd,
		            'output'    => $output,
		            'exit_code' => $return_code,
		        ], 500);
		    }
		    if (file_exists($temp_path)) {
    			unlink($original_path);
    			rename($temp_path, $new_path);
			}
		    $file_size_bytes_after = filesize($new_path);
		} else {
			// Solo renombrar el archivo si no se puede comprimir
		    if ($original_path != $new_path) {
		        rename($original_path, $new_path);
		    }
		    $file_size_bytes_after = filesize($new_path);
		}

		// Obtener ruta relativa y URL pública
		$upload_dir    = wp_upload_dir();
		$relative_path = str_replace(trailingslashit($upload_dir['basedir']), '', $new_path);
		$new_url       = trailingslashit($upload_dir['baseurl']) . $relative_path;

		// Preparar actualización de campos
		if (!empty($params['title']))      $update_data['post_title']   = sanitize_text_field($params['title']);
		if (!empty($params['description']))$update_data['post_content'] = sanitize_textarea_field($params['description']);
		if (!empty($params['legend']))     $update_data['post_excerpt'] = sanitize_text_field($params['legend']);
		if (!empty($params['slug']))       $update_data['post_name']    = $params['slug'];
		if (!empty($new_url))              $update_data['guid']         = esc_url_raw($new_url);

		// Actualizar post
		if (!empty($update_data)) {
			$wpdb->update($wpdb->posts, $update_data, $where);
		}

		// Texto alternativo
		if (!empty($params['alt_text'])) {
			update_post_meta($params['post_id'], '_wp_attachment_image_alt', sanitize_text_field($params['alt_text']));
		}

		// Actualizar derivados del metadata
    	update_post_meta($post->ID, '_wp_attached_file', $relative_path);

    	// Actualizar post_content
		update_yoast_info($new_url, $old_url, $post->ID);

		wp_cache_flush();

		$datos_drive = array(
			'id_sheet' => '1r1WXkd812cJPu4BUvIeGDGYXfSsnebSAgOvDSvIEQyM',
			'sheet'    => 'Documentos!A1',
			'values'   => [[
				date('Y-m-d H:i:s'),
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

		return new WP_REST_Response([
			'status'        => 'success',
			'message'       => 'Se han actualizado los datos y se ha optimizado el archivo.',
			'new_name_file' => $new_filename,
			'old_url'       => $old_url,
			'new_url'       => $new_url,
			'new_path'      => $new_path,
			'relative_path' => $relative_path,
			'size'          => $file_size_bytes_after,
		], 200);

	} catch (\Throwable $th) {
		return new WP_REST_Response(['status' => 'error', 'message' => $th->getMessage()], 500);
	}
}