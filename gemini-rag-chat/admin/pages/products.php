<?php if (!defined('ABSPATH')) exit;

global $wpdb;

// Configuración de paginación
$items_per_page = 10;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Buscador
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Construir query con búsqueda
$where_clause = '';
if (!empty($search)) {
    $where_clause = $wpdb->prepare(" WHERE product_name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
}

// Obtener total de productos
$total_products = $wpdb->get_var("
    SELECT COUNT(*) FROM {$wpdb->prefix}rag_woo_products
    $where_clause
");

// Obtener productos paginados
$woo_products = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}rag_woo_products
    $where_clause
    ORDER BY id DESC
    LIMIT %d OFFSET %d
", $items_per_page, $offset));

// Calcular total de páginas
$total_pages = ceil($total_products / $items_per_page);

// Procesar especificaciones (si están serializadas)
function parse_specifications($specs) {
    if (empty($specs)) return [];
    
    if (is_serialized($specs)) {
        $unserialized = maybe_unserialize($specs);
        if (is_array($unserialized)) {
            return $unserialized;
        }
    }
    
    if (strpos($specs, ';') !== false) {
        $items = explode(';', $specs);
        $result = [];
        foreach ($items as $item) {
            if (strpos($item, ':') !== false) {
                list($key, $value) = explode(':', $item, 2);
                $result[trim($key)] = trim($value);
            } else {
                $result[] = trim($item);
            }
        }
        return $result;
    }
    
    return [$specs];
}

// Función para formatear especificaciones en HTML
function format_specifications_html($specs) {
    if (empty($specs) || !is_array($specs)) return '<p>No hay especificaciones</p>';
    
    $html = '<div class="modal-specs">';
    foreach ($specs as $key => $value) {
        if (is_numeric($key)) {
            $html .= '<div class="spec-item">✓ ' . esc_html($value) . '</div>';
        } else {
            $html .= '<div class="spec-item"><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</div>';
        }
    }
    $html .= '</div>';
    return $html;
}
?>

<div class="wrap">
    <h1>Productos WooCommerce</h1>
    
    <div class="woo-products-header">
        <div>
            <p class="description">Productos sincronizados desde WooCommerce. Total: <strong><?php echo intval($total_products); ?></strong> productos</p>
        </div>
        <div class="action-buttons">
            <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button">
                📦 Gestionar en WooCommerce
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=refresh_woo_view'), 'refresh_woo_view'); ?>" 
               class="button button-primary"
               style="background: #7b1fa2; border-color: #6a1b9a;">
                🔄 Sincronizar
            </a>
        </div>
    </div>
    
    <!-- Buscador -->
    <div class="woo-search-box">
        <form method="get" action="">
            <input type="hidden" name="page" value="gemini-rag-products">
            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" 
                   placeholder="Buscar por nombre de producto..." class="regular-text">
            <button type="submit" class="button">🔍 Buscar</button>
            <?php if (!empty($search)): ?>
                <a href="?page=gemini-rag-products" class="button">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if (empty($woo_products)): ?>
        <div class="notice notice-warning">
            <p><?php echo !empty($search) ? 'No se encontraron productos con "' . esc_html($search) . '"' : 'No hay productos sincronizados.'; ?>
            <?php if (!empty($search)): ?>
                <a href="?page=gemini-rag-products">Ver todos</a>
            <?php else: ?>
                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=refresh_woo_view'), 'refresh_woo_view'); ?>">Sincronizar ahora</a>
            <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="woo-products-table">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    32
                        <th width="50">ID</th>
                        <th width="280">Producto</th>
                        <th width="100">Categoría</th>
                        <th width="80">Marca</th>
                        <th width="80">Precio</th>
                        <th width="80">Stock</th>
                        <th width="80">Especificaciones</th>
                        <th width="80">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($woo_products as $product): ?>
                    <tr>
                        <td><?php echo intval($product->id); ?></td>
                        <td>
                            <strong><?php echo esc_html($product->product_name); ?></strong><br>
                            <small class="product-desc-preview"><?php echo wp_trim_words($product->short_description, 10); ?></small>
                        </td>
                        <td>
                            <?php 
                            $categories = maybe_unserialize($product->categories);
                            if (is_array($categories)) {
                                echo esc_html(implode(', ', array_slice($categories, 0, 1)));
                                if (count($categories) > 1) echo ' +' . (count($categories) - 1);
                            } else {
                                echo esc_html($product->categories);
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($product->brand ?: '—'); ?></td>
                        <td><strong><?php echo esc_html($product->price ?: 'Consultar'); ?></strong></td>
                        <td>
                            <?php 
                            $stock = intval($product->stock);
                            
                            if ($stock > 10) {
                                echo '<span class="product-badge badge-in-stock">✓ ' . $stock . '</span>';
                            } elseif ($stock > 0) {
                                echo '<span class="product-badge badge-low-stock">⚠️ ' . $stock . '</span>';
                            } else {
                                echo '<span class="product-badge badge-out-stock">❌</span>';
                            }
                            ?>
                        </td>
                        <td class="specs-preview">
                            <?php 
                            $specs = parse_specifications($product->specifications);
                            if (!empty($specs) && is_array($specs)):
                                $count_specs = count($specs);
                                if ($count_specs > 0):
                                    $first_spec = reset($specs);
                                    if (is_numeric(key($specs))):
                                        echo '<span class="spec-count">📋 ' . $count_specs . ' especs</span>';
                                    else:
                                        $first_key = key($specs);
                                        echo '<span class="spec-count">📋 ' . $first_key . ': ' . esc_html(substr($first_spec, 0, 20)) . '</span>';
                                    endif;
                                endif;
                            else: ?>
                            <span class="description">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small view-product" 
                                    data-id="<?php echo intval($product->id); ?>"
                                    data-name="<?php echo esc_attr($product->product_name); ?>"
                                    data-desc="<?php echo esc_attr($product->long_description ?: $product->short_description); ?>"
                                    data-short-desc="<?php echo esc_attr($product->short_description); ?>"
                                    data-price="<?php echo esc_attr($product->price); ?>"
                                    data-stock="<?php echo intval($product->stock); ?>"
                                    data-brand="<?php echo esc_attr($product->brand); ?>"
                                    data-categories="<?php echo esc_attr(json_encode($categories)); ?>"
                                    data-specs="<?php echo esc_attr(json_encode(parse_specifications($product->specifications))); ?>"
                                    data-url="<?php echo esc_url($product->product_url); ?>">
                                👁️
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <div class="woo-pagination">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo intval($total_products); ?> productos</span>
                <span class="pagination-links">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => !empty($search) ? array('search' => $search) : array()
                    ));
                    
                    echo $page_links;
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal para ver producto -->
<div id="product-modal" class="product-modal" style="display: none;">
    <div class="product-modal-overlay"></div>
    <div class="product-modal-container">
        <div class="product-modal-header">
            <h2 id="modal-product-title">Producto</h2>
            <button class="modal-close">&times;</button>
        </div>
        <div class="product-modal-body">
            <div class="modal-row">
                <div class="modal-label">Precio:</div>
                <div class="modal-value" id="modal-price"></div>
            </div>
            <div class="modal-row">
                <div class="modal-label">Stock:</div>
                <div class="modal-value" id="modal-stock"></div>
            </div>
            <div class="modal-row">
                <div class="modal-label">Marca:</div>
                <div class="modal-value" id="modal-brand"></div>
            </div>
            <div class="modal-row">
                <div class="modal-label">Categorías:</div>
                <div class="modal-value" id="modal-categories"></div>
            </div>
            <div class="modal-row">
                <div class="modal-label">Descripción corta:</div>
                <div class="modal-value" id="modal-short-desc"></div>
            </div>
            <div class="modal-row">
                <div class="modal-label">Descripción:</div>
                <div class="modal-value" id="modal-desc"></div>
            </div>
            <div class="modal-row">
                <div class="modal-label">Especificaciones:</div>
                <div class="modal-value" id="modal-specs"></div>
            </div>
        </div>
        <div class="product-modal-footer">
            <a href="#" id="modal-product-link" class="button button-primary" target="_blank">Ver producto en tienda</a>
            <button class="button modal-close-btn">Cerrar</button>
        </div>
    </div>
