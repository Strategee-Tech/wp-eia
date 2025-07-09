<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getPaginatedImages.php';

?>

<h1>Galeria de Imágenes</h1>


<?php

$page                       = isset( $_GET['pagination'] ) ? sanitize_text_field( wp_unslash( $_GET['pagination'] ) ) : 1;
$per_page                   = isset( $_GET['per_page'] ) ? sanitize_text_field( wp_unslash( $_GET['per_page'] ) ) : 20;

$status                     = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null;
$year                       = isset( $_GET['year'] ) ? sanitize_text_field( wp_unslash( $_GET['year'] ) ) : null;
$month                      = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : null;

$scan                       = isset( $_GET['scan'] ) ? sanitize_text_field( wp_unslash( $_GET['scan'] ) ) : null;
$delete                     = isset( $_GET['delete'] ) ? sanitize_text_field( wp_unslash( $_GET['delete'] ) ) : null;
$optimize                   = isset( $_GET['optimize'] ) ? sanitize_text_field( wp_unslash( $_GET['optimize'] ) ) : null;


$folder = null;
if($month !== 'all' && $year !== 'all'){
    $folder = $year . '/' . $month;
} else if($month !== 'all' && $year === 'all'){
    $folder = '/' . $month;
} else if($month === 'all' && $year !== 'all'){
    $folder = $year . '/';
}
if($status === 'all'){
    $status = null;
}
if($scan === '1'){
    $scan = null;
}
if($delete === '1'){
    $delete = null;
}
if($optimize === '1'){
    $optimize = null;
}

$image_data = getPaginatedImages($page, $per_page, $status, $folder, $scan, $delete, $optimize);

?>


