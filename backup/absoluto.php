
<?php
// Evitar el acceso directo al archivo
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Añadir el elemento de menú al panel de administración de WordPress
 */
add_action( 'admin_menu', 'wpil_add_admin_menu' );

function wpil_add_admin_menu() {
    add_menu_page(
        'Lista de Imágenes de Uploads',   // Título de la página en la etiqueta <title>
        'Gestor de Imágenes',             // Texto del menú
        'manage_options',                 // Capacidad requerida para ver el menú
        'wpil-image-lister',              // Slug de la página
        'wpil_display_image_list_page',   // Función que renderiza la página
        'dashicons-images-alt2',          // Icono del menú
        80                                // Posición en el menú
    );
}

// --- NUEVO: Registrar el endpoi de la API REST para el CSV ---
add_action( 'rest_api_init', 'wpil_register_csv_export_route' );

function wpil_register_csv_export_route() {
    register_rest_route( 'wp-image-lister/v1', '/export-csv', array(
        'methods'             => 'GET', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'wpil_generate_csv_data_for_api',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
        'args' => array(
            'folder' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'Subcarpeta dentro de uploads para filtrar imágenes.',
            ),
            'orderby' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'Columna por la que ordenar.',
            ),
            'order' => array(
                'required'          => false,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'enum'              => array( 'asc', 'desc' ),
                'description'       => 'Dirección de ordenación (asc o desc).',
            ),
        ),
    ));
}

/**
 * Función de callback para el endpoint REST de exportación CSV.
 * Genera el contenido CSV y lo devuelve como una cadena dentro de una respuesta JSON.
 *
 * @param WP_REST_Request $request La solicitud REST.
 * @return WP_REST_Response La respuesta de la API que contiene el contenido CSV.
 */
function wpil_generate_csv_data_for_api( $request ) {
    $selected_folder = $request->get_param( 'folder' );
    $orderby         = $request->get_param( 'orderby' );
    $order           = $request->get_param( 'order' );

    $images = wpil_get_all_images_in_uploads( $selected_folder, true, $orderby, $order, );

    // Usar output buffering para capturar el contenido CSV
    ob_start();
    $output = fopen( 'php://output', 'w' );

    // Cabeceras del CSV
    fputcsv( $output, array( 'Ruta Relativa', 'Nombre del Archivo', 'Dimensiones', 'Tamano (KB)', 'Vinculado a Attachment', 'ID Adjunto', 'Fecha de Modificacion', 'URL Completa' ) );

    // Datos del CSV
    foreach ( $images as $image ) {
        fputcsv( $output, array(
            $image['relative_path'],
            $image['filename'],
            $image['dimensions'],
            number_format( $image['size_kb'], 2 ),
            $image['is_attachment'] ? 'Si' : 'No',
            $image['attachment_id'] ?? '',
            $image['modified_date'],
            $image['url']
        ) );
    }

    fclose( $output );
    $csv_content = ob_get_clean(); // Obtener el contenido del buffer y limpiarlo

    // Devolver el contenido CSV como una propiedad en una respuesta JSON
    return new WP_REST_Response( array(
        'success'    => true,
        'csv_data'   => $csv_content,
        'filename'   => 'image_list_' . date( 'YmdHis' ) . '.csv', // Nombre del archivo sugerido
    ), 200 );
}

// NO NECESITAMOS REGISTRAR UN ENDPOINT REST DE WP PARA EL ENVÍO DE URLS PORQUE ES DIRECTO AL EXTERNO

/**
 * 2. Función que renderiza la página de administración
 */
