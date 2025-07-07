<?php
add_action( 'rest_api_init', 'wpil_register_csv_export_route' );

function wpil_register_csv_export_route() {
    register_rest_route( 'api/v1', '/regenerate-metadata', array(
        'methods'             => 'GET', // Usamos GET ya que solo estamos recuperando datos
        'callback'            => 'regenerate_metadata',
        'permission_callback' => function() {
            return current_user_can( 'manage_options' );
        },
        'args' => array(
            'attachment_id' => array(
                'required'          => '__return_true',
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => 'ID del attachment.',
            ),
        ),
    )); 
}

function regenerate_metadata($request) {
    $attachment_id = $request->get_param('attachment_id');
    
    wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, get_attached_file($attachment_id)));
    return new WP_REST_Response(array('status' => 'success'), 200);
}