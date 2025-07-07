<?php

function wpil_generate_csv_data_for_api( $request ) {
    $selected_folder = $request->get_param( 'folder' );
    $orderby         = $request->get_param( 'orderby' );
    $order           = $request->get_param( 'order' );

    $images = get_all_images_in_uploads( $selected_folder, true, $orderby, $order, );

    // Usar output buffering para capturar el contenido CSV
    ob_start();
    $output = fopen( 'php://output', 'w' );

    // Cabeceras del CSV
    fputcsv( $output, array( 'Ruta Relativa', 'Nombre del Archivo', 'Dimensiones', 'Tamano (KB)', 'Vinculado a Attachment', 'ID Adjunto', 'Fecha de Modificacion', 'URL Completa' ) );

    // Datos del CSV
    foreach ( $images as $image ) {
        fputcsv( $output, array(
            $image['relative_path'],
            $image['filename'],
            $image['dimensions'],
            number_format( $image['size_kb'], 2 ),
            $image['is_attachment'] ? 'Si' : 'No',
            $image['attachment_id'] ?? '',
            $image['modified_date'],
            $image['url']
        ) );
    }

    fclose( $output );
    $csv_content = ob_get_clean(); // Obtener el contenido del buffer y limpiarlo

    // Devolver el contenido CSV como una propiedad en una respuesta JSON
    return new WP_REST_Response( array(
        'success'    => true,
        'csv_data'   => $csv_content,
        'filename'   => 'image_list_' . date( 'YmdHis' ) . '.csv', // Nombre del archivo sugerido
    ), 200 );
}