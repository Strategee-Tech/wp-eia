function geminiPost(imageUrl) {

    const prompt = `Analiza la siguiente imagen y devuelve un objeto JSON con las siguientes propiedades:
    - title: Un título conciso y descriptivo para la imagen.
    - description: Una descripción detallada de lo que se ve en la imagen.
    - alt: Un texto alternativo corto y descriptivo para SEO y accesibilidad.
    - legend: Una leyenda más larga y creativa para la imagen.
    - slug: Un slug amigable para URL (ej. "mi-imagen-increible").

    Asegúrate de que la salida sea un objeto JSON válido y nada más.`;

    const requestBody = {
        contents: [
            {
                parts: [
                    {
                        text: prompt
                    },
                    {
                        // Para URLs de imagen, se usa 'file_data' y 'file_uri'
                        file_data: {
                            mime_type: 'image/jpeg', // Asegúrate de que coincida con el tipo real de tu imagen
                            file_uri: imageUrl
                        }
                    }
                ]
            }
        ]
    };



    const response = fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=AIzaSyB9Q9OQoIMSoJVz_00P5jsSt3eQmJbSK5c', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            model: 'gemini-2.0-flash',
            messages: [{
                role: 'user',
                content: prompt
            }]
        })
    });
    const data = response.json();
    console.log(JSON.stringify(data));
}