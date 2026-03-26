<?php
/**
 * Clase para manejar productos de WooCommerce vía VIEW
 */

if (!defined('ABSPATH')) exit;

class ChatRAG_WooCommerce {
    
    private $wpdb;
    private $view_name;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->view_name = $wpdb->prefix . 'rag_woo_products';
        
        if (!class_exists('WooCommerce')) {
            error_log('⚠️ ChatRAG_WooCommerce: WooCommerce no está activo');
            return;
        }
        
        $this->ensureViewExists();
    }
    
    public function isWooActive() {
        return class_exists('WooCommerce');
    }
    
    private function ensureViewExists() {
        $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->view_name}'");
        if (!$view_exists) {
            $this->createView();
        }
    }
    
    /**
     * 🔥 MEJORADA: Crear VIEW con todas las especificaciones posibles
     */
    public function createView() {
        $view_name = $this->view_name;
        
        $sql = "
        CREATE OR REPLACE VIEW $view_name AS
        SELECT 
            p.ID as id,
            p.post_title as product_name,
            p.post_content as long_description,
            p.post_excerpt as short_description,
            
            MAX(CASE WHEN pm.meta_key = '_price' THEN pm.meta_value END) as price,
            MAX(CASE WHEN pm.meta_key = '_stock' THEN pm.meta_value END) as stock,
            MAX(CASE WHEN pm.meta_key = '_sku' THEN pm.meta_value END) as sku,
            
            CASE 
                WHEN MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) = 'instock' THEN 'Disponible'
                WHEN MAX(CASE WHEN pm.meta_key = '_stock_status' THEN pm.meta_value END) = 'outofstock' THEN 'Agotado'
                ELSE 'Consultar'
            END as availability,
            
            -- 🔥 COMBINAR TODAS LAS FUENTES DE ESPECIFICACIONES
            GROUP_CONCAT(DISTINCT 
                CASE 
                    WHEN pm.meta_key = 'yikes_woo_products_tabs' THEN 
                        SUBSTRING_INDEX(SUBSTRING_INDEX(pm.meta_value, 'content\";s:', -1), '\";', 1)
                    WHEN pm.meta_key = '_product_attributes' THEN 
                        REPLACE(REPLACE(pm.meta_value, 's:', ' '), ';', ': ')
                    WHEN pm.meta_key = '_specifications' THEN pm.meta_value
                    WHEN pm.meta_key = '_technical_details' THEN pm.meta_value
                    WHEN pm.meta_key LIKE 'pa_%' THEN 
                        CONCAT(REPLACE(pm.meta_key, 'pa_', ''), ': ', pm.meta_value)
                    ELSE NULL
                END 
                SEPARATOR '\n'
            ) as specifications,
            
            (SELECT GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ')
             FROM {$this->wpdb->term_relationships} tr
             INNER JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id
             WHERE tr.object_id = p.ID
             AND tt.taxonomy IN ('product_brand', 'pa_marca', 'marca', 'brand')) as brand,
            
            (SELECT GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ')
             FROM {$this->wpdb->term_relationships} tr
             INNER JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id
             WHERE tr.object_id = p.ID
             AND tt.taxonomy = 'product_cat') as categories,
            
            CONCAT('" . home_url() . "/producto/', p.post_name) as product_url,
            
            p.post_date as created_at

        FROM {$this->wpdb->posts} p
        LEFT JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'product'
        AND p.post_status = 'publish'
        GROUP BY p.ID
        ";
        
        $this->wpdb->query($sql);
        error_log('✅ VIEW WooCommerce actualizada');
    }
    
    public function searchProducts($query, $limit = 100) {
    if (!$this->isWooActive()) {
        return [];
    }
    
    // 🔥 LIMPIAR LA CONSULTA - eliminar signos de puntuación
    $clean_query = mb_strtolower($query, 'UTF-8');
    $clean_query = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $clean_query); // Eliminar todo excepto letras, números y espacios
    $clean_query = trim($clean_query);
    
    error_log('🔍 Búsqueda WooCommerce - Consulta original: ' . $query);
    error_log('🔍 Búsqueda WooCommerce - Consulta limpia: ' . $clean_query);
    
    // Extraer palabras clave
    $words = explode(' ', $clean_query);
    
    // Stopwords
    $stopwords = ['para', 'tiene', 'tienen', 'ustedes', 'busco', 'hola', 'quiero', 'necesito', 
                  'como', 'una', 'un', 'el', 'la', 'los', 'las', 'me', 'te', 'se', 'con', 'por', 
                  'que', 'cual', 'donde', 'cuando', 'porque', 'entre', 'contra', 'versus', 'vs',
                  'cual', 'diferencia', 'tienen', 'tiene', 'pueden', 'puede', 'para', 'que',
                  'usted', 'ustedes', 'su', 'sus', 'del', 'al', 'lo', 'mi', 'tu'];
    
    $words = array_filter($words, function($w) use ($stopwords) { 
        return !empty($w) && strlen($w) > 2 && !in_array($w, $stopwords);
    });
    $words = array_unique($words);
    
    error_log('📝 Palabras clave: ' . implode(', ', $words));
    
    // Si no hay palabras clave, no mostrar nada
    if (empty($words)) {
        error_log('⚠️ Sin palabras clave válidas');
        return [];
    }
    
    // BUSCAR PRODUCTOS
    $products = $this->searchWithRelevance($words);
    
    if (!empty($products)) {
        // Ordenar por relevancia
        usort($products, function($a, $b) {
            return $b->relevance_score - $a->relevance_score;
        });
        
        // Mostrar los más relevantes
        $products = array_slice($products, 0, 10);
        error_log('✅ Encontrados ' . count($products) . ' productos');
        
        return $this->prepareProducts($products);
    }
    
    error_log('⚠️ No se encontraron productos');
    return [];
}
    
 /**
 * 🔥 DINÁMICO: Calcular relevancia de un producto
 */
