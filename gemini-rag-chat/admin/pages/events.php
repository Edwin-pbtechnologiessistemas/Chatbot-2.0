<?php if (!defined('ABSPATH')) exit;

global $wpdb;

// Configuración de paginación
$items_per_page = 10;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Filtros
$search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';

$view_name = $wpdb->prefix . 'rag_events_view';

// Verificar si la vista existe, si no, crearla
$view_exists = $wpdb->get_var("SHOW TABLES LIKE '{$view_name}'");

if (!$view_exists) {
    if (class_exists('ChatRAG_EventsCalendar')) {
        $events_calendar = new ChatRAG_EventsCalendar();
        $events_calendar->createEventsView();
        echo '<div class="notice notice-success is-dismissible"><p>✅ Vista de eventos y seminarios creada correctamente.</p></div>';
    }
}

// Construir condiciones WHERE
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = $wpdb->prepare(
        "(titulo LIKE %s OR categorias LIKE %s OR etiquetas LIKE %s OR ubicacion LIKE %s OR meta_description LIKE %s)",
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%',
        '%' . $wpdb->esc_like($search) . '%'
    );
}

if (!empty($type_filter) && in_array($type_filter, ['evento_calendar', 'seminario'])) {
    $where_conditions[] = $wpdb->prepare("tipo = %s", $type_filter);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Obtener total de elementos
$total_items = $wpdb->get_var("
    SELECT COUNT(*) FROM {$view_name}
    {$where_clause}
");

// Obtener elementos paginados
$items = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$view_name}
    {$where_clause}
    ORDER BY 
        CASE 
            WHEN tipo = 'evento_calendar' AND estado = 'Próximo' THEN 1
            WHEN tipo = 'evento_calendar' AND estado = 'En curso' THEN 2
            WHEN tipo = 'seminario' THEN 3
            ELSE 4
        END,
        fecha_inicio DESC
    LIMIT %d OFFSET %d
", $items_per_page, $offset));

$total_pages = ceil($total_items / $items_per_page);

// Obtener resumen
$summary = $wpdb->get_row("
    SELECT 
        SUM(CASE WHEN tipo = 'evento_calendar' THEN 1 ELSE 0 END) as events,
        SUM(CASE WHEN tipo = 'seminario' THEN 1 ELSE 0 END) as seminars,
        SUM(CASE WHEN estado = 'Próximo' THEN 1 ELSE 0 END) as upcoming,
        COUNT(*) as total
    FROM {$view_name}
");
?>

<div class="wrap">
    <h1>📅 Eventos y Seminarios</h1>
    
    <div class="events-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <p class="description">Total: <strong><?php echo intval($total_items); ?></strong> elementos 
            (<?php echo intval($summary->events); ?> eventos | <?php echo intval($summary->seminars); ?> seminarios)</p>
        </div>
        <div class="action-buttons">
            <a href="<?php echo admin_url('edit.php?post_type=tribe_events'); ?>" class="button">
                📅 Gestionar Eventos
            </a>
            <a href="<?php echo admin_url('edit.php?category_name=Seminarios-grabados'); ?>" class="button">
                🎥 Gestionar Seminarios
            </a>
            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=refresh_events_view'), 'refresh_events_view'); ?>" 
               class="button button-primary"
               style="background: #21759b; border-color: #1e5a7a;">
                🔄 Sincronizar
            </a>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="events-filters" style="background: white; padding: 15px 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <form method="get" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="page" value="gemini-rag-events">
            <input type="text" name="search" value="<?php echo esc_attr($search); ?>" 
                   placeholder="Buscar por título, categoría, descripción..." class="regular-text" style="min-width: 250px;">
            <select name="type">
                <option value="">Todos los tipos</option>
                <option value="evento_calendar" <?php selected($type_filter, 'evento_calendar'); ?>>📅 Eventos</option>
                <option value="seminario" <?php selected($type_filter, 'seminario'); ?>>🎥 Seminarios Grabados</option>
            </select>
            <button type="submit" class="button">🔍 Buscar</button>
            <?php if (!empty($search) || !empty($type_filter)): ?>
                <a href="?page=gemini-rag-events" class="button">Limpiar</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Tarjetas de resumen -->
    <div class="events-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px;">
        <div class="summary-card" style="background: white; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #21759b; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 24px; font-weight: bold; color: #21759b;"><?php echo intval($summary->events); ?></div>
            <div style="color: #666;">📅 Eventos</div>
        </div>
        <div class="summary-card" style="background: white; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #28a745; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo intval($summary->seminars); ?></div>
            <div style="color: #666;">🎥 Seminarios Grabados</div>
        </div>
        <div class="summary-card" style="background: white; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #ffc107; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="font-size: 24px; font-weight: bold; color: #ffc107;"><?php echo intval($summary->upcoming); ?></div>
            <div style="color: #666;">⏰ Próximos Eventos</div>
        </div>
    </div>
    
    <?php if (empty($items)): ?>
        <div class="notice notice-warning">
            <p>
                <?php 
                if (!empty($search) || !empty($type_filter)) {
                    echo 'No se encontraron elementos con los filtros seleccionados.';
                } else {
                    echo 'No hay elementos sincronizados. <a href="' . wp_nonce_url(admin_url('admin-post.php?action=refresh_events_view'), 'refresh_events_view') . '">Sincronizar ahora</a>';
                }
                ?>
            </p>
        </div>
    <?php else: ?>
        <div class="events-table" style="background: white; border-radius: 12px; overflow-x: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    32
                        <th width="50">ID</th>
                        <th width="60">Tipo</th>
                        <th width="280">Título / Descripción</th>
                        <th width="100">Fecha</th>
                        <th width="120">Ubicación</th>
                        <th width="100">Categorías</th>
                        <th width="80">Estado</th>
                        <th width="80">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo intval($item->ID); ?></td>
                        <td>
                            <?php 
                            if ($item->tipo == 'evento_calendar') {
                                echo '<span style="font-size: 20px;">📅</span><br><small>Evento</small>';
                            } else {
                                echo '<span style="font-size: 20px;">🎥</span><br><small>Seminario</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <strong><?php echo esc_html($item->titulo); ?></strong><br>
                            <small><?php echo esc_html(substr($item->descripcion_corta, 0, 80)); ?></small>
                            <?php if ($item->meta_description): ?>
                                <br><small class="meta-desc" style="color: #666;">🔍 SEO: <?php echo esc_html(substr($item->meta_description, 0, 100)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($item->fecha_formateada); ?>
                            <?php if ($item->hora): ?>
                                <br><small><?php echo esc_html($item->hora); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            if ($item->ubicacion == 'Online') {
                                echo '<span class="event-badge online" style="background: #e8f0fe; color: #1e5a7a; padding: 2px 6px; border-radius: 4px; font-size: 11px;">🌐 Online</span>';
                            } else {
                                echo esc_html($item->ubicacion);
                                if ($item->ciudad) {
                                    echo '<br><small>' . esc_html($item->ciudad) . '</small>';
                                }
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (!empty($item->categorias)) {
                                $categorias = explode(', ', $item->categorias);
                                echo '<span class="category-badge" style="background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-size: 11px;">' . esc_html($categorias[0]) . '</span>';
                                if (count($categorias) > 1) {
                                    echo ' +' . (count($categorias) - 1);
                                }
                            } else {
                                echo '—';
                            }
                            ?>
                            <?php if ($item->etiquetas): ?>
                                <br><small style="color: #666;">🏷️ <?php echo esc_html(substr($item->etiquetas, 0, 30)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            if ($item->tipo == 'evento_calendar') {
                                if ($item->estado == 'Próximo') {
                                    echo '<span style="background: #e3f2fd; color: #1976d2; padding: 3px 8px; border-radius: 12px; font-size: 11px;">⏰ Próximo</span>';
                                } elseif ($item->estado == 'En curso') {
                                    echo '<span style="background: #e8f5e9; color: #388e3c; padding: 3px 8px; border-radius: 12px; font-size: 11px;">🟢 En curso</span>';
                                } else {
                                    echo '<span style="background: #f5f5f5; color: #757575; padding: 3px 8px; border-radius: 12px; font-size: 11px;">✅ Finalizado</span>';
                                }
                            } else {
                                echo '<span style="background: #e8f5e9; color: #388e3c; padding: 3px 8px; border-radius: 12px; font-size: 11px;">🎥 Disponible</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($item->url); ?>" target="_blank" class="button button-small" style="font-size: 12px; padding: 2px 8px; height: auto;">Ver</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginación -->
        <?php if ($total_pages > 1): ?>
        <div class="events-pagination" style="margin-top: 20px; text-align: center;">
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo intval($total_items); ?> elementos</span>
                <span class="pagination-links">
                    <?php
                    $page_links = paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => array_filter(['search' => $search, 'type' => $type_filter])
                    ));
                    echo $page_links;
                    ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.events-filters select {
    height: 30px;
}
.button-small {
    font-size: 12px;
    padding: 2px 8px;
    height: auto;
}
.meta-desc {
    color: #666;
    font-size: 10px;
}
</style>