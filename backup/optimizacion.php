<?php

add_action('admin_menu', function() {
    add_menu_page(
        'Optimizar imagenes',
        'Optimizar imagenes',
        'manage_options',
        'optimizar_archivos',
        'limpiar_uploads_callback'
    );
});

function limpiar_uploads_callback() {
    global $wpdb; // Acceso a la base de datos de WordPress
    $upload_dir = wp_upload_dir(); // Obtiene el directorio de subida desde la configuración de WordPress
    
    // Lista de dimensiones conocidas 
	$dimensions = [
		"180x135",
		"180x143",
		"180x180",
		"265x400",
		"280x400",
		"352x400",
		"352x510",
		"360x400",
		"360x510",
		"360x550",
		"400x237",
		"400x248",
		"400x317",
		"400x331",
		"400x366",
		"400x376",
		"400x400",
		"441x400",
		"441x510",
		"441x550",
		"457x510",
		"500x300",
		"512x400",
		"512x510",
		"512x550",
		"525x400",
		"525x510",
		"525x550",
		"534x1024",
		"575x1024",
		"576x1024",
		"601x400",
		"601x510",
		"610x1024",
		"640x1024",
		"683x1024",
		"720x405",
		"743x400",
		"743x510",
		"743x550",
		"750x237",
		"750x366",
		"750x457",
		"750x510",
		"750x533",
		"750x550",
		"768x135",
		"768x167",
		"768x180",
		"768x238",
		"768x355",
		"768x366",
		"768x369",
		"768x412",
		"768x450",
		"768x487",
		"768x512",
		"768x513",
		"768x657",
		"768x683",
		"768x768",
		"768x860",
		"768x1228",
		"768x1289",
		"768x1290",
		"768x1365",
		"768x1368",
		"768x1474",
		"780x237",
		"780x366",
		"780x457",
		"780x533",
		"780x550",
		"800x400",
		"800x1536",
		"842x400",
		"862x1536",
		"864x1536",
		"914x1536",
		"915x1024",
		"915x1536",
		"928x400",
		"952x400",
		"961x1536",
		"1000x237",
		"1000x400",
		"1000x600",
		"1000x1024",
		"1024x223",
		"1024x318",
		"1024x400",
		"1024x550",
		"1024x600",
		"1024x649",
		"1024x682",
		"1024x683",
		"1024x768",
		"1024x1024",
		"1280x720",
		"1366x768",
		"1474x2048",
		"1536x334",
		"1536x477",
		"1536x899",
		"1536x974",
		"1536x1024",
		"2048x446",
		"2048x1199",
		"2048x1299",
		"2048x1365",
		"2048x1366"
	];
 
    echo "<div class='wrap'>";
    echo "<h2>Optimizar archivos</h2>";

    // Mostrar el formulario de subida
    echo "<form method='post' enctype='multipart/form-data'>";
    echo "<p>Selecciona un archivo TXT o CSV con las columnas 'old' y 'new' (primera fila como encabezados):</p>";
    echo "<input type='file' name='data_file' accept='.txt,.csv'>"; // Cambiado a .txt y .csv
    echo "<p class='submit'><input type='submit' name='submit_file' id='submit' class='button button-primary' value='Cargar y Mostrar'></p>";
    echo "</form>";

    // Procesar el archivo si se ha subido
    if (isset($_POST['submit_file']) && isset($_FILES['data_file'])) {
        $file_info = $_FILES['data_file'];

        // Verificar si hubo un error en la subida
        if ($file_info['error'] !== UPLOAD_ERR_OK) {
            echo "<div class='notice notice-error'><p>Error al subir el archivo: " . esc_html($file_info['error']) . "</p></div>";
            return;
        }
        
        $folder = '2025/06/';

        // Verificar el tipo de archivo (solo como precaución, ya el 'accept' lo maneja)
        $file_mimes = array(
            'text/csv',
            'text/plain' // Los .txt a menudo se detectan como text/plain
        );

        if (in_array($file_info['type'], $file_mimes) || pathinfo($file_info['name'], PATHINFO_EXTENSION) === 'txt' || pathinfo($file_info['name'], PATHINFO_EXTENSION) === 'csv') {
            $uploaded_file_path = $file_info['tmp_name'];

            echo "<h3>Contenido del archivo:</h3>";
            echo "<table class='wp-list-table widefat fixed striped'>";
            echo "<thead><tr><th>Valor Antiguo (OLD)</th><th>Valor Nuevo (NEW)</th></tr></thead>";
            echo "<tbody>";

            // Abrir el archivo en modo lectura
            if (($handle = fopen($uploaded_file_path, "r")) !== FALSE) {
                $row_count = 0;
                while (($data = fgetcsv($handle, 0, ",")) !== FALSE) { // El 0 significa longitud máxima, "," es el delimitador
                    // Saltar la primera fila si son encabezados
                    if ($row_count === 0) {
                        $row_count++;
                        continue;
                    }

                    //Columnas del CSV
                    $old_value     = isset($data[0]) ? esc_html($data[0]) : '';
                    $new_file_name = isset($data[1]) ? esc_html($data[1]) : '';
                    $title         = isset($data[2]) ? esc_html($data[2]) : '';
                    $alt_text      = isset($data[3]) ? esc_html($data[3]) : '';
                    $slug          = isset($data[4]) ? esc_html($data[4]) : '';

                    // Solo mostrar filas si al menos una celda tiene valor
                    if (!empty($old_value) && !empty($new_file_name)) {
                        echo "<tr><td>{$old_value}</td><td>{$new_file_name}</td></tr>";
                        
                        // Buscar las imágenes en wp_posts (usamos la columna 'guid' para buscar la URL antigua)
                      	$sql     = "SELECT ID, guid FROM {$wpdb->posts} WHERE guid = %s AND post_type = 'attachment'";
						$results = $wpdb->get_results($wpdb->prepare($sql, $old_value));
                        
                        // Si encontramos coincidencias
                        if(!empty($results)) {
                            foreach ($results as $post) {
                                // Actualizar la URL en el campo 'guid' y el texto alternativo
                                $post_id     = $post->ID;
                                $current_url = $post->guid;

                                // Extraer el path relativo de la URL, eliminando el dominio
                                $file_path = parse_url($current_url, PHP_URL_PATH);
                    
                                // Eliminar el prefijo '/wp-content/uploads/' de la URL
                                $file_path = str_replace('/wp-content/uploads', '', $file_path); // Esto elimina la parte extra
                                
                                // Construir la ruta absoluta del archivo en el servidor
                                $old_file_path = $upload_dir['basedir'] . $file_path; // Ruta completa en el servidor

                                echo "<pre>";
                                print_r("path viejo ".$old_file_path);

                                 // El nuevo nombre basado en la URL proporcionada (usando basename para obtener solo el nombre)
                                $new_file_path   = ABSPATH . 'wp-content/uploads/'.$folder.$new_file_name; // Nueva ruta en el mismo directorio
                                $new_path_rename = ABSPATH . 'wp-content/uploads/'.$folder.$slug.'.webp';
                                
                                echo "<pre>";
                                print_r("NEW PATH RENAME".$new_path_rename);
                                
                                echo "<pre>";
                                print_r("nuevo archivo ".$new_file_name);
                                
                                echo "<pre>";
                                print_r("path nuevo:".$new_file_path);

                                // Comprobar si el archivo existe antes de renombrarlo
                                if (file_exists($old_file_path)) {
                                    echo "<pre>";
                                    echo "existe el archivo viejo $old_file_path";
                                    // Renombrar el archivo
                                    if (rename($new_file_path, $new_path_rename)) {
                                        
                                        echo "<br>";
                                        echo "archivo renombrado";
                                        echo "<br>";
										
										$wpdb->update(
											$wpdb->posts, 
											array(
												'guid' 			 => get_site_url().'/wp-content/uploads/'.$folder.$slug.'.webp',
												'post_title' 	 => $title, // Si deseas actualizar el título
												'post_name'  	 => $slug,  // Si deseas actualizar el slug
												'post_mime_type' => 'image/webp', // Si deseas cambiar el tipo de archivo
											),
											array(
												'ID' 		=> $post_id,
												'post_type' => 'attachment',  // Asegúrate de que solo se actualicen los adjuntos
											)
										); 

                                        // Actualizamos el texto alternativo
                                        update_post_meta($post_id, '_wp_attachment_image_alt', $alt_text); // 'alt_text' es el nuevo texto alternativo
                                        update_post_meta($post_id, '_wp_attached_file', $folder.$slug.'.webp');
                                        
                                        // Regenerar los metadatos (incluidas las miniaturas)
                                        $metadata = wp_generate_attachment_metadata($post_id, get_attached_file($post_id));
                                    
                                        // Actualizar los metadatos de la imagen
                                        update_post_meta($post_id, '_wp_attachment_metadata', $metadata);

                                        // Extraer el nombre del archivo (basename) y el directorio (dirname)
                                        $original    = basename($file_path); // → e.g imagen.webp
                                        $upload_path = dirname($file_path);  // → /2025/06/
                                        $path_info   = pathinfo($original);
                                        $basename    = $path_info['filename'];
                                        $extension   = $path_info['extension']; 
                                    
                                        // Carpeta donde están las imágenes
                                        $directory = ABSPATH .'wp-content/uploads/'.ltrim($upload_path, '/') . '/';
                                        foreach ($dimensions as $dim) {
                                            $file_to_remove = $directory . $basename . '-' . $dim . '.' . $extension;
                                            echo "<br>";
                                            if (file_exists($file_to_remove)) {
                                                unlink($file_to_remove);
                                                echo "Miniatura Eliminada: $file_to_remove\n";
                                            } else {
                                                echo "Miniatura No encontrada: $file_to_remove\n";
                                            }
                                        }
                                        if (unlink($old_file_path)) { 
                                            echo "Archivo original Eliminado: $old_file_path\n";
                                        }  else {
                                            echo "Archivo original No encontrado: $file_to_remove\n";
                                        }
                                    } else {
                                        echo "<br> no se ha podido renombrar el archivo";
                                    }
                                }
                            }
                        }
                    }
                    $row_count++;
                }
                fclose($handle); // Cerrar el archivo
            } else {
                echo "<div class='notice notice-error'><p>No se pudo abrir el archivo para lectura.</p></div>";
            }
            echo "</tbody>";
            echo "</table>";

        } else {
            echo "<div class='notice notice-error'><p>Tipo de archivo no permitido. Por favor, sube un archivo CSV (.csv) o TXT (.txt).</p></div>";
        }
		echo "<p>Proceso terminado.</p></div>";
    } 
}



 
SELECT pm.meta_id, pm.post_id, pm.meta_value
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE pm.meta_key IN('_elementor_data','enclosure')
AND p.post_type IN('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')
AND meta_value REGEXP 'wp-content\\\\/uploads\\\\/2023\\\\/10\\\\/130_DAZ8858-scaled\\.jpg'

