const user = 'it@strategee.us';
const password = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
const credentials = btoa(`${user}:${password}`);
// admin-media-editor.js (Asegúrate de que este script esté encolado solo en tu página de administración)

document.addEventListener('DOMContentLoaded', function() {
    const editTriggers = document.querySelectorAll('.edit-attachment-trigger')
    const modal = document.getElementById('edit-metadata-modal');
    const modalAttachmentIdSpan = document.getElementById('modal-attachment-id');
    const form = document.getElementById('edit-metadata-form');
    const modalSlugLabel = document.getElementById('modal-slug-label');
    const inputSlug = document.getElementById('modal-slug');
    const inputTitle = document.getElementById('modal-title');
    const imgModal = document.getElementById('modal-image');
    const inputAlt = document.getElementById('modal-alt');
    const inputDescription = document.getElementById('modal-description');
    const saveBtn = document.getElementById('save-metadata-btn');
    const cancelBtn = document.getElementById('cancel-metadata-btn');
    const statusMessage = document.getElementById('save-status-message');
    const iaGenerateBtn = document.getElementById('regenerate-alt-btn');
    const modalUrl = document.getElementById('modal-url');
    const inputWidth = document.getElementById('input-width');
    const fileLink = document.getElementById('file-link');
    const fileLinkText = document.getElementById('file-link-text');
    const fastEdit = document.getElementById('modal-fast-edit');
    const scanResourceBtn = document.getElementById('scan-resource-btn');
    const scanLoader = document.getElementById('scan-loader');

    let currentAttachmentId = null; // Para almacenar el ID del adjunto que se está editando

    let isLoading = false;

    let resize = false;

    inputSlug.style.display = 'none';
    modalSlugLabel.style.display = 'none';

    // fastEdit.addEventListener('change', function() {
    //     resize = this.checked;
    //     if(resize){
    //         inputSlug.style.display = 'none';
    //         modalSlugLabel.style.display = 'none';
    //     } else {
    //         inputSlug.style.display = 'block';
    //         modalSlugLabel.style.display = 'inline';
    //     }
    // });


    editTriggers.forEach(trigger => {
        trigger.addEventListener('click', async function() {

            currentAttachmentId = this.dataset.attachmentId || '';
            const currentTitle = this.dataset.attachmentTitle || '';
            const currentAlt = this.dataset.attachmentAlt || '';
            const currentDescription = this.dataset.attachmentDescription || '';
            const currentSlug = this.dataset.attachmentName || '';
            const currentUrl = this.dataset.attachmentUrl || '';
            const currentDimensions = this.dataset.attachmentDimensions || '';
            resize = parseInt(currentDimensions.split('x')[0]) > 1920;
            
            if(!currentDimensions){
                imgModal.style.display = 'none';
                iaGenerateBtn.style.display = 'none';
                fileLinkText.style.display = 'block';
            } else {
                imgModal.style.display = 'block';
                iaGenerateBtn.style.display = 'flex';
                fileLinkText.style.display = 'none';
            }

            

            inputSlug.value = currentSlug;
            inputTitle.value = currentTitle;
            imgModal.src = currentUrl;
            inputAlt.value = currentAlt;
            inputDescription.value = currentDescription;
            modalUrl.value = currentUrl;
            inputWidth.value = currentDimensions;
            modal.style.display = 'flex'; // Muestra el modal
            fileLink.href = currentUrl;
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
        isLoading = true;

        let width = parseInt(inputWidth.value.split('x')[0]);

        const updatedData = {
            title: inputTitle.value,
            alt: inputAlt.value,
            description: inputDescription.value,
            slug: inputSlug.value,
            width: inputWidth.value,
            resize: width > 1920,
            //fast_edit: fastEdit.checked ? 1 : 0,
        };


        try {
            // Llamada a la API REST de WordPress para actualizar el adjunto
            // Usamos la API REST de WP, no tu endpoint personalizado, para actualizar los campos estándar.
            // URL: /wp-json/wp/v2/media/{id}
            let endpoint ='';
            console.log(updatedData);
            if(width > 0){
                endpoint = `${window.location.origin}/wp-json/api/v1/seo-optimization`;
            } else {
                endpoint = `${window.location.origin}/wp-json/api/v1/optimization-file`;
            }
            const response = await fetch(endpoint, {
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
                    resize: updatedData.resize,
                    //fast_edit: updatedData.fast_edit,
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
                row.children[2].textContent = updatedData.title; // Columna 'Alt'
                row.children[6].textContent = updatedData.slug;   // Columna 'Title'
                row.children[7].children[0].classList.remove('dashicons-no-alt');   // Columna 'Title'
                row.children[7].children[0].classList.add('dashicons-yes');   // Columna 'Title'
                row.children[7].children[0].classList.add('color-green');
            }

            // Opcional: Cerrar el modal después de un breve retraso
            setTimeout(() => {
                modal.style.display = 'none';
                statusMessage.textContent = '';
            }, 1000);
            isLoading = false;

        } catch (error) {
            console.error('Error al guardar metadatos:', error);
            statusMessage.textContent = `Error: ${error.message}`;
            statusMessage.style.color = 'red';
        } finally {
            saveBtn.disabled = false; // Habilita el botón de nuevo
        }
    });



    iaGenerateBtn.addEventListener('click', async function() {

        document.getElementById('gemini-icon').style.display = 'none';
        document.getElementById('loader').style.display = 'block';
        iaGenerateBtn.disabled = true;
        cancelBtn.disabled = true;
        saveBtn.disabled = true;
        const result = await geminiPost(modalUrl.value);
        document.getElementById('gemini-icon').style.display = 'block';
        document.getElementById('loader').style.display = 'none';
        iaGenerateBtn.disabled = false;
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