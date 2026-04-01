<?php
/**
 * Clase para manejar la lógica de Gemini
 */

if (!defined('ABSPATH')) exit;

class Gemini_RAG_Handler {
    
    private $db;
    private $woo;
    private $api_keys = [];
private $current_key_index = 0;
    
    public function __construct($db) {
        $this->db = $db;
        $this->api_keys = array_filter([
    get_option('gemini_api_key_1', ''),
    get_option('gemini_api_key_2', ''),
    get_option('gemini_api_key_3', ''),
    get_option('gemini_api_key_4', ''),
    get_option('gemini_api_key_5', '')
]);
        
        // Inicializar WooCommerce handler si existe el archivo
        $woo_file = plugin_dir_path(__FILE__) . 'class-woocommerce.php';
        if (file_exists($woo_file)) {
            require_once $woo_file;
            $this->woo = new ChatRAG_WooCommerce();
        } else {
            $this->woo = null;
            error_log('⚠️ Archivo class-woocommerce.php no encontrado');
        }
        
        if ($this->woo && $this->woo->isWooActive()) {
            error_log('✅ Usando WooCommerce como fuente de productos');
        } else {
            error_log('⚠️ WooCommerce no activo, usando tabla personalizada');
        }
    }
    private function getNextApiKey() {

    $keys = $this->api_keys;

    if (empty($keys)) return null;

    // Obtener índice actual guardado
    $index = get_option('gemini_key_index', 0);

    // Seleccionar key
    $api_key = $keys[$index];

    // Calcular siguiente índice
    $next_index = ($index + 1) % count($keys);

    // Guardar para la siguiente request
    update_option('gemini_key_index', $next_index);
    error_log("🔑 Usando API KEY: " . substr($api_key, 0, 10));

    return $api_key;
}
    
    public function processQuery($question) {
    global $wpdb;
    $table_company = $this->db->getTables()['company'];
    
    // 1. INFO DE EMPRESA
    $company_info = $wpdb->get_results(
        "SELECT * FROM $table_company 
         WHERE info_type IN ('empresa', 'ubicacion', 'contacto', 'mision') 
         ORDER BY order_index ASC LIMIT 10"
    );
    
    // 2. PRODUCTOS
    $products = [];
    $context_products = "";
    if ($this->woo && $this->woo->isWooActive()) {
        $products = $this->woo->searchProducts($question, 10);
        $context_products = $this->woo->formatForGemini($products);
        error_log("📦 Productos encontrados: " . count($products));
    }
    
    // 3. EVENTOS 🔥
    $events = [];
    $context_events = "";
    if (class_exists('ChatRAG_EventsCalendar')) {
        $events_calendar = new ChatRAG_EventsCalendar();
        if ($events_calendar->isEventsCalendarActive()) {
            $events = $events_calendar->searchEvents($question, 5);
            $context_events = $events_calendar->formatForGemini($events);
            error_log("📅 Eventos encontrados: " . count($events));
        }
    }
    
    // 4. CONSTRUIR CONTEXTO
    $context_company = "--- INFORMACIÓN EMPRESA ---\n";
    foreach ($company_info as $info) {
        $context_company .= "{$info->title}: {$info->content}\n";
    }
    
    $full_context = $context_company;
    
    if (!empty($context_products)) {
        $full_context .= "\n--- CATÁLOGO DE PRODUCTOS ---\n" . $context_products;
    }
    
    if (!empty($context_events)) {
        $full_context .= "\n--- EVENTOS Y SEMINARIOS ---\n" . $context_events;
    }
    
    // 5. LLAMADA A GEMINI
    $response = $this->callGemini($question, $full_context);
    
    return [
        'response' => $this->formatResponse($response),
        'context' => $full_context,
        'products_count' => count($products),
        'events_count' => count($events)
    ];
}
    
