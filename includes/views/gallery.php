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
if($scan === '0'){
    $scan = null;
}
if($delete === '0'){
    $delete = null;
}
if($optimize === '0'){
    $optimize = null;
}

$image_data = getPaginatedImages($page, $per_page, $status, $folder, $scan, $delete, $optimize);

function getStatusStyle($status){
    $stylesForStatus = '';

    switch ($status) {
        case 'pendiente':
        $stylesForStatus = 'background-color: #FFBF00; color: #333333;';
        break;
    case 'por optimizar':
        $stylesForStatus = 'background-color: #00BFFF; color: #FFFFFF;';
        break;
    case 'optimizadas':
        $stylesForStatus = 'background-color: #2ECC71; color: #FFFFFF;';
        break;
    case 'eliminar':
        $stylesForStatus = 'background-color: #DC143C; color: #FFFFFF;';
        break;
    default:
        $stylesForStatus = 'background-color: #2ECC71; color: #2E7D32;';
        break;
    }
    return $stylesForStatus;
}

function getStatusIcon($status){
    $icon = '';
    switch ($status) {
        case 'pendiente':
        $icon = 'dashicons dashicons-warning';
        break;
    case 'por optimizar':
        $icon = 'dashicons dashicons-flag';
        break;
    case 'optimizadas':
        $icon = 'dashicons dashicons-yes';
        break;
    case 'eliminar':
        $icon = 'dashicons dashicons-trash';
        break;
    default:
        $icon = 'dashicons dashicons-yes';
        break;
    }
    return $icon;
}

function getIconSize($size){
    $size = intval($size);
    $iconSize = '';
    $colorSize = '';
    switch ($size ) {
        case $size < 400000 && $size > 0:
        $iconSize = 'dashicons dashicons-yes';
        $colorSize = 'color: #2ECC71;';
        break;
    case $size < 800000 && $size > 400000:
        $iconSize = 'dashicons dashicons-flag';
        $colorSize = 'color: #FFBF00;';
        break;
    case $size > 800000:
        $iconSize = 'dashicons dashicons-warning';
        $colorSize = 'color: #DC143C;';
        break;
    default:
        $iconSize = 'dashicons dashicons-no';
        $colorSize = 'color: #2ECC71;';
        break;
    }
    return array($iconSize, $colorSize);
}

function getIconAlt($alt){
    $iconSize = '';
    $colorSize = '';
    echo $alt;
    switch ($alt) {
        case empty($alt):
            $iconSize = 'dashicons dashicons-no-alt';
            $colorSize = 'color: #DC143C;';
            break;
        default:
            $iconSize = 'dashicons dashicons-yes';
            $colorSize = 'color: #2ECC71;';
            break;
    }
    return array($iconSize, $colorSize);
}

function getIconDimensions($width){
    $iconSize = '';
    $colorSize = '';
    switch ($width) {
        case $width < 1920 && $width > 0:
        $iconSize = 'dashicons dashicons-yes';
        $colorSize = 'color: #2ECC71;';
        break;
    case $width > 1920:
        $iconSize = 'dashicons dashicons-flag';
        $colorSize = 'color: #FFBF00;';
        break;
    default:
        $iconSize = 'dashicons dashicons-no';
        $colorSize = 'color: #2ECC71;';
        break;
    }
    return array($iconSize, $colorSize);
}

function getIconExtension($extension){
    $extension = str_replace('image/', '', $extension);
    $iconSize = '';
    $colorSize = '';

    switch ($extension) {
        case $extension == 'webp':
        $iconSize = 'dashicons dashicons-yes';
        $colorSize = 'color: #2ECC71;';
        break;

        default:
        $iconSize = 'dashicons dashicons-no-alt';
        $colorSize = 'color: #DC143C;';
        break;
    }
    return array($iconSize, $colorSize, $extension);
}

?>


