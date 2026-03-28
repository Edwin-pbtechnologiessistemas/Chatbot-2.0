<?php
/**
 * Archivo de prueba para verificar búsqueda de eventos
 * Acceder a: https://tusitio.com/wp-content/plugins/gemini-rag-chat/test-events-query.php
 */

// Cargar WordPress
define('WP_USE_THEMES', false);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Verificar que el usuario es administrador (por seguridad)
if (!current_user_can('manage_options')) {
    die('Acceso denegado. Solo administradores.');
}

// Incluir clases necesarias
require_once plugin_dir_path(__FILE__) . 'class-database.php';
require_once plugin_dir_path(__FILE__) . 'class-events-calendar.php';
require_once plugin_dir_path(__FILE__) . 'class-woocommerce.php';
require_once plugin_dir_path(__FILE__) . 'class-gemini-handler.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Prueba de Búsqueda de Eventos</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .test-box { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .event-item { border-bottom: 1px solid #eee; padding: 10px; margin: 5px 0; }
        .event-title { font-weight: bold; color: #007cba; }
        .event-date { color: #666; font-size: 12px; }
        .event-location { color: #28a745; font-size: 12px; }
        .event-score { color: #ff9800; font-size: 11px; }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
        hr { margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔍 Prueba de Búsqueda de Eventos</h1>
";

// Inicializar
$db = new ChatRAG_Database();
$events_calendar = new ChatRAG_EventsCalendar();
$woo = new ChatRAG_WooCommerce();
$gemini = new Gemini_RAG_Handler($db);

// Lista de preguntas de prueba
$test_queries = [
    "¿Hay algún webinar próximo?",
    "¿Qué seminarios grabados tienen?",
    "¿Hay eventos sobre termografía?",
    "¿Cuándo es el próximo evento en Santa Cruz?",
    "¿Tienen seminarios de SONEL?",
    "¿Qué webinars hay grabados?",
    "¿Algún evento sobre mediciones eléctricas?",
    "Hola, ¿cómo estás?",
    "¿Qué productos tienen?",
    "¿Hay eventos este mes?"
];

foreach ($test_queries as $query) {
    echo "<div class='test-box'>";
    echo "<h3>📝 Pregunta: <strong style='color:#d32f2f'>{$query}</strong></h3>";
    
    // 1. Buscar eventos
    echo "<h4>📅 Búsqueda de Eventos:</h4>";
    $events = $events_calendar->searchEvents($query, 5);
    
    if (empty($events)) {
        echo "<p style='color:#999;'>❌ No se encontraron eventos relacionados.</p>";
    } else {
        echo "<table style='width:100%; border-collapse:collapse;'>";
        foreach ($events as $event) {
            $score = isset($event->relevance_score) ? $event->relevance_score : 0;
            echo "<tr style='border-bottom:1px solid #eee;'>";
            echo "<td style='padding:8px;'>";
            echo "<div class='event-title'>" . ($event->tipo == 'evento_calendar' ? '📅' : '🎥') . " {$event->titulo}</div>";
            echo "<div class='event-date'>📆 Fecha: {$event->fecha_formateada}</div>";
            echo "<div class='event-location'>📍 Ubicación: {$event->ubicacion}</div>";
            if ($score > 0) {
                echo "<div class='event-score'>⭐ Relevancia: {$score}</div>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 2. Buscar productos (para comparar)
    echo "<h4>🛒 Búsqueda de Productos (primeros 3):</h4>";
    $products = $woo->searchProducts($query, 3);
    if (empty($products)) {
        echo "<p style='color:#999;'>❌ No se encontraron productos relacionados.</p>";
    } else {
        foreach ($products as $product) {
            echo "<div style='padding:5px 0;'>• {$product->product_name}</div>";
        }
    }
    
    // 3. Mostrar contexto completo que se enviaría a Gemini
    echo "<h4>📤 Contexto que se enviaría a Gemini:</h4>";
    
    // Simular el contexto que arma processQuery
    $company_info = $db->getCompanyInfo();
    $context_company = "--- INFORMACIÓN EMPRESA ---\n";
    foreach ($company_info as $info) {
        $context_company .= "{$info->title}: {$info->content}\n";
    }
    
    $context_products = "";
    if (!empty($products)) {
        $context_products = "--- PRODUCTOS RELACIONADOS ---\n";
        foreach (array_slice($products, 0, 3) as $p) {
            $context_products .= "• {$p->product_name}\n";
            if ($p->price) $context_products .= "  Precio: {$p->price}\n";
        }
    }
    
    $context_events = "";
    if (!empty($events)) {
        $context_events = "--- EVENTOS RELACIONADOS ---\n";
        foreach ($events as $e) {
            $context_events .= "• {$e->titulo}\n";
            $context_events .= "  Fecha: {$e->fecha_formateada}\n";
            $context_events .= "  Lugar: {$e->ubicacion}\n";
            $context_events .= "  Estado: " . ($e->estado == 'Proximo' ? 'Próximo' : $e->estado) . "\n";
        }
    }
    
    $full_context = $context_company . "\n" . $context_products . "\n" . $context_events;
    echo "<pre style='max-height:200px; overflow:auto;'>{$full_context}</pre>";
    
    echo "</div>";
    echo "<hr>";
}

echo "</body></html>";