<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getAllImages.php';

?>

<H1>Gestion y Optimización de Imágenes</H1>


<?php

$selected_folder            = isset( $_GET['wpil_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['wpil_folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
$selected_folder            = trim( $selected_folder, '/' );

$orderby                    = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'size_bytes';
$order                      = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';

$showMiniatures             = isset( $_GET['miniatures'] ) ? 1 : 0;
$showAttachment             = isset( $_GET['attachment'] ) ? 1 : 0;
$delete_all                 = isset($_GET['delete_all']) ? 1 : 0;

$all_images = get_all_images_in_uploads($selected_folder, $showAttachment, $orderby, $order, $showMiniatures);

?>

<div class="wrap">
<h2>Listado de Imágenes <?php echo ! empty( $selected_folder ) ? 'en: `' . esc_html( $selected_folder ) . '`' : ' (todas)'; ?></h2>

<p>
    <strong>Archivos encontrados:</strong> <?php echo number_format( $total_files ); ?><br>
    <strong>Peso Total:</strong> <?php echo size_format( $total_size_bytes ); ?>
</p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Ruta Relativa</th>
                <th>Nombre del Archivo</th>
                <th>Dimensiones</th>
                <?php
                // Función auxiliar para generar cabeceras ordenables
                function wpil_get_sortable_header( $column_id, $column_title, $current_orderby, $current_order, $selected_folder, $status, $showMiniatures ) {
                    $new_order = ( $current_orderby === $column_id && $current_order === 'asc' ) ? 'desc' : 'asc';
                    $class = ( $current_orderby === $column_id ) ? 'sorted ' . $current_order : 'sortable ' . $new_order;
                    $query_args = array(
                        'page'      => 'wpil-image-lister',
                        'orderby'   => $column_id,
                        'order'     => $new_order,
                        'status'	=> $status == true ? '0' : '1',
                        'miniatures' => $showMiniatures == true ? '0' : '1'
                    );
                    if ( ! empty( $selected_folder ) ) {
                        $query_args['wpil_folder'] = $selected_folder;
                    }
                    $column_url = add_query_arg( $query_args, admin_url( 'admin.php' ) );
                    ?>
                    <th scope="col" class="manage-column column-<?php echo esc_attr( $column_id ); ?> <?php echo esc_attr( $class ); ?>">
                        <a href="<?php echo esc_url( $column_url ); ?>">
                            <span><?php echo esc_html( $column_title ); ?></span>
                            <span class="sorting-indicator"></span>
                        </a>
                    </th>
                    <?php
                }
                wpil_get_sortable_header( 'size_bytes', 'Tamaño (KB)', $orderby, $order, $selected_folder, $status, $showMiniatures );
                wpil_get_sortable_header( 'is_attachment', 'Vinculado a Attachment', $orderby, $order, $selected_folder, $status, $showMiniatures );
                wpil_get_sortable_header( 'modified_date', 'Fecha de Modificación', $orderby, $order, $selected_folder, $status, $showMiniatures );
                ?>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ( empty( $all_images ) ) :
                ?>
                <tr>
                    <td colspan="7">No se encontraron imágenes en el directorio especificado.</td>
                </tr>
                <?php
            else :
                foreach ( $all_images as $image ) :
                    ?>
                    <tr>
                        <td><?php echo esc_html( $image['relative_path'] ); ?></td>
                        <td><?php echo esc_html( $image['filename'] ); ?></td>
                        <td><?php echo esc_html( $image['dimensions'] );  echo $image['is_miniature'] == 1 ? 'Min' : '' ?>  </td>
                        <td><?php echo esc_html( number_format( $image['size_kb'], 2 ) ); ?></td>
                        <td>
                            <?php
                            if ( $image['is_attachment'] ) {								
                                echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Sí (ID: ' . esc_html( $image['attachment_id'] ) . ')';
                            } else {
                                if( !$image['en_contenido'] ) {
                                    echo '<span class="dashicons dashicons-no-alt" style="color: red;"></span> No Content';									
                                } else {
                                    echo '<span class="dashicons dashicons-no-alt" style="color: green;"></span> En contenido';									
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $image['modified_date'] ); ?></td>
                        <td><a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank"><?php echo esc_url( $image['url'] ); ?></a></td>
                    </tr>
                    <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>

</div>






