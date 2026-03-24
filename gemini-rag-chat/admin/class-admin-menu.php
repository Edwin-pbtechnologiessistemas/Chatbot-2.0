<?php
/**
 * Clase para el menú de administración del plugin Gemini RAG Chat
 * Adaptado del proyecto original
 */

if (!defined('ABSPATH')) exit;

class Gemini_RAG_Admin_Menu {
    
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        
        add_action('admin_menu', [$this, 'addMenus']);
        add_action('admin_enqueue_scripts', [$this, 'adminScripts']);
        
        // Registrar AJAX handlers para importación y eliminación
        add_action('wp_ajax_chat_rag_import_products', [$this, 'handleImportProducts']);
        add_action('wp_ajax_chat_rag_delete_product', [$this, 'handleDeleteProduct']);
        add_action('wp_ajax_chat_rag_delete_company', [$this, 'handleDeleteCompany']);
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
            'Importar Productos',
            'Importar Productos',
            'manage_options',
            'gemini-rag-import-products',
            [$this, 'renderImportProducts']
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
    // Guardar configuración
    if (isset($_POST['submit'])) {
        check_admin_referer('gemini_rag_settings');
        $api_key = sanitize_text_field($_POST['gemini_api_key']);
        update_option('gemini_api_key', $api_key);
        echo '<div class="notice notice-success"><p>Configuración guardada</p></div>';
    }
    
    $api_key = get_option('gemini_api_key', '');
    ?>
    <div class="wrap">
        <h1>Configuración de Gemini RAG Chat</h1>
        
        <form method="post">
            <?php wp_nonce_field('gemini_rag_settings'); ?>
            <table class="form-table">
                <tr>
                    <th>API Key de Gemini</th>
                    <td>
                        <input type="password" 
                               name="gemini_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text"
                               placeholder="Ingresa tu API key de Google AI Studio">
                        <p class="description">
                            Obtén tu API key en <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Guardar configuración', 'primary', 'submit'); ?>
        </form>
        
        <div class="notice notice-info">
            <p><strong>📌 Importante:</strong> El modelo usado es <code>gemini-3.1-flash-lite-preview</code></p>
        </div>
    </div>
    <?php
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
    
    public function renderDashboard() {
        $product_count = $this->database->getProductCount();
        $company_count = $this->database->getCompanyCount();
        include plugin_dir_path(__FILE__) . 'pages/dashboard.php';
    }
    
    public function renderImportProducts() {
        include plugin_dir_path(__FILE__) . 'pages/import-products.php';
    }
    
    public function renderImportCompany() {
        include plugin_dir_path(__FILE__) . 'pages/import-company-simple.php';
    }
    
    public function renderProducts() {
        $products = $this->database->getAllProducts();
        include plugin_dir_path(__FILE__) . 'pages/products.php';
    }
    
    public function renderCompany() {
        $company_info = $this->database->getCompanyInfo();
        include plugin_dir_path(__FILE__) . 'pages/company.php';
    }
    
    /**
     * Handler AJAX para importar productos desde CSV
     */
    public function handleImportProducts() {
        // Verificar nonce
        if (!check_ajax_referer('gemini_rag_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error('No se recibió archivo');
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Error al subir el archivo');
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            wp_send_json_error('Solo se permiten archivos CSV');
        }
        
        $result = $this->processProductImport($file);
        wp_send_json_success($result);
    }
    
    /**
     * Procesar importación de productos
     */
    private function processProductImport($file) {
        global $wpdb;
        $table_products = $this->database->getTables()['products'];
        
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            return 'No se pudo leer el archivo';
        }
        
        // Leer encabezados
        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return 'El archivo no tiene encabezados';
        }
        