    /**
     * Construir contexto con agrupación por categoría
     */
    private function buildContextFromWoo($company_info, $products) {
        $context = "--- INFORMACIÓN CORPORATIVA PBTechnologies ---\n";
        foreach ($company_info as $info) {
            $context .= "{$info->title}: {$info->content}\n: {$info->subcontent}\n";
        }
        
        $context .= "\n--- CATÁLOGO DETALLADO DE PRODUCTOS ---\n";
        
        if (!empty($products)) {
            // Agrupar productos por categoría para mejor organización
            $grouped = [];
            foreach ($products as $p) {
                $cat = !empty($p->categories) ? $p->categories : 'Otros';
                $grouped[$cat][] = $p;
            }
            
            foreach ($grouped as $category => $items) {
                $context .= "\n📁 CATEGORÍA: {$category}\n";
                $context .= str_repeat('-', 50) . "\n";
                
                foreach ($items as $p) {
                    $context .= "===========================================\n";
                    $context .= "🔹 PRODUCTO: {$p->product_name}\n";
                    $context .= "🏷️ MARCA: " . ($p->brand ?: 'No especificada') . "\n";
                    
                    if (!empty($p->short_description)) {
                        $short_desc = $this->sanitizeText($p->short_description);
                        $context .= "\n📝 DESCRIPCIÓN CORTA:\n" . substr($short_desc, 0, 800) . "\n";
                    }
                    
                    if (!empty($p->long_description)) {
                        $long_desc = $this->sanitizeText($p->long_description);
                        $context .= "\n📖 DESCRIPCIÓN:\n" . substr($long_desc, 0, 1000) . "\n";
                    }
                    
                    if (!empty($p->specifications)) {
                        $specs = $this->sanitizeText($p->specifications);
                        $context .= "\n⚙️ ESPECIFICACIONES TÉCNICAS:\n" . substr($specs, 0, 1000) . "\n";
                    }
                    $context .= "✅ ESTADO: {$p->availability}\n";
                    $context .= "🔗 URL: {$p->product_url}\n";
                    $context .= "===========================================\n\n";
                }
            }
        } else {
            $context .= "No se encontraron productos coincidentes en nuestro catálogo.\n";
        }
        
        return $context;
    }
    
    /**
     * Búsqueda en tabla personalizada (fallback)
     */
    private function searchProductsOptimized($query) {
        global $wpdb;
        $table_products = $this->db->getTables()['products'];
        
        $clean_query = mb_strtolower($query, 'UTF-8');
        $words = explode(' ', $clean_query);
        
        $words = array_filter($words, function($w) { 
            return strlen($w) > 3 && !in_array($w, ['para', 'tiene', 'tienen', 'ustedes', 'busco', 'hola']); 
        });

        if (!empty($words)) {
            $conditions = [];
            foreach ($words as $word) {
                $conditions[] = $wpdb->prepare(
                    "(product_name LIKE %s OR keywords LIKE %s OR category LIKE %s)", 
                    '%' . $wpdb->esc_like($word) . '%', 
                    '%' . $wpdb->esc_like($word) . '%',
                    '%' . $wpdb->esc_like($word) . '%'
                );
            }
            
            $sql = "SELECT * FROM $table_products WHERE " . implode(' AND ', $conditions) . " LIMIT 15";
            $products = $wpdb->get_results($sql);
            
            if (!empty($products)) return $products;

            $sql_or = "SELECT * FROM $table_products WHERE " . implode(' OR ', $conditions) . " LIMIT 15";
            $products = $wpdb->get_results($sql_or);
            if (!empty($products)) return $products;
        }

        return $wpdb->get_results("SELECT * FROM $table_products ORDER BY id DESC LIMIT 5");
    }
    private function updateKeyStats($api_key, $status) {

    $stats = get_option('gemini_api_key_stats', []);

    if (!isset($stats[$api_key])) {
        $stats[$api_key] = [
            'count' => 0,
            'status' => 'sin_uso',
            'last_used' => ''
        ];
    }

    $stats[$api_key]['count'] += 1;
    $stats[$api_key]['status'] = $status;
    $stats[$api_key]['last_used'] = current_time('mysql');

    update_option('gemini_api_key_stats', $stats);
}
    
