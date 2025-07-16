<?php

/**
 * Helper function to get attachment ID from its file path or URL.
 * THIS FUNCTION MUST BE DEFINED AND ACCESSIBLE FOR check_attachment_in_content TO WORK.
 * You can place it in your theme's functions.php or a custom plugin.
 *
 * @param string $filepath The file path (relative to uploads dir) or URL of the attachment.
 * @return int|false The attachment ID on success, false on failure.
 */
function get_attachment_id_by_path($filepath) {
    global $wpdb;

    if (empty($filepath)) {
        return false;
    }

    // Try with attachment_url_to_postid if it's a full URL
    if (filter_var($filepath, FILTER_VALIDATE_URL)) {
        $attachment_id = attachment_url_to_postid($filepath);
        if ($attachment_id) {
            return (int) $attachment_id;
        }
    }

    $upload_dir = wp_upload_dir();
    $base_upload_dir = $upload_dir['basedir'];
    $uploads_url_base = $upload_dir['baseurl'];

    $relative_path = $filepath;

    // Normalize input filepath to a relative path if it's absolute or a full URL
    if (strpos($filepath, $base_upload_dir) === 0) {
        $relative_path = substr($filepath, strlen($base_upload_dir) + 1);
    } elseif (strpos($filepath, $uploads_url_base) === 0) {
        $relative_path = substr($filepath, strlen($uploads_url_base) + 1);
    }

    // Attempt to find by _wp_attached_file meta key (most reliable for server paths)
    $sql_meta = $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
        $relative_path
    );
    $attachment_id = $wpdb->get_var($sql_meta);
    if ($attachment_id) {
        return (int) $attachment_id;
    }

    // Fallback: Try to find by basename in _wp_attached_file (less precise but covers some cases)
    $filename = basename($relative_path);
    if (!empty($filename) && $filename !== $relative_path) {
        $sql_basename = $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($filename)
        );
        $attachment_id = $wpdb->get_var($sql_basename);
        if ($attachment_id) {
            return (int) $attachment_id;
        }
    }

    // Final fallback: Search in wp_posts guid field (for URLs or filenames)
    // Note: guid is a URL, so matching by basename is common here.
    if (!empty($filename)) {
        $sql_guid = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
            '%' . $wpdb->esc_like($filename) . '%'
        );
        $attachment_id = $wpdb->get_var($sql_guid);
        if ($attachment_id) {
            return (int) $attachment_id;
        }
    }

    return false;
}


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
function check_attachment_in_content($file_paths = [], $post_types = []) {
    global $wpdb;

    if (empty($file_paths)) {
        return [];
    }

    // Default post types if none are provided
    if (empty($post_types)) {
        $post_types = [
            'post','page','custom_post_type','lp_course','service','portfolio',
            'gva_event','gva_header','footer','team','elementskit_template',
            'elementskit_content','elementor_library'
        ];
    }

    // Generate placeholders for post types in the SQL IN clause
    $post_types_placeholders = implode(',', array_fill(0, count($post_types), '%s'));

    // Get WordPress upload directory info for flexible path matching
    $upload_dir = wp_upload_dir();
    $uploads_url_base = preg_quote($upload_dir['baseurl'], '/'); // Escapar la URL base para REGEXP

    // Build the REGEXP pattern for file paths
    $regexp_patterns = [];
    foreach ($file_paths as $path) {
        // 1. Full original path (escaped)
        $regexp_patterns[] = preg_quote($path, '/');

        // 2. Just the basename (filename) (escaped)
        $regexp_patterns[] = preg_quote(basename($path), '/');

        // 3. Path relative to wp-content/uploads/ (if it's a full URL)
        // This handles cases where post_content stores relative paths like '2024/07/image.jpg'
        // or full URLs like 'http://domain.com/wp-content/uploads/2024/07/image.jpg'
        if (strpos($path, $uploads_url_base) === 0) {
            $relative_path_from_url = substr($path, strlen($uploads_url_base));
            $regexp_patterns[] = preg_quote($relative_path_from_url, '/');
        }
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

    // --- DEBUGGING: Uncomment the line below to see the generated SQL query ---
    // error_log('SQL Query for check_attachment_in_content: ' . $query);

    $results = $wpdb->get_results($query, ARRAY_A);

    // Process results to find and map attachment IDs
    $found_attachments_map = []; // Map file_path to attachment_id

    foreach ($results as $row) {
        $content = $row['post_content_value'];

        foreach ($file_paths as $input_path) {
            // Check if the content contains the full path, basename, or relative path
            // We use strpos for a quick check before calling get_attachment_id_by_path,
            // as REGEXP already confirmed a match.
            if (strpos($content, $input_path) !== false ||
                strpos($content, basename($input_path)) !== false ||
                (isset($relative_path_from_url) && strpos($content, $relative_path_from_url) !== false)) {

                $attachment_id = get_attachment_id_by_path($input_path);
                if ($attachment_id) {
                    // Store the found ID, mapping it to the original input file path
                    $found_attachments_map[$input_path] = (int) $attachment_id;
                    // We found an ID for this input_path, no need to re-check this input_path
                    // against other content rows if it's already mapped.
                    break; // Exit inner foreach for input_path
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