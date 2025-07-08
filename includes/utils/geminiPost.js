async function geminiPost(imageUrl) {

    const prompt = `Analiza la siguiente imagen y devuelve un objeto JSON con las siguientes propiedades:
    - title: Un título conciso y descriptivo para la imagen.
    - description: Una descripción detallada de lo que se ve en la imagen.
    - alt: Un texto alternativo corto y descriptivo para SEO y accesibilidad.
    - legend: Una leyenda más larga y creativa para la imagen.
    - slug: Un slug amigable para URL (ej. "mi-imagen-increible").

    Asegúrate de que la salida sea un objeto JSON válido y nada más.`;

    const base64Image = await imageUrlToBase64(imageUrl);

    const requestBody = {
        contents: [
            {
                parts: [
                    {
                        text: prompt
                    },
                    {
                        inline_data: {
                            mime_type: 'image/png',
                            data: base64Image
                        }
                    }
                ]
            }
        ]
    };

    const response = await fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=AIzaSyB9Q9OQoIMSoJVz_00P5jsSt3eQmJbSK5c', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody)
    });
    const data = await response.json();
    console.log(JSON.stringify(data));
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