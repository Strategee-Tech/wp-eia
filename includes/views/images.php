<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
}

?>

<?php 
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getAllImages.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/getSortableImageTableHeader.php';

?>

<H1>Gestion y Optimización de Imágenes</H1>

<?php
$selected_folder            = isset( $_GET['folder'] ) ? sanitize_text_field( wp_unslash( $_GET['folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
$selected_folder            = trim( $selected_folder, '/' );

$orderby                    = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'size_bytes';
$order                      = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';


$showMiniatures             = isset( $_GET['miniatures'] ) ? 1 : 0;
$showAttachment             = isset( $_GET['attachment'] ) ? 1 : 0;
$delete_all                 = isset($_GET['delete_all']) ? 1 : 0;



$all_images = get_all_images_in_uploads($selected_folder, $orderby, $order, $showMiniatures);


$total_files      = count( $all_images['all_images'] );
$total_size_bytes = array_sum( array_column( $all_images['all_images'], 'size_bytes' ) );
$total_delete_size_bytes = 0;
$total_delete_size_bytes_thumbnails = 0;
$thumbnail_count =  count($all_images['all_thumbnails']);
$to_delete_count = 0;
foreach ( $all_images['all_images'] as $image ) {
    if( isset($image['to_delete']) && $image['to_delete'] === true) {
        $to_delete_count++;
        $total_delete_size_bytes += $image['size_bytes'];
    }
}

$thumbnail_to_delete_count = 0;
foreach ( $all_images['all_thumbnails'] as $thumbnail ) {
    if( isset($thumbnail['to_delete']) && $thumbnail['to_delete'] === true) {
        $thumbnail_to_delete_count++;
        $total_delete_size_bytes_thumbnails += $thumbnail['size_bytes'];
    }
}

?>

<form method="get" action="" id='form-filter'>
    <input type="hidden" name="page" value="images" />
    <?php if ( ! empty( $orderby ) ) : ?>
        <input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>" />
    <?php endif; ?>
    <?php if ( ! empty( $order ) ) : ?>
        <input type="hidden" name="order" value="<?php echo esc_attr( $order ); ?>" />
    <?php endif; ?>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="folder">Carpeta a Mostrar:</label></th>
            <td>
                <input type="text" id="folder" name="folder" value="<?php echo esc_attr( $selected_folder ); ?>" class="regular-text" placeholder="Ej: 2024/06 o mis-imagenes-api" />
                <p class="description">
                    Introduce la subcarpeta dentro de `wp-content/uploads/` (ej: `2024/06` o `mis-imagenes-api`).
                </p>
            </td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" name="submit" id="submit" class="button button-secondary" value="Mostrar Imágenes">
    <?php if($to_delete_count > 0): ?>
        <input type="button" id="send_urls_button" class="button button-primary" value="Enviar URLs por POST">
    <?php endif; ?>
    </p>
</form>

<hr>

<div class="wrap">
<h2>Listado de Imágenes <?php echo ! empty( $selected_folder ) ? 'en: `' . esc_html( $selected_folder ) . '`' : ' (todas)'; ?></h2>

<p>
    <strong>Imagenes Encontradas:</strong> <?php echo number_format( $total_files ); ?><br>
    <strong>Peso Total de Imágenes:</strong> <?php echo size_format( $total_size_bytes ); ?><br>
    <strong>Imagenes para eliminar:</strong> <?php echo number_format( $to_delete_count ); ?><br>
    <strong>Espacio Imágenes Liberado:</strong> <?php echo size_format( $total_delete_size_bytes ); ?><br>
    <br>
    <strong>Miniaturas encontradas:</strong> <?php echo number_format( $thumbnail_count ); ?><br>
    <strong>Miniaturas para eliminar:</strong> <?php echo number_format( $thumbnail_to_delete_count ); ?><br>
    <strong>Espacio Miniaturas Liberado:</strong> <?php echo size_format( $total_delete_size_bytes_thumbnails ); ?><br>
</p>

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
                <th>URL</th>
            </tr>
        </thead>

        
        <tbody>
            <?php
            if ( empty( $all_images['all_images'] ) ) :
            ?>
                <tr>
                    <td colspan="7">No se encontraron imágenes en el directorio especificado.</td>
                </tr>
            <?php
            else :
                foreach ( $all_images['all_images'] as $image ) :
            ?>
                    <tr>
                        <!-- <td><?php echo esc_html( $image['relative_path'] ); ?></td> -->
                        <td>
                            <span 
                                class="edit-attachment-trigger"
                                data-attachment-id="<?php echo esc_attr( $image['attachment_id'] ); ?>"
                                data-attachment-title="<?php echo esc_attr( $image['title'] ); ?>"
                                data-attachment-alt="<?php echo esc_attr( $image['alt'] ); ?>"
                                data-attachment-description="<?php echo esc_attr( $image['description'] ); ?>"
                                data-attachment-slug="<?php echo esc_attr( $image['filename'] ); ?>"
                                data-attachment-size="<?php echo esc_attr( $image['dimensions'] ); ?>"
                                data-attachment-url="<?php echo esc_attr( $image['url'] ); ?>"
                            >
                                <?php echo esc_html( $image['filename'] ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( $image['dimensions'] );  echo $image['is_thumbnail'] == 1 ? ' (Thumbnail)' : '' ?>  </td>
                        <td><?php echo esc_html( number_format( $image['size_kb'], 2 ) ); ?></td>
                        <td>
                            <?php
                            if($image['to_delete'] !== false){
                                if($image['attachment_id']){    
                                    echo '<span class="dashicons dashicons-trash" style="color: red;"></span> ID: ' . esc_html( $image['attachment_id'] );						
                                }else{
                                    echo '<span class="dashicons dashicons-trash" style="color: red;"></span> No ID';						
                                }						
                            }else{
                                if ( $image['attachment_id'] ) {								
                                    echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ID: ' . esc_html( $image['attachment_id'] );
                                } else {
                                    echo '<span class="dashicons dashicons-yes-alt" style="color: orange;"></span> No ID';									
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html( $image['title'] ); ?></td>
                        <td><?php echo esc_html( $image['alt'] ); ?></td>
                        <td><a href="<?php echo esc_url( $image['url'] ); ?>" target="_blank"><?php echo esc_url( $image['url'] ); ?></a></td>
                    </tr>
                    <?php
                endforeach;
            endif;
            ?>
        </tbody>
    </table>

</div>


<div id="edit-metadata-modal" style="display: none; background: rgba(0,0,0,0.5); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 5px; width: 400px; max-width: 90%;">
        <h3>Editar Metadatos de Archivo</h3>
        <p>ID del adjunto: <span id="modal-attachment-id"></span></p>
        <form id="edit-metadata-form">
            <p>
                <label for="modal-slug">Slug:</label><br>
                <input type="text" id="modal-slug" name="slug" style="width: 100%;" />
            </p>
            <p>
                <label for="modal-title">Título:</label><br>
                <input type="text" id="modal-title" name="title" style="width: 100%;" />
            </p>
            <p>
                <label for="modal-alt">Texto Alternativo (Alt):</label><br>
                <input type="text" id="modal-alt" name="alt" style="width: 100%;" />
            </p>
            <p>
                <label for="modal-description">Descripción:</label><br>
                <textarea id="modal-description" name="description" rows="5" style="width: 100%;"></textarea>
            </p>
            <input type="hidden" id="modal-url" name="url" />
            <p>
                <button type="submit" id="save-metadata-btn" class="button button-primary">Guardar Cambios</button>
                <button type="button" id="cancel-metadata-btn" class="button">Cancelar</button>
                <button type="button" id="regenerate-alt-btn" class="button">Generar Alt</button>
                <span id="save-status-message" style="margin-left: 10px;"></span>
            </p>
        </form>
    </div>
</div>



<script>

const user = 'it@strategee.us';
const password = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
const credentials = btoa(`${user}:${password}`);
// admin-media-editor.js (Asegúrate de que este script esté encolado solo en tu página de administración)

document.addEventListener('DOMContentLoaded', function() {
    const editTriggers = document.querySelectorAll('.edit-attachment-trigger');
    const modal = document.getElementById('edit-metadata-modal');
    const modalAttachmentIdSpan = document.getElementById('modal-attachment-id');
    const form = document.getElementById('edit-metadata-form');
    const inputSlug = document.getElementById('modal-slug');
    const inputTitle = document.getElementById('modal-title');
    const inputAlt = document.getElementById('modal-alt');
    const inputDescription = document.getElementById('modal-description');
    const saveBtn = document.getElementById('save-metadata-btn');
    const cancelBtn = document.getElementById('cancel-metadata-btn');
    const statusMessage = document.getElementById('save-status-message');
    const regenerateAltBtn = document.getElementById('regenerate-alt-btn');
    const modalUrl = document.getElementById('modal-url');

    let currentAttachmentId = null; // Para almacenar el ID del adjunto que se está editando

    // URL de la API REST de WordPress.
    // Esto debería venir de wp_localize_script desde PHP para seguridad y portabilidad.
    // Ejemplo si lo pasas desde PHP: const restApiBaseUrl = yourPluginVar.restApiUrl;
    // const nonce = yourPluginVar.nonce;
    const restApiBaseUrl = window.location.origin + '/wp-json/api/v1'; // Ajusta esto si tu base de la API es diferente
    const nonce = 'TU_NONCE_GENERADO_EN_PHP'; // <--- ¡IMPORTANTE! Genera esto con wp_create_nonce('wp_rest') en PHP y pásalo via wp_localize_script


    let resize = false;

    editTriggers.forEach(trigger => {
        trigger.addEventListener('click', async function() {

            currentAttachmentId = this.dataset.attachmentId;
            const currentTitle = this.dataset.attachmentTitle;
            const currentAlt = this.dataset.attachmentAlt;
            const currentDescription = this.dataset.attachmentDescription;
            const currentSlug = this.dataset.attachmentSlug;
            const currentUrl = this.dataset.attachmentUrl;
            resize = parseInt(this.dataset.attachmentSize.split('x')[0]) > 1920;
            

            modalAttachmentIdSpan.textContent = currentAttachmentId;
            inputSlug.value = currentSlug;
            inputTitle.value = currentTitle;
            inputAlt.value = currentAlt;
            inputDescription.value = currentDescription;
            modalUrl.value = currentUrl;
            modal.style.display = 'flex'; // Muestra el modal
        });
    });

    // Cierra el modal al hacer clic en Cancelar
    cancelBtn.addEventListener('click', function() {
        modal.style.display = 'none';
        statusMessage.textContent = ''; // Limpia el mensaje de estado
    });

    // Cierra el modal si se hace clic fuera del contenido (en el fondo oscuro)
    // modal.addEventListener('click', function(e) {
    //     if (e.target === modal) {
    //         modal.style.display = 'none';
    //         statusMessage.textContent = '';
    //     }
    // });


    // Manejar el envío del formulario
    form.addEventListener('submit', async function(e) {
        e.preventDefault(); // Evita que el formulario se envíe de forma tradicional

        saveBtn.disabled = true; // Deshabilita el botón mientras se guarda
        statusMessage.textContent = 'Guardando...';
        statusMessage.style.color = 'blue';

        const updatedData = {
            title: inputTitle.value,
            alt: inputAlt.value,
            description: inputDescription.value,
            slug: inputSlug.value
        };

        try {
            // Llamada a la API REST de WordPress para actualizar el adjunto
            // Usamos la API REST de WP, no tu endpoint personalizado, para actualizar los campos estándar.
            // URL: /wp-json/wp/v2/media/{id}
            const response = await fetch(`https://eia2025.strategee.us/wp-json/api/v1/seo-optimization`, {
                method: 'POST', // Las actualizaciones en la API REST de WP suelen ser POST o PUT
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Basic ${credentials}`
                },
                body: JSON.stringify({
                    title: updatedData.title,
                    alt_text: updatedData.alt, // Para el alt text, el campo es 'alt_text' en la API
                    description: updatedData.description,
                    slug: updatedData.slug,
                    post_id: currentAttachmentId,
                    resize: resize
                })
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Error desconocido al guardar.');
            }

            const result = await response.json();
            console.log('Adjunto actualizado:', result);
            statusMessage.textContent = 'Guardado exitoso!';
            statusMessage.style.color = 'green';

            // Opcional: Actualizar la tabla visible en el DOM
            const row = document.querySelector(`.edit-attachment-trigger[data-attachment-id="${currentAttachmentId}"]`).closest('tr');
            if (row) {
                // Asumiendo el orden de las columnas: Alt y Title
                row.children[4].textContent = updatedData.title; // Columna 'Alt'
                row.children[5].textContent = updatedData.alt;   // Columna 'Title'
            }

            // Opcional: Cerrar el modal después de un breve retraso
            setTimeout(() => {
                modal.style.display = 'none';
                statusMessage.textContent = '';
            }, 1500);

        } catch (error) {
            console.error('Error al guardar metadatos:', error);
            statusMessage.textContent = `Error: ${error.message}`;
            statusMessage.style.color = 'red';
        } finally {
            saveBtn.disabled = false; // Habilita el botón de nuevo
        }
    });

    regenerateAltBtn.addEventListener('click', async function() {
        const result = await geminiPost(modalUrl.value);
        inputAlt.value = result.alt;
        inputTitle.value = result.title;
        inputDescription.value = result.description;
        inputSlug.value = result.slug;
        statusMessage.textContent = 'Generado exitoso!';
        statusMessage.style.color = 'green';
    });
});
</script>






