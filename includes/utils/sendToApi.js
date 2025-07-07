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