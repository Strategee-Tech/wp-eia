<?php

// Define tus meta_keys
define( 'STG_META_IS_SCANNED', '_stg_is_scanned' );
define( 'STG_META_IS_IN_USE', '_stg_is_in_use' );
define( 'STG_META_HAS_ALT_TEXT', '_stg_has_alt_text' ); // Considera usar la meta nativa si es para el alt de WP
define( 'STG_META_IS_EXCLUDED', '_stg_is_excluded' );

// Array de todas las meta_keys que gestionará el plugin para facilitar la iteración
define( 'STG_ATTACHMENT_META_KEYS', serialize( array(
    STG_META_IS_SCANNED,
    STG_META_IS_IN_USE,
    STG_META_HAS_ALT_TEXT,
    STG_META_IS_EXCLUDED,
) ) );


/**
 * Función genérica para obtener un estado booleano de un adjunto.
 *
 * @param int    $attachment_id ID del adjunto.
 * @param string $meta_key      La meta_key a obtener (ej. stg_META_IS_SCANNED).
 * @param bool   $default       Valor por defecto si la meta_key no existe.
 * @return bool True si el estado es '1', false si es '0' o no existe (con valor por defecto).
 */
function stg_get_attachment_status( $attachment_id, $meta_key, $default = false ) {
    $status = get_post_meta( $attachment_id, $meta_key, true );
    // Convertir a booleano: '1' es true, cualquier otra cosa (incluido vacío o '0') es false.
    return ( '1' === $status );
}

/**
 * Función genérica para establecer un estado booleano de un adjunto.
 *
 * @param int    $attachment_id ID del adjunto.
 * @param string $meta_key      La meta_key a establecer.
 * @param bool   $status        El estado booleano (true/false).
 * @return bool True en éxito, false en fallo.
 */
function stg_set_attachment_status( $attachment_id, $meta_key, $status ) {
    // Almacenamos '1' para true, y '0' para false para consistencia en la BD.
    $value_to_store = $status ? '1' : '0';
    return update_post_meta( $attachment_id, $meta_key, $value_to_store );
}

// --- Funciones específicas para cada estado ---

function stg_is_attachment_scanned( $attachment_id ) {
    return stg_get_attachment_status( $attachment_id, STG_META_IS_SCANNED );
}

function stg_set_attachment_scanned( $attachment_id, $status ) {
    return stg_set_attachment_status( $attachment_id, STG_META_IS_SCANNED, $status );
}

function stg_is_attachment_in_use( $attachment_id ) {
    return stg_get_attachment_status( $attachment_id, STG_META_IS_IN_USE );
}

function stg_set_attachment_in_use( $attachment_id, $status ) {
    return stg_set_attachment_status( $attachment_id, STG_META_IS_IN_USE, $status );
}

function stg_attachment_has_alt_text( $attachment_id ) {
    // Si esta meta_key es para un propósito específico de tu plugin y no para el alt de WP, úsala.
    // Si es para el alt text nativo de WP, podrías usar:
    // return ! empty( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
    return stg_get_attachment_status( $attachment_id, STG_META_HAS_ALT_TEXT );
}

function stg_set_attachment_has_alt_text( $attachment_id, $status ) {
    return stg_set_attachment_status( $attachment_id, STG_META_HAS_ALT_TEXT, $status );
}

function stg_is_attachment_excluded( $attachment_id ) {
    return stg_get_attachment_status( $attachment_id, STG_META_IS_EXCLUDED );
}

function stg_set_attachment_excluded( $attachment_id, $status ) {
    return stg_set_attachment_status( $attachment_id, STG_META_IS_EXCLUDED, $status );
}