SELECT pm.meta_id, pm.post_id, pm.meta_value
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE pm.meta_key IN('_elementor_css')
AND p.post_type IN('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')
AND pm.meta_value REGEXP 'uploads\\/2023\\/07\\/eventos-integracion-eia\\.webp';

SELECT pm.meta_id, pm.post_id, pm.meta_value
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE pm.meta_key IN('_thumbnail_id')
AND p.post_type IN('post', 'page', 'custom_post_type', 'lp_course', 'service', 'portfolio', 'gva_event', 'gva_header', 'footer', 'team', 'elementskit_template', 'elementskit_content','elementor_library')
AND pm.meta_value = '129385';


// sin agrugar
SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type IN (
    'post','page','custom_post_type','lp_course','service','portfolio',
    'gva_event','gva_header','footer','team','elementskit_template',
    'elementskit_content','elementor_library'
)
AND p.post_status IN('publish','private','draft')
AND (
    (pm.meta_key IN('_elementor_data') AND pm.meta_value REGEXP 'wp-content\\\\/uploads\\\\/2025\\\\/03\\\\/descarga\\.png')
    OR
    (pm.meta_key IN('_elementor_css','enclosure')  AND pm.meta_value REGEXP 'uploads\\/2020\\/10\\/201007-invitacion-dia-de-la-familia\\.wav')
    OR
    (pm.meta_key = '_thumbnail_id' AND pm.meta_value = '129385')
);

