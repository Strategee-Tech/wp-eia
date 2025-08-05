<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getPaginatedImages.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/imageNames.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/filePagination.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/helpers/getAttachmentPage.php';


wp_enqueue_script(
    'delete-files', // Handle o identificador único
    plugins_url( '../assets/js/delete-files.js', __FILE__ ), // URL del script dentro de tu plugin
    array(), // Dependencias (ninguna en este caso)
    '1.0.0', // Versión
    true // Cargar en el footer
);
wp_enqueue_script(
    'scan-service', // Handle o identificador único
    plugins_url( '../assets/js/scanService.js', __FILE__ ), // URL del script dentro de tu plugin
    array(), // Dependencias (ninguna en este caso)
    '1.0.0', // Versión
    true // Cargar en el footer
);

wp_enqueue_script(
    'geminiService',
    plugins_url( '../assets/js/geminiService.js', __FILE__ ),
    array(),
    '1.0.0',
    true
);

wp_enqueue_script(
    'modal-edit',
    plugins_url( '../assets/js/modal-edit.js', __FILE__ ),
    array(),
    '1.0.3',
    true
);

wp_enqueue_style(
    'gallery-view',
    plugins_url( '../assets/css/gallery-view.css', __FILE__ ),
    array(),
    '1.0.0'
);

wp_enqueue_script(
    'init-scan',
    plugins_url( '../assets/js/init-scan.js', __FILE__ ),
    array(),
    '1.0.0',
    true
);

wp_enqueue_style(
    'loader',
    plugins_url( '../assets/css/loader.css', __FILE__ ),
    array(),
    '1.0.0'
);

// Obtener credenciales desde wp_options
$credentials = array(
    'user_auth' => get_option('user_auth'),
    'pass_auth' => get_option('pass_auth'),
);

wp_localize_script('delete-files', 'credentials', $credentials);
wp_localize_script('init-scan', 'credentials', $credentials);
wp_localize_script('modal-edit', 'credentials', $credentials);
wp_localize_script('geminiService', 'credentials', $credentials);
wp_localize_script('scan-service', 'credentials', $credentials);

?>

<div style="display: flex; align-items: center; justify-content: space-between; padding: 20px; padding-left: 0;">
    <img src="<?php echo esc_url(WP_EIA_PLUGIN_URL . 'includes/assets/images/stg_optimizer.png'); ?>" alt="" style="width: 250px;">
    <img src="<?php echo esc_url(WP_EIA_PLUGIN_URL . 'includes/assets/images/by-stg.png'); ?>" alt="" style="width: 100px;">
</div>


<?php

$page         = isset( $_GET['pagination'] ) ? sanitize_text_field( wp_unslash( $_GET['pagination'] ) ) : 1;
$per_page     = isset( $_GET['per_page'] ) ? sanitize_text_field( wp_unslash( $_GET['per_page'] ) ) : 20;

$mime_type    = isset( $_GET['mime_type'] ) ? sanitize_text_field( wp_unslash( $_GET['mime_type'] ) ) : null;
$mime_type    = $mime_type === 'all' ? null : $mime_type;

$usage_status = isset( $_GET['usage_status'] ) ? sanitize_text_field( wp_unslash( $_GET['usage_status'] ) ) : null;
$usage_status = $usage_status === 'all' ? null : $usage_status;


$search       = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : null;

$year         = isset( $_GET['year'] ) ? sanitize_text_field( wp_unslash( $_GET['year'] ) ) : null;
$month        = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : null;


$folder = null;

if($month !== null && $year !== null){
    if($month !== 'all' && $year !== 'all'){
        $folder = $year . '/' . $month;
    } else if($month !== 'all' && $year === 'all'){
        $folder = '/' . $month;
    } else if($month === 'all' && $year !== 'all'){
        $folder = $year . '/';
    }
}

$image_data = getAttachmentPage($page, $per_page, $folder, $mime_type, $search, $usage_status);

