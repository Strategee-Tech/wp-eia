<?php
function get_thumbnail($id) {
    global $wpdb;

    $sql = $wpdb->prepare(
        "SELECT pm.post_id 
         FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_thumbnail_id'
           AND pm.meta_value = %d
           AND p.post_status IN ('publish','private','draft')
         LIMIT 1",
        $id
    );

    // Obtenemos un valor
    $result = $wpdb->get_var($sql);

    // Si existe, devuelve true, si no false
    return $result ? true : false;



    
}