    private function callGemini($question, $context) {
    if (empty($this->api_keys)) {
        return "⚠️ No hay API keys configuradas.";
    }

    $model = "gemini-2.5-flash";
    $prompt = $this->buildPrompt($question, $context);

    // 🔥 LOG DE CONTROL: Verifica que el prompt no salga vacío aquí
    error_log('Longitud del prompt antes de enviar: ' . strlen($prompt));

    $body = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 8182,
            "topP" => 0.95,
            "topK" => 20 // Bajamos un poco para asegurar estabilidad
        ]
    ];

    $body = $this->sanitizeArrayForJson($body);
    $json_body = json_encode($body, JSON_UNESCAPED_UNICODE);
    
    if ($json_body === false) {
        return "⚠️ Error interno: No se pudo procesar la consulta correctamente.";
    }

    $max_retries = count($this->api_keys);

    for ($i = 0; $i < $max_retries; $i++) {
        $api_key = $this->getNextApiKey();
        if (!$api_key) continue;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

        $args = [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $json_body,
            'timeout' => 200,
            'method'  => 'POST'
        ];

        error_log("📤 Enviando a Gemini con API key: " . substr($api_key, 0, 10));

        $request = wp_remote_post($url, $args);

        if (is_wp_error($request)) {
            error_log("❌ WP Error: " . $request->get_error_message());
            $this->updateKeyStats($api_key, 'error');
            continue;
        }

        $code = wp_remote_retrieve_response_code($request);
        $body_response = wp_remote_retrieve_body($request);
        
        error_log("📥 Respuesta código: " . $code);
        
        error_log("📝 RESPUESTA COMPLETA DE GEMINI:");
        error_log("===========================================");
        error_log($body_response);
        error_log("===========================================");

        if ($code == 429 || $code == 403) {
            error_log("⚠️ Rate limit excedida");
            $this->updateKeyStats($api_key, 'rate_limit');
            continue;
        }

        if ($code !== 200) {
            error_log("❌ Error código HTTP: " . $code);
            error_log("❌ Respuesta: " . substr($body_response, 0, 500));
            $this->updateKeyStats($api_key, 'error');
            continue;
        }

        $data = json_decode($body_response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("❌ Error decodificando JSON: " . json_last_error_msg());
            continue;
        }

        $response_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($response_text) {
            $this->updateKeyStats($api_key, 'activa');
            
            // 🔥 MOSTRAR EN LOG DE PHP EL TEXTO COMPLETO
            error_log("📝 TEXTO COMPLETO DE RESPUESTA:");
            error_log("===========================================");
            error_log($response_text);
            error_log("===========================================");
            error_log("📏 Longitud: " . strlen($response_text) . " caracteres");
            
            return trim($response_text);
        }
    }

    return "⚠️ No disponible La Consulta Con el Asistente. Si quieres Comunicarte A los Siguientes numero: WhatsApp al +591 710 33004 , llamarnos al +591 3 3454600 o visitarnos en nuestra oficina en Av. Cristo Redentor, C. Cosorio 2015, Santa Cruz de la Sierra.";
}

/**
 * 🔥 NUEVA FUNCIÓN: Sanitizar array recursivamente para JSON
 */
private function sanitizeArrayForJson($array) {
    array_walk_recursive($array, function(&$item) {
        if (is_string($item)) {
            // Eliminar caracteres no UTF-8
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
            // Eliminar caracteres de control excepto saltos de línea y tabs
            $item = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $item);
            // Reemplazar caracteres inválidos
            $item = preg_replace('/[^\P{C}\n\r\t]/u', '?', $item);
        }
    });
    return $array;
}
    
    private function sanitizeText($text) {
    if (empty($text)) return '';
    
    // Convertir a UTF-8 de manera más robusta
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'auto');
    }
    
    // 🔥 FORZAR A UTF-8 VÁLIDO
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    
    // Eliminar caracteres de control no deseados (manteniendo \n, \r, \t)
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    
    // Reemplazar caracteres problemáticos que no son imprimibles
    $text = preg_replace('/[^\P{C}\n\r\t]/u', '?', $text);
    
    // Limitar longitud para evitar problemas
    $text = substr($text, 0, 20000);
    
    return trim($text);
}
    
private function buildPrompt($question, $context) {
    return "Estás actuando como Ingeniero de Soporte de PBTechnologies (Bolivia).
    
    ### OBJETO DEL SISTEMA ###
    Tu tarea es entender la necesidad del cliente y recomendar los equipos más adecuados del catálogo.
Si hay varias opciones válidas, debes COMPARARLAS de forma clara y ayudar al cliente a elegir según el nivel de uso:.

    ### REGLAS DE ANÁLISIS DINÁMICO (ESTRICTAS) ###
    1. **NO INVENTAR:** Si el producto no está en el catálogo, di: 'Actualmente no contamos con ese equipo específico en nuestro catálogo digital'. No menciones marcas externas como Bosch o Makita si no están en el texto.
    2. **ANÁLISIS DE ACCIÓN:** Extrae el 'VERBO' o 'NECESIDAD' (fuga, consumo, temperatura, corte) y busca el equipo que lo resuelva.
    3. **PRIORIDAD:** Si un equipo menciona la palabra clave (ej: 'fuga') en su descripción técnica, elígelo aunque esté al final de la lista.
    4. **PROHIBIDO TABLAS:** No uses tablas bajo ninguna circunstancia (rompen el chat). Usa solo LISTAS CON VIÑETAS.
    5. **CERO PRECIOS:** No menciones montos. Di siempre 'Precio a consultar'.
    6. Si es un saludo Responde el saludo de manera cortes.
    7. invita a consultar solo cuando te pidan numero o direccion, No en todos los mensajes: por WhatsApp, numero de contacto y direccion de la oficina

    ### FORMATO DE RESPUESTA REQUERIDO ###
    Si encuentras coincidencia, responde así:
    'Sí, en PBTechnologies contamos con [Producto] ideal para [Aplicación]:
    * **[Nombre Producto]**: [Breve descripción de por qué sirve]
    * **Característica clave**: [Dato técnico]
    * **[Ideal para]**: [tipo de trabajo o usuario]
    * 🔗 **Ver detalles**: [URL]'

    Si el cliente menciona compara 2 o más productos específicos, responde así:

Comparación entre los equipos:

* **[Producto 1]**
  - Ideal para: [tipo de trabajo]
  - Ventaja principal: [punto fuerte]
  - Limitación: [en qué se queda corto]

* **[Producto 2]**
  - Ideal para: [tipo de trabajo]
  - Ventaja principal: [diferencia clave]
  - Limitación: [punto débil]

**Diferencia clave:**
[Explica en lenguaje simple la diferencia más importante]

**¿Cuál elegir?**
[Recomendación directa según tipo de usuario o necesidad]

    ### CATÁLOGO COMPLETO ###
    {$context}

    ### PREGUNTA DEL CLIENTE ###
    '{$question}'

    ### RESPUESTA TÉCNICA (PBTechnologies - Solo Listas): ###";
}
    
    /**
     * Registrar consulta en logs
     */
    public function logQuery($question, $products_count, $response_length) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . 'rag_query_logs', [
            'question' => substr($question, 0, 500),
            'products_found' => $products_count,
            'response_length' => $response_length,
            'user_ip' => $_SERVER['REMOTE_ADDR'],
            'created_at' => current_time('mysql')
        ]);
    }

