document.addEventListener('DOMContentLoaded', function() {
    if (typeof imagesToDelete !== 'undefined') {
        console.log('Datos recibidos desde el plugin:', imagesToDelete);

        const outputContainer = document.getElementById('contenedor-salida');
        if (outputContainer) {
            outputContainer.textContent = imagesToDelete;
        }
    } else {
        console.log('imagesToDelete no está definido desde el plugin.');
    }
});