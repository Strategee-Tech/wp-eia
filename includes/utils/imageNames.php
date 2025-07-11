<?php

function isThumbnail($filename) {
    return preg_match('/-\d+x\d+\./', $filename);
}


/**
 * Reconstruye la URL de la imagen original a partir de la URL de una miniatura de WordPress.
 *
 * Esta función es útil para obtener la ruta del archivo de la imagen original (tamaño completo)
 * cuando solo se dispone de la URL de una miniatura generada por WordPress. Se basa en la
 * convención de nombres de WordPress, donde las dimensiones de la imagen se anexan
 * al final del nombre del archivo antes de la extensión (ej., 'imagen-original-300x200.jpg').
 *
 * @param string $thumb_url La URL completa de la imagen en miniatura (ej., 'https://ejemplo.com/wp-content/uploads/2024/06/mi-imagen-150x150.jpg').
 * @return array Un array asociativo que contiene:
 * - 'original_url' (string): La URL deducida de la imagen original.
 * - 'name_clean' (string): El nombre de archivo "limpio" de la imagen original (sin las dimensiones).
 */
function get_original_data_from_thumbnail($thumb_url) {
    // Obtener el nombre del archivo (sin la ruta completa)
    $filename = basename($thumb_url); // e.g. "Little-heroes-Juan-Camilo-Clavijo-Lopez-780x433.png"

    // Separar nombre y extensión
    $parts = pathinfo($filename);
    $name  = $parts['filename']; // "Little-heroes-Juan-Camilo-Clavijo-Lopez-780x433"
    $ext   = $parts['extension']; // "png"

    // Eliminar el patrón de "-anchoxalto" al final
    $name_clean = preg_replace('/-\d+x\d+$/', '', $name);

    // Reconstruir el nombre limpio con extensión
    $clean_filename = $name_clean . '.' . $ext;

    // Reconstruir la URL base del upload
    $base_url     = wp_upload_dir()['baseurl'];
    $subpath      = str_replace(wp_upload_dir()['basedir'], '', dirname($thumb_url));
    $original_url = trailingslashit($base_url . $subpath) . $clean_filename;

    // Obtener el adjunto original
    return array('original_url' => $original_url, 'name_clean' => $clean_filename);
}





/**
 * Extrae la extensión de archivo de una URL dada.
 *
 * @param string $url La URL completa del archivo (ej. 'https://example.com/ruta/a/imagen.jpg?v=123').
 * @return string|null La extensión del archivo (ej. 'jpg', 'pdf', 'webp'), o null si no se puede determinar.
 */
function getFileExtensionFromUrl(string $url): ?string
{
    // Paso 1: Extraer solo la parte de la ruta del archivo de la URL.
    // Esto elimina el protocolo, dominio, parámetros de consulta, etc.,
    // dejando solo '/wp-content/uploads/2025/07/eia-robotics-nuevo-3.webp'
    $path = parse_url($url, PHP_URL_PATH);

    // Si parse_url no pudo extraer una ruta (ej. URL mal formada o vacía),
    // o si la ruta es nula, no hay extensión que extraer.
    if ($path === null || $path === false) {
        return null;
    }

    // Paso 2: Usar pathinfo() para obtener la información detallada de la ruta del archivo.
    // Retorna un array asociativo con 'dirname', 'basename', 'extension', 'filename'.
    $file_info = pathinfo($path);

    // Paso 3: Acceder al índice 'extension' del array.
    // Retorna la extensión si existe, de lo contrario, no se define el índice 'extension'.
    if (isset($file_info['extension'])) {
        return $file_info['extension'];
    } else {
        // No se encontró una extensión (ej. la URL apunta a un directorio o a un archivo sin extensión).
        return null;
    }
}