private function calculateRelevance($product, $words) {
    $score = 0;
    $text = strtolower(
        $product->product_name . ' ' . 
        $product->brand . ' ' . 
        $product->categories . ' ' . 
        $product->short_description . ' ' . 
        $product->long_description . ' ' . 
        $product->specifications
    );
    
    $match_count = 0;
    
    foreach ($words as $word) {
        $word_lower = strtolower($word);
        
        // 🔥 BONUS GRANDE si la palabra está en el NOMBRE
        if (stripos($product->product_name, $word_lower) !== false) {
            $score += 50;
            $match_count++;
        }
        // Coincidencia exacta en cualquier campo
        elseif (preg_match('/\b' . preg_quote($word_lower, '/') . '\b/', $text)) {
            $score += 25;
            $match_count++;
        }
        // Coincidencia parcial
        elseif (strpos($text, $word_lower) !== false) {
            $score += 10;
            $match_count++;
        }
        
        // 🔥 Bonus si está en categorías
        if (stripos($product->categories, $word_lower) !== false) {
            $score += 20;
        }
    }
    
    // Bonus proporcional
    if (count($words) > 0) {
        $score += ($match_count / count($words)) * 30;
    }
    
    return $score;
}

private function searchWithRelevance($words) {
    $fields = ['product_name', 'brand', 'categories', 'short_description', 'long_description', 'specifications'];
    
    $conditions = [];
    $params = [];
    
    foreach ($words as $word) {
        // 🔥 ESCAPAR CORRECTAMENTE para LIKE
        $escaped_word = $this->wpdb->esc_like($word);
        
        $field_conditions = [];
        foreach ($fields as $field) {
            $field_conditions[] = "{$field} LIKE %s";
            $params[] = '%' . $escaped_word . '%';
        }
        $conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
    }
    
    $sql = "SELECT * FROM {$this->view_name} 
            WHERE " . implode(' OR ', $conditions);
    
    error_log('🔍 SQL BÚSQUEDA: ' . $sql);
    error_log('🔍 PALABRAS: ' . implode(', ', $words));
    
    $products = $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    
    error_log('🔍 TOTAL PRODUCTOS ENCONTRADOS: ' . count($products));
    
    // Si no hay productos con LIKE, intentar búsqueda más flexible
    if (empty($products)) {
        error_log('🔍 Intentando búsqueda flexible...');
        $flexible_conditions = [];
        $flexible_params = [];
        
        foreach ($words as $word) {
            // Buscar palabras similares (primeros 5 caracteres)
            $short_word = substr($word, 0, 5);
            $flexible_conditions[] = "(product_name LIKE %s OR short_description LIKE %s)";
            $flexible_params[] = '%' . $this->wpdb->esc_like($short_word) . '%';
            $flexible_params[] = '%' . $this->wpdb->esc_like($short_word) . '%';
        }
        
        $flexible_sql = "SELECT * FROM {$this->view_name} 
                         WHERE " . implode(' OR ', $flexible_conditions);
        
        $products = $this->wpdb->get_results($this->wpdb->prepare($flexible_sql, $flexible_params));
        error_log('🔍 PRODUCTOS ENCONTRADOS (búsqueda flexible): ' . count($products));
    }
    
    // Calcular relevancia
    foreach ($products as $product) {
        $product->relevance_score = $this->calculateRelevance($product, $words);
        error_log('📊 ' . $product->product_name . ' - Score: ' . $product->relevance_score);
    }
    
    // Filtrar productos con score > 0
    $products = array_filter($products, function($p) {
        return $p->relevance_score > 0;
    });
    
    usort($products, function($a, $b) {
        return $b->relevance_score - $a->relevance_score;
    });
    
    error_log('✅ PRODUCTOS RELEVANTES: ' . count($products));
    
    return $products;
}
    
    /**
 * Preparar productos - NO eliminar información importante
 */