function getIconAlt($alt){
    $iconSize = '';
    $colorSize = '';
    if(empty($alt) || $alt == null || $alt == ''){
        $iconSize = 'dashicons dashicons-no-alt';
        $colorSize = 'color: #DC143C;';
    } else {
        $iconSize = 'dashicons dashicons-yes';
        $colorSize = 'color: #2ECC71;';
    }
    return array($iconSize, $colorSize);    
}
?>

<div class="wrap">

    <div id="options-container" class="options-container">

        <form id="filter-form" method="get" class="filter-container" action="">
            
            <div>
                <input type="hidden" name="page" value="stg-optimizer" />
                <input id="scan-input" type="hidden" name="scan" value="0">
                <input id="delete-input" type="hidden" name="delete" value="0">
                <input id="optimize-input" type="hidden" name="optimize" value="0">
                <div>
                    <label for="search">Buscar</label>
                    <input type="search" name="search" id="search" placeholder="Buscar..." value="<?php echo esc_attr( $search ); ?>">
                </div>
                <div>
                    <label for="mime_type">Tipo de archivo</label>
                    <select name="mime_type" id="">
                        <option <?php echo $mime_type === 'all' ? 'selected' : ''; ?> value="all">Todos</option>
                        <option <?php echo $mime_type === 'image' ? 'selected' : ''; ?> value="image">Imagenes</option>
                        <option <?php echo $mime_type === 'audio' ? 'selected' : ''; ?> value="audio">Audios</option>
                        <option <?php echo $mime_type === 'video' ? 'selected' : ''; ?> value="video">Videos</option>
                        <option <?php echo $mime_type === 'text' ? 'selected' : ''; ?> value="text">Textos</option>
                        <option <?php echo $mime_type === 'application' ? 'selected' : ''; ?> value="application">Documentos</option>
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
                <div>
                    <label for="month">Estado</label>
                    <select name="usage_status" id="">
                        <option value="all">Todos</option>
                        <option <?php echo $usage_status === 'in_use' ? 'selected' : ''; ?> value="in_use">En Uso</option>
                        <option <?php echo $usage_status === 'not_in_use' ? 'selected' : ''; ?> value="not_in_use">Sin Uso</option>
                        <option <?php echo $usage_status === 'has_alt' ? 'selected' : ''; ?> value="has_alt">Con Alt</option>
                        <option <?php echo $usage_status === 'no_alt' ? 'selected' : ''; ?> value="no_alt">Sin Alt</option>
                        <option <?php echo $usage_status === 'scanned' ? 'selected' : ''; ?> value="scanned">Escaneado</option>
                        <option <?php echo $usage_status === 'unscanned' ? 'selected' : ''; ?> value="unscanned">Sin Escanear</option>
                        <option <?php echo $usage_status === 'blocked' ? 'selected' : ''; ?> value="blocked">Excluidos</option>
                        <option <?php echo $usage_status === 'not_blocked' ? 'selected' : ''; ?> value="not_blocked">Sin Excluir</option>
                    </select>
                </div>
            </div>

            <div style="flex-grow: 1;"></div>

            <button id="filter-btn" style='width: 250px; flex-direction: row; gap: 5px;' class="btn" type="submit">
                <span class="dashicons dashicons-filter"></span>
                Filtrar
            </button>

        </form>

        <div id="options-container" style="display: flex; align-items: center; justify-content: space-between;">
            <span id="scan-progress">
                
            </span>
            
            <div style="width: 250px; display: flex; align-items: center; gap: 10px;">
                <button id="scan-all-btn" class="btn primary-btn" type="button" style="display: flex; flex-direction: row; gap: 5px;"> 
                    <div id="spinner-loader" style="display: none;" class="spinner-loader"></div>
                    <span id="icon-scan">Escanear Todos</span>
                </button>
                <button id="delete-all-btn" class="btn delete-btn" type="button" style="display: flex; flex-direction: row; gap: 5px; "> 
                    <div id="spinner-loader" style="display: none;" class="spinner-loader"></div>
                    <span id='icon-delete'>Eliminar Todos</span>
                </button>
            </div>
        </div>


    </div>

    


    <table class="wp-list-table widefat fixed striped stg-table">
        <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="select-all-images"></th> <th style="width: 60px;">ID</th>
                <th>Título</th>
                <th style="width: 50px; text-align: center;">Ext.</th>
                <th style="width: 100px; text-align: center;">Tamaño (px)</th>
                <th style="width: 100px; text-align: center;">Peso (KB)</th>
                <th>slug</th>
                <th style="width: 60px; text-align: center;">Alt</th>
                <th style="width: 125px; text-align: center;">Estado</th>
            </tr>
        </thead>

        <tbody>
            <?php
            if ( empty( $image_data['records'] ) ) :
            ?>
                <tr>
                    <td colspan="9">No se encontraron archivos en el directorio especificado.</td> 
                </tr>
            <?php
            else :
                foreach ( $image_data['records'] as $image ) :
            ?>
                    <tr 
                        class="image-row edit-attachment-trigger" 
                        data-attachment-id="<?php echo esc_attr( $image['attachment_id'] ); ?>"
                        data-attachment-url="<?php echo esc_attr( $image['attachment_url'] ); ?>"
                        data-attachment-path="<?php echo esc_attr( $image['file_path_relative'] ); ?>"
                        data-attachment-name="<?php echo esc_attr( $image['post_name'] ); ?>"
                        data-attachment-title="<?php echo esc_attr( $image['post_title'] ); ?>"
                        data-attachment-alt="<?php echo esc_attr( $image['image_alt_text'] ); ?>"
                        data-attachment-description="<?php echo esc_attr( $image['file_description'] ); ?>"
                        data-attachment-dimensions="<?php if(isset($image['image_width']) && $image['image_width'] > 0): echo esc_attr( $image['image_width'] . ' x ' . $image['image_height'] ); else: null; endif; ?>"
                    >
                        <td><input type="checkbox" class="image-selector" value="<?php echo esc_attr( $image['attachment_id'] ); ?>"></td> <td><?php echo esc_html( $image['attachment_id'] ); ?></td>
                        <td><?php echo esc_html( $image['post_title'] ); ?></td>

                        <td style="text-align: center;">
                            <?php echo getFileExtensionFromUrl($image['attachment_url']); ?>
                        </td>

                        <td style="text-align: center;">
                            <?php if(isset($image['image_width'])): ?>
                                <?php echo esc_html( $image['image_width'] ); ?> x <?php echo esc_html( $image['image_height'] ); ?>
                            <?php endif; ?>
                        </td>

                        <td style="text-align: center;">
                            <?php echo esc_html( number_format(($image['file_filesize'] / 1024), 0) );?>KB
                        </td>

                        <td><?php echo esc_html( $image['file_path_relative'] ); ?></td>

                        <td style="text-align: center;">
                            <span
                                style="<?php echo esc_html( getIconAlt($image['image_alt_text'])[1] ); ?>"
                                class="<?php echo esc_html( getIconAlt($image['image_alt_text'])[0] ); ?>"
                            ></span>
                        </td>

                        <td style="text-align: center; <?php if($image['is_in_use'] == 1): ?>background-color: rgba(0,150,64,0.3);<?php else: ?>background-color: rgba(255,54,0,0.3);<?php endif; ?>">
                            <?php echo esc_html( $image['is_in_use'] == 1 ? 'En Uso' : 'Sin Uso' ); ?>
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

