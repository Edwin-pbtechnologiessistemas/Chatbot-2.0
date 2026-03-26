<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap gemini-rag-dashboard">
    <h1>Gemini RAG Chat - Asistente Inteligente</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <h3>Productos</h3>
                <p class="stat-number"><?php echo intval($product_count); ?></p>
                <small>Importados manualmente</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-content">
                <h3>Info Empresa</h3>
                <p class="stat-number"><?php echo intval($company_count); ?></p>
            </div>
        </div>
        
        <!-- 🔥 NUEVO: Card para WooCommerce -->
        <div class="stat-card">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <h3>WooCommerce</h3>
                <p class="stat-number"><?php echo intval($woo_product_count); ?></p>
                <small>Productos sincronizados</small><br>
                <small>Última act: <?php echo $woo_last_updated; ?></small>
            </div>
        </div>
    </div>
    
    <div class="info-boxes">
        <div class="info-box">
            <h2>📋 Estructura de Productos</h2>
            <p>Tu CSV debe tener estas columnas:</p>
            <pre>product_name,category,subcategory,brand,short_description,long_description,specifications,price,product_url</pre>
            <p><strong>Nota:</strong> Las keywords se generan automáticamente y la disponibilidad se establece como "Disponible"</p>
            <p><strong>Especificaciones:</strong> Puedes usar punto y coma (;) para separar características</p>
        </div>
        
        <div class="info-box">
            <h2>📋 Estructura de Empresa</h2>
            <p>Tu CSV debe tener estas columnas:</p>
            <pre>info_type,title,content,subcontent,order_index,keywords</pre>
            <p><strong>Tipos disponibles:</strong> empresa, mision, servicios, marcas, contacto, ubicacion, horario, redes_sociales</p>
        </div>
    </div>
    
    <div class="quick-actions">
        <h2>Acciones rápidas</h2>
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-import-products'); ?>" class="button button-primary">Importar Productos</a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-import-company'); ?>" class="button button-primary">Importar Empresa</a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-products'); ?>" class="button">Ver Productos</a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-company'); ?>" class="button">Ver Info Empresa</a>
        </div>
    </div>
    
    <!-- 🔥 NUEVA SECCIÓN: Gestión de WooCommerce -->
    <div class="woo-management" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px; border: 1px solid #ddd;">
        <h2 style="margin-top: 0; color: #23282d;">🛒 Gestión de WooCommerce</h2>
        <p>Actualiza la VIEW de WooCommerce para sincronizar los últimos cambios en productos, atributos y especificaciones técnicas.</p>
        
        <div class="action-buttons" style="margin-top: 15px;">
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=refresh_woo_view'), 'refresh_woo_view'); ?>" 
               class="button button-primary" 
               style="background: #7b1fa2; border-color: #6a1b9a;"
               onclick="return confirm('¿Actualizar VIEW de WooCommerce? Esto sincronizará todos los productos, especificaciones y atributos.')">
                🔄 Actualizar VIEW de WooCommerce
            </a>
        </div>
        
        <div class="woo-info" style="margin-top: 15px; padding: 10px; background: #e8f0fe; border-radius: 5px;">
            <small>
                <strong>ℹ️ Información:</strong> La VIEW <code>wp_rag_woo_products</code> combina datos de productos WooCommerce, 
                atributos y especificaciones técnicas. Actualízala cuando:
                <ul style="margin: 5px 0 0 20px;">
                    <li>Agregues o modifiques productos en WooCommerce</li>
                    <li>Cambies atributos de productos (corriente, voltaje, etc.)</li>
                    <li>Actualices especificaciones técnicas</li>
                </ul>
            </small>
        </div>
    </div>
</div>

<style>
.gemini-rag-dashboard .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.gemini-rag-dashboard .stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.gemini-rag-dashboard .stat-icon {
    font-size: 40px;
    margin-right: 15px;
}

.gemini-rag-dashboard .stat-content h3 {
    margin: 0 0 5px 0;
    font-size: 14px;
    color: #23282d;
}

.gemini-rag-dashboard .stat-number {
    font-size: 28px;
    font-weight: bold;
    margin: 0;
    color: #0073aa;
}

.gemini-rag-dashboard .info-boxes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.gemini-rag-dashboard .info-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
}

.gemini-rag-dashboard .info-box h2 {
    margin-top: 0;
    font-size: 18px;
}

.gemini-rag-dashboard .info-box pre {
    background: #f1f1f1;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}

.gemini-rag-dashboard .quick-actions {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 30px;
}

.gemini-rag-dashboard .quick-actions h2 {
    margin-top: 0;
    font-size: 18px;
}

.gemini-rag-dashboard .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.gemini-rag-dashboard .action-buttons .button {
    padding: 8px 16px;
    height: auto;
}
</style>