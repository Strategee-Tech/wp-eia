<?php

/**
 * Searches for attachment IDs within the post_content of WordPress posts
 * based on provided file paths (URLs or relative paths).
 *
 * @param array $file_paths An array of file paths (URLs or relative paths) to search for.
 * @param array $post_types (Optional) An array of post types to search within. Defaults to common types.
 * @return array An associative array where keys are the input file paths and values are
 * the corresponding attachment IDs found (or 0 if not found).
 * Returns an empty array if no file paths are provided.
 */
function checkAttachmentInContent($file_paths = [],) {
    global $wpdb;

    if (empty($file_paths)) {
        return [];
    }

    $post_types = [
        'post','page','custom_post_type','lp_course','service','portfolio',
        'gva_event','gva_header','footer','team','elementskit_template',
        'elementskit_content','elementor_library' // Elementor-related post types often have content
    ];

    // Generate placeholders for post types in the SQL IN clause
    $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    // Build the REGEXP pattern for file paths (search for both full path and just filename)
    $regexp_patterns = [];
    foreach ($file_paths as $path) {
        // Escape the full path for REGEXP
        $regexp_patterns[] = preg_quote($path, '/');
        // Escape just the basename (filename) for REGEXP
        $regexp_patterns[] = preg_quote(basename($path), '/');
    }
    // Combine unique patterns with OR
    $regexp_string = implode('|', array_unique($regexp_patterns));

    // Construct the SQL query
    $sql = "
        SELECT p.ID AS post_id,
               p.post_content AS post_content_value
        FROM {$wpdb->posts} p
        WHERE p.post_type IN ($post_types_placeholders)
        AND p.post_status IN('publish','private','draft')
        AND p.post_content REGEXP %s
    ";

    // Prepare parameters for wpdb->prepare
    $params = $post_types; // Add post types first
    $params[] = $regexp_string; // Then add the REGEXP pattern

    // Prepare and execute the query
    $query = $wpdb->prepare($sql, $params);
    $results = $wpdb->get_results($query, ARRAY_A);

    // Process results to find and map attachment IDs
    $found_attachments_map = []; // Map file_path to attachment_id

    foreach ($results as $row) {
        $content = $row['post_content_value'];

        foreach ($file_paths as $input_path) {
            // Check if the content contains the full path or just the filename
            if (strpos($content, $input_path) !== false || strpos($content, basename($input_path)) !== false) {
                // If a match is found in the content, try to get the attachment ID
                // from the *original input path*.
                $attachment_id = get_attachment_id_by_path($input_path);
                if ($attachment_id) {
                    // Store the found ID, mapping it to the original input file path
                    $found_attachments_map[$input_path] = (int) $attachment_id;
                    // We found an ID for this input_path, no need to check other paths against this content
                    // or to re-find an ID for this input_path in other posts.
                    break;
                }
            }
        }
    }

    // Format the final output: 0 if not found, ID if found.
    $final_result = [];
    foreach ($file_paths as $path) {
        $final_result[$path] = isset($found_attachments_map[$path]) ? $found_attachments_map[$path] : 0;
    }

    return $final_result;
}