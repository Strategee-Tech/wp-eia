<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getAllImages.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getSortableImageHeader.php';

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


$total_files      = count( $all_images );
$total_size_bytes = array_sum( array_column( $all_images, 'size_bytes' ) );

?>

<form method="get" action="" id='form-filter'>
    <input type="hidden" name="page" value="wpil-image-lister" />
    <?php if ( ! empty( $orderby ) ) : ?>
        <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
    <?php endif; ?>
    <?php if ( ! empty( $order ) ) : ?>
        <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="wpil_folder">Carpeta a Mostrar:</label></th>
            <td>
                <input type="text" id="wpil_folder" name="wpil_folder" value="<?php echo esc_attr( $selected_folder ); ?>" class="regular-text" placeholder="Ej: 2024/06 o mis-imagenes-api" />
                <p class="description">Introduce la subcarpeta dentro de `wp-content/uploads/` (ej: `2024/06` o `mis-imagenes-api`). Déjalo vacío para listar todas las imágenes.</p>
            </td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" name="submit" id="submit" class="button button-secondary" value="Mostrar Imágenes">
        <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST">
        <input type="button" id="export_csv_button" class="button button-primary" value="Descargar CSV">
        
        <?php if(!$status && $showMiniatures): ?>
            <input type="button" id="btn-delete-all" class="button button-primary" value="Eliminar Todos">
        <?php endif; ?>

        <label>
            <input type="checkbox" name='status' id="check-status" value="1" <?php echo $status == true ? '' : 'checked' ?> >
            Mostrar solo sin attachment
        </label>
    
        <label>
            <input type="checkbox" name='miniatures' id="check-miniatures" value="1" <?php echo $showMiniatures == true ? 'checked' : '' ?> >
            Mostrar Miniaturas
        </label>
    </p>
</form>

<hr>

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
                getSortableImageTableHeader( 'size_bytes', 'Tamaño (KB)', $orderby, $order, $selected_folder, $status, $showMiniatures );
                getSortableImageTableHeader( 'is_attachment', 'Vinculado a Attachment', $orderby, $order, $selected_folder, $status, $showMiniatures );
                getSortableImageTableHeader( 'modified_date', 'Fecha de Modificación', $orderby, $order, $selected_folder, $status, $showMiniatures );
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






