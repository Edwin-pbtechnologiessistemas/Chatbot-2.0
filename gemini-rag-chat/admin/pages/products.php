<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Productos en Base de Datos</h1>
    
    <?php if (empty($products)): ?>
        <div class="notice notice-warning">
            <p>No hay productos importados todavía. <a href="<?php echo admin_url('admin.php?page=gemini-rag-import-products'); ?>">Importar productos</a></p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                32<th width="50">ID</th>
                    <th>Nombre</th>
                    <th width="120">Categoría</th>
                    <th width="100">Marca</th>
                    <th width="80">Precio</th>
                    <th width="100">Disponibilidad</th>
                    <th width="100">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo intval($product->id); ?></td>
                    <td><strong><?php echo esc_html($product->product_name); ?></strong><br>
                        <small><?php echo esc_html(substr($product->short_description, 0, 60)); ?>...</small>
                    </td>
                    <td><?php echo esc_html($product->category); ?><br>
                        <small><?php echo esc_html($product->subcategory); ?></small>
                    </td>
                    <td><?php echo esc_html($product->brand); ?></td>
                    <td><?php echo esc_html($product->price); ?></td>
                    <td><?php echo esc_html($product->availability ?: 'Disponible'); ?></td>
                    <td>
                        <a href="#" class="view-product" data-id="<?php echo intval($product->id); ?>">Ver</a> |
                        <a href="#" class="delete-product" data-id="<?php echo intval($product->id); ?>">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Modal para ver producto -->
<div id="product-modal" style="display:none;">
    <div class="product-details"></div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.delete-product').on('click', function(e) {
        e.preventDefault();
        if (confirm('¿Estás seguro de eliminar este producto?')) {
            var id = $(this).data('id');
            var row = $(this).closest('tr');
            
            $.post(gemini_rag_admin.ajax_url, {
                action: 'chat_rag_delete_product',
                id: id,
                nonce: gemini_rag_admin.nonce
            }, function(response) {
                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                        if ($('tbody tr').length === 0) {
                            location.reload();
                        }
                    });
                } else {
                    alert('Error al eliminar: ' + response.data);
                }
            });
        }
    });
    
    $('.view-product').on('click', function(e) {
        e.preventDefault();
        var id = $(this).data('id');
        // Aquí podrías mostrar un modal con los detalles completos
        alert('Función en desarrollo. ID: ' + id);
    });
});
</script>