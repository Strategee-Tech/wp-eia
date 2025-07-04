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
    <h2>Imágenes</h2>

</div>






