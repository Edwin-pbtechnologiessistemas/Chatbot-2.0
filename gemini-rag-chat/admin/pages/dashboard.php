<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap gemini-rag-dashboard">
    <h1>Gemini RAG Chat - Asistente Inteligente</h1>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏢</div>
            <div class="stat-content">
                <h3>Info Empresa</h3>
                <p class="stat-number"><?php echo intval($company_count); ?></p>
                <small>Datos para el asistente</small>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">🛒</div>
            <div class="stat-content">
                <h3>WooCommerce</h3>
                <p class="stat-number"><?php echo intval($woo_product_count); ?></p>
                <small>Productos sincronizados</small><br>
                <small>Última act: <?php echo $woo_last_updated; ?></small>
            </div>
        </div>
        
        <!-- 🔥 NUEVA TARJETA DE EVENTOS -->
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-content">
                <h3>The Events Calendar</h3>
                <p class="stat-number"><?php echo intval($events_count); ?></p>
                <small>Eventos sincronizados</small><br>
                <small>Última act: <?php echo $events_last_updated; ?></small>
                <?php if ($events_summary['upcoming'] > 0): ?>
                    <small>📅 Próximos: <?php echo $events_summary['upcoming']; ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="info-boxes">
        <div class="info-box">
            <h2>📋 Estructura de Empresa</h2>
            <p>Tu CSV debe tener estas columnas:</p>
            <pre>info_type,title,content,subcontent,order_index,keywords</pre>
            <p><strong>Tipos disponibles:</strong> empresa, mision, servicios, marcas, contacto, ubicacion, horario, redes_sociales</p>
        </div>
        
        <div class="info-box">
            <h2>📋 Estructura de Productos (WooCommerce)</h2>
            <p>Los productos se sincronizan automáticamente desde WooCommerce a la vista:</p>
            <pre>wp_rag_woo_products</pre>
            <p><strong>La vista incluye:</strong> nombre, descripción, especificaciones, precio, stock, marca, categorías y más.</p>
        </div>
        
        <!-- 🔥 NUEVA INFO BOX DE EVENTOS -->
        <div class="info-box">
            <h2>📋 Estructura de Eventos (The Events Calendar)</h2>
            <p>Los eventos se sincronizan automáticamente desde The Events Calendar a la vista:</p>
            <pre>wp_rag_events_view</pre>
            <p><strong>La vista incluye:</strong> nombre, descripción, fecha, ubicación, organizador, categorías, etiquetas y estado (próximo/en curso/finalizado).</p>
        </div>
    </div>
    
    <div class="quick-actions">
        <h2>Acciones rápidas</h2>
        <div class="action-buttons">
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-import-company'); ?>" class="button button-primary">
                📤 Importar Empresa
            </a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-company'); ?>" class="button">
                🏢 Ver Info Empresa
            </a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-products'); ?>" class="button">
                🛒 Ver Productos
            </a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-events'); ?>" class="button">
                📅 Ver Eventos
            </a>
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-settings'); ?>" class="button">
                ⚙️ Configuración API
            </a>
        </div>
    </div>
    
    <!-- Gestión de WooCommerce -->
    <div class="woo-management">
        <h2>🛒 Gestión de WooCommerce</h2>
        <p>Actualiza la VIEW de WooCommerce para sincronizar los últimos cambios en productos, atributos y especificaciones técnicas.</p>
        
        <div class="action-buttons">
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=refresh_woo_view'), 'refresh_woo_view'); ?>" 
               class="button button-primary" 
               style="background: #7b1fa2; border-color: #6a1b9a;"
               onclick="return confirm('¿Actualizar VIEW de WooCommerce? Esto sincronizará todos los productos, especificaciones y atributos.')">
                🔄 Actualizar VIEW de WooCommerce
            </a>
        </div>
        
        <div class="woo-info">
            <small>
                <strong>ℹ️ Información:</strong> La VIEW <code>wp_rag_woo_products</code> combina datos de productos WooCommerce, 
                atributos y especificaciones técnicas. Actualízala cuando:
                <ul>
                    <li>Agregues o modifiques productos en WooCommerce</li>
                    <li>Cambies atributos de productos (corriente, voltaje, etc.)</li>
                    <li>Actualices especificaciones técnicas</li>
                </ul>
            </small>
        </div>
    </div>
    
    <!-- 🔥 NUEVA: Gestión de Eventos -->
    <div class="events-management" style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-radius: 8px; border: 1px solid #ddd;">
        <h2 style="margin-top: 0; color: #23282d;">📅 Gestión de Eventos (The Events Calendar)</h2>
        <p>Actualiza la VIEW de Eventos para sincronizar los últimos cambios en eventos, ubicaciones y organizadores.</p>
        
        <div class="action-buttons" style="margin-top: 15px;">
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=refresh_events_view'), 'refresh_events_view'); ?>" 
               class="button button-primary" 
               style="background: #21759b; border-color: #1e5a7a;"
               onclick="return confirm('¿Actualizar VIEW de Eventos? Esto sincronizará todos los eventos, ubicaciones y organizadores.')">
                🔄 Actualizar VIEW de Eventos
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=tribe_events'); ?>" class="button">
                📅 Gestionar en The Events Calendar
            </a>
        </div>
        
        <div class="events-info" style="margin-top: 15px; padding: 10px; background: #e8f0fe; border-radius: 5px;">
            <small>
                <strong>ℹ️ Información:</strong> La VIEW <code>wp_rag_events_view</code> combina datos de eventos, 
                ubicaciones (venues), organizadores, categorías y etiquetas. Actualízala cuando:
                <ul style="margin: 5px 0 0 20px;">
                    <li>Agregues o modifiques eventos</li>
                    <li>Cambies ubicaciones (venue)</li>
                    <li>Actualices organizadores</li>
                    <li>Modifiques categorías o etiquetas de eventos</li>
                </ul>
            </small>
        </div>
    </div>
</div>