<div class="wrap">

    <form method="get" class="filter-container" action="">
        <div>
            <input type="hidden" name="page" value="gallery" />
            <div>
                <lsabel for="status">Estado de Optimización</lsabel>
                <select name="status" id="">
                    <option <?php echo $status === 'all' ? 'selected' : ''; ?> value="all">Todos</option>
                    <option <?php echo $status === 'pendiente' ? 'selected' : ''; ?> value="pendiente">Pendientes</option>
                    <option <?php echo $status === 'por optimizar' ? 'selected' : ''; ?> value="por optimizar">Por optimizar</option>
                    <option <?php echo $status === 'optimizadas' ? 'selected' : ''; ?> value="optimizadas">Optimizadas</option>
                    <option <?php echo $status === 'eliminar' ? 'selected' : ''; ?> value="eliminar">Eliminar</option>
                </select>
            </div>
            <div>
                <label for="year">Año</label>
                <select name="year" id="">
                    <option value="all">Todos</option>
                    <option <?php echo $year === '2025' ? 'selected' : ''; ?> value="2025">2025</option>
                    <option <?php echo $year === '2024' ? 'selected' : ''; ?> value="2024">2024</option>
                    <option <?php echo $year === '2023' ? 'selected' : ''; ?> value="2023">2023</option>
                    <option <?php echo $year === '2022' ? 'selected' : ''; ?> value="2022">2022</option>
                    <option <?php echo $year === '2021' ? 'selected' : ''; ?> value="2021">2021</option>
                    <option <?php echo $year === '2020' ? 'selected' : ''; ?> value="2020">2020</option>
                </select>
            </div>
            <div>
                <label for="month">Mes</label>
                <select name="month" id="">
                    <option value="all">Todos</option>
                    <option <?php echo $month === '01' ? 'selected' : ''; ?> value="01">01</option>
                    <option <?php echo $month === '02' ? 'selected' : ''; ?> value="02">02</option>
                    <option <?php echo $month === '03' ? 'selected' : ''; ?> value="03">03</option>
                    <option <?php echo $month === '04' ? 'selected' : ''; ?> value="04">04</option>
                    <option <?php echo $month === '05' ? 'selected' : ''; ?> value="05">05</option>
                    <option <?php echo $month === '06' ? 'selected' : ''; ?> value="06">06</option>
                    <option <?php echo $month === '07' ? 'selected' : ''; ?> value="07">07</option>
                    <option <?php echo $month === '08' ? 'selected' : ''; ?> value="08">08</option>
                    <option <?php echo $month === '09' ? 'selected' : ''; ?> value="09">09</option>
                    <option <?php echo $month === '10' ? 'selected' : ''; ?> value="10">10</option>
                    <option <?php echo $month === '11' ? 'selected' : ''; ?> value="11">11</option>
                    <option <?php echo $month === '12' ? 'selected' : ''; ?> value="12">12</option>
                </select>
            </div>
        </div>
        <div style='flex-grow: 1;'></div>
        <button class="btn" type="submit">
            <span class="dashicons dashicons-filter"></span>
            Filtrar
        </button>
        <button class="btn" type="button">
            <span class="dashicons dashicons-search"></span>
            Escanear
        </button>
        <button class="btn" type="button">
            <span class="dashicons dashicons-dashboard"></span>
            Optimizar
        </button>
        <button class="btn delete-btn" type="button">
            <span class="dashicons dashicons-trash"></span>
            Eliminar
        </button>
    </form>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <!-- <th>Ruta Relativa</th> -->
                <th style="width: 60px;">ID</th>
                <th>Título</th>
                <th>slug</th>
                <th>Alt</th>
                <th style="width: 100px;">Estado</th>
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
                        <td><?php echo esc_html( $image['file_path_relative'] ); ?></td>
                        <td><?php echo esc_html( $image['image_alt_text'] ); ?></td>
                        <td>
                            <?php echo esc_html( $image['optimization_status'] ); ?>
                        </td>
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
                                data-attachment-url="<?php echo esc_attr( $image['attachment_url'] ); ?>"
                            ></span>
                            <?php if ( $image['usage'] ) : ?>
                                <span class="dashicons dashicons-trash"></span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url( $image['attachment_url'] ); ?>" target="_blank">
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
                    $first_page_url = add_query_arg( 'pagination', 1, remove_query_arg( 's' ) ); // remove_query_arg('s') para limpiar si hay búsqueda
                    if ( $image_data['current_page'] > 1 ) {
                        echo '<a class="first-page button" href="' . esc_url( $first_page_url ) . '">&laquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>';
                    }

                    // Enlace a la página anterior
                    $prev_page_url = add_query_arg( 'pagination', $image_data['prev_page'], remove_query_arg( 's' ) );
                    if ( $image_data['prev_page'] !== null ) {
                        echo '<a class="prev-page button" href="' . esc_url( $prev_page_url ) . '">&lsaquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>';
                    }
                    ?>
                    <span class="paging-input">
                        <label for="current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Página actual', 'tu-textdomain' ); ?></label>
                        <input class="current-page" id="current-page-selector" type="text" name="page" value="<?php echo esc_attr( $image_data['current_page'] ); ?>" size="1" aria-describedby="table-paging">
                        <span class="tablenav-paging-text"> de <span class="total-pages"><?php echo esc_html( $image_data['total_pages'] ); ?></span></span>
                    </span>
                    <?php
                    // Enlace a la página siguiente
                    $next_page_url = add_query_arg( 'pagination', $image_data['next_page'], remove_query_arg( 's' ) );
                    if ( $image_data['next_page'] !== null ) {
                        echo '<a class="next-page button" href="' . esc_url( $next_page_url ) . '">&rsaquo;</a>';
                    } else {
                        echo '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>';
                    }

                    // Enlace a la última página
                    $last_page_url = add_query_arg( 'pagination', $image_data['total_pages'], remove_query_arg( 's' ) );
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

<style>
    .filter-container {
        display: flex;
        margin-bottom: 10px;
        gap: 8px;
    }

    .filter-container > div {
        display: flex;
        gap: 10px;
    }
    .filter-container > div > div {
        display: flex;
        flex-direction: column;
    }
    .filter-container > div > label {
        margin-bottom: 5px;
        font-size: 12px;
    }

    .btn {

        display: flex;
        align-items: center;
        gap: 2px;
        flex-direction: column;
    }

    .delete-btn {
        background-color:rgb(185, 34, 34);
        transition: background-color 0.3s ease;
        color: #fff;
        padding: 3px 10px;
    }  
    .delete-btn:hover {
        background-color:rgb(170, 36, 36);
    }
</style>

<?php
echo '$folder: ' . $folder; 
echo '<br>';
echo '$status: ' . $status;
echo '<br>';
echo '$scan: ' . $scan;
echo '<br>';
echo '$delete: ' . $delete;
echo '<br>';
echo '$optimize: ' . $optimize;
echo '<br>';
echo '<pre>';
print_r($image_data);
echo '</pre>';
?>  