<?php

function check_attachment_in_elementor($attachment_ids = [], $file_paths = [] ) {
    global $wpdb;
    // Construimos la tabla temporal
    $unionParts = [];
    foreach ($attachment_ids as $id) {
        $unionParts[] = $wpdb->prepare("SELECT %d AS attachment_id, NULL AS path", $id);
    }
    foreach ($file_paths as $path) {
        $unionParts[] = $wpdb->prepare("SELECT NULL AS attachment_id, %s AS path", $path);
    }
    $unionQuery = implode(" UNION ALL ", $unionParts);
    
    $unionParts = [];
    foreach ($attachment_ids as $id) {
        $unionParts[] = $wpdb->prepare("SELECT %d AS attachment_id, NULL AS path", $id);
    }
    foreach ($file_paths as $path) {
        $unionParts[] = $wpdb->prepare("SELECT NULL AS attachment_id, %s AS path", $path);
    }
    $unionQuery = implode(" UNION ALL ", $unionParts);
    
    // Consulta principal
    $sql = "
    SELECT 
        t.attachment_id,
        t.path,
        CASE 
            WHEN EXISTS (
                SELECT 1
                FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                WHERE p.post_type IN (
                    'post','page','custom_post_type','lp_course','service','portfolio',
                    'gva_event','gva_header','footer','team','elementskit_template',
                    'elementskit_content','elementor_library'
                )
                AND p.post_status IN ('publish','private','draft')
                AND (
                    (pm.meta_key = '_thumbnail_id' AND pm.meta_value = t.attachment_id)
                    OR (pm.meta_key IN('_elementor_data') AND t.attachment_id IS NOT NULL AND pm.meta_value REGEXP CONCAT('\"id\":', t.attachment_id))
                    OR (pm.meta_key IN('_elementor_data') AND t.path IS NOT NULL AND pm.meta_value REGEXP REPLACE(t.path, '/', '\\\\\\\\/'))
                    OR (pm.meta_key IN('_elementor_css','enclosure') AND t.path IS NOT NULL AND pm.meta_value REGEXP REPLACE(t.path, '/', '\\\\\\\\/'))
                )
            ) THEN 1
            ELSE 0
        END AS en_uso
    FROM (
        $unionQuery
    ) t;
    ";
    
    // Ejecutar consulta
    $results = $wpdb->get_results($sql, ARRAY_A);

    return $results;

}