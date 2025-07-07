// js/sendToApi.js

// Usa DOMContentLoaded para asegurar que el DOM esté completamente cargado
// y que wp_localize_script haya inyectado el objeto global.
document.addEventListener('DOMContentLoaded', async function() {

    const sendBtn = document.getElementById('send_urls_button');

    const url = 'https://eia2025.strategee.us/borrar_archivos.php';
    const user = 'it@strategee.us';
    const password = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
    const credentials = btoa(`${user}:${password}`);



    // Es crucial verificar si el objeto existe antes de intentar usarlo
    if (typeof imagesToDelete !== 'undefined' && imagesToDelete !== null) {
        console.log(imagesToDelete);
        sendBtn.addEventListener('click', async function() {
        if(confirm('¿Estas seguro de eliminar estas imágenes?')){

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Basic ${credentials}`
                },
                body: JSON.stringify(imagesToDelete)
            })

            if (!response.ok) {
                throw new Error('Network response from external endpoint was not ok. Status: ' + response.status + ' ' + response.statusText);
            }
            const data = await response.json();
            console.log(JSON.stringify(data));
        }
    });

    } else {
        console.warn('Advertencia: imagesToDelete NO está definido o es nulo. Esto puede indicar un problema con wp_localize_script.');
    }
});