<?php if (!defined('ABSPATH')) exit;

// Guardar configuración
if (isset($_POST['submit'])) {
    check_admin_referer('gemini_rag_settings');
    
    // Guardar cada API key individualmente (formato que espera el handler)
    update_option('gemini_api_key_1', sanitize_text_field($_POST['gemini_api_key_1']));
    update_option('gemini_api_key_2', sanitize_text_field($_POST['gemini_api_key_2']));
    update_option('gemini_api_key_3', sanitize_text_field($_POST['gemini_api_key_3']));
    update_option('gemini_api_key_4', sanitize_text_field($_POST['gemini_api_key_4']));
    update_option('gemini_api_key_5', sanitize_text_field($_POST['gemini_api_key_5']));
    
    echo '<div class="notice notice-success is-dismissible"><p>✅ Configuración guardada correctamente</p></div>';
}

// Cargar valores actuales
$api_key_1 = get_option('gemini_api_key_1', '');
$api_key_2 = get_option('gemini_api_key_2', '');
$api_key_3 = get_option('gemini_api_key_3', '');
$api_key_4 = get_option('gemini_api_key_4', '');
$api_key_5 = get_option('gemini_api_key_5', '');
?>

<div class="wrap">
    <h1>⚙️ Configuración de Gemini RAG Chat</h1>
    
    <div class="rag-settings-container">
        <div class="rag-settings-card">
            <h2>🔑 API Keys de Gemini</h2>
            <p>Configura las API Keys de Gemini AI. Puedes agregar hasta 5 keys para balancear carga y evitar límites de tasa.</p>
            
            <form method="post">
                <?php wp_nonce_field('gemini_rag_settings'); ?>
                
                <div class="keys-container">
                    <div class="key-field">
                        <label for="api_key_1">
                            API Key 1:
                            <span class="badge">Principal</span>
                        </label>
                        <input type="password" 
                               id="api_key_1"
                               name="gemini_api_key_1"
                               value="<?php echo esc_attr($api_key_1); ?>"
                               class="regular-text"
                               placeholder="AIzaSy...">
                        <button type="button" class="button toggle-key" data-target="api_key_1">
                            👁️ Mostrar
                        </button>
                    </div>
                    
                    <div class="key-field">
                        <label for="api_key_2">API Key 2:</label>
                        <input type="password" 
                               id="api_key_2"
                               name="gemini_api_key_2"
                               value="<?php echo esc_attr($api_key_2); ?>"
                               class="regular-text"
                               placeholder="AIzaSy...">
                        <button type="button" class="button toggle-key" data-target="api_key_2">
                            👁️ Mostrar
                        </button>
                    </div>
                    
                    <div class="key-field">
                        <label for="api_key_3">API Key 3:</label>
                        <input type="password" 
                               id="api_key_3"
                               name="gemini_api_key_3"
                               value="<?php echo esc_attr($api_key_3); ?>"
                               class="regular-text"
                               placeholder="AIzaSy...">
                        <button type="button" class="button toggle-key" data-target="api_key_3">
                            👁️ Mostrar
                        </button>
                    </div>
                    
                    <div class="key-field">
                        <label for="api_key_4">API Key 4:</label>
                        <input type="password" 
                               id="api_key_4"
                               name="gemini_api_key_4"
                               value="<?php echo esc_attr($api_key_4); ?>"
                               class="regular-text"
                               placeholder="AIzaSy...">
                        <button type="button" class="button toggle-key" data-target="api_key_4">
                            👁️ Mostrar
                        </button>
                    </div>
                    
                    <div class="key-field">
                        <label for="api_key_5">API Key 5:</label>
                        <input type="password" 
                               id="api_key_5"
                               name="gemini_api_key_5"
                               value="<?php echo esc_attr($api_key_5); ?>"
                               class="regular-text"
                               placeholder="AIzaSy...">
                        <button type="button" class="button toggle-key" data-target="api_key_5">
                            👁️ Mostrar
                        </button>
                    </div>
                </div>
                
                <div class="info-box">
                    <span class="info-icon">💡</span>
                    <div>
                        <strong>Consejo:</strong> Puedes obtener una API Key gratuita en 
                        <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a>
                    </div>
                </div>
                
                <div class="rag-form-actions">
                    <?php submit_button('Guardar configuración', 'primary', 'submit'); ?>
                </div>
            </form>
        </div>
        
        <div class="rag-settings-card">
            <h2>📊 Información del Sistema</h2>
            
            <table class="system-info">
                 <tr>
                    <th>Modelo utilizado:</th>
                    <td><code>gemini-2.5-flash</code></td>
                 </tr>
                 <tr>
                    <th>Versión del plugin:</th>
                    <td><?php echo defined('GEMINI_RAG_VERSION') ? GEMINI_RAG_VERSION : '1.0.0'; ?></td>
                 </tr>
                 <tr>
                    <th>WooCommerce:</th>
                    <td><?php echo class_exists('WooCommerce') ? '✅ Activo' : '❌ No detectado'; ?></td>
                 </tr>
                 <tr>
                    <th>Keys configuradas:</th>
                    <td>
                        <?php 
                        $keys = array_filter([
                            $api_key_1, $api_key_2, $api_key_3, $api_key_4, $api_key_5
                        ]);
                        $count = count($keys);
                        echo $count . ' de 5';
                        if ($count > 0) {
                            echo ' <span class="status-active">✓ Activas</span>';
                        }
                        ?>
                    </td>
                 </tr>
            </table>
        </div>
        
        <div class="rag-settings-card">
            <h2>🧪 Probar Conexión</h2>
            <p>Verifica que tus API Keys funcionen correctamente.</p>
            <button type="button" id="test-api-connection" class="button button-secondary">
                Probar conexión
            </button>
            <div id="test-result" style="margin-top: 15px; display: none;"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Mostrar/ocultar API keys
    $('.toggle-key').on('click', function() {
        var target = $(this).data('target');
        var input = $('#' + target);
        var type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        $(this).text(type === 'password' ? '👁️ Mostrar' : '🔒 Ocultar');
    });
    
    // Probar conexión API
    $('#test-api-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#test-result');
        
        $btn.prop('disabled', true).text('Probando...');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'test_gemini_connection',
                nonce: '<?php echo wp_create_nonce("test_gemini_connection"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                } else {
                    $result.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
                }
                $result.show();
            },
            error: function() {
                $result.html('<div class="notice notice-error"><p>❌ Error al conectar con el servidor</p></div>');
                $result.show();
            },
            complete: function() {
                $btn.prop('disabled', false).text('Probar conexión');
            }
        });
    });
});
</script>