</div>

<style>
/* Buscador */
.woo-search-box {
    background: white;
    padding: 15px 20px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.woo-search-box form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.woo-search-box .regular-text {
    min-width: 300px;
}

/* Tabla optimizada */
.woo-products-table {
    background: white;
    border-radius: 12px;
    overflow-x: auto;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.woo-products-table .wp-list-table {
    width: 100%;
    border-collapse: collapse;
}

.woo-products-table .wp-list-table th,
.woo-products-table .wp-list-table td {
    padding: 12px 8px;
    vertical-align: middle;
}

/* Badges de stock */
.product-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
    text-align: center;
    min-width: 40px;
}

.badge-in-stock {
    background: #d4edda;
    color: #155724;
}

.badge-low-stock {
    background: #fff3cd;
    color: #856404;
}

.badge-out-stock {
    background: #f8d7da;
    color: #721c24;
}

/* Especificaciones compactas */
.specs-preview {
    font-size: 11px;
    color: #666;
}

.spec-count {
    background: #f0f0f0;
    padding: 2px 6px;
    border-radius: 12px;
    font-size: 10px;
    display: inline-block;
}

/* Botón de acción compacto */
.button-small {
    font-size: 16px;
    padding: 4px 10px;
    line-height: 1;
    height: auto;
    min-width: 32px;
}

/* Product description preview */
.product-desc-preview {
    color: #6c757d;
    font-size: 11px;
}

/* Paginación */
.woo-pagination {
    margin-top: 20px;
    text-align: center;
}

.woo-pagination .tablenav-pages {
    display: inline-block;
}

.woo-pagination .pagination-links {
    display: inline-block;
    margin-left: 10px;
}

.woo-pagination .page-numbers {
    display: inline-block;
    padding: 5px 12px;
    margin: 0 2px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #2271b1;
}

.woo-pagination .page-numbers.current {
    background: #2271b1;
    border-color: #2271b1;
    color: white;
}

.woo-pagination .page-numbers:hover:not(.current) {
    background: #f0f0f0;
}

/* Modal */
.product-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.product-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.6);
}

