<?php
/**
 * Función para obtener todas las imágenes del directorio de uploads o de una subcarpeta específica.
 * Excluye miniaturas y filtra opcionalmente por si están vinculadas o no a attachments .
 *
 * @param string $subfolder        Carpeta relativa dentro de uploads (ej: '2024/06' o 'mis-imagenes-api').
 * @param string $orderby          Columna por la que ordenar (size_bytes, is_attachment, modified_date).
 * @param string $order            Dirección de ordenación (asc o desc).
 * @return array Lista de arrays de imágenes.
 */
function getPaginatedImages( $subfolder = '', $page = 1, $per_page = 10 ) {

    global $wpdb;

    $upload_dir_info    = wp_upload_dir();
    $base_upload_path   = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url    = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path         = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path    .= trailingslashit( $subfolder );
    }
    
    try {

        echo $subfolder;
        

        $attachments_in_folder = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT
                    p.ID AS attachment_id,
                    p.post_title,
                    p.guid AS attachment_url,
                    pm.meta_value AS file_path_relative
                FROM
                    wp_posts AS p
                JOIN
                    wp_postmeta AS pm ON p.ID = pm.post_id
                WHERE
                    p.post_type = 'attachment'
                    AND pm.meta_key = '_wp_attached_file'
                    AND pm.meta_value LIKE %s",
                $wpdb->esc_like($subfolder) . '%'
            ),
            ARRAY_A
        );

        echo '<pre>';
        print_r( $attachments_in_folder );
        echo '</pre>';
        die();


    } catch (\Throwable $th) {
        error_log( 'WPIL Error iterating directory: ' . $th->getMessage() . ' for path: ' . $start_path );
        return array();
    }
}


?>