// agrupar
SELECT pm.meta_key,
    MIN(pm.meta_id) AS meta_id,
    MIN(pm.post_id) AS post_id,
    MIN(pm.meta_value) AS meta_value
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type IN (
    'post','page','custom_post_type','lp_course','service','portfolio',
    'gva_event','gva_header','footer','team','elementskit_template',
    'elementskit_content','elementor_library'
)
AND p.post_status IN('publish','private','draft')
AND (
    (pm.meta_key IN('_elementor_data') AND pm.meta_value REGEXP 'wp-content\\\\/uploads\\\\/2025\\\\/03\\\\/descarga\\.png')
    OR
    (pm.meta_key IN('_elementor_css','enclosure')  AND pm.meta_value REGEXP 'uploads\\/2020\\/10\\/201007-invitacion-dia-de-la-familia\\.wav')
    OR
    (pm.meta_key = '_thumbnail_id' AND pm.meta_value = '129385')
)
GROUP BY pm.meta_key;


SELECT pm.meta_key,
    MIN(pm.meta_id) AS meta_id,
    MIN(pm.post_id) AS post_id,
    MIN(pm.meta_value) AS meta_value
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type IN (
    'post','page','custom_post_type','lp_course','service','portfolio',
    'gva_event','gva_header','footer','team','elementskit_template',
    'elementskit_content','elementor_library'
)
AND p.post_status IN('publish','private','draft')
AND (
    (
        pm.meta_key IN('_elementor_data') AND 
        (pm.meta_value REGEXP '\\\\/2025\\\\/03\\\\/descarga\\.jpeg' OR pm.meta_value REGEXP '\"id\":171352')
    )
    OR
    (pm.meta_key IN('_elementor_css','enclosure') AND pm.meta_value REGEXP '2020\\/10\\/201007-invitacion-dia-de-la-familia\\.wav')
    OR
    (pm.meta_key = '_thumbnail_id' AND pm.meta_value = '129385')
)
GROUP BY pm.meta_key;


