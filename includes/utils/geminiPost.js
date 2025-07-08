async function geminiPost(imageUrl) {

    const prompt = `Como experto en SEO y marketing digital especializado en contenido educativo para sitios web universitarios, analiza detalladamente la siguiente imagen. Genera un objeto JSON válido, sin texto adicional, que incluya las siguientes propiedades, optimizadas para el contexto de la Universidad EIA:

    - **title**: Un título corto, atractivo y descriptivo (máx. 60 caracteres) para uso en la meta-etiqueta <title> o como encabezado H1/H2, que refleje el contenido de la imagen y sea relevante para la Universidad EIA. Incorpora palabras clave relevantes para educación superior o áreas de estudio si aplica.
    - **description**: Una meta-descripción detallada y persuasiva (máx. 160 caracteres) que resuma el contenido visual de la imagen, invite a la interacción y esté optimizada para aparecer en resultados de búsqueda, destacando aspectos únicos de la Universidad EIA o el ambiente académico.
    - **alt**: Un texto alternativo conciso y preciso (máx. 125 caracteres) que describa la imagen para usuarios con discapacidad visual y para los motores de búsqueda. Debe ser informativo y reflejar fielmente lo que se ve, incluyendo elementos clave de la Universidad EIA si son visibles (ej. 'Estudiantes en el campus EIA', 'Laboratorio de Mecatrónica EIA').
    - **legend**: Una leyenda o pie de foto más extenso y contextual (máx. 250 caracteres) que complemente el contenido principal de la página. Debe añadir valor informativo o narrativo, explicando el contexto de la imagen dentro de las actividades, eventos, logros o vida estudiantil de la Universidad EIA.
    - **slug**: Un slug amigable para URLs (máx. 50 caracteres) derivado del título o descripción, en minúsculas, usando guiones como separadores de palabras y sin caracteres especiales. Debe ser descriptivo y SEO-friendly, relevante para el contenido de la Universidad EIA.
    
    Asegúrate de que la salida sea un objeto JSON **estrictamente válido** y nada más.`.trim();

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
        ],
        "generationConfig": {
        "responseMimeType": "application/json",
        "responseSchema": {
          "type": "OBJECT",
          "properties": {
            "title": { "type": "STRING", "description": "Título conciso y descriptivo de la imagen." },
            "description": { "type": "STRING", "description": "Descripción detallada de la imagen." },
            "alt": { "type": "STRING", "description": "Texto alternativo para la imagen, optimizado para SEO y accesibilidad." },
            "slug": { "type": "STRING", "description": "Slug amigable para URL, en minúsculas y con guiones." }
          },
          "required": ["title", "description", "alt", "slug"]
        }
      }
    };

    const response = await fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=AIzaSyB9Q9OQoIMSoJVz_00P5jsSt3eQmJbSK5c', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestBody)
    });
    const data = await response.json();
    const result = JSON.parse(data.candidates[0].content.parts[0].text);
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