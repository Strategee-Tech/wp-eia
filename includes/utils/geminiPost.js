async function geminiPost(imageUrl) {

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
                        file_data: {
                            mime_type: 'image/png',
                            file_uri: imageUrl
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