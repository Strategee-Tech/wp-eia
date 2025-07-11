<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}

add_action( 'rest_api_init', 'wp_scan_image' );

date_default_timezone_set('America/Bogota');

