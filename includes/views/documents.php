<?php

// Verificar permisos
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getAllDocuments.php';

// Obtener la carpeta seleccionada del parámetro GET o usar un valor por defecto    
$selected_folder = isset( $_GET['wpil_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['wpil_folder'] ) ) : sanitize_text_field( wp_unslash( date('Y').'/'. date('m') ) );
$selected_folder = trim( $selected_folder, '/' );

// Obtener los parámetros de ordenación
$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'size_bytes';
$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc'; // Por defecto descendente


// Obtener los documentos y las estadísticas
$documents = wpil_get_all_documents_in_uploads( $selected_folder, $orderby, $order);


global $wpdb; //para hacer consultas a la base de datos

$total_files      = count( $documents );
$total_size_bytes = array_sum( array_column( $documents, 'size_bytes' ) );

?>
<div class="wrap">
    <h1>Gestión y Optimización Documentos</h1>

    <form method="get" action="" id='form-filter'>
        <input type="hidden" name="page" value="documents" />
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
                    <p class="description">Introduce la subcarpeta dentro de `wp-content/uploads/` (ej: `2024/06` o `mis-documentos-api`). Déjalo vacío para listar todos las documentos.</p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-secondary" value="Mostrar Documentos">
            <?php if($total_files > 0) { ?>
                <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST para eliminar">
            <?php } ?>
        </p>
    </form>

    <hr>

    <h2>Listado de Documentos <?php echo ! empty( $selected_folder ) ? 'en: `' . esc_html( $selected_folder ) . '`' : ' (todas)'; ?></h2>

    <p>
        <strong>Archivos encontrados que se pueden eliminar:</strong> <?php echo number_format( $total_files ); ?><br>
        <strong>Peso Total:</strong> <?php echo size_format( $total_size_bytes ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Ruta Relativa</th>
                <th>Nombre del Archivo</th>
                <?php
                // Función auxiliar para generar cabeceras ordenables
                function wpil_get_sortable_header_docs( $column_id, $column_title, $current_orderby, $current_order, $selected_folder ) {
                    $new_order = ( $current_orderby === $column_id && $current_order === 'asc' ) ? 'desc' : 'asc';
                    $class = ( $current_orderby === $column_id ) ? 'sorted ' . $current_order : 'sortable ' . $new_order;
                    $query_args = array(
                        'page'      => 'documents',
                        'orderby'   => $column_id,
                        'order'     => $new_order, 
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
                wpil_get_sortable_header_docs( 'size_bytes', 'Tamaño (KB)', $orderby, $order, $selected_folder );
                wpil_get_sortable_header_docs( 'is_attachment', 'Vinculado a Attachment', $orderby, $order, $selected_folder );
                wpil_get_sortable_header_docs( 'modified_date', 'Fecha de Modificación', $orderby, $order, $selected_folder );
                ?>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ( empty( $documents ) ) :
                ?>
                <tr>
                    <td colspan="7">No se encontraron documentos en el directorio especificado.</td>
                </tr>
                <?php
            else :
                foreach ( $documents as $document ) :
                    ?>
                    <tr>
                        <td><?php echo esc_html( $document['relative_path'] ); ?></td>
                        <td><?php echo esc_html( $document['filename'] ); ?></td>
                        <td><?php echo esc_html( number_format( $document['size_kb'], 2 ) ); ?></td>
                        <td>
                            <?php
                            if ( $document['esta_en_uso'] ) {
                                if ( $document['is_attachment'] ) {
                                    echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Sí (ID: ' . esc_html( $document['attachment_id'] ) . ')';
                                } elseif ( $document['en_contenido'] ) {
                                    echo '<span class="dashicons dashicons-media-text" style="color: orange;"></span> En contenido';
                                } elseif ( $document['en_programa'] ) {
                                    echo '<span class="dashicons dashicons-welcome-learn-more" style="color: orange;"></span> En LearnPress';
                                } elseif ( $document['en_postmeta'] ) {
                                    echo '<span class="dashicons dashicons-admin-generic" style="color: orange;"></span> En postmeta';
                                } else {
                                    echo '<span class="dashicons dashicons-warning" style="color: orange;"></span> En uso (no identificado)';
                                }
                            } else {
                                echo '<span class="dashicons dashicons-trash" style="color: red;"></span> Se puede eliminar';
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $document['modified_date'] ); ?></td>
                        <td><a href="<?php echo esc_url( $document['url'] ); ?>" target="_blank"><?php echo esc_url( $document['url'] ); ?></a></td>
                    </tr>
                    <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>
</div>