function wpil_display_image_list_page() {
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
    $images     = wpil_get_all_images_in_uploads( $selected_folder, $status, $orderby, $order, $showMiniatures );
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
        <h1>Gestor de Imágenes de Uploads</h1>
        <p>Aquí puedes ver un listado de imágenes de la carpeta especificada dentro de `wp-content/uploads/`.</p>

        <form method="get" action="" id='form-filter'>
            <input type="hidden" name="page" value="wpil-image-lister" />
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
                        <p class="description">Introduce la subcarpeta dentro de `wp-content/uploads/` (ej: `2024/06` o `mis-imagenes-api`). Déjalo vacío para listar todas las imágenes.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-secondary" value="Mostrar Imágenes">
                <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST">
                <input type="button" id="export_csv_button" class="button button-primary" value="Descargar CSV">
				
				<?php if(!$status && $showMiniatures): ?>
                	<input type="button" id="btn-delete-all" class="button button-primary" value="Eliminar Todos">
				<?php endif; ?>
		
				<label>
                	<input type="checkbox" name='status' id="check-status" value="1" <?php echo $status == true ? '' : 'checked' ?> >
					Mostrar solo sin attachment
				</label>
			
				<label>
                	<input type="checkbox" name='miniatures' id="check-miniatures" value="1" <?php echo $showMiniatures == true ? 'checked' : '' ?> >
					Mostrar Miniaturas
				</label>
            </p>
        </form>

        <hr>

        <h2>Listado de Imágenes <?php echo ! empty( $selected_folder ) ? 'en: `' . esc_html( $selected_folder ) . '`' : ' (todas)'; ?></h2>

        <p>
            <strong>Archivos encontrados:</strong> <?php echo number_format( $total_files ); ?><br>
            <strong>Peso Total:</strong> <?php echo size_format( $total_size_bytes ); ?>
        </p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Ruta Relativa</th>
                    <th>Nombre del Archivo</th>
                    <th>Dimensiones</th>
                    <?php
                    // Función auxiliar para generar cabeceras ordenables
                    function wpil_get_sortable_header( $column_id, $column_title, $current_orderby, $current_order, $selected_folder, $status, $showMiniatures ) {
                        $new_order = ( $current_orderby === $column_id && $current_order === 'asc' ) ? 'desc' : 'asc';
                        $class = ( $current_orderby === $column_id ) ? 'sorted ' . $current_order : 'sortable ' . $new_order;
                        $query_args = array(
                            'page'      => 'wpil-image-lister',
                            'orderby'   => $column_id,
                            'order'     => $new_order,
							'status'	=> $status == true ? '0' : '1',
							'miniatures' => $showMiniatures == true ? '0' : '1'
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
                    wpil_get_sortable_header( 'size_bytes', 'Tamaño (KB)', $orderby, $order, $selected_folder, $status, $showMiniatures );
                    wpil_get_sortable_header( 'is_attachment', 'Vinculado a Attachment', $orderby, $order, $selected_folder, $status, $showMiniatures );
                    wpil_get_sortable_header( 'modified_date', 'Fecha de Modificación', $orderby, $order, $selected_folder, $status, $showMiniatures );
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
                            <td><?php echo esc_html( $image['dimensions'] );  echo $image['is_miniature'] == 1 ? 'Min' : '' ?>  </td>
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
			document.getElementById('check-status').addEventListener('click', ()=>{
				document.getElementById('submit').click()

			})
			
			document.getElementById('check-miniatures').addEventListener('click', ()=>{
				document.getElementById('submit').click()

			})
			
			
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
}

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
function wpil_get_all_images_in_uploads( $subfolder = '', $check_attachments = null, $orderby = 'size_bytes', $order = 'desc', $show_miniatures = false ) {
	
    global $wpdb;
	
    $upload_dir_info  = wp_upload_dir();
    $base_upload_path = trailingslashit( $upload_dir_info['basedir'] );
    $base_upload_url  = trailingslashit( $upload_dir_info['baseurl'] );

    $start_path = $base_upload_path;
    if ( ! empty( $subfolder ) ) {
        $start_path .= trailingslashit( $subfolder );
    }

    $all_images = array();
	$scaleds    = array();
    $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'svg' );

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
                $filename = $file->getFilename();
				// Detectar si contiene '-scaled'
				if (strpos($filename, '-scaled') !== false) {
					$pathinfo = pathinfo($filename);
					//echo "Path antes de limpiar: " . $pathinfo['filename'] . "\n";

					// Eliminar solo '-scaled' al final del nombre (antes de la extensión)
					$name_clean = str_replace('-scaled', '', $pathinfo['filename']);

					// Reconstruir el nombre original
					$original_filename = $name_clean . '.' . $pathinfo['extension'];

					//echo "Original limpio: " . $original_filename . "\n";
					$scaleds[] = $original_filename;
				}
			}
		}

        foreach ( $iterator as $file ) {
            if ( $file->isFile() ) {
                $filename     = $file->getFilename();
                $extension    = strtolower( $file->getExtension() );
				$is_miniature = false;
				
				// Excluir miniaturas de WordPress
				if ( preg_match( '/-\d+x\d+\./', $filename ) && in_array( $extension, $allowed_extensions ) ) {
					if( $show_miniatures == 1 ) {
						$is_miniature = true;
					} else {
						continue;
					}
				}
				
                if ( in_array( $extension, $allowed_extensions ) ) {
                    $relative_path   = str_replace( $base_upload_path, '', $file->getPathname() );
                    $file_size_bytes = $file->getSize();

                    $dimensions = 'N/A';
                    $image_info = @getimagesize( $file->getPathname() );
                    if ( $image_info !== false ) {
                        $dimensions = $image_info[0] . 'x' . $image_info[1];
                    }

                    $is_attachment = false;
                    $attachment_id = null;

                    // Realizar la verificación de attachment solo si es necesario (cuando $check_attachments no es null)
                    if ( $check_attachments !== null ) {
                        $relative_path_for_db = ltrim( $relative_path, '/' );
                        if ( isset( $attachment_paths[ $relative_path_for_db ] ) ) {
                            $is_attachment = true;
                            $attachment_id = $attachment_paths[ $relative_path_for_db ];
                        }
                    }

                    // Aplicar el filtro de attachment
                    if ( $check_attachments === true && ! $is_attachment ) {
                        continue; // Si se pide solo adjuntas y no lo es, saltar
                    }
                    if ( $check_attachments === false && $is_attachment ) {
                        continue; // Si se pide solo no adjuntas y sí lo es, saltar
                    }
					
					$query = $wpdb->prepare(
						"SELECT COUNT(*) 
						 FROM $wpdb->posts
						 WHERE post_content LIKE %s 
						 AND post_status = 'publish'
						 AND post_type IN ('post', 'page', 'custom_post_type')",
						'%' . $wpdb->esc_like($base_upload_url . $relative_path) . '%'
					);
					
					$en_contenido = null;
					if( !$is_attachment && !$is_miniature ) {
						$en_contenido = $wpdb->get_var($query);
					}
					if(!$is_attachment && $is_miniature){
						$data_thumb       = get_original_attachment_from_thumbnail($file->getPathname());
						$is_attachment_id = attachment_url_to_postid($data_thumb['original_url']);
						
						if(!empty($scaleds)) {
							if (in_array($data_thumb['name_clean'], $scaleds)) {
								continue;
							}
						}
					
						if($is_attachment_id){
							continue;
						}
					}
                    $all_images[] = array(
                        'full_path'       => $file->getPathname(),
                        'relative_path'   => $relative_path,
                        'filename'        => $filename,
                        'dimensions'      => $dimensions,
                        'size_bytes'      => $file_size_bytes,
                        'size_kb'         => $file_size_bytes / 1024,
                        'is_attachment'   => $is_attachment,
                        'attachment_id'   => $attachment_id,
                        'modified_date'   => date( 'Y-m-d H:i:s', $file->getMTime() ),
                        'url'             => $base_upload_url . $relative_path,
						'is_miniature'    => $is_miniature,
						'en_contenido'	  => $en_contenido
                    );
                }
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
/**
 * 4. Función ORIGINAL para exportar el CSV. Esta función ya NO será llamada directamente desde el navegador para descarga.
 * Su lógica ahora es usada por 'wpil_generate_csv_data_for_api' y se mantiene por si la necesitas para otros usos.
 */
function wpil_export_images_to_csv( $selected_folder = '', $orderby = '', $order = 'asc' ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'No tienes suficientes permisos para realizar esta acción.', 'wp-image-lister' ) );
    }

    $images = wpil_get_all_images_in_uploads( $selected_folder, true, $orderby, $order );

    $filename = 'image_list';
    if ( ! empty( $selected_folder ) ) {
        $filename .= '_' . sanitize_file_name( str_replace( '/', '_', $selected_folder ) );
    }
    $filename .= '_' . date( 'YmdHis' ) . '.csv';

    // Se mantiene la lógica de cabeceras, pero en este contexto, si esta función fuera llamada directamente
    // (lo cual ya no ocurre desde la UI), seguiría intentando la descarga directa.
    // La nueva API maneja el retorno del contenido.
    if ( ob_get_contents() ) {
        ob_clean();
    }
    if ( headers_sent( $file, $line ) ) {
        error_log( "WPIL DEBUG: Las cabeceras ya fueron enviadas en $file en la línea $line antes de la descarga CSV." );
        return;
    }

    header( 'Content-Type: text/csv; charset=' . get_option( 'blog_charset' ) );
    header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $output = fopen( 'php://output', 'w' );

    fputcsv( $output, array( 'Ruta Relativa', 'Nombre del Archivo', 'Dimensiones', 'Tamano (KB)', 'Vinculado a Attachment', 'ID Adjunto', 'Fecha de Modificacion', 'URL Completa' ) );

    foreach ( $images as $image ) {
        fputcsv( $output, array(
            $image['relative_path'],
            $image['filename'],
            $image['dimensions'],
            number_format( $image['size_kb'], 2 ),
            $image['is_attachment'] ? 'Si' : 'No',
            $image['attachment_id'] ?? '',
            $image['modified_date'],
            $image['url'],
			
        ) );
    }

    fclose( $output );
    exit;
}

function get_original_attachment_from_thumbnail($thumb_url) {
    // Obtener el nombre del archivo (sin la ruta completa)
    $filename = basename($thumb_url); // e.g. "Little-heroes-Juan-Camilo-Clavijo-Lopez-780x433.png"

    // Separar nombre y extensión
    $parts = pathinfo($filename);
    $name  = $parts['filename']; // "Little-heroes-Juan-Camilo-Clavijo-Lopez-780x433"
    $ext   = $parts['extension']; // "png"

    // Eliminar el patrón de "-anchoxalto" al final
    $name_clean = preg_replace('/-\d+x\d+$/', '', $name);

    // Reconstruir el nombre limpio con extensión
    $clean_filename = $name_clean . '.' . $ext;

    // Reconstruir la URL base del upload
    $base_url     = wp_upload_dir()['baseurl'];
    $subpath      = str_replace(wp_upload_dir()['basedir'], '', dirname($thumb_url));
    $original_url = trailingslashit($base_url . $subpath) . $clean_filename;

    // Obtener el adjunto original
    return array('original_url' => $original_url, 'name_clean' => $clean_filename);
}