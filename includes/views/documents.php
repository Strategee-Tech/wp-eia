<?php
/**
 * 2. Función que renderiza la página de administración
 */

// Verificar permisos
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}
// Obtener la carpeta seleccionada del parámetro GET o usar un valor por defecto    
$selected_folder = isset( $_GET['wpil_folder'] ) ? sanitize_text_field( wp_unslash( $_GET['wpil_folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
$status  		 = isset( $_GET['status']) ? false: true;	
$selected_folder = trim( $selected_folder, '/' );

// Obtener los parámetros de ordenación
$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'size_bytes';
$order   = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc'; // Por defecto ascendente
$showMiniatures = isset( $_GET['miniatures'] ) ? true : false; // Por defecto ascendente


// Obtener las imágenes y las estadísticas
$images     = wpil_get_all_documents_in_uploads( $selected_folder, $status, $orderby, $order, $showMiniatures );
$delete_all = isset($_GET['delete_all']) ? 1 : 0;

if( $delete_all && !empty($images)) {
	foreach( $images as $image ) {
		if( $image['en_contenido'] ) {
			continue;
		}
		if (file_exists($image['full_path'])) {
			unlink($image['full_path']);
		} else {
			echo 'Archivo no eliminado'. $image['full_path'];
		}
	}
	$clean_url = remove_query_arg( 'delete_all' );
	wp_redirect( esc_url_raw( $clean_url ) );
	exit;
}

global $wpdb;

$total_files      = count( $images );
$total_size_bytes = array_sum( array_column( $images, 'size_bytes' ) );

?>
<div class="wrap">
    <h1>Gestor de Documentos de Uploads</h1>
    <p>Aquí puedes ver un listado de documentos de la carpeta especificada dentro de `wp-content/uploads/`.</p>

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
            <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST">
            <input type="button" id="export_csv_button" class="button button-primary" value="Descargar CSV">
			
			<?php if(!$status && $showMiniatures): ?>
            	<input type="button" id="btn-delete-all" class="button button-primary" value="Eliminar Todos">
			<?php endif; ?>
        </p>
    </form>

    <hr>

    <h2>Listado de Documentos <?php echo ! empty( $selected_folder ) ? 'en: `' . esc_html( $selected_folder ) . '`' : ' (todas)'; ?></h2>

    <p>
        <strong>Archivos encontrados:</strong> <?php echo number_format( $total_files ); ?><br>
        <strong>Peso Total:</strong> <?php echo size_format( $total_size_bytes ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Ruta Relativa</th>
                <th>Nombre del Archivo</th>
                <?php
                // Función auxiliar para generar cabeceras ordenables
                function wpil_get_sortable_header_docs( $column_id, $column_title, $current_orderby, $current_order, $selected_folder, $status ) {
                    $new_order = ( $current_orderby === $column_id && $current_order === 'asc' ) ? 'desc' : 'asc';
                    $class = ( $current_orderby === $column_id ) ? 'sorted ' . $current_order : 'sortable ' . $new_order;
                    $query_args = array(
                        'page'      => 'documents',
                        'orderby'   => $column_id,
                        'order'     => $new_order,
						'status'	=> $status == true ? '0' : '1',
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
                wpil_get_sortable_header_docs( 'size_bytes', 'Tamaño (KB)', $orderby, $order, $selected_folder, $status );
                wpil_get_sortable_header_docs( 'is_attachment', 'Vinculado a Attachment', $orderby, $order, $selected_folder, $status );
                wpil_get_sortable_header_docs( 'modified_date', 'Fecha de Modificación', $orderby, $order, $selected_folder, $status );
                ?>
                <th>URL</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ( empty( $images ) ) :
                ?>
                <tr>
                    <td colspan="7">No se encontraron imágenes en el directorio especificado.</td>
                </tr>
                <?php
            else :
                foreach ( $images as $image ) :
                    ?>
                    <tr>
                        <td><?php echo esc_html( $image['relative_path'] ); ?></td>
                        <td><?php echo esc_html( $image['filename'] ); ?></td>
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
    // --- FIN DEL SCRIPT JAVASCRIPT ---

    // --- SCRIPT JAVASCRIPT PARA DESCARGAR CSV ---
    document.getElementById('export_csv_button').addEventListener('click', function() {
        var button = this;
        button.value = 'Generando CSV...';
        button.disabled = true;

        var folder = document.getElementById('wpil_folder').value;
        var orderbyInput = document.querySelector('input[name="orderby"]');
        var orderInput = document.querySelector('input[name="order"]');

        var orderby = orderbyInput ? orderbyInput.value : '';
        var order = orderInput ? orderInput.value : 'asc';

        // Construir la URL del endpoint REST para el CSV
        var csvApiEndpoint = '<?php echo esc_url_raw( rest_url( 'wp-image-lister/v1/export-csv' ) ); ?>';
        var params = new URLSearchParams({
            folder: folder,
            orderby: orderby,
            order: order
        });

        // --- CAMBIO CLAVE AQUÍ: Obtener y añadir el nonce ---
        var nonce = '<?php echo wp_create_nonce( 'wp_rest' ); ?>'; // Genera el nonce en PHP
        // Los nonces para GET se suelen pasar como parámetro de URL
        // Opcional, pero recomendado si tienes problemas de cookie/sesión con el WAF/CSP
        // params.append('_wpnonce', nonce); // Si prefieres pasarlo en la URL

        fetch(csvApiEndpoint + '?' + params.toString(), {
            method: 'GET', // Método GET
            headers: {
                // Envía el nonce en la cabecera X-WP-Nonce
                'X-WP-Nonce': nonce
            }
        })
        // --- FIN DEL CAMBIO CLAVE ---
        .then(response => {
            if (!response.ok) {
                // Si el estado HTTP no es 2xx, el servidor rechazó la solicitud (ej. por permisos)
                // Importante: Si la API de WordPress devuelve un JSON de error, puedes leerlo aquí
                return response.json().then(err => {
                    throw new Error('Error al obtener el CSV de la API: ' + response.status + ' ' + response.statusText + ' - ' + (err.message || 'Error desconocido'));
                });
            }
            return response.json(); // Esperamos una respuesta JSON
        })
        .then(data => {
            if (data.success && data.csv_data) {
                var blob = new Blob([data.csv_data], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                if (link.download !== undefined) {
                    var url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', data.filename || 'image_list.csv');

                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                } else {
                    alert('Su navegador no soporta la descarga automática. Por favor, guarde el contenido manualmente: \n' + data.csv_data);
                }
                console.log('CSV generado y descarga iniciada.');
            } else {
                alert('Error: No se pudo generar el CSV. ' + (data.message || 'Respuesta inesperada de la API.'));
            }
        })
        .catch(error => {
            console.error('Error al descargar el CSV:', error);
            alert('Error al descargar el CSV. Consulta la consola para más detalles. Posiblemente un problema de permisos o de conexión: ' + error.message); // Muestra el mensaje de error completo
        })
        .finally(() => {
            button.value = 'Descargar CSV';
            button.disabled = false;
        });
    });
	
	document.addEventListener('DOMContentLoaded', () => {
		const deleteBtn = document.getElementById('btn-delete-all');
		if(deleteBtn) {
			deleteBtn.addEventListener('click', ()=>{
				if(!confirm( 'Deseas eliminar todos los registros de la lista actual?') ) return;
				window.location.href = `${window.location.href}&delete_all=1`;
			})
		}
	})
    // --- FIN DEL SCRIPT JAVASCRIPT PARA DESCARGAR CSV ---    </script>
<?php


/**
 * 3. Función para obtener todas las imágenes del directorio de uploads o de una subcarpeta específica.
 * Excluye miniaturas y filtra opcionalmente por si están vinculadas o no a attachments.
 *
 * @param string $subfolder        Carpeta relativa dentro de uploads (ej: '2024/06' o 'mis-imagenes-api').
 * @param bool|null $check_attachments Si es true, muestra solo imágenes vinculadas a un attachment.
 * Si es false, muestra solo imágenes NO vinculadas a un attachment.
 * Si es null, muestra todas las imágenes (sin filtrar por attachment).
 * @param string $orderby          Columna por la que ordenar (size_bytes, is_attachment, modified_date).
 * @param string $order            Dirección de ordenación (asc o desc).
 * @return array Lista de arrays de imágenes.
 */
function wpil_get_all_documents_in_uploads( $subfolder = '', $check_attachments = null, $orderby = 'size_bytes', $order = 'desc', $show_miniatures = false ) {
	
    global $wpdb;
	
    $upload_dir_info  = wp_upload_dir();
    $base_upload_path = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url  = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path .= trailingslashit( $subfolder );
    }

    $all_images = array();
    $not_allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg', 'avif' );
    $attachment_paths = array();
    // Solo necesitamos obtener los attachments si $check_attachments no es null
    if ( $check_attachments !== null ) {
        $results = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file'", ARRAY_A );
        foreach ( $results as $row ) {
            $attachment_paths[ $row['meta_value'] ] = $row['post_id'];
        }
    }

    if ( ! is_dir( $start_path ) ) {
        return array();
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $start_path, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
		
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

				// Realizar la verificación de attachment solo si es necesario (cuando $check_attachments no es null)
				if ( $check_attachments !== null ) {
					$relative_path_for_db = ltrim( $relative_path, '/' );
					if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
						$is_attachment = true;
						$attachment_id = $attachment_paths[ $relative_path_for_db ];
					}
				}
				
				$query = $wpdb->prepare(
					"SELECT COUNT(*) 
				 	FROM $wpdb->posts
				 	WHERE post_content LIKE %s 
				 	AND post_status = 'publish'
				 	AND post_type IN ('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				);

				$programas = $wpdb->prepare(
					"SELECT COUNT(*) 
					 FROM {$wpdb->prefix}learnpress_courses
					 WHERE post_content LIKE %s 
					 AND post_status = 'publish'",
					'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
				);
				
				$en_contenido = $wpdb->get_var($query);
				$en_programa  = $wpdb->get_var($programas);
				if($en_contenido || $en_programa){
					continue;
				}

				$all_images[] = array(
					'full_path'       => $file->getPathname(),
					'relative_path'   => $relative_path,
					'filename'        => $filename,
					'size_bytes'      => $file_size_bytes,
					'size_kb'         => $file_size_bytes / 1024,
					'is_attachment'   => $is_attachment,
					'attachment_id'   => $attachment_id,
					'modified_date'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
					'url'             => $base_upload_url . $relative_path,
					//'en_contenido'	  => $en_contenido
				);
            }
        }
    } catch ( UnexpectedValueException $e ) {
        error_log( 'WPIL Error iterating directory: ' . $e->getMessage() . ' for path: ' . $start_path );
        return array();
    }

    if ( ! empty( $orderby ) && ! empty( $all_images ) ) {
        usort( $all_images, function( $a, $b ) use ( $orderby, $order ) {
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
    return $all_images;
}