/**
 * Formatear respuesta - solo limpiar texto, NO convertir links
 */
/**
 * Formatear respuesta - limpiar espacios y formatear
 */
private function formatResponse($response) {
    // 1. 🔥 ELIMINAR SALTOS DE LÍNEA AL INICIO Y FINAL
    $response = ltrim($response, "\n\r");
    $response = rtrim($response);
    
    // 2. Eliminar espacios en blanco excesivos
    $response = preg_replace("/\n{3,}/", "\n\n", $response);
    $response = preg_replace("/[\t]+/", "", $response);
    
    // 3. Eliminar líneas vacías al inicio
    $response = preg_replace('/^\s*\n/', '', $response);
    
    // 4. Eliminar HTML corrupto
    $response = preg_replace('/<a[^>]*>.*?<\/a>/i', '', $response);
    $response = preg_replace('/" target="blank"[^>]*>/i', '', $response);
    $response = preg_replace('/rel="noopener noreferrer"[^>]*>/i', '', $response);
    $response = preg_replace('/class="product-link"[^>]*>/i', '', $response);
    
    // 5. Eliminar texto "🔗 URL:" sobrante
    $response = preg_replace('/🔗\s*URL:\s*/i', '', $response);
    
    // 6. 🔥 ELIMINAR LÍNEAS VACÍAS DESPUÉS DEL TÍTULO
    $response = preg_replace('/(Sí, en PBTechnologies.*?)\n\s*\n/', "$1\n", $response);
    
    // 7. Convertir saltos de línea a <br>
    $response = nl2br($response);
    
    // 8. Formatear negritas
    $response = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $response);
    
    // 9. 🔥 ELIMINAR BR AL INICIO
    $response = preg_replace('/^(<br\s*\/?>\s*)+/', '', $response);
    
    // 10. Reducir BR duplicados
    $response = preg_replace('/(<br\s*\/?>\s*){3,}/', '<br><br>', $response);
    
    return $response;
}
/**
 * AJAX handler para probar conexión con Gemini API
 */
public function test_gemini_connection() {
    // Verificar nonce
    if (!check_ajax_referer('test_gemini_connection', 'nonce', false)) {
        wp_send_json_error('Nonce inválido');
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('No tienes permisos');
    }
    
    // Obtener API keys configuradas
    $api_keys = array_filter([
        get_option('gemini_api_key_1', ''),
        get_option('gemini_api_key_2', ''),
        get_option('gemini_api_key_3', ''),
        get_option('gemini_api_key_4', ''),
        get_option('gemini_api_key_5', '')
    ]);
    
    if (empty($api_keys)) {
        wp_send_json_error('No hay API keys configuradas. Ve a Configuración y agrega al menos una API key.');
    }
    
    // Probar la primera key
    $api_key = reset($api_keys);
    
    $url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
    
    $response = wp_remote_get($url, ['timeout' => 10]);
    
    if (is_wp_error($response)) {
        wp_send_json_error('Error de conexión: ' . $response->get_error_message());
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        wp_send_json_error('API Key inválida: ' . ($body['error']['message'] ?? 'Error desconocido'));
    }
    
    if (isset($body['models'])) {
        wp_send_json_success('Conexión exitosa! API Key válida. Modelos disponibles: ' . count($body['models']));
    }
    
    wp_send_json_error('Respuesta inesperada de la API');
}
}