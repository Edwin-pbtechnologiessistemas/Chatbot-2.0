<?php
/**
 * Plugin Name: Gemini RAG Product Chat
 * Description: Chat inteligente profesional para PBTechnologies usando Gemini 3.1 Flash Lite con RAG.
 * Version: 3.0
 */

if (!defined('ABSPATH')) exit;

// Definir constantes
define('GEMINI_RAG_PATH', plugin_dir_path(__FILE__));
define('GEMINI_RAG_URL', plugin_dir_url(__FILE__));
define('GEMINI_RAG_VERSION', '3.0');

// Cargar clases necesarias
require_once GEMINI_RAG_PATH . 'class-database.php';
require_once GEMINI_RAG_PATH . 'class-gemini-handler.php';

class Gemini_RAG_Plugin {
    
    private $db;
    private $gemini;
    
    public function __construct() {
        // Inicializar clases
        $this->db = new ChatRAG_Database();
        $this->gemini = new Gemini_RAG_Handler($this->db);
        
        // Hooks principales
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_chat_rag_query', [$this, 'handle_chat_query']);
        add_action('wp_ajax_nopriv_chat_rag_query', [$this, 'handle_chat_query']);
        
        // Cargar admin si estamos en el área administrativa
        if (is_admin()) {
            require_once GEMINI_RAG_PATH . 'admin/class-admin-menu.php';
            new Gemini_RAG_Admin_Menu($this->db);
        }
        
        // Iniciar sesión si no existe
        if (!session_id()) {
            session_start();
        }
        
        // Debug: Verificar que el plugin se está cargando
        error_log('Gemini RAG Plugin Cargado - Constructor ejecutado');
    }
    
    /**
     * Cargar assets del frontend
     */
    public function enqueue_assets() {
        wp_enqueue_style('rag-chat-style', GEMINI_RAG_URL . 'assets/css/chat-style.css', [], time());
        wp_enqueue_script('rag-chat-script', GEMINI_RAG_URL . 'assets/js/chat-script.js', ['jquery'], time(), true);
        
        wp_localize_script('rag-chat-script', 'chat_rag', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('chat_rag_nonce')
        ]);
        
        // Debug
        error_log('Assets enqueued - AJAX URL: ' . admin_url('admin-ajax.php'));
    }
    
    /**
     * Manejar la consulta del chat
     */
    public function handle_chat_query() {
        // Debug: Verificar que la función se está ejecutando
        error_log('handle_chat_query ejecutado');
        
        try {
            // Verificar nonce
            if (!check_ajax_referer('chat_rag_nonce', 'nonce', false)) {
                error_log('Nonce inválido');
                wp_send_json_error(['message' => 'Nonce inválido']);
                return;
            }
            
            // Validar pregunta
            if (!isset($_POST['question'])) {
                error_log('No se recibió pregunta');
                wp_send_json_error(['message' => 'No se recibió ninguna pregunta']);
                return;
            }
            
            $question = sanitize_text_field($_POST['question']);
            error_log('Pregunta recibida: ' . $question);
            
            // Procesar con Gemini Handler
            $result = $this->gemini->processQuery($question);
            
            // Registrar en logs
            $this->gemini->logQuery($question, $result['products_count'], strlen($result['response']));
            
            // Enviar respuesta
            wp_send_json_success([
                'answer' => $result['response'],
                'debug_context' => $result['context']
            ]);
            
        } catch (Exception $e) {
            error_log('Error en handle_chat_query: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}

// Inicializar el plugin
new Gemini_RAG_Plugin();