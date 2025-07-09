<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getPaginatedImages.php';

?>

<h1>Galeria de Imágenes</h1>


<?php

$page                       = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 1;
$per_page                   = isset( $_GET['per_page'] ) ? sanitize_text_field( wp_unslash( $_GET['per_page'] ) ) : 20;

$image_data = getPaginatedImages($page, $per_page);

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
            if ( empty( $image_data['records'] ) ) :
            ?>
                <tr>
                    <td colspan="7">No se encontraron imágenes en el directorio especificado.</td>
                </tr>
            <?php
            else :
                foreach ( $image_data['records'] as $image ) :
            ?>
                    <tr>
                        <!-- <td><?php echo esc_html( $image['relative_path'] ); ?></td> -->
                        <td><?php echo esc_html( $image['attachment_id'] ); ?></td>
                        <td><?php echo esc_html( $image['post_title'] ); ?></td>
                        <td><?php echo esc_html( $image['post_name'] ); ?></td>
                        <td><?php echo esc_html( $image['image_alt_text'] ); ?></td>
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


    <?php if ( $image_data['total_pages'] > 1 ) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php printf( esc_html__( '%d elementos', 'tu-textdomain' ), $image_data['total_records'] ); ?></span>
                <span class="pagination-links">
                    <?php
                    // Enlace a la primera página
                    $first_page_url = add_query_arg( 'paged', 1, remove_query_arg( 's' ) ); // remove_query_arg('s') para limpiar si hay búsqueda
                    if ( $image_data['current_page'] > 1 ) {
                        echo '<a class="first-page button" href="' . esc_url( $first_page_url ) . '">&laquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                    }

                    // Enlace a la página anterior
                    $prev_page_url = add_query_arg( 'paged', $image_data['prev_page'], remove_query_arg( 's' ) );
                    if ( $image_data['prev_page'] !== null ) {
                        echo '<a class="prev-page button" href="' . esc_url( $prev_page_url ) . '">&lsaquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
                    }
                    ?>
                    <span class="paging-input">
                        <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Página actual', 'tu-textdomain' ); ?></label>
                        <input class="current-page" id="current-page-selector" type="text" name="paged" value="<?php echo esc_attr( $image_data['current_page'] ); ?>" size="1" aria-describedby="table-paging">
                        <span class="tablenav-paging-text"> de <span class="total-pages"><?php echo esc_html( $image_data['total_pages'] ); ?></span></span>
                    </span>
                    <?php
                    // Enlace a la página siguiente
                    $next_page_url = add_query_arg( 'paged', $image_data['next_page'], remove_query_arg( 's' ) );
                    if ( $image_data['next_page'] !== null ) {
                        echo '<a class="next-page button" href="' . esc_url( $next_page_url ) . '">&rsaquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
                    }

                    // Enlace a la última página
                    $last_page_url = add_query_arg( 'paged', $image_data['total_pages'], remove_query_arg( 's' ) );
                    if ( $image_data['current_page'] < $image_data['total_pages'] ) {
                        echo '<a class="last-page button" href="' . esc_url( $last_page_url ) . '">&raquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
    <?php endif; ?>






</div>

<?php
echo '<pre>';
print_r($image_data);
echo '</pre>';
?>  