private function prepareProducts($products) {
    foreach ($products as $product) {
        // Limpiar HTML de TODOS los campos
        $product->product_name = $this->cleanHtml($product->product_name);
        $product->brand = $this->cleanHtml($product->brand);
        $product->categories = $this->cleanHtml($product->categories);
        
        // 🔥 IMPORTANTE: Limpiar descripciones pero mantener el texto
        if (!empty($product->short_description)) {
            $product->short_description = $this->cleanHtml($product->short_description);
        }
        
        if (!empty($product->long_description)) {
            $product->long_description = $this->cleanHtml($product->long_description);
        }
        
        if (!empty($product->specifications)) {
            $product->specifications = $this->cleanHtml($product->specifications);
        }
        
        // Arreglar URL
        if (strpos($product->product_url, 'home_url') !== false) {
            $product->product_url = str_replace('" . home_url() . "', home_url(), $product->product_url);
        }
    }
    return $products;
}

private function cleanHtml($html) {
    if (empty($html)) return '';
    
    // Eliminar tags HTML
    $text = wp_strip_all_tags($html);
    
    // Decodificar entidades HTML
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    
    // Eliminar caracteres especiales
    $text = preg_replace('/[^\p{L}\p{N}\s\.\,\-\/\°\%]/u', ' ', $text);
    
    // Normalizar espacios
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}
    
    /**
     * 🔥 NUEVO: Extraer especificaciones de la descripción cuando no hay campo specifications
     */
    private function extractSpecificationsFromDescription($product) {
        $specs = [];
        $text = $product->short_description . ' ' . $product->long_description;
        
        // Patrones para extraer características técnicas
        $patterns = [
            'corriente' => '/(\d+(?:[.,]\d+)?)\s*(A|Amperios|Amperes|AC|DC)/i',
            'voltaje' => '/(\d+(?:[.,]\d+)?)\s*(V|Voltios|Volts)/i',
            'rango' => '/(\d+(?:[.,]\d+)?)\s*-\s*(\d+(?:[.,]\d+)?)\s*([A-Za-z°]+)/i',
            'precisión' => '/±\s*(\d+(?:[.,]\d+)?\s*%?)/i',
            'resolución' => '/(\d+\s*[xX*]\s*\d+)/i',
            'temperatura' => '/-?\d+(?:[.,]\d+)?\s*°?\s*C/i',
            'medición' => '/mide|medir|medición|rango\s+de\s+medición/i'
        ];
        
        $found = [];
        foreach ($patterns as $key => $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $found[] = ucfirst($key) . ': ' . implode(', ', array_unique($matches[0]));
            }
        }
        
        // Extraer características destacadas
        if (preg_match('/(hasta|max|máx)\s+(\d+(?:[.,]\d+)?)\s*(A|V|W)/i', $text, $match)) {
            $found[] = 'Máximo: ' . $match[2] . ' ' . $match[3];
        }
        
        if (!empty($found)) {
            return implode(' | ', $found);
        }
        
        return 'No especificadas en catálogo, consultar ficha técnica';
    }
    
    /**
 * 🔥 MEJORADA: Formatear productos para Gemini con descripciones COMPLETAS
 */
