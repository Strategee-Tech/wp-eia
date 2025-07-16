<?php
function get_attachment_id_by_path($path) {
    global $wpdb;
    $sql = $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
            WHERE meta_key = '_wp_attached_file' 
            AND meta_value = %s",
            $path
    );
    return $wpdb->get_var($sql);
}