// EN PHP

global $wpdb;

// Variables dinámicas
$file_path_relative = '2025/03/descarga.png'; // Ejemplo
$thumbnail_id       = 129385;
$attachment_id      = 243243; // ID opcional del attachment (Elementor)

// 1. Escapar el patrón para REGEXP
$pattern_escaped = preg_quote($file_path_relative, '/'); // "2025/03/descarga\.png"

// 2. Para _elementor_data (dobles backslashes para JSON)
$elementor_data_pattern = '\\\\/' . str_replace('/', '\\\\/', $file_path_relative); // "\/2025\/03\/descarga\.png"

// 3. Para _elementor_css y otros (slash normal)
$elementor_css_pattern = $pattern_escaped; // "2025/03/descarga\.png"

// 4. Para buscar por ID en JSON (opcional)
$elementor_id_pattern = '"id":' . intval($attachment_id);

// Post types
$post_types = [
    'post','page','custom_post_type','lp_course','service','portfolio',
    'gva_event','gva_header','footer','team','elementskit_template',
    'elementskit_content','elementor_library'
];
$post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

// Construcción de la query
$sql = "
    SELECT pm.meta_key,
        MIN(pm.meta_id) AS meta_id,
        MIN(pm.post_id) AS post_id,
        MIN(pm.meta_value) AS meta_value
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
    WHERE p.post_type IN ($post_types_placeholders)
    AND p.post_status IN('publish','private','draft')
    AND (
        (
            pm.meta_key IN('_elementor_data') 
            AND (
                pm.meta_value REGEXP %s 
                OR pm.meta_value REGEXP %s
            )
        )
        OR
        (pm.meta_key IN('_elementor_css','enclosure') AND pm.meta_value REGEXP %s)
        OR
        (pm.meta_key = '_thumbnail_id' AND pm.meta_value = %d)
    )
    GROUP BY pm.meta_key
";

// Preparar la query con parámetros dinámicos
$query = $wpdb->prepare(
    $sql,
    array_merge($post_types, [$elementor_data_pattern, $elementor_id_pattern, $elementor_css_pattern, $thumbnail_id])
);

// Ejecutar y obtener resultados
$results = $wpdb->get_results($query, ARRAY_A);

// Ver resultados
echo '<pre>'; print_r($results); echo '</pre>';

    
// echo "<pre>";
// print_r($results);

// die;