.product-modal-container {
    position: relative;
    background: white;
    width: 90%;
    max-width: 700px;
    max-height: 85vh;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.product-modal-header {
    background: #f8f9fa;
    padding: 20px 24px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.product-modal-header h2 {
    margin: 0;
    font-size: 20px;
    color: #23282d;
}

.modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.modal-close:hover {
    color: #da291c;
}

.product-modal-body {
    padding: 24px;
    overflow-y: auto;
    max-height: calc(85vh - 120px);
}

.modal-row {
    margin-bottom: 20px;
    display: flex;
    flex-wrap: wrap;
}

.modal-label {
    width: 120px;
    font-weight: 600;
    color: #495057;
    flex-shrink: 0;
}

.modal-value {
    flex: 1;
    color: #212529;
    line-height: 1.5;
}

.modal-specs {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.spec-item {
    padding: 6px 0;
    border-bottom: 1px solid #f0f0f0;
}

.product-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e9ecef;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    background: #f8f9fa;
}

/* Responsive */
@media (max-width: 782px) {
    .modal-row {
        flex-direction: column;
    }
    
    .modal-label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .woo-search-box .regular-text {
        width: 100%;
    }
    
    .woo-search-box form {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Modal functionality
    var $modal = $('#product-modal');
    
    function openModal(productData) {
        $('#modal-product-title').text(productData.name);
        $('#modal-price').text(productData.price || 'Consultar');
        
        var stockHtml = '';
        if (productData.stock > 10) {
            stockHtml = '<span class="product-badge badge-in-stock">✓ ' + productData.stock + ' uds</span>';
        } else if (productData.stock > 0) {
            stockHtml = '<span class="product-badge badge-low-stock">⚠️ ' + productData.stock + ' uds</span>';
        } else {
            stockHtml = '<span class="product-badge badge-out-stock">❌ Agotado</span>';
        }
        $('#modal-stock').html(stockHtml);
        
        $('#modal-brand').text(productData.brand || '—');
        
        var categories = productData.categories;
        if (Array.isArray(categories) && categories.length) {
            $('#modal-categories').text(categories.join(', '));
        } else if (typeof categories === 'string' && categories) {
            $('#modal-categories').text(categories);
        } else {
            $('#modal-categories').text('—');
        }
        
        $('#modal-short-desc').html(productData.shortDesc || 'No disponible');
        $('#modal-desc').html(productData.longDesc || productData.shortDesc || 'No disponible');
        
        var specsHtml = '';
        if (productData.specs && productData.specs.length) {
            specsHtml = '<div class="modal-specs">';
            $.each(productData.specs, function(key, value) {
                if (typeof key === 'number') {
                    specsHtml += '<div class="spec-item">✓ ' + escapeHtml(value) + '</div>';
                } else {
                    specsHtml += '<div class="spec-item"><strong>' + escapeHtml(key) + ':</strong> ' + escapeHtml(value) + '</div>';
                }
            });
            specsHtml += '</div>';
        } else {
            specsHtml = '<p>No hay especificaciones técnicas</p>';
        }
        $('#modal-specs').html(specsHtml);
        
        $('#modal-product-link').attr('href', productData.url);
        
        $modal.fadeIn(200);
    }
    
    function closeModal() {
        $modal.fadeOut(200);
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    $('.view-product').on('click', function() {
        var $btn = $(this);
        
        var productData = {
            id: $btn.data('id'),
            name: $btn.data('name'),
            price: $btn.data('price'),
            stock: $btn.data('stock'),
            brand: $btn.data('brand'),
            categories: $btn.data('categories'),
            shortDesc: $btn.data('short-desc'),
            longDesc: $btn.data('desc'),
            specs: $btn.data('specs'),
            url: $btn.data('url')
        };
        
        if (typeof productData.categories === 'string') {
            try {
                productData.categories = JSON.parse(productData.categories);
            } catch(e) {
                productData.categories = [productData.categories];
            }
        }
        
        if (typeof productData.specs === 'string') {
            try {
                productData.specs = JSON.parse(productData.specs);
            } catch(e) {
                productData.specs = [];
            }
        }
        
        openModal(productData);
    });
    
    $('.modal-close, .modal-close-btn, .product-modal-overlay').on('click', function() {
        closeModal();
    });
    
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            closeModal();
        }
    });
});
</script>