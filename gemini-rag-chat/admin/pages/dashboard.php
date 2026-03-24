<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap gemini-rag-dashboard">
    <h1>Gemini RAG Chat - Asistente Inteligente</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📦</div>
            <div class="stat-content">
                <h3>Productos</h3>
                <p class="stat-number"><?php echo intval($product_count); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-content">
                <h3>Info Empresa</h3>
                <p class="stat-number"><?php echo intval($company_count); ?></p>
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
</div>