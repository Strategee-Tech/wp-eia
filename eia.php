<?php
/*
Plugin Name: wp-eia
Plugin URI:  https://www.strategee.us/eia-plugin
Description: Un plugin para el proyecto EIA.
Version:     1.0.8
Author:      Strategee
Author URI:  https://www.strategee.us
GitHub Plugin URI: https://github.com/VictorZRC/wp-eia
Text Domain: wp-eia
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Función para mostrar un mensaje de administración
function eia_admin_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p>Bienvenido al EIA Plugin</p>
    </div>
    <?php
}



// --- NUEVO CÓDIGO PARA EL MENÚ DE ADMINISTRACIÓN ---

// 1. Función para añadir la página de opciones al menú de administración
function eia_agregar_pagina_menu() {

    add_menu_page(
        'Configuración de EIA Plugin', // Título de la página
        'EIA Nuevo Plugin',                        // Título del menú
        'manage_options',                   // Capacidad requerida para ver el menú
        'eia-plugin',            // Slug único del menú
        'eia_contenido_pagina',// Función que renderiza el contenido de la página
        'dashicons-format-gallery',          // Icono del menú (puedes usar un Dashicon o una URL de imagen)
        99                                  // Posición en el menú (99 para que esté casi al final)
    );
}

add_action( 'admin_menu', 'eia_agregar_pagina_menu' );

// 2. Función que renderiza el contenido de la página de opciones
function eia_contenido_pagina() {

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'No tienes suficientes permisos para acceder a esta página.', 'wp-image-lister' ) );
    }

    $selected_folder    = isset( $_GET['folder'] ) ? sanitize_text_field( wp_unslash( $_GET['folder'] ) ) : sanitize_text_field( wp_unslash( '2025/06' ) );
    $selected_folder    = trim( $selected_folder, '/' );
    $showMiniatures     = isset( $_GET['miniatures'] ) ? true : false;

    $orderby            = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'size_bytes';
    $order              = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'desc';
    
    ?>









    <div class="wrap">

        <h1>Actualizaccción 4:48</h1>

        <form method="get" action="" id='form-filter'>

        

        </form>



    </div>
    <?php
}