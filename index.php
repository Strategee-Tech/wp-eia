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

if ( ! defined( 'WP_EIA_PLUGIN_DIR' ) ) {
    define( 'WP_EIA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Configuración inicial de Stg Optimizer Plugin
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/configStgPlugin.php';

// Optimización de archivos
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/optimizar_archivos.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/utils/dontShowAlerts.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/admin-menus.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/api/seoOptimization.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/api/googleSheet.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/api/optimizationFiles.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/api/deleteFiles.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/api/scanFiles.php';
require_once WP_EIA_PLUGIN_DIR . 'includes/api/geminiImages.php';

