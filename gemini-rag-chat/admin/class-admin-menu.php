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
        
        // Registrar AJAX handlers para importación y eliminación
        add_action('wp_ajax_chat_rag_import_products', [$this, 'handleImportProducts']);
        add_action('wp_ajax_chat_rag_delete_product', [$this, 'handleDeleteProduct']);
        add_action('wp_ajax_chat_rag_delete_company', [$this, 'handleDeleteCompany']);
        
        // 🔥 NUEVO: Handler para actualizar VIEW de WooCommerce
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
            update_option('gemini_api_key_1', sanitize_text_field($_POST['gemini_api_key_1']));
            update_option('gemini_api_key_2', sanitize_text_field($_POST['gemini_api_key_2']));
            update_option('gemini_api_key_3', sanitize_text_field($_POST['gemini_api_key_3']));
            update_option('gemini_api_key_4', sanitize_text_field($_POST['gemini_api_key_4']));
            update_option('gemini_api_key_5', sanitize_text_field($_POST['gemini_api_key_5']));
            echo '<div class="notice notice-success"><p>Configuración guardada</p></div>';
        }
        
        $api_key_1 = get_option('gemini_api_key_1', '');
        $api_key_2 = get_option('gemini_api_key_2', '');
        $api_key_3 = get_option('gemini_api_key_3', '');
        $api_key_4 = get_option('gemini_api_key_4', '');
        $api_key_5 = get_option('gemini_api_key_5', '');
        ?>
        <div class="wrap">
            <h1>Configuración de Gemini RAG Chat</h1>
            
            <form method="post">
                <?php wp_nonce_field('gemini_rag_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>API Key 1</th>
                        <td>
                            <input type="password" name="gemini_api_key_1"
                                value="<?php echo esc_attr($api_key_1); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>API Key 2</th>
                        <td>
                            <input type="password" name="gemini_api_key_2"
                                value="<?php echo esc_attr($api_key_2); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>API Key 3</th>
                        <td>
                            <input type="password" name="gemini_api_key_3"
                                value="<?php echo esc_attr($api_key_3); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>API Key 4</th>
                        <td>
                            <input type="password" name="gemini_api_key_4"
                                value="<?php echo esc_attr($api_key_4); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th>API Key 5</th>
                        <td>
                            <input type="password" name="gemini_api_key_5"
                                value="<?php echo esc_attr($api_key_5); ?>"
                                class="regular-text">
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar configuración', 'primary', 'submit'); ?>
            </form>
            
            <div class="notice notice-info">
                <p><strong>📌 Importante:</strong> El modelo usado es <code>gemini-2.5-flash</code></p>
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
    
    /**
     * 🔥 NUEVO: Handler para actualizar VIEW de WooCommerce
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
        // 🔥 Procesar mensaje de éxito después de actualizar
        $woo_refreshed = isset($_GET['woo_refreshed']) && $_GET['woo_refreshed'] == '1';
        if ($woo_refreshed) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ VIEW de WooCommerce actualizada correctamente. Los productos y especificaciones están sincronizados.</p></div>';
        }
        
        $product_count = $this->database->getProductCount();
        $company_count = $this->database->getCompanyCount();
        
        // 🔥 Obtener contador de productos WooCommerce
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
        
        // Columnas requeridas
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