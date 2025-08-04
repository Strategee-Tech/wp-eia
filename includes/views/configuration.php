<?php
if (isset($_POST['gemini_api_key']) && check_admin_referer('guardar_config_gemini')) {
    update_option('gemini_api_key', sanitize_text_field($_POST['gemini_api_key']));
    update_option('gemini_prompt', sanitize_textarea_field($_POST['gemini_prompt']));
    echo '<div class="notice notice-success is-dismissible"><p>¡Configuración guardada!</p></div>';
}
$api_key = get_option('gemini_api_key', '');
$prompt  = get_option('gemini_prompt', '');
?>

<div class="wrap">
    <h1>Configuraciones</h1>
    <form method="post">
        <?php wp_nonce_field('guardar_config_gemini'); ?>
        <table class="form-table">
            <tr>
                <th><label for="gemini_api_key">API Key Gemini</label></th>
                <td><input type="text" name="gemini_api_key" id="gemini_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>" /></td>
            </tr>
            <tr>
                <th><label for="gemini_prompt">Prompt De Gemini</label></th>
                <td><textarea name="gemini_prompt" id="gemini_prompt" class="large-text" rows="5"><?php echo esc_textarea($prompt); ?></textarea></td>
            </tr>
        </table>
        <?php submit_button('Guardar configuración'); ?>
    </form>
</div>