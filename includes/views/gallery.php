<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getPaginatedImages.php';

getPaginatedImages();
?>

<h1>Galeria de Imágenes</h1>


<?php


$selected_folder            = isset( $_GET['folder'] ) ? sanitize_text_field( wp_unslash( $_GET['folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
$selected_folder            = trim( $selected_folder, '/' );

$page                       = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 1;
$per_page                   = isset( $_GET['per_page'] ) ? sanitize_text_field( wp_unslash( $_GET['per_page'] ) ) : 10;

?>


<div class="wrap">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <!-- <th>Ruta Relativa</th> -->
                <th>Nombre del Archivo</th>
                <th style="width: 100px;">Dimensiones</th>
                <th style="width: 100px;">Tamaño (KB)</th>
                <th style="width: 100px;">ID</th>
                <th>Title</th>
                <th>Alt</th>
                <th style="width: 100px;">Acciones</th>
            </tr>
        </thead>

        
        
    </table>
</div>