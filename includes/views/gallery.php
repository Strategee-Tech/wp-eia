<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getPaginatedImages.php';

?>

<h1>Galeria de Imágenes</h1>


<?php


$selected_folder            = isset( $_GET['folder'] ) ? sanitize_text_field( wp_unslash( $_GET['folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
$selected_folder            = trim( $selected_folder, '/' );

$page                       = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 1;
$per_page                   = isset( $_GET['per_page'] ) ? sanitize_text_field( wp_unslash( $_GET['per_page'] ) ) : 10;

$images = getPaginatedImages($selected_folder, $page, $per_page);

?>


<div class="wrap">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <!-- <th>Ruta Relativa</th> -->
                <th style="width: 60px;">ID</th>
                <th>Título</th>
                <th>slug</th>
                <th>Alt</th>
                <th style="width: 100px;">Acciones</th>
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
                        <!-- <td><?php echo esc_html( $image['relative_path'] ); ?></td> -->
                        <td>
                            <?php echo esc_html( $image['filename'] ); ?>
                        </td>
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
                        <td><?php echo esc_html( $image['title'] ); ?></td>
                        <td><?php echo esc_html( $image['alt'] ); ?></td>
                        <td>
                            <span 
                                style="cursor: pointer;"
                                class="edit-attachment-trigger dashicons dashicons-edit"
                                data-attachment-id="<?php echo esc_attr( $image['attachment_id'] ); ?>"
                                data-attachment-title="<?php echo esc_attr( $image['title'] ); ?>"
                                data-attachment-alt="<?php echo esc_attr( $image['alt'] ); ?>"
                                data-attachment-description="<?php echo esc_attr( $image['description'] ); ?>"
                                data-attachment-slug="<?php echo esc_attr( $image['filename'] ); ?>"
                                data-attachment-size="<?php echo esc_attr( $image['dimensions'] ); ?>"
                                data-attachment-url="<?php echo esc_attr( $image['url'] ); ?>"
                            ></span>
                            <a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank">
                                <span class="dashicons dashicons-visibility"></span>
                            </a>
                        </td>
                    </tr>
                    <?php   
                endforeach;
            endif;
            ?>
        </tbody>

        
        
    </table>
</div>