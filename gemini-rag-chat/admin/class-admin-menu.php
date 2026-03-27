<?php
/**
 * Clase para el menú de administración del plugin Gemini RAG Chat
 */

if (!defined('ABSPATH')) exit;

class Gemini_RAG_Admin_Menu {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('admin_enqueue_scripts', [$this, 'adminScripts']);
        
        // Registrar AJAX handlers para eliminación de empresa
        add_action('wp_ajax_chat_rag_delete_company', [$this, 'handleDeleteCompany']);
        add_action('wp_ajax_test_gemini_connection', [$this, 'test_gemini_connection']);
        
        // Handler para actualizar VIEW de WooCommerce
        add_action('admin_post_refresh_woo_view', [$this, 'handleRefreshWooView']);
    }
    
    public function addMenus() {
        add_menu_page(
            'Gemini RAG Chat',
            'Gemini RAG',
            'manage_options',
            'gemini-rag',
            [$this, 'renderDashboard'],
            'dashicons-format-chat',
            30
        );
        
        add_submenu_page(
            'gemini-rag',
            'Importar Empresa',
            'Importar Empresa',
            'manage_options',
            'gemini-rag-import-company',
            [$this, 'renderImportCompany']
        );
        
        add_submenu_page(
            'gemini-rag',
            'Ver Productos',
            'Ver Productos',
            'manage_options',
            'gemini-rag-products',
            [$this, 'renderProducts']
        );
        
        add_submenu_page(
            'gemini-rag',
            'Info Empresa',
            'Info Empresa',
            'manage_options',
            'gemini-rag-company',
            [$this, 'renderCompany']
        );
        
        add_submenu_page(
            'gemini-rag',
            'Configuración',
            'Configuración',
            'manage_options',
            'gemini-rag-settings',
            [$this, 'renderSettings']
        );
    }

    public function renderSettings() {
        include plugin_dir_path(__FILE__) . 'pages/settings.php';
    }
    
    public function adminScripts($hook) {
        if (strpos($hook, 'gemini-rag') === false) {
            return;
        }
        
        wp_enqueue_style('gemini-rag-admin', plugin_dir_url(__FILE__) . 'css/admin-style.css', [], '1.0');
        wp_enqueue_script('jquery');
        
        wp_localize_script('jquery', 'gemini_rag_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('gemini_rag_admin_nonce')
        ]);
    }
    
    /**
     * Handler para actualizar VIEW de WooCommerce
     */
    public function handleRefreshWooView() {
        // Verificar permisos
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }
        
        // Verificar nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'refresh_woo_view')) {
            wp_die('Nonce inválido.');
        }
        
        // Verificar que la clase existe
        if (class_exists('ChatRAG_WooCommerce')) {
            try {
                $woo = new ChatRAG_WooCommerce();
                $woo->refreshView();
                
                // Guardar timestamp de última actualización
                update_option('woo_view_last_updated', current_time('mysql'));
                
                // Redirigir con mensaje de éxito
                wp_redirect(add_query_arg(
                    'woo_refreshed', 
                    '1', 
                    admin_url('admin.php?page=gemini-rag')
                ));
                exit;
            } catch (Exception $e) {
                wp_die('Error al actualizar: ' . $e->getMessage());
            }
        } else {
            wp_die('Error: No se pudo cargar la clase ChatRAG_WooCommerce. ¿WooCommerce está activo?');
        }
    }
    
    public function renderDashboard() {
        // Procesar mensaje de éxito después de actualizar
        $woo_refreshed = isset($_GET['woo_refreshed']) && $_GET['woo_refreshed'] == '1';
        if ($woo_refreshed) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ VIEW de WooCommerce actualizada correctamente. Los productos y especificaciones están sincronizados.</p></div>';
        }
        
        $company_count = $this->database->getCompanyCount();
        
        // Obtener contador de productos WooCommerce
        $woo_product_count = 0;
        $woo_last_updated = get_option('woo_view_last_updated', 'Nunca');
        
        if (class_exists('ChatRAG_WooCommerce')) {
            try {
                $woo = new ChatRAG_WooCommerce();
                $woo_product_count = $woo->getProductCount();
            } catch (Exception $e) {
                error_log('Error al obtener productos WooCommerce: ' . $e->getMessage());
            }
        }
        
        include plugin_dir_path(__FILE__) . 'pages/dashboard.php';
    }
    
    public function renderImportCompany() {
        include plugin_dir_path(__FILE__) . 'pages/import-company-simple.php';
    }
    
    public function renderProducts() {
        // Mostrar productos desde WooCommerce
        include plugin_dir_path(__FILE__) . 'pages/products.php';
    }
    
    public function renderCompany() {
        $company_info = $this->database->getCompanyInfo();
        include plugin_dir_path(__FILE__) . 'pages/company.php';
    }
    
    /**
     * Handler AJAX para eliminar información de empresa
     */
    public function handleDeleteCompany() {
        if (!check_ajax_referer('gemini_rag_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        $id = intval($_POST['id']);
        $result = $this->database->deleteCompanyInfo($id);
        
        if ($result) {
            wp_send_json_success('Información eliminada');
        } else {
            wp_send_json_error('Error al eliminar');
        }
    }
}