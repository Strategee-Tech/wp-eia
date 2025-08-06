<?php
if (isset($_POST['gemini_api_key']) && check_admin_referer('guardar_config_gemini')) {
    update_option('gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
    update_option('gemini_api_url', sanitize_text_field($_POST['gemini_api_url']));
    update_option('user_auth', sanitize_text_field($_POST['user_auth']));
    update_option('pass_auth', sanitize_text_field($_POST['pass_auth']));
    update_option('gemini_prompt', sanitize_textarea_field($_POST['gemini_prompt']));
    update_option('google_sheet_id', sanitize_text_field($_POST['google_sheet_id']));
    update_option('name_sheet_images', sanitize_text_field($_POST['name_sheet_images']));
    update_option('name_sheet_files', sanitize_text_field($_POST['name_sheet_files']));
    update_option('name_sheet_deleteds', sanitize_text_field($_POST['name_sheet_deleteds']));
    update_option('wp_cli_download_url', sanitize_text_field($_POST['wp_cli_download_url']));
    echo '<div class="notice notice-success is-dismissible"><p>¡Configuración guardada!</p></div>';
}
$api_key   = get_option('gemini_api_key', '');
$api_url   = get_option('gemini_api_url', '');
$prompt    = get_option('gemini_prompt', '');
$user_auth = get_option('user_auth', '');
$pass_auth = get_option('pass_auth', '');
$google_sheet_id     = get_option('google_sheet_id', '');
$name_sheet_images   = get_option('name_sheet_images', '');
$name_sheet_files    = get_option('name_sheet_files', '');
$name_sheet_deleteds = get_option('name_sheet_deleteds', '');
$wp_cli_download_url = get_option('wp_cli_download_url');

?>

<div class="wrap">
    <h1>Configuraciones</h1>
    <form method="post">
        <?php wp_nonce_field('guardar_config_gemini'); ?>

        <div class="form-field" style="margin-top: 20px;">
            <label><strong>Información de autenticación Básica</strong></label><br>
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="user_auth"><strong>Usuario</strong></label><br>
            <input type="text" name="user_auth" id="user_auth" class="regular-text" value="<?php echo esc_attr($user_auth); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="pass_auth"><strong>Contraseña</strong></label><br>
            <input type="text" name="pass_auth" id="pass_auth" class="regular-text" value="<?php echo esc_attr($pass_auth); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label><strong>Completa la siguiente información si deseas guardar el seguimiento de optimización y eliminación de archivos en una hoja de Google Drive</strong></label><br>
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="google_sheet_id"><strong>Id de la hoja de Google Drive</strong></label><br>
            <input type="text" name="google_sheet_id" id="google_sheet_id" class="regular-text" value="<?php echo esc_attr($google_sheet_id); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="name_sheet_images"><strong>Nombre de la hoja para las imágenes</strong></label><br>
            <input type="text" name="name_sheet_images" id="name_sheet_images" class="regular-text" value="<?php echo esc_attr($name_sheet_images); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="name_sheet_files"><strong>Nombre de la hoja para los archivos (Multimedia y documentos)</strong></label><br>
            <input type="text" name="name_sheet_files" id="name_sheet_files" class="regular-text" value="<?php echo esc_attr($name_sheet_files); ?>" />
        </div>

        <p style="color: red;margin-top: 20px;">Nota: En caso de usar la funcionalidad de Google Drive, se debe compartir la hoja del drive al siguiente correo electrónico: <strong>automation-services@effortless-lock-294114.iam.gserviceaccount.com</strong> y establecer permisos de "Editor"</p>

        <div class="form-field" style="margin-top: 20px;">
            <label for="name_sheet_deleteds"><strong>Nombre de la hoja para los archivos eliminados</strong></label><br>
            <input type="text" name="name_sheet_deleteds" id="name_sheet_deleteds" class="regular-text" value="<?php echo esc_attr($name_sheet_deleteds); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label><strong>Modifica la url de WP-CLI solo si ha cambiado</strong></label><br>
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="wp_cli_download_url"><strong>Url WP-CLI</strong></label><br>
            <input type="text" name="wp_cli_download_url" id="wp_cli_download_url" class="regular-text" value="<?php echo esc_attr($wp_cli_download_url); ?>" />
        </div>

        <div class="form-field">
            <label for="gemini_api_key"><strong>API Key Gemini</strong></label><br>
            <input type="text" name="gemini_api_key" id="gemini_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="gemini_api_url"><strong>URL de la API de Gemini</strong><br><small>(e.g: https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite-preview-06-17:generateContent)</small></label><br>
            <input type="text" name="gemini_api_url" id="gemini_api_url" class="regular-text" value="<?php echo esc_attr($api_url); ?>" />
        </div>

        <div class="form-field" style="margin-top: 20px;">
            <label for="gemini_prompt"><strong>Prompt de Gemini</strong></label><br>
            <textarea name="gemini_prompt" id="gemini_prompt" class="large-text" rows="10"><?php echo esc_textarea($prompt); ?></textarea>
        </div>

        <div style="margin-top: 20px;">
            <?php submit_button('Guardar configuración'); ?>
        </div>
    </form>
</div>