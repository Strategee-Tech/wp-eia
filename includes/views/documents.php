<?php

// Verificar permisos
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}
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
            <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST para eliminar">
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
                            if ( $document['is_attachment'] ) {								
                                echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> Sí (ID: ' . esc_html( $document['attachment_id'] ) . ')';
                            } else {
								if( !$document['en_contenido'] ) {
                                	echo '<span class="dashicons dashicons-no-alt" style="color: red;"></span> No Content';									
								} else {
                                	echo '<span class="dashicons dashicons-no-alt" style="color: green;"></span> En contenido';									
								}
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
<script>
    // --- SCRIPT JAVASCRIPT PARA ENVIAR LAS URLS POR POST DIRECTAMENTE A UN ENDPOINT EXTERNO ---
    document.getElementById('send_urls_button').addEventListener('click', function() {
        var button = this;
        button.value = 'Enviando...';
        button.disabled = true;

        var folder = document.getElementById('wpil_folder').value;
        var orderbyInput = document.querySelector('input[name="orderby"]');
        var orderInput = document.querySelector('input[name="order"]');

        var orderby = orderbyInput ? orderbyInput.value : '';
        var order = orderInput ? orderInput.value : 'asc';

        var urls = [];
        var urlCells = document.querySelectorAll('table.wp-list-table tbody tr td:last-child a');
        urlCells.forEach(function(cell) {
            urls.push(cell.href);
        });

        var dataToSend = {
            source: 'wp-image-lister-plugin',
            folder_selected: folder,
            current_orderby: orderby,
            current_order: order,
            image_urls: urls,
            total_images: urls.length,
            timestamp: new Date().toISOString()
        };

        // !!! IMPORTANTE: REEMPLAZA ESTA URL CON LA DE TU SERVICIO EXTERNO REAL !!!
        var externalEndpointUrl = 'https://intimate-ape-readily.ngrok-free.app/api/prueba';

        fetch(externalEndpointUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(dataToSend)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response from external endpoint was not ok. Status: ' + response.status + ' ' + response.statusText);
            }
            return response.text();
        })
        .then(data => {
            console.log('Respuesta del servicio externo:', data);
            alert('URLs enviadas exitosamente al servicio externo. Respuesta: ' + (data.substring(0, 100) + '...'));
        })
        .catch(error => {
            console.error('Error al enviar las URLs al servicio externo:', error);
            alert('Error al enviar las URLs al servicio externo. Consulta la consola del navegador para más detalles.');
        })
        .finally(() => {
            button.value = 'Enviar URLs por POST';
            button.disabled = false;
        });
    });
    // --- FIN DEL SCRIPT JAVASCRIPT PARA ENVIAR LAS URLS---  
</script>
<?php


/**
 * Función para obtener todos las documentos del directorio de uploads o de una subcarpeta específica.
 * Excluye miniaturas y filtra opcionalmente por si están vinculadas o no a attachments.
 *
 * @param string $subfolder        Carpeta relativa dentro de uploads (ej: '2024/06' o 'mis-documentos-api').
 * @param string $orderby          Columna por la que ordenar (size_bytes, is_attachment, modified_date).
 * @param string $order            Dirección de ordenación (asc o desc).
 * @return array Lista de arrays de documentos.
 */
function wpil_get_all_documents_in_uploads( $subfolder = '', $orderby = 'size_bytes', $order = 'desc' ) {
	
    global $wpdb;
	
    $upload_dir_info  = wp_upload_dir();
    $base_upload_path = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url  = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path .= trailingslashit( $subfolder );
    }

    $all_documents 			= array();
    $not_allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif' );
    $attachment_paths       = array();
	$results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key = '_wp_attached_file'
               AND meta_value LIKE %s",
            $wpdb->esc_like($subfolder) . '%'
        ),
        ARRAY_A
    ); 
    foreach ( $results as $row ) {
        $attachment_paths[ $row['meta_value'] ] = $row['post_id'];
    }


    if ( ! is_dir( $start_path ) ) {
        return array();
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $start_path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $posts = $wpdb->get_results("
		    SELECT post_id, meta_value 
		    FROM {$wpdb->prefix}postmeta AS wpostmeta
		    LEFT JOIN {$wpdb->prefix}posts AS wpost ON wpostmeta.post_id = wpost.ID
		    WHERE wpostmeta.meta_key = '_elementor_data'
		    AND wpost.post_status IN('publish','private')
		");

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
				$ext = strtolower($file->getExtension());
        		if (in_array($ext, $not_allowed_extensions)) continue;
                $filename        = $file->getFilename();
                $extension       = strtolower( $file->getExtension() );
				$relative_path   = str_replace( $base_upload_path, '', $file->getPathname() );
				$file_size_bytes = $file->getSize();
				$is_attachment   = false;
				$attachment_id   = null;

				// Realizar la verificación de si attachment
				$relative_path_for_db = ltrim( $relative_path, '/' );
				if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
					$is_attachment = true;
					$attachment_id = $attachment_paths[ $relative_path_for_db ];
				}
				
				$query = $wpdb->prepare(
					"SELECT COUNT(*) 
				 	FROM $wpdb->posts
				 	WHERE post_content LIKE %s 
				 	AND post_status IN('publish','private')
				 	AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				);

				$programas = $wpdb->prepare(
					"SELECT COUNT(*) 
					 FROM {$wpdb->prefix}learnpress_courses
					 WHERE post_content LIKE %s 
					 AND post_status IN('publish','private')",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				);  
				$en_contenido = $wpdb->get_var($query);
				$en_programa  = $wpdb->get_var($programas);
 
				if($en_contenido || $en_programa){
					continue;
				}

				$filenamewithfolder = str_replace('/', '\/', $relative_path);
				$aux_post = false;
				foreach ($posts as $post) {
			        if (strpos($post->meta_value, $filenamewithfolder) !== false) { 
			           	$aux_post = true;
                        continue; 
			        } 
				}
				if($aux_post) {
					continue;
				}

				$all_documents[] = array(
					'full_path'       => $file->getPathname(),
					'relative_path'   => $relative_path,
					'filename'        => $filename,
					'size_bytes'      => $file_size_bytes,
					'size_kb'         => $file_size_bytes / 1024,
					'is_attachment'   => $is_attachment,
					'attachment_id'   => $attachment_id,
					'modified_date'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
					'url'             => $base_upload_url . $relative_path,
					'en_contenido'	  => $en_contenido
				);
            }
        }
    } catch ( UnexpectedValueException $e ) {
        error_log( 'WPIL Error iterating directory: ' . $e->getMessage() . ' for path: ' . $start_path );
        return array();
    }

    if ( ! empty( $orderby ) && ! empty( $all_documents ) ) {
        usort( $all_documents, function( $a, $b ) use ( $orderby, $order ) {
            $value_a = null;
            $value_b = null;

            switch ( $orderby ) {
                case 'size_bytes':
                    $value_a = $a['size_bytes'];
                    $value_b = $b['size_bytes'];
                    break;
                case 'is_attachment':
                    $value_a = (int) $a['is_attachment'];
                    $value_b = (int) $b['is_attachment'];
                    break;
                case 'modified_date':
                    $value_a = strtotime( $a['modified_date'] );
                    $value_b = strtotime( $b['modified_date'] );
                    break;
                default:
                    return 0;
            }

            if ( $value_a == $value_b ) {
                return 0;
            }

            if ( $order === 'asc' ) {
                return ( $value_a < $value_b ) ? -1 : 1;
            } else { // 'desc'
                return ( $value_a > $value_b ) ? -1 : 1;
            }
        });
    }
    return $all_documents;
}