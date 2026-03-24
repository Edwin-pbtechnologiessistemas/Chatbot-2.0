<?php if (!defined('ABSPATH')) exit;

// Procesar importación si se envió el formulario
$import_result = '';
if (isset($_POST['import_company']) && isset($_FILES['company_file'])) {
    $import_result = process_company_import($_FILES['company_file']);
}

function process_company_import($file) {
    global $wpdb;
    $table = $wpdb->prefix . 'rag_company_info';
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return '<div class="notice notice-error"><p>Error al subir el archivo</p></div>';
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        return '<div class="notice notice-error"><p>Solo se permiten archivos CSV</p></div>';
    }
    
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) {
        return '<div class="notice notice-error"><p>No se pudo leer el archivo</p></div>';
    }
    
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        return '<div class="notice notice-error"><p>El archivo no tiene encabezados</p></div>';
    }
    
    $headers = array_map('trim', $headers);
    $headers = array_map('strtolower', $headers);
    
    $required = ['info_type', 'title', 'content'];
    $missing = array_diff($required, $headers);
    
    if (!empty($missing)) {
        fclose($handle);
        return '<div class="notice notice-error"><p>Columnas requeridas faltantes: ' . implode(', ', $missing) . '</p></div>';
    }
    
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
        
        $info_type = isset($data[$col_index['info_type']]) ? trim($data[$col_index['info_type']]) : '';
        $title = isset($data[$col_index['title']]) ? trim($data[$col_index['title']]) : '';
        $content = isset($data[$col_index['content']]) ? trim($data[$col_index['content']]) : '';
        $subcontent = isset($data[$col_index['subcontent']]) ? trim($data[$col_index['subcontent']]) : '';
        $order_index = isset($data[$col_index['order_index']]) ? intval($data[$col_index['order_index']]) : 0;
        $keywords = isset($data[$col_index['keywords']]) ? trim($data[$col_index['keywords']]) : '';
        
        if (empty($info_type) || empty($title) || empty($content)) {
            $errors[] = "Fila $row_num: datos incompletos";
            continue;
        }
        
        $result = $wpdb->insert($table, [
            'info_type' => $info_type,
            'title' => $title,
            'content' => $content,
            'subcontent' => $subcontent,
            'order_index' => $order_index,
            'keywords' => $keywords
        ]);
        
        if ($result) {
            $count++;
        } else {
            $errors[] = "Fila $row_num: error BD - " . $wpdb->last_error;
        }
    }
    
    fclose($handle);
    
    if ($count > 0) {
        $message = "<div class='notice notice-success'><p>✅ Se importaron $count registros de empresa correctamente</p>";
        if (!empty($errors)) {
            $message .= "<p>⚠️ Errores: " . implode('<br>', array_slice($errors, 0, 5)) . "</p>";
        }
        $message .= "</div>";
        return $message;
    } else {
        return "<div class='notice notice-error'><p>No se importó ningún registro: " . implode(', ', $errors) . "</p></div>";
    }
}
?>

<div class="wrap">
    <h1>📋 Importar Información de Empresa</h1>
    
    <?php echo $import_result; ?>
    
    <div class="import-container">
        <h2>Subir archivo CSV</h2>
        
        <div class="format-info">
            <h3>📌 Columnas requeridas:</h3>
            <p style="font-family: monospace; font-size: 16px; background: white; padding: 10px; border-radius: 5px;">
                info_type, title, content, subcontent, order_index, keywords
            </p>
            
            <h3>📋 Tipos disponibles:</h3>
            <p>
                <strong>empresa</strong> - Información general de la empresa<br>
                <strong>mision</strong> - Misión y visión<br>
                <strong>servicios</strong> - Servicios ofrecidos<br>
                <strong>marcas</strong> - Marcas representadas<br>
                <strong>contacto</strong> - Información de contacto<br>
                <strong>ubicacion</strong> - Dirección y ubicación<br>
                <strong>horario</strong> - Horarios de atención<br>
                <strong>redes_sociales</strong> - Redes sociales
            </p>
            
            <h3>📝 Ejemplo:</h3>
            <pre style="background: white; padding: 10px; border-radius: 5px; overflow-x: auto;">
info_type,title,content,subcontent,order_index,keywords
empresa,PBTechnologies S.R.L.,"Empresa boliviana especializada en termografía industrial","Más de 10 años de experiencia",1,"empresa, termografia"
contacto,Información de Contacto,"Teléfono: 591-123456","WhatsApp: 591-789012",2,"contacto, telefono"
ubicacion,Dirección,"Av. Principal #123, La Paz",,3,"ubicacion, direccion"
            </pre>
        </div>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="import_company" value="1">
            
            <input type="file" name="company_file" id="company_file" accept=".csv" required 
                   style="margin-bottom: 20px; padding: 10px; border: 2px dashed #ccc; width: 100%;">
            
            <button type="submit" class="button button-primary" style="padding: 15px 30px; font-size: 16px;">
                Importar Información
            </button>
        </form>
        
        <div style="margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=gemini-rag-company'); ?>" class="button">
                Ver Información de Empresa
            </a>
        </div>
    </div>
</div>