<?php
/**
 * Clase para manejar eventos de The Events Calendar y seminarios grabados
 */

if (!defined('ABSPATH')) exit;

class ChatRAG_EventsCalendar {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Verificar si The Events Calendar está activo
     */
    public function isEventsCalendarActive() {
        return class_exists('Tribe__Events__Main');
    }
    
    public function createEventsView() {
    $view_name = $this->wpdb->prefix . 'rag_events_view';
    
    // Eliminar vista si existe
    $this->wpdb->query("DROP VIEW IF EXISTS {$view_name}");
    
    $site_url = get_site_url();
    
    $sql = "
        CREATE VIEW {$view_name} AS
        
        SELECT 
            'evento_calendar' as tipo,
            p.ID,
            p.post_title as titulo,
            p.post_content as descripcion_larga,
            pm_start.meta_value as fecha_inicio,
            DATE_FORMAT(pm_start.meta_value, '%d/%m/%Y') as fecha_formateada,
            COALESCE(v.post_title, 'Online') as ubicacion,
            CASE 
                WHEN pm_start.meta_value > NOW() THEN 'Proximo'
                WHEN pm_end.meta_value < NOW() THEN 'Finalizado'
                ELSE 'En curso'
            END as estado,
            CONCAT('{$site_url}/evento/', p.post_name) as url
        FROM {$this->wpdb->posts} p
        LEFT JOIN {$this->wpdb->postmeta} pm_start ON p.ID = pm_start.post_id AND pm_start.meta_key = '_EventStartDate'
        LEFT JOIN {$this->wpdb->postmeta} pm_end ON p.ID = pm_end.post_id AND pm_end.meta_key = '_EventEndDate'
        LEFT JOIN {$this->wpdb->postmeta} pm_venue ON p.ID = pm_venue.post_id AND pm_venue.meta_key = '_EventVenueID'
        LEFT JOIN {$this->wpdb->posts} v ON pm_venue.meta_value = v.ID AND v.post_type = 'tribe_venue'
        WHERE p.post_type = 'tribe_events'
          AND p.post_status = 'publish'
        
        UNION ALL
        
        SELECT 
            'seminario' as tipo,
            p.ID,
            p.post_title as titulo,
            p.post_content as descripcion_larga,
            p.post_date as fecha_inicio,
            DATE_FORMAT(p.post_date, '%d/%m/%Y') as fecha_formateada,
            'Online' as ubicacion,
            'Disponible' as estado,
            CONCAT('{$site_url}/', p.post_name) as url
        FROM {$this->wpdb->posts} p
        WHERE p.post_type = 'post'
          AND p.post_status = 'publish'
          AND p.ID IN (
              SELECT tr.object_id
              FROM {$this->wpdb->term_relationships} tr
              INNER JOIN {$this->wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
              INNER JOIN {$this->wpdb->terms} t ON tt.term_id = t.term_id
              WHERE tt.taxonomy = 'category'
                AND t.name IN ('Seminarios grabados', 'Webinar')
          )
        
        ORDER BY fecha_inicio DESC
    ";
    
    $result = $this->wpdb->query($sql);
    
    if ($result === false) {
        error_log('Error al crear vista de eventos: ' . $this->wpdb->last_error);
        return false;
    }
    
    error_log("✅ Vista de eventos creada exitosamente");
    return true;
}
    
    /**
     * Obtener contador de eventos/seminarios
     */
    public function getEventCount() {
        $view_name = $this->wpdb->prefix . 'rag_events_view';
        
        $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");
        
        if (!$view_exists) {
            $this->createEventsView();
        }
        
        $count = $this->wpdb->get_var("SELECT COUNT(*) FROM {$view_name}");
        
        return intval($count);
    }
    
    /**
     * Obtener resumen de eventos/seminarios para el dashboard
     */
    public function getEventsSummary() {
    $view_name = $this->wpdb->prefix . 'rag_events_view';
    
    $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");
    
    if (!$view_exists) {
        $this->createEventsView();
    }
    
    $summary = $this->wpdb->get_row("
        SELECT 
            SUM(CASE WHEN tipo = 'evento_calendar' THEN 1 ELSE 0 END) as events,
            SUM(CASE WHEN tipo = 'seminario' THEN 1 ELSE 0 END) as seminars,
            SUM(CASE WHEN estado = 'Proximo' THEN 1 ELSE 0 END) as upcoming,
            COUNT(*) as total
        FROM {$view_name}
    ");
    
    return [
        'events' => intval($summary->events),
        'seminars' => intval($summary->seminars),
        'upcoming' => intval($summary->upcoming),
        'total' => intval($summary->total)
    ];
}
    
    /**
 * Buscar eventos/seminarios por texto (similar a búsqueda de productos)
 */
public function searchEvents($query, $limit = 5) {
    $view_name = $this->wpdb->prefix . 'rag_events_view';
    
    $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");
    
    if (!$view_exists) {
        $this->createEventsView();
        // Verificar de nuevo
        $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");
        if (!$view_exists) {
            error_log("❌ La vista {$view_name} no existe después de crearla");
            return [];
        }
    }
    
    // 🔥 NORMALIZAR LA CONSULTA (igual que en productos)
    $clean_query = $this->normalizeText($query);
    $words = explode(' ', $clean_query);
    
    // Stopwords
    $stopwords = ['para', 'tiene', 'tienen', 'ustedes', 'busco', 'hola', 
                  'quiero', 'necesito', 'como', 'una', 'un', 'el', 'la',
                  'los', 'las', 'que', 'cual', 'donde', 'cuando', 'porque',
                  'sobre', 'con', 'sin', 'por', 'para', 'hay', 'hacerca'];
    
    $words = array_filter($words, function($w) use ($stopwords) { 
        return !empty($w) && strlen($w) > 2 && !in_array($w, $stopwords);
    });
    $words = array_unique($words);
    
    error_log("🔍 Búsqueda eventos - Palabras clave: " . implode(', ', $words));
    
    if (empty($words)) {
        error_log("⚠️ Sin palabras clave para eventos");
        return [];
    }
    
    // 🔥 OBTENER TODOS LOS EVENTOS
    $sql = "SELECT * FROM {$view_name}";
    $events = $this->wpdb->get_results($sql);
    
    if (empty($events)) {
        error_log("⚠️ No hay eventos en la vista");
        return [];
    }
    
    error_log("📅 Total eventos en vista: " . count($events));
    
    $results = [];
    
    foreach ($events as $event) {
        // 🔥 NORMALIZAR TODO EL TEXTO DEL EVENTO
        $text = $this->normalizeText(
            $event->titulo . ' ' . 
            $event->descripcion_larga . ' ' . 
            $event->ubicacion
        );
        
        $score = 0;
        $match_count = 0;
        $title_text = $this->normalizeText($event->titulo);
        
        foreach ($words as $word) {
            if (strlen($word) < 3) continue;
            
            // 🔥 BONUS GRANDE si la palabra está en el TÍTULO
            if (strpos($title_text, $word) !== false) {
                $score += 50;
                $match_count++;
                error_log("   Match en título: '{$word}' en '{$event->titulo}' -> +50");
            }
            // Coincidencia exacta en cualquier campo
            elseif (strpos($text, $word) !== false) {
                $score += 30;
                $match_count++;
                error_log("   Match en texto: '{$word}' en '{$event->titulo}' -> +30");
            }
            // Coincidencia parcial
            elseif (strpos($text, substr($word, 0, -1)) !== false) {
                $score += 15;
                $match_count++;
            }
        }
        
        // Bonus proporcional
        if (count($words) > 0) {
            $score += ($match_count / count($words)) * 40;
        }
        
        // 🔥 Aceptar si tiene puntuación suficiente
        if ($score > 20) {
            $event->relevance_score = $score;
            $results[] = $event;
            error_log("✅ Evento relevante: {$event->titulo} - Score: {$score}");
        }
    }
    
    // Ordenar por relevancia
    usort($results, function($a, $b) {
        return $b->relevance_score - $a->relevance_score;
    });
    
    $final = array_slice($results, 0, $limit);
    error_log("📅 Eventos encontrados: " . count($final));
    
    return $final;
}

/**
 * Normalizar texto (misma función que en WooCommerce)
 */
private function normalizeText($text) {
    if (empty($text)) return '';
    
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u',
        'ñ'=>'n','Ñ'=>'n'
    ]);
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}
    
