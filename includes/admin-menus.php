<?php
// Asegúrate de que esta constante esté definida antes de usarla
if ( ! defined( 'WP_EIA_PLUGIN_DIR' ) ) {
    // Esto es un fallback, si se incluye directamente, pero debería venir del archivo principal
    define( 'WP_EIA_PLUGIN_DIR', plugin_dir_path( dirname( __FILE__ ) ) . '/' );
}



add_action( 'admin_menu', 'eia_register_admin_menus' );

function eia_register_admin_menus() {
    $icon_url = WP_EIA_PLUGIN_URL . 'includes/assets/images/menu-icon.png'; 
    // Menú Principal
    add_menu_page(
        'STG Optimizer',
        'STG Optimizer',
        'manage_options',
        'stg-optimizer',
        'render_main', // Función callback para la página principal
        $icon_url,
        1
    );

    add_submenu_page(
        'stg-optimizer',          // Slug del menú padre
        'Configuración',          // Título de la página
        'Configuración',          // Título del submenú
        'manage_options',
        'stg-configuration',
        'render_configuration'
    );
}

// --- Funciones de renderizado (simplemente incluyen el archivo de la vista) ---

function render_main() {
    require_once WP_EIA_PLUGIN_DIR . 'includes/views/gallery.php';
}

function render_configuration() {
    require_once WP_EIA_PLUGIN_DIR . 'includes/views/configuration.php';
}