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
                    p.post_mime_type,
                    p.post_content AS image_description,
                    p.post_excerpt AS image_legend,
                    pm_file.meta_value AS file_path_relative,
                    pm_alt.meta_value AS image_alt_text
                FROM " . $wpdb->posts . " AS p -- ¡AQUÍ ESTÁ LA CLÁUSULA FROM FALTANTE!
                JOIN
                    " . $wpdb->postmeta . " AS pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_wp_attached_file'
                LEFT JOIN
                    " . $wpdb->postmeta . " AS pm_alt ON p.ID = pm_alt.post_id AND pm_alt.meta_key = '_wp_attachment_image_alt'
                WHERE
                    p.post_type = 'attachment'
                    AND p.post_mime_type LIKE 'image/%'
                    AND pm_file.meta_value LIKE %s", // El LIKE %s ya maneja el comodín '%'
                '%' . $wpdb->esc_like($subfolder) . '/%' // Asegura que coincida con un subfolder
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