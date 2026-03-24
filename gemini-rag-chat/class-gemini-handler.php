<?php
/**
 * Clase para manejar la lógica de Gemini
 * Separada del archivo principal para mejor organización
 */

if (!defined('ABSPATH')) exit;

class Gemini_RAG_Handler {
    
    private $db;
    public $api_key;
    
    public function __construct($db) {
        $this->db = $db;
        $this->api_key = get_option('gemini_api_key', '');
        
        if (empty($this->api_key)) {
            error_log('⚠️ API Key de Gemini no configurada');
        } else {
            error_log('✅ API Key de Gemini configurada');
        }
    }
    
    public function processQuery($question) {
        global $wpdb;
        $table_products = $this->db->getTables()['products'];
        $table_company = $this->db->getTables()['company'];

        $company_info = $wpdb->get_results(
            "SELECT * FROM $table_company 
             WHERE info_type IN ('empresa', 'ubicacion', 'contacto') 
             ORDER BY order_index ASC LIMIT 5"
        );

        $products = $this->searchProductsOptimized($question);
        $context = $this->buildContext($company_info, $products);
        $response = $this->callGemini($question, $context);
        
        return [
            'response' => $response,
            'context' => $context,
            'products_count' => count($products)
        ];
    }
    
    // ✅ ESTA ES LA FUNCIÓN QUE FUNCIONA - COPIADA DIRECTAMENTE DE TU CÓDIGO ORIGINAL
    private function searchProductsOptimized($query) {
        global $wpdb;
        $table_products = $this->db->getTables()['products'];
        
        $clean_query = mb_strtolower($query, 'UTF-8');
        $words = explode(' ', $clean_query);
        
        // Solo palabras con significado
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
    
    // ✅ BUILD CONTEXT MEJORADO CON DESCRIPCIÓN DETALLADA
    private function buildContext($company_info, $products) {
        $context = "--- INFORMACIÓN CORPORATIVA PBTechnologies ---\n";
        foreach ($company_info as $info) {
            $context .= "{$info->title}: {$info->content}\n: {$info->subcontent}\n";
        }
        
        $context .= "\n--- CATÁLOGO DETALLADO DE PRODUCTOS ---\n";
        if (!empty($products)) {
            foreach ($products as $p) {
                $context .= "===========================================\n";
                $context .= "PRODUCTO: {$p->product_name}\n";
                $context .= "MARCA: {$p->brand} | CATEGORÍA: {$p->category} | SUBCATEGORÍA: {$p->subcategory}\n";
                
                // DESCRIPCIÓN CORTA
                if (!empty($p->short_description)) {
                    $context .= "\n📝 DESCRIPCIÓN CORTA:\n{$p->short_description}\n";
                }
                
                // DESCRIPCIÓN LARGA - CRUCIAL PARA INFORMACIÓN DETALLADA
                if (!empty($p->long_description)) {
                    $context .= "\n📖 DESCRIPCIÓN DETALLADA:\n{$p->long_description}\n";
                }
                
                // ESPECIFICACIONES TÉCNICAS
                if (!empty($p->specifications)) {
                    $context .= "\n⚙️ ESPECIFICACIONES TÉCNICAS:\n{$p->specifications}\n";
                }
                
                $context .= "\n✅ ESTADO: " . ($p->availability ? $p->availability : 'Disponible') . "\n";
                $context .= "🔗 URL: {$p->product_url}\n";
                $context .= "===========================================\n\n";
            }
        } else {
            $context .= "No se encontraron productos coincidentes en nuestra base de datos oficial.\n";
        }
        
        return $context;
    }
    
    private function callGemini($question, $context) {
        if (empty($this->api_key)) {
            return "⚠️ Error de configuración: La API key de Gemini no está configurada.";
        }
        
        $model = "gemini-3.1-flash-lite-preview";
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $this->api_key;
        
        $prompt = $this->buildPrompt($question, $context);
        
        $body = [
            "contents" => [["parts" => [["text" => $prompt]]]],
            "generationConfig" => [
                "temperature" => 0.1,
                "maxOutputTokens" => 800
            ]
        ];
        
        $request = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode($body),
            'timeout' => 30
        ]);
        
        if (is_wp_error($request)) {
            return "Error de red. Por favor, intenta de nuevo.";
        }
        
        $data = json_decode(wp_remote_retrieve_body($request), true);
        $response = $data['candidates'][0]['content']['parts'][0]['text'] ?? "Lo siento, no puedo responder ahora.";
        
        return preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$2', $response);
    }
    
    private function buildPrompt($question, $context) {
        return "INSTRUCCIÓN DEL SISTEMA:
Eres el Ingeniero de Soporte Técnico de PBTechnologies S.R.L. (Bolivia).

REGLAS PARA COMPARACIONES:
1. Si el usuario pide comparar productos (ej. '¿Cuál es la diferencia entre el Ti5 y el Ti7?'), genera una TABLA comparativa clara.
2. Compara puntos clave basándote en las 'ESPECIFICACIONES TÉCNICAS'.
3. Indica claramente cuál es el modelo superior o para qué caso de uso se recomienda cada uno.
4. Al final de la comparación, proporciona las URLs de ambos productos.

REGLAS GENERALES:
- Usa siempre los datos del CONTEXTO DE INVENTARIO.
- Si un dato no está en las especificaciones, di 'Consultar con un asesor'.
- Tono profesional y experto.

CONTEXTO DE INVENTARIO:
$context

PREGUNTA DEL CLIENTE:
$question";
    }
    
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
}