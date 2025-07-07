// js/sendToApi.js

// Usa DOMContentLoaded para asegurar que el DOM esté completamente cargado
// y que wp_localize_script haya inyectado el objeto global.
document.addEventListener('DOMContentLoaded', function() {
    // Es crucial verificar si el objeto existe antes de intentar usarlo
    if (typeof imagesToDelete !== 'undefined' && imagesToDelete !== null) {
        console.log('¡imagesToDelete está definido! Datos recibidos:', imagesToDelete);

        // Aquí puedes seguir con tu lógica para usar imagesToDelete
        // Por ejemplo:
        if (Array.isArray(imagesToDelete)) {
            imagesToDelete.forEach(function(image) {
                console.log('Procesando imagen:', image);
                // Tu lógica para enviar a la API
            });
        } else {
            console.log('imagesToDelete no es un arreglo:', imagesToDelete);
        }

    } else {
        console.warn('Advertencia: imagesToDelete NO está definido o es nulo. Esto puede indicar un problema con wp_localize_script.');
    }
});