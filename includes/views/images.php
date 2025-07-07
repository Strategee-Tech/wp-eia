<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

?>

<?php 
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getAllImages.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getSortableImageTableHeader.php';

?>

<H1>Gestion y Optimización de Imágenes</H1>

<?php
$selected_folder            = isset( $_GET['folder'] ) ? sanitize_text_field( wp_unslash( $_GET['folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
$selected_folder            = trim( $selected_folder, '/' );

$orderby                    = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'size_bytes';
$order                      = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';


$showMiniatures             = isset( $_GET['miniatures'] ) ? 1 : 0;
$showAttachment             = isset( $_GET['attachment'] ) ? 1 : 0;
$delete_all                 = isset($_GET['delete_all']) ? 1 : 0;



$all_images = get_all_images_in_uploads($selected_folder, $orderby, $order, $showMiniatures);


$total_files      = count( $all_images['all_images'] );
$total_size_bytes = array_sum( array_column( $all_images['all_images'], 'size_bytes' ) );
$total_delete_size_bytes = 0;
$total_delete_size_bytes_thumbnails = 0;
$thumbnail_count =  count($all_images['all_thumbnails']);
$to_delete_count = 0;
foreach ( $all_images['all_images'] as $image ) {
    if( isset($image['to_delete']) && $image['to_delete'] === true) {
        $to_delete_count++;
        $total_delete_size_bytes += $image['size_bytes'];
    }
}

$thumbnail_to_delete_count = 0;
foreach ( $all_images['all_thumbnails'] as $thumbnail ) {
    if( isset($thumbnail['to_delete']) && $thumbnail['to_delete'] === true) {
        $thumbnail_to_delete_count++;
        $total_delete_size_bytes_thumbnails += $thumbnail['size_bytes'];
    }
}

?>

<form method="get" action="" id='form-filter'>
    <input type="hidden" name="page" value="images" />
    <?php if ( ! empty( $orderby ) ) : ?>
        <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
    <?php endif; ?>
    <?php if ( ! empty( $order ) ) : ?>
        <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="folder">Carpeta a Mostrar:</label></th>
            <td>
                <input type="text" id="folder" name="folder" value="<?php echo esc_attr( $selected_folder ); ?>" class="regular-text" placeholder="Ej: 2024/06 o mis-imagenes-api" />
                <p class="description">
                    Introduce la subcarpeta dentro de `wp-content/uploads/` (ej: `2024/06` o `mis-imagenes-api`).
                </p>
            </td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" name="submit" id="submit" class="button button-secondary" value="Mostrar Imágenes">
    <?php if($to_delete_count > 0): ?>
        <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST">
    <?php endif; ?>
    </p>
</form>

<hr>

<div class="wrap">
<h2>Listado de Imágenes <?php echo ! empty( $selected_folder ) ? 'en: `' . esc_html( $selected_folder ) . '`' : ' (todas)'; ?></h2>

<p>
    <strong>Imagenes Encontradas:</strong> <?php echo number_format( $total_files ); ?><br>
    <strong>Peso Total de Imágenes:</strong> <?php echo size_format( $total_size_bytes ); ?><br>
    <strong>Imagenes para eliminar:</strong> <?php echo number_format( $to_delete_count ); ?><br>
    <strong>Espacio Imágenes Liberado:</strong> <?php echo size_format( $total_delete_size_bytes ); ?><br>
    <br>
    <strong>Miniaturas encontradas:</strong> <?php echo number_format( $thumbnail_count ); ?><br>
    <strong>Miniaturas para eliminar:</strong> <?php echo number_format( $thumbnail_to_delete_count ); ?><br>
    <strong>Espacio Miniaturas Liberado:</strong> <?php echo size_format( $total_delete_size_bytes_thumbnails ); ?><br>
</p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Ruta Relativa</th>
                <th>Nombre del Archivo</th>
                <th>Dimensiones</th>
                <?php
                // Función auxiliar para generar cabeceras ordenables
                getSortableImageTableHeader( 'size_bytes', 'Tamaño (KB)', $orderby, $order, $selected_folder, $status, $showMiniatures );
                getSortableImageTableHeader( 'attachment_id', 'Vinculado a Attachment', $orderby, $order, $selected_folder, $status, $showMiniatures );
                getSortableImageTableHeader( 'modified_date', 'Fecha de Modificación', $orderby, $order, $selected_folder, $status, $showMiniatures );
                ?>
                <th>URL</th>
            </tr>
        </thead>

        
        <tbody>
            <?php
            if ( empty( $all_images['all_images'] ) ) :
            ?>
                <tr>
                    <td colspan="7">No se encontraron imágenes en el directorio especificado.</td>
                </tr>
            <?php
            else :
                foreach ( $all_images['all_images'] as $image ) :
            ?>
                    <tr>
                        <td><?php echo esc_html( $image['relative_path'] ); ?></td>
                        <td><?php echo esc_html( $image['filename'] ); ?></td>
                        <td><?php echo esc_html( $image['dimensions'] );  echo $image['is_thumbnail'] == 1 ? ' (Thumbnail)' : '' ?>  </td>
                        <td><?php echo esc_html( number_format( $image['size_kb'], 2 ) ); ?></td>
                        <td>
                            <?php
                            if($image['to_delete'] !== false){
                                if($image['attachment_id']){    
                                    echo '<span class="dashicons dashicons-trash" style="color: red;"></span> ID: ' . esc_html( $image['attachment_id'] );						
                                }else{
                                    echo '<span class="dashicons dashicons-trash" style="color: red;"></span> No ID';						
                                }						
                            }else{
                                if ( $image['attachment_id'] ) {								
                                    echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ID: ' . esc_html( $image['attachment_id'] );
                                } else {
                                    echo '<span class="dashicons dashicons-yes-alt" style="color: orange;"></span> No ID';									
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $image['alt'] ); ?></td>
                        <td><a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank"><?php echo esc_url( $image['url'] ); ?></a></td>
                    </tr>
                    <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>

</div>