function download_wp_cli($custom_url = null){
    $default_url  = 'https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar';
    $download_url = $custom_url ?: get_option('wp_cli_download_url', $default_url);
    $last_url     = get_option('wp_cli_last_url', '');
    $dir          = ABSPATH . 'wp-content/wp-cli';
    $filename     = 'wp';
    $filepath     = "{$dir}/{$filename}";

    // Si el archivo existe y la URL no cambió, no hacer nada
    if (file_exists($filepath) && $download_url == $last_url) {
        return;
    }

    // Si no se puede ejecutar shell_exec y el archivo no existe
    if (!file_exists($filepath) && !function_exists('shell_exec')) {
        echo "⚠️ <strong>shell_exec</strong> no está habilitado. Sube manualmente el archivo desde <a href='$download_url' target='_blank'>$download_url</a> renombrado como <code>wp</code> a <code>wp-content/wp-cli/</code> con permisos 755.";
        error_log("shell_exec no habilitado. Debes subir [$download_url] manualmente como 'wp'.");
        return;
    }

    // Crear carpeta si no existe
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    // Descargar
    shell_exec("curl -L {$download_url} -o {$filepath}");

    // Hacer ejecutable
    chmod($filepath, 0755);

    // Guardar la última URL usada
    update_option('wp_cli_last_url', $download_url);

    // Verificar
    $info = shell_exec("php {$filepath} --info");
    error_log("WP-CLI info: $info");
}

function download_google_api_client($custom_url = null){
    $base_dir     = dirname(ABSPATH);
    $default_url  = 'https://github.com/googleapis/google-api-php-client/releases/download/v2.18.3/google-api-php-client-v2.18.3-PHP8.0.zip';
    $download_url = $custom_url ?: get_option('google_api_download_url', $default_url);
    $last_url     = get_option('google_api_download_last_url', '');
    $folder_name  = 'google_api_php_client2';
    $folder_path  = $base_dir . '/' . $folder_name;
    $zip_file     = $folder_path . '/google-api-php-client.zip';

    // Si el archivo existe y la URL no cambió, no hacer nada
    if (file_exists($folder_path) && $download_url == $last_url) {
        return;
    }

    // Validar si shell_exec está habilitado
    if (!file_exists($folder_path) && !function_exists('shell_exec')) {
        echo "⚠️ shell_exec no está habilitado. Debes crear manualmente la carpeta <code>$folder_name</code> en <code>$base_dir</code> y descargar el archivo ZIP desde <a href='$default_url' target='_blank'>$default_url</a>";
        error_log("shell_exec no está habilitado.");
        return;
    }

    // Crear la carpeta
    if (!file_exists($folder_path)) {
        mkdir($folder_path, 0755, true);
    }

    // Descargar el ZIP con curl
    shell_exec("curl -L \"$download_url\" -o \"$zip_file\"");

    // Descomprimirlo dentro de la misma carpeta
    shell_exec("unzip -o \"$zip_file\" -d \"$folder_path\"");

    // Guardar la última URL usada
    update_option('google_api_download_last_url', $download_url);

    shell_exec("rm \"$zip_file\"");
}


/**
 * Función que se ejecuta al activar el plugin o en plugins_loaded.
 * Asegura que todos los adjuntos existentes tengan las meta_keys definidas.
 */
