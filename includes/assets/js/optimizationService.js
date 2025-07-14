
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
    const generateBtn = document.getElementById('regenerate-alt-btn');
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



    generateBtn.addEventListener('click', async function() {

        document.getElementById('gemini-icon').style.display = 'none';
        document.getElementById('loader').style.display = 'block';
        generateBtn.disabled = true;
        cancelBtn.disabled = true;
        saveBtn.disabled = true;
        const result = await geminiPost(modalUrl.value);
        document.getElementById('gemini-icon').style.display = 'block';
        document.getElementById('loader').style.display = 'none';
        generateBtn.disabled = false;
        cancelBtn.disabled = false;
        saveBtn.disabled = false;
        inputAlt.value = result.alt;
        inputTitle.value = result.title;
        inputDescription.value = result.description;
        inputSlug.value = result.slug;
        statusMessage.textContent = 'Generado exitoso!';
        statusMessage.style.color = 'green';

    });
});