<div id="edit-metadata-modal" style="display: none; background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <form id="edit-metadata-form">
            <div style="display: flex; flex-direction: column; justify-content: space-between; align-items: center; gap: 10px;">

                <a id="file-link" href="#" target="_blank">
                    <img id="modal-image" src="" alt="" style="background:gray; width: 100%; max-width: 300px; max-height: 300px; object-fit: contain;">
                    <span id="file-link-text">Ver archivo</span>
                </a>

                <div style="flex-grow: 1; width: 100%;">
                    <input type="hidden" id="input-width" name="width" value="0"/>
                    <div>
                        <label id="modal-slug-label" for="modal-slug">Slug:</label><br>
                        <input type="text" id="modal-slug" name="slug" style="width: 100%;" />
                    </div>
                    <div>
                        <label for="modal-title">Título:</label><br>
                        <input type="text" id="modal-title" name="title" style="width: 100%;" />
                    </div>
                    <div>
                        <label for="modal-alt">Texto Alternativo (Alt):</label><br>
                        <input type="text" id="modal-alt" name="alt" style="width: 100%;" />
                    </div>
                    <div>
                        <label for="modal-description">Descripción:</label><br>
                        <textarea id="modal-description" name="description" rows="5" style="width: 100%;"></textarea>
                    </div>
                    <input type="hidden" id="modal-url" name="url" />
                </div>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 10px; margin-bottom: 10px;">
                <button id="scan-resource-btn" class="button" type="button" style="display: flex; align-items: center; gap: 5px;">
                    <div id="scan-loader" class="loader"></div> 
                    Escanear recurso
                </button>
            </div>

            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                <input type="checkbox" id="modal-fast-edit" name="fast_edit" checked/>
                <label for="modal-fast-edit">Edición rápida</label>
            </div>
            <div style="text-align: right; font-size: 12px; margin-bottom: 10px; margin-top: 2px;">
                <small id="fast_edit_option">Si se selecciona la opción de edicion rápida, solo se actualizará el texto alternativo (alt), título y descripción de la imagen.</small>
                <small style="display: none;" id="complete_edit">Se actualizará el texto alternativo (alt), título, descripción y se optimizará la imagen. Esto puede tardar unos minutos.</small>
            </div>
            
            <span id="save-status-message" style="margin-left: 10px;"></span>
            <div class="modal-footer">
                <button type="button" id="regenerate-alt-btn" class="button">
                    <svg id="gemini-icon" data-name="Capa 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 121.24 121.23">
                        <path class="cls-1" d="M121.24,60.61c-33.48,0-60.62,27.14-60.62,60.62,0-33.48-27.14-60.62-60.62-60.62,33.48,0,60.62-27.14,60.62-60.61,0,33.47,27.14,60.61,60.62,60.61Z"/>
                    </svg>
                    <div id="loader" class="loader"></div> 
                    <span id="regenerate-alt-text">Generar con IA</span>
                </button>

                <!-- <button type="button" style="display: none; align-items: center; gap: 5px;" id="delete-btn" class="btn delete-btn">
                    <span class="dashicons dashicons-trash"></span>
                    Eliminar
                </button> -->


                <div style="flex-grow: 1;"></div>
                <button type="button" id="cancel-metadata-btn" class="button">Cancelar</button>
                <button type="submit" id="save-metadata-btn" class="button button-primary">Guardar Cambios</button>
                
            </div>
        </form>
    </div>
</div>


<?php

wp_localize_script(
    'delete-files', // El "handle" del script al que adjuntarás los datos
    'filesToDelete', // El nombre del objeto JavaScript que se creará
    array($image_data['files_to_delete'],[]) // El array PHP con los datos
);

wp_localize_script(
    'init-scan', // El "handle" del script al que adjuntarás los datos
    'totalRecords', // El nombre del objeto JavaScript que se creará
    $image_data['total_records'] // El array PHP con los datos
);

    // echo '$folder: ' . $folder; 
    // echo '<br>';
    // echo '<pre>' . htmlspecialchars(print_r($image_data, true)) . '</pre>';
?>  