    /**
     * Formatear eventos/seminarios para el contexto de Gemini
     */
    public function formatForGemini($events) {
        if (empty($events)) {
            return "No hay eventos o seminarios disponibles en este momento.";
        }
        
        $output = "--- EVENTOS Y SEMINARIOS PBTechnologies ---\n";
        
        foreach ($events as $event) {
            $output .= "===========================================\n";
            
            if ($event->tipo == 'evento_calendar') {
                $output .= "📅 EVENTO: {$event->titulo}\n";
            } else {
                $output .= "🎥 SEMINARIO GRABADO: {$event->titulo}\n";
            }
            
            if ($event->fecha_formateada) {
                $output .= "📆 Fecha: {$event->fecha_formateada}";
                if ($event->hora) {
                    $output .= " a las {$event->hora}";
                }
                $output .= "\n";
            }
            
            $output .= "📍 Lugar: {$event->ubicacion}\n";
            
            if ($event->ciudad && $event->ciudad != 'Online') {
                $output .= "🏙️ Ciudad: {$event->ciudad}\n";
            }
            
            if ($event->estado && $event->tipo == 'evento_calendar') {
                $estado_texto = $event->estado == 'Proximo' ? 'Próximo' : $event->estado;
                $output .= "📌 Estado: {$estado_texto}\n";
            }
            
            $output .= "🔗 Enlace: {$event->url}\n";
            $output .= "===========================================\n\n";
        }
        
        return $output;
    }
    
    /**
     * Obtener solo seminarios grabados
     */
    public function getSeminars($limit = 10) {
        $view_name = $this->wpdb->prefix . 'rag_events_view';
        
        $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");
        
        if (!$view_exists) {
            $this->createEventsView();
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT * FROM {$view_name}
            WHERE tipo = 'seminario'
            ORDER BY fecha_inicio DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Obtener solo eventos
     */
    public function getEvents($limit = 10) {
        $view_name = $this->wpdb->prefix . 'rag_events_view';
        
        $view_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");
        
        if (!$view_exists) {
            $this->createEventsView();
        }
        
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT * FROM {$view_name}
            WHERE tipo = 'evento_calendar'
            ORDER BY 
                CASE 
                    WHEN estado = 'Proximo' THEN 1
                    WHEN estado = 'En curso' THEN 2
                    ELSE 3
                END,
                fecha_inicio DESC
            LIMIT %d
        ", $limit));
    }
}