<div class="wrap">

    <form id="filter-form" method="get" class="filter-container" action="">
        <div>
            <input type="hidden" name="page" value="gallery" />
            <input id="scan-input" type="hidden" name="scan" value="0">
            <input id="delete-input" type="hidden" name="delete" value="0">
            <input id="optimize-input" type="hidden" name="optimize" value="0">
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
        <button id="filter-btn" class="btn" type="submit">
            <span class="dashicons dashicons-filter"></span>
            Filtrar
        </button>
        <button id="scan-btn" class="btn" type="button">
            <span class="dashicons dashicons-search"></span>
            Escanear
        </button>
        <button hidden id="optimize-btn" class="btn" type="button">
            <span class="dashicons dashicons-dashboard"></span>
            Optimizar
        </button>
        <?php if($delete > 0): ?>
        <button id="delete-btn" class="btn delete-btn" type="button">
            <span class="dashicons dashicons-trash"></span>
            Eliminar
        </button>
        <?php endif; ?>
    </form>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th style="width: 30px;"><input type="checkbox" id="select-all-images"></th> <th style="width: 60px;">ID</th>
            <th>Título</th>
            <th style="width: 80px; text-align: center;">Extensión</th>
            <th style="width: 80px; text-align: center;">Tamaño (px)</th>
            <th style="width: 100px; text-align: center;">Peso (KB)</th>
            <th>slug</th>
            <th style="width: 60px; text-align: center;">Alt</th>
            <th style="width: 125px;">Estado</th>
            <th style="width: 100px;">Acciones</th>
        </tr>
    </thead>

    <tbody>
        <?php
        if ( empty( $image_data['records'] ) ) :
        ?>
            <tr>
                <td colspan="10">No se encontraron imágenes en el directorio especificado.</td> </tr>
        <?php
        else :
            foreach ( $image_data['records'] as $image ) :
        ?>
                <tr class="image-row">
                    <td><input type="checkbox" class="image-selector" value="<?php echo esc_attr( $image['attachment_id'] ); ?>"></td> <td><?php echo esc_html( $image['attachment_id'] ); ?></td>
                    <td><?php echo esc_html( $image['post_title'] ); ?></td>

                    <td style="text-align: center;">
                        <span
                            style="<?php echo esc_html( getIconExtension($image['post_mime_type'])[1] ); ?>"
                            class="<?php echo esc_html( getIconExtension($image['post_mime_type'])[0] ); ?>"
                        ></span>
                        <?php echo esc_html( str_replace('image/', '', $image['post_mime_type']) ); ?>
                    </td>

                    <td style="text-align: center;">
                        <span
                            style="<?php echo esc_html( getIconDimensions($image['image_width'])[1] ); ?>"
                            class="<?php echo esc_html( getIconDimensions($image['image_width'])[0] ); ?>"
                        ></span>
                        <?php echo esc_html( $image['image_width'] ); ?> x <?php echo esc_html( $image['image_height'] ); ?>
                    </td>

                    <td style="text-align: center;">
                        <span
                            style="<?php echo esc_html( getIconSize($image['image_filesize'])[1] ); ?>"
                            class="<?php echo esc_html( getIconSize($image['image_filesize'])[0] ); ?>"
                        ></span>
                        <?php echo esc_html( number_format(($image['image_filesize'] / 1024), 0) );?>KB
                    </td>

                    <td><?php echo esc_html( $image['file_path_relative'] ); ?></td>

                    <td style="text-align: center;">
                        <span
                            style="<?php echo esc_html( getIconAlt($image['image_alt_text'])[1] ); ?>"
                            class="<?php echo esc_html( getIconAlt($image['image_alt_text'])[0] ); ?>"
                        ></span>
                    </td>

                    <td style="text-align: center; <?php echo getStatusStyle($image['optimization_status']); ?>">
                        <span class="dashicons <?php echo getStatusIcon($image['optimization_status']); ?>"></span>
                        <?php echo esc_html( ucwords($image['optimization_status']) ); ?>
                    </td>
                    <td style="text-align: center;">
                        <span
                            style="cursor: pointer;"
                            class="edit-attachment-trigger dashicons dashicons-edit"
                            data-attachment-id="<?php echo esc_attr( $image['attachment_id'] ); ?>"
                            data-attachment-title="<?php echo esc_attr( $image['post_title'] ); ?>" data-attachment-alt="<?php echo esc_attr( $image['image_alt_text'] ); ?>" data-attachment-description="<?php echo esc_attr( $image['post_content'] ); ?>" data-attachment-slug="<?php echo esc_attr( basename($image['file_path_relative']) ); ?>" data-attachment-size="<?php echo esc_attr( $image['image_width'] . 'x' . $image['image_height'] ); ?>" data-attachment-url="<?php echo esc_attr( $image['guid'] ); ?>" ></span>
                        <?php if ( !empty( $image['usage'] ) ) : // Cambié $image['usage'] a !empty($image['usage']) para verificar si está siendo usado ?>
                            <span class="dashicons dashicons-trash"></span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $image['guid'] ); ?>" target="_blank"> <span class="dashicons dashicons-visibility"></span>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const scanBtn = document.getElementById('scan-btn');
        const deleteBtn = document.getElementById('delete-btn');
        const optimizeBtn = document.getElementById('optimize-btn');
        const filterForm = document.getElementById('filter-form');

        scanBtn.addEventListener('click', function() {
            document.getElementById('scan-input').value = '1';
            filterForm.submit();
        });
        deleteBtn.addEventListener('click', function() {
            document.getElementById('delete-input').value = '1';
            filterForm.submit();
        });
        optimizeBtn.addEventListener('click', function() {
            document.getElementById('optimize-input').value = '1';
            filterForm.submit();
        });
    });


</script>

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
    .image-row {
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .image-row:hover {
        background-color:rgb(235, 235, 235) !important;
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