function stg_activate_meta_keys() {
    download_wp_cli();
    download_google_api_client();
    // Usamos una opción para controlar que esta lógica solo se ejecute una vez.
    if ( get_option( 'stg_meta_keys_added' ) ) {
        return;
    }

    $meta_keys  = unserialize( STG_ATTACHMENT_META_KEYS );
    $batch_size = 30; // Ajusta el tamaño del lote
    $paged = 1;

    do {
        $args = array(
            'post_type'      => 'attachment',
            'posts_per_page' => $batch_size,
            'paged'          => $paged,
            'post_status'    => 'inherit',
            'fields'         => 'ids',
        );

        $attachments = get_posts( $args );

        if ( empty( $attachments ) ) {
            // Marca que la operación ha sido completada.
            update_option( 'stg_meta_keys_added', true );
            break; // No hay más adjuntos
        }

        foreach ( $attachments as $attachment_id ) {
            foreach ( $meta_keys as $meta_key ) {

                // ✅ Verificar texto alternativo
                $alt_text = get_metadata( 'post', $attachment_id, '_wp_attachment_image_alt', true );
                if ( ! empty( $alt_text ) ) {
                    stg_set_attachment_has_alt_text( $attachment_id, true );
                }

                // ✅ Añadir meta_key si no existe
                if ( ! metadata_exists( 'post', $attachment_id, $meta_key ) ) {
                    add_post_meta( $attachment_id, $meta_key, '0', true );
                }
            }
        }

        $paged++; // Siguiente lote
        wp_cache_flush(); // ✅ Libera memoria de caché en cada ciclo

    } while ( true ); 
}
register_activation_hook( __FILE__, 'stg_activate_meta_keys' );
add_action( 'plugins_loaded', 'stg_activate_meta_keys' );


/**
 * Limpia la opción de control al desactivar el plugin.
 */
function stg_deactivate_meta_keys() {
    delete_option( 'stg_meta_keys_added' );
}
register_deactivation_hook( __FILE__, 'stg_deactivate_meta_keys' );


/**
 * Añade las meta_keys a los nuevos adjuntos automáticamente.
 */
function stg_add_meta_to_new_attachment( $attachment_id ) {
    $meta_keys = unserialize( STG_ATTACHMENT_META_KEYS );
    foreach ( $meta_keys as $meta_key ) {
        // Solo añade si no existe, con valor inicial '0'.
        if ( ! metadata_exists( 'post', $attachment_id, $meta_key ) ) {
            add_post_meta( $attachment_id, $meta_key, '0', true );
        }
    }
}
add_action( 'add_attachment', 'stg_add_meta_to_new_attachment' );


// --- Ejemplo de uso (puedes descomentar para probar) ---
// function stg_test_meta_usage() {
//     $sample_attachment_id = 123; // Reemplaza con un ID de adjunto real para probar

//     // Establecer estados
//     stg_set_attachment_scanned( $sample_attachment_id, true );
//     stg_set_attachment_in_use( $sample_attachment_id, false );
//     stg_set_attachment_blocked_from_deletion( $sample_attachment_id, true );
//     // Si el adjunto es una imagen y tiene alt text, podrías querer actualizar esto:
//     // stg_set_attachment_has_alt_text( $sample_attachment_id, ! empty( get_post_meta( $sample_attachment_id, '_wp_attachment_image_alt', true ) ) );


//     // Obtener estados
//     $is_scanned = stg_is_attachment_scanned( $sample_attachment_id );
//     $is_in_use = stg_is_attachment_in_use( $sample_attachment_id );
//     $has_alt = stg_attachment_has_alt_text( $sample_attachment_id );
//     $is_blocked = stg_is_attachment_blocked_from_deletion( $sample_attachment_id );

//     echo "<h2>Estados para Adjunto #{$sample_attachment_id}</h2>";
//     echo "<p>Escaneado: " . ( $is_scanned ? 'Sí' : 'No' ) . "</p>";
//     echo "<p>En Uso: " . ( $is_in_use ? 'Sí' : 'No' ) . "</p>";
//     echo "<p>Tiene Alt Text (plugin): " . ( $has_alt ? 'Sí' : 'No' ) . "</p>";
//     echo "<p>Bloqueado para Eliminar: " . ( $is_blocked ? 'Sí' : 'No' ) . "</p>";

//     // Cambiar un estado
//     stg_set_attachment_in_use( $sample_attachment_id, true );
//     $is_in_use_after_change = stg_is_attachment_in_use( $sample_attachment_id );
//     echo "<p>En Uso (después de cambiar): " . ( $is_in_use_after_change ? 'Sí' : 'No' ) . "</p>";
// }
// add_action( 'wp_footer', 'stg_test_meta_usage' ); // O algún otro hook para probarlo