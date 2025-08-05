<?php
if (isset($_POST['gemini_api_key']) && check_admin_referer('guardar_config_gemini')) {
    update_option('gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
    update_option('gemini_api_url', sanitize_text_field($_POST['gemini_api_url']));
    update_option('user_auth', sanitize_text_field($_POST['user_auth']));
    update_option('pass_auth', sanitize_text_field($_POST['pass_auth']));
    update_option('gemini_prompt', sanitize_textarea_field($_POST['gemini_prompt']));
    echo '<div class="notice notice-success is-dismissible"><p>¡Configuración guardada!</p></div>';
}
$api_key   = get_option('gemini_api_key', '');
$api_url   = get_option('gemini_api_url', '');
$prompt    = get_option('gemini_prompt', '');
$user_auth = get_option('user_auth', '');
$pass_auth = get_option('pass_auth', '');

?>

<div class="wrap">
    <h1>Configuraciones</h1>
    <form method="post">
        <?php wp_nonce_field('guardar_config_gemini'); ?>

        <div class="form-field">
            <label for="credentials_auth"><strong>Autenticación Básica</strong></label><br>
        </div>

        <div class="form-field">
            <label for="user_auth"><strong>Usuario</strong></label><br>
            <input type="text" name="user_auth" id="user_auth" class="regular-text" value="<?php echo esc_attr($user_auth); ?>" />
        </div>

        <div class="form-field">
            <label for="pass_auth"><strong>Contraseña</strong></label><br>
            <input type="text" name="pass_auth" id="pass_auth" class="regular-text" value="<?php echo esc_attr($pass_auth); ?>" />
        </div>

        <hr>

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