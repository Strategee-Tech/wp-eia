<?php

if ( ! function_exists( 'wp_crop_image' ) ) {
    include( ABSPATH . 'wp-admin/includes/image.php' );
}


add_action( 'rest_api_init', 'wpil_register_csv_export_route' );

function wpil_register_csv_export_route() {
    register_rest_route( 'api/v1', '/regenerate-metadata', array(
        'methods'             => 'GET', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'regenerate_metadata',
        'permission_callback' => '__return_true',
        'args' => array(
            'attachment_id' => array(
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'ID del attachment.',
            ),
        ),
    )); 
}

function regenerate_metadata($request) {
    $attachment_id = $request->get_param('attachment_id');

    try {
        $metadata = wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id));
        update_post_meta($attachment_id, '_wp_attachment_metadata', $metadata);
        return new WP_REST_Response(array('status' => 'success', 'message' => 'Metadata regenerada correctamente'), 200);
    } catch (\Throwable $th) {
        return new WP_REST_Response(array('status' => 'error', 'message' => $th->getMessage()), 500);
    }
}