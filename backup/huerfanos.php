<?php
add_action('admin_menu', function() {
    add_menu_page(
        'Eliminar archivos',
        'Eliminar archivos',
        'manage_options',
        'eliminar_archivos',
        'eliminar_archivos_callback'
    );
});

function eliminar_archivos_callback() { 
	$server_ip = $_SERVER['SERVER_ADDR'];
	$log_url   = "http://{$server_ip}/tmp_files/log_registros_eliminados.txt";
    echo "<div class='wrap'>";
    echo "<h2>Eiminar archivos</h2>";

    // Mostrar el formulario de subida
    echo "<form method='post' enctype='multipart/form-data' onsubmit=\"return confirm('¿Estás seguro de que deseas eliminar los archivos listados en este archivo? Esta acción no se puede deshacer.');\">";
    echo "<p>Selecciona un archivo TXT o CSV con las columnas 'url' (primera fila como encabezados):</p>";
    echo "<input type='file' name='data_file' accept='.txt,.csv'>"; // Cambiado a .txt y .csv
    echo "<p class='submit'><input type='submit' name='submit_file' id='submit' class='button button-primary' value='Eliminar archivos'></p>";
    echo "</form>";

    // Procesar el archivo si se ha subido
    if (isset($_POST['submit_file']) && isset($_FILES['data_file'])) {
        $file_info = $_FILES['data_file'];

        // Verificar si hubo un error en la subida
        if ($file_info['error'] !== UPLOAD_ERR_OK) {
            echo "<div class='notice notice-error'><p>Error al subir el archivo: " . esc_html($file_info['error']) . "</p></div>";
            return;
        } 

        // Verificar el tipo de archivo (solo como precaución, ya el 'accept' lo maneja)
        $file_mimes = array(
            'text/csv',
            'text/plain' // Los .txt a menudo se detectan como text/plain
        );

        if (in_array($file_info['type'], $file_mimes) || pathinfo($file_info['name'], PATHINFO_EXTENSION) === 'txt' || pathinfo($file_info['name'], PATHINFO_EXTENSION) === 'csv') { 
			$uploaded_file_path = $file_info['tmp_name'];

			// Renombramos el archivo para evitar colisiones 
			$destination_path = '/var/www/html/tmp_files/' . basename($file_info['name']);

			// Mover el archivo desde /tmp a /var/www/html/tmp_files/
			if (move_uploaded_file($uploaded_file_path, $destination_path)) {
				$cmd = "php /var/www/html/borrar_archivos.php " . escapeshellarg($destination_path) . " > /dev/null 2>&1 &";
				shell_exec($cmd);
				echo "<div class='notice notice-success'><p>Eliminación iniciada en segundo plano. Revisa el estado en unos minutos: <a target='_blank' href='" . esc_url($log_url) . "'>Ver registros</a></p></div>";
			} else {
				echo "<div class='notice notice-error'><p>Error al mover el archivo temporal a /var/www/html/tmp_files/</p></div>";
			}  
        } else {
            echo "<div class='notice notice-error'><p>Tipo de archivo no permitido. Por favor, sube un archivo CSV (.csv) o TXT (.txt).</p></div>";
        } 
    } 
}