        // Limpiar encabezados
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);
        
        // Columnas requeridas (sin availability ni keywords)
        $required = ['product_name', 'category', 'subcategory', 'brand', 'short_description', 'long_description', 'specifications', 'price', 'product_url'];
        $missing = array_diff($required, $headers);
        
        if (!empty($missing)) {
            fclose($handle);
            return 'Columnas requeridas faltantes: ' . implode(', ', $missing);
        }
        
        // Obtener índices
        $col_index = [];
        foreach ($headers as $idx => $name) {
            $col_index[$name] = $idx;
        }
        
        $count = 0;
        $errors = [];
        $row_num = 1;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            $row_num++;
            
            if (empty(array_filter($data))) {
                continue;
            }
            
            // Extraer datos
            $product_name = isset($data[$col_index['product_name']]) ? trim($data[$col_index['product_name']]) : '';
            $category = isset($data[$col_index['category']]) ? trim($data[$col_index['category']]) : '';
            $subcategory = isset($data[$col_index['subcategory']]) ? trim($data[$col_index['subcategory']]) : '';
            $brand = isset($data[$col_index['brand']]) ? trim($data[$col_index['brand']]) : '';
            $short_description = isset($data[$col_index['short_description']]) ? trim($data[$col_index['short_description']]) : '';
            $long_description = isset($data[$col_index['long_description']]) ? trim($data[$col_index['long_description']]) : '';
            $specifications = isset($data[$col_index['specifications']]) ? trim($data[$col_index['specifications']]) : '';
            $price = isset($data[$col_index['price']]) ? trim($data[$col_index['price']]) : '';
            $product_url = isset($data[$col_index['product_url']]) ? trim($data[$col_index['product_url']]) : '';
            
            if (empty($product_name) || empty($category)) {
                $errors[] = "Fila $row_num: nombre o categoría vacíos";
                continue;
            }
            
            // Generar keywords automáticamente
            $keywords = strtolower($product_name . ' ' . $brand . ' ' . $category . ' ' . $subcategory);
            $keywords = preg_replace('/[^a-z0-9áéíóúñ\s]/', '', $keywords);
            $keywords = implode(', ', array_unique(explode(' ', $keywords)));
            
            // Verificar si el producto ya existe por URL
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_products WHERE product_url = %s",
                $product_url
            ));
            
            if ($exists) {
                // Actualizar
                $result = $wpdb->update($table_products, [
                    'product_name' => $product_name,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'brand' => $brand,
                    'short_description' => $short_description,
                    'long_description' => $long_description,
                    'specifications' => $specifications,
                    'price' => $price,
                    'availability' => 'Disponible',
                    'product_url' => $product_url,
                    'keywords' => $keywords
                ], ['id' => $exists]);
                
                if ($result !== false) {
                    $count++;
                } else {
                    $errors[] = "Fila $row_num: error al actualizar";
                }
            } else {
                // Insertar nuevo
                $result = $wpdb->insert($table_products, [
                    'product_name' => $product_name,
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'brand' => $brand,
                    'short_description' => $short_description,
                    'long_description' => $long_description,
                    'specifications' => $specifications,
                    'price' => $price,
                    'availability' => 'Disponible',
                    'product_url' => $product_url,
                    'keywords' => $keywords
                ]);
                
                if ($result) {
                    $count++;
                } else {
                    $errors[] = "Fila $row_num: error BD - " . $wpdb->last_error;
                }
            }
        }
        
        fclose($handle);
        
        $message = "✅ Se importaron/actualizaron $count productos correctamente";
        if (!empty($errors)) {
            $message .= "\n⚠️ Errores (" . count($errors) . "):\n" . implode("\n", array_slice($errors, 0, 10));
        }
        
        return $message;
    }
    
    /**
     * Handler AJAX para eliminar producto
     */
    public function handleDeleteProduct() {
        if (!check_ajax_referer('gemini_rag_admin_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce inválido');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('No tienes permisos');
        }
        
        $id = intval($_POST['id']);
        $result = $this->database->deleteProduct($id);
        
        if ($result) {
            wp_send_json_success('Producto eliminado');
        } else {
            wp_send_json_error('Error al eliminar');
        }
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