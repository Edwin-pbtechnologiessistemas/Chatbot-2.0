<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Información de la Empresa</h1>
    
    <?php if (empty($company_info)): ?>
        <div class="notice notice-warning">
            <p>No hay información de empresa importada todavía. <a href="<?php echo admin_url('admin.php?page=gemini-rag-import-company'); ?>">Importar información</a></p>
        </div>
    <?php else: ?>
        <div class="company-info-grid">
            <?php 
            $grouped = [];
            foreach ($company_info as $info) {
                $grouped[$info->info_type][] = $info;
            }
            ?>
            
            <?php foreach ($grouped as $type => $items): ?>
                <div class="info-section">
                    <h2><?php echo ucfirst($type); ?></h2>
                    
                    <?php foreach ($items as $item): ?>
                        <div class="info-card" data-id="<?php echo $item->id; ?>">
                            <h3><?php echo esc_html($item->title); ?></h3>
                            <div class="content"><?php echo nl2br(esc_html($item->content)); ?></div>
                            <?php if (!empty($item->subcontent)): ?>
                                <div class="subcontent"><?php echo nl2br(esc_html($item->subcontent)); ?></div>
                            <?php endif; ?>
                            <div class="keywords"><small>Keywords: <?php echo esc_html($item->keywords); ?></small></div>
                            <div class="actions">
                                <a href="#" class="edit-company" data-id="<?php echo $item->id; ?>">Editar</a> |
                                <a href="#" class="delete-company" data-id="<?php echo $item->id; ?>">Eliminar</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    $('.delete-company').on('click', function(e) {
        e.preventDefault();
        if (confirm('¿Estás seguro de eliminar esta información?')) {
            var id = $(this).data('id');
            var card = $(this).closest('.info-card');
            
            $.post(gemini_rag_admin.ajax_url, {
                action: 'chat_rag_delete_company',
                id: id,
                nonce: gemini_rag_admin.nonce
            }, function(response) {
                if (response.success) {
                    card.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error al eliminar: ' + response.data);
                }
            });
        }
    });
});
</script>