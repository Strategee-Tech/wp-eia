async function geminiPost(imageUrl) {
    const url         = `${window.location.origin}/wp-json/api/v1/gemini`;
    const user        = credentials.user_auth;
    const password    = credentials.pass_auth;
    const credentials = btoa(`${user}:${password}`);

    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Basic ${credentials}`
        },
        body: JSON.stringify({
            imageUrl: imageUrl
        })
    });
    const data   = await response.json();
    const result = (Array.isArray(data) && data[0] !== undefined) ? JSON.parse(JSON.stringify(data[0])) : JSON.parse(JSON.stringify(data));
    return result;
}

async function imageUrlToBase64(imageUrl) {
    try {
        // 1. Obtener la imagen usando fetch
        const response = await fetch(imageUrl);

        // Asegurarse de que la respuesta sea OK y que sea una imagen
        if (!response.ok) {
            throw new Error(`No se pudo cargar la imagen desde la URL: ${response.statusText}`);
        }
        if (!response.headers.get('Content-Type').startsWith('image/')) {
            throw new Error('La URL no apunta a un tipo de archivo de imagen válido.');
        }

        const blob = await response.blob(); // Obtener el contenido como Blob

        // 2. Convertir el Blob a Base64
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => {
                // El resultado es un Data URL (ej: "data:image/jpeg;base64,...")
                // Necesitamos solo la parte Base64 después de la coma.
                const base64String = reader.result.split(',')[1];
                resolve(base64String);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob); // Lee el Blob como Data URL
        });

    } catch (error) {
        console.error("Error al convertir URL a Base64:", error);
        throw error; // Propagar el error
    }
}