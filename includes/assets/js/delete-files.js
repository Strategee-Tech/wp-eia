document.addEventListener('DOMContentLoaded', async function() {

    const sendBtn     = document.getElementById('delete-all-btn');
    const url         = `${window.location.origin}/wp-json/api/v1/borrar-archivos`;
    const user        = 'it@strategee.us';
    const password    = 'f7f720a2499f9b06c0b5cce877da9fff#.!';
    const credentials = btoa(`${user}:${password}`);

    
    sendBtn.addEventListener('click', async function() {


        if (typeof filesToDelete !== 'undefined' && filesToDelete !== null) {

            if(confirm('¿Estas seguro de eliminar los archivo?')){

                console.log(filesToDelete);

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Basic ${credentials}`
                    },
                    body: JSON.stringify(filesToDelete)
                })

                if (!response.ok) {
                    throw new Error('Network response from external endpoint was not ok. Status: ' + response.status + ' ' + response.statusText);
                }

                alert('Archivos eliminados correctamente.');
                location.reload();
                const data = await response.json();
                console.log(JSON.parse(JSON.stringify(data)));
            }

        } else {
            console.warn('Advertencia: filesToDelete NO está definido o es nulo. Esto puede indicar un problema con wp_localize_script.');
        }

    });
});