public function formatForGemini($products) {
    if (empty($products)) return '';

    $output = '';
    $index = 1;

    foreach ($products as $p) {
        $output .= "===========================================\n";
        $output .= "PRODUCTO {$index}: {$p->product_name}\n";
        $output .= "===========================================\n";
        
        if (!empty($p->brand)) {
            $output .= "MARCA: {$p->brand}\n";
        }
        
        if (!empty($p->categories)) {
            $output .= "CATEGORÍA: {$p->categories}\n";
        }
        
        // 🔥 ESPECIFICACIONES TÉCNICAS - mostrar TODO, no solo números de folletos
        if (!empty($p->specifications) && $p->specifications !== 'a:0:{}') {
            // Limpiar números de folletos y contenido serializado
            $clean_specs = preg_replace('/\d+:"\s*[^"]*"\s*|\d+:/', '', $p->specifications);
            $clean_specs = preg_replace('/[{};]/', '', $clean_specs);
            $clean_specs = trim($clean_specs);
            
            if (!empty($clean_specs) && strlen($clean_specs) > 10) {
                $output .= "\n⚙️ ESPECIFICACIONES TÉCNICAS:\n";
                $output .= $clean_specs . "\n";
            }
        }
        
        // 🔥 DESCRIPCIÓN CORTA - COMPLETA, no truncada
        if (!empty($p->short_description)) {
            $output .= "\n📝 DESCRIPCIÓN CORTA:\n";
            $output .= $p->short_description . "\n";
        }
        
        // 🔥 DESCRIPCIÓN LARGA - COMPLETA, ¡esto es lo más importante!
        if (!empty($p->long_description)) {
            $output .= "\n📖 DESCRIPCIÓN COMPLETA:\n";
            $output .= $p->long_description . "\n";
        }
        
        // Si no hay descripciones, mostrar al menos las características extraídas
        if (empty($p->short_description) && empty($p->long_description)) {
            $output .= "\n📝 CARACTERÍSTICAS:\n";
            $features = $this->extractFeaturesFromSpecs($p->specifications);
            $output .= $features . "\n";
        }
        
        $output .= "✅ DISPONIBILIDAD: {$p->availability}\n";
        $output .= "🔗 URL: {$p->product_url}\n";
        $output .= "===========================================\n\n";
        
        $index++;
        if ($index > 15) break;
    }
    
    return $output;
}

/**
 * Extraer características de especificaciones cuando no hay descripciones
 */
private function extractFeaturesFromSpecs($specs) {
    if (empty($specs) || $specs === 'a:0:{}') {
        return "No hay información detallada disponible.";
    }
    
    // Limpiar y extraer características clave
    $clean = preg_replace('/\d+:"\s*[^"]*"\s*|\d+:/', '', $specs);
    $clean = preg_replace('/[{};]/', '', $clean);
    $clean = trim($clean);
    
    if (empty($clean)) {
        return "Consultar ficha técnica para más detalles.";
    }
    
    return $clean;
}
    
    /**
     * 🔥 NUEVO: Extraer características clave de la descripción
     */
    private function extractKeyFeatures($description) {
        $features = [];
        
        // Buscar características numéricas
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(A|V|W|Hz|Ω)/i', $description, $match)) {
            $features[] = "• Medición: {$match[1]} {$match[2]}";
        }
        
        if (preg_match('/(hasta|max|máx)\s+(\d+(?:[.,]\d+)?)\s*(A|V|W)/i', $description, $match)) {
            $features[] = "• Rango máximo: {$match[2]} {$match[3]}";
        }
        
        if (preg_match('/(precisión|exactitud)\s*:?\s*([^,.]+)/i', $description, $match)) {
            $features[] = "• Precisión: " . trim($match[2]);
        }
        
        if (preg_match('/(resolución)\s*:?\s*([^,.]+)/i', $description, $match)) {
            $features[] = "• Resolución: " . trim($match[2]);
        }
        
        if (empty($features)) {
            // Si no encuentra características, mostrar los primeros 200 caracteres
            return substr($description, 0, 300);
        }
        
        return implode("\n", $features);
    }
    
    
    public function getProductCount() {
        if (!$this->isWooActive()) {
            return 0;
        }
        return $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->view_name}");
    }
    
    public function refreshView() {
        $this->createView();
        error_log('🔄 VIEW WooCommerce actualizada');
    }
}