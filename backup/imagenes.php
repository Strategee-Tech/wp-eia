<?php 

// Evitar el acceso directo al archivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Registrar el endpoint de la API
add_action( 'rest_api_init', 'mi_plugin_imagenes_registrar_ruta_flexible' );

function mi_plugin_imagenes_registrar_ruta_flexible() {
    register_rest_route( 'mi-plugin-imagenes/v1', '/upload-flexible', array( // Nueva ruta para esta funcionalidad
        'methods' => 'POST',
        'callback' => 'mi_plugin_imagenes_subir_imagen_flexible',
        'permission_callback' => '__return_true', // ¡IMPORTANTE! Considera autenticación/autorización en producción
        'args' => array(
            'directory' => array(
                'required' => true,
                'type'     => 'string',
                'description' => 'El directorio de destino en formato YYYY/MM (ej. 2025/06).',
                'validate_callback' => function( $param, $request, $key ) {
                    return (bool) preg_match( '/^\d{4}\/\d{2}$/', $param );
                }
            ),
            'alt' => array(
                'required' => false,
                'type'     => 'string',
                'description' => 'Texto alternativo para la imagen.',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'title' => array(
                'required' => false,
                'type'     => 'string',
                'description' => 'Título de la imagen.',
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'name' => array(
                'required' => false,
                'type'     => 'string',
                'description' => 'Nombre lógico o identificador para la imagen.',
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ));
}

/**
 * Función para manejar la subida de imágenes a una carpeta específica (año/mes)
 * con metadatos adicionales, sin registro en la DB de WP.
 *
 * @param WP_REST_Request $request La solicitud REST.
 * @return WP_REST_Response La respuesta de la API.
 */
function mi_plugin_imagenes_subir_imagen_flexible( $request ) {
    $files = $request->get_file_params(); // Obtiene los archivos subidos

    if ( empty( $files ) || ! isset( $files['imagen'] ) ) {
        return new WP_REST_Response( array( 'message' => 'No se ha subido ninguna imagen o el nombre del campo es incorrecto. El campo debe llamarse "imagen".' ), 400 );
    }

    $uploaded_file = $files['imagen'];

    // Validar el tipo de archivo (esencial por seguridad)
    $allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
    if ( ! in_array( $uploaded_file['type'], $allowed_types ) ) {
        return new WP_REST_Response( array( 'message' => 'Tipo de archivo no permitido. Solo se permiten JPEG, PNG, GIF y WebP.' ), 400 );
    }

    // --- Obtener y validar parámetros adicionales ---
    $directory_param = $request->get_param( 'directory' );
    $alt_param       = $request->get_param( 'alt' );
    $title_param     = $request->get_param( 'title' );
    $name_param      = $request->get_param( 'name' );

    // Parsear el directorio YYYY/MM
    list( $target_year, $target_month ) = explode( '/', $directory_param );
    $target_month_padded = str_pad( $target_month, 2, '0', STR_PAD_LEFT ); // Asegura formato 01, 02, etc.

    // --- Definir la carpeta de destino ---
    $upload_dir_info = wp_upload_dir(); // Obtiene el directorio base de uploads de WordPress
    $base_upload_dir = $upload_dir_info['basedir']; // Ejemplo: /var/www/html/wp-content/uploads

    // Construir la subcarpeta de destino (ej: 2025/06/)
    $target_sub_dir = trailingslashit( $target_year ) . trailingslashit( $target_month_padded );
    $target_full_dir_path = $base_upload_dir . '/' . $target_sub_dir; // Ruta absoluta del nuevo directorio

    // Crear el nuevo directorio si no existe
    if ( ! wp_mkdir_p( $target_full_dir_path ) ) { // wp_mkdir_p es una función segura de WP para crear directorios
        return new WP_REST_Response( array( 'message' => 'Error: No se pudo crear el directorio de destino: ' . $target_full_dir_path ), 500 );
    }

    // Sanear y generar un nombre de archivo único para evitar colisiones y problemas de seguridad
    $filename = sanitize_file_name( $uploaded_file['name'] );
    $file_extension = pathinfo( $filename, PATHINFO_EXTENSION );
    $file_basename = basename( $filename, '.' . $file_extension );
    // Opcional: Si 'name' se proporciona, úsalo como parte del nombre de archivo.
    // Asegúrate de que el nombre final sea único.
    if ( ! empty( $name_param ) ) {
        $unique_filename = sanitize_title( $name_param ) . '-' . uniqid() . '.' . $file_extension;
    } else {
        $unique_filename = $file_basename . '-' . uniqid() . '.' . $file_extension;
    }

    $target_file_path = $target_full_dir_path . $unique_filename;

    // Mover el archivo subido del directorio temporal de PHP al directorio de destino
    if ( move_uploaded_file( $uploaded_file['tmp_name'], $target_file_path ) ) {
        // La imagen se ha subido con éxito
        $response_data = array(
            'message' => 'Imagen subida con éxito al directorio especificado.',
            'file_name' => $unique_filename,
            'file_path' => $target_file_path, // Ruta absoluta en el servidor
            'file_url' => $upload_dir_info['baseurl'] . '/' . $target_sub_dir . $unique_filename,
            'type' => $uploaded_file['type'],
            'received_alt' => $alt_param,
            'received_title' => $title_param,
            'received_name' => $name_param,
            'target_directory' => $directory_param,
        );
        return new WP_REST_Response( $response_data, 200 );
    } else {
        // Hubo un error al mover la imagen
        return new WP_REST_Response( array( 'message' => 'Error al mover la imagen al directorio de destino.' ), 500 );
    }
}