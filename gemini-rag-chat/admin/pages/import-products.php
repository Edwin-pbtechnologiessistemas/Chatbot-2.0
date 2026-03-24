<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1>Importar Productos</h1>
    
    <div class="import-container">
        <h2>📤 Subir archivo CSV</h2>
        
        <div class="format-info">
            <h3 style="margin-top: 0;">📋 Columnas requeridas:</h3>
            <div style="background: #fff; padding: 15px; border-radius: 5px; font-family: monospace;">
                product_name | category | subcategory | brand | short_description | long_description | specifications | price | product_url
            </div>
            
            <h3>📌 Ejemplo:</h3>
            <div style="background: #fff; padding: 15px; border-radius: 5px; overflow-x: auto;">
                Termómetro Infrarrojo,Termografía,Termómetros,FLIR,"Termómetro sin contacto","Mide temperatura a distancia","Rango: -30°C a 650°C; Precisión: ±2°C",850 Bs,https://pbt.com.bo/producto/termometro
            </div>
            
            <h3>⚠️ Importante:</h3>
            <ul style="margin-bottom: 0;">
                <li>✅ Guarda tu Excel como <strong>CSV UTF-8</strong> (delimitado por comas)</li>
                <li>✅ La primera fila debe ser los nombres de las columnas</li>
                <li>✅ Las keywords se generan automáticamente</li>
                <li>✅ Los productos se actualizan si la URL ya existe</li>
            </ul>
        </div>
        
        <form id="import-products-form" enctype="multipart/form-data">
            <input type="file" id="product-file" name="file" accept=".csv" required 
                   style="margin-bottom: 20px; padding: 10px; border: 2px dashed #ccc; width: 100%;">
            
            <button type="submit" class="button button-primary" style="padding: 15px 30px; font-size: 16px;">
                Importar Productos
            </button>
        </form>
        
        <div id="import-progress" style="display:none; margin-top: 20px;">
            <div style="background: #f0f0f0; height: 4px; border-radius: 2px;">
                <div class="progress-fill" style="width: 0%; height: 100%; background: #da291c; border-radius: 2px;"></div>
            </div>
            <p class="progress-text" style="text-align: center;">Procesando...</p>
        </div>
        
        <div id="import-result" style="margin-top: 20px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#import-products-form').on('submit', function(e) {
        e.preventDefault();
        
        var file = $('#product-file')[0].files[0];
        if (!file) {
            alert('Por favor selecciona un archivo');
            return;
        }
        
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Solo se permiten archivos CSV');
            return;
        }
        
        $('#import-progress').show();
        $('.progress-fill').css('width', '0%');
        $('.progress-text').text('Subiendo archivo...');
        
        var formData = new FormData();
        formData.append('action', 'chat_rag_import_products');
        formData.append('file', file);
        formData.append('nonce', gemini_rag_admin.nonce);
        
        $.ajax({
            url: gemini_rag_admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        var percent = Math.round((e.loaded / e.total) * 100);
                        $('.progress-fill').css('width', percent + '%');
                        $('.progress-text').text('Subiendo: ' + percent + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                $('.progress-fill').css('width', '100%');
                $('.progress-text').text('Procesando...');
                
                setTimeout(function() {
                    $('#import-progress').hide();
                    if (response.success) {
                        $('#import-result').html('<div class="notice notice-success"><p>' + response.data.replace(/\n/g, '<br>') + '</p></div>');
                    } else {
                        $('#import-result').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                }, 500);
            },
            error: function() {
                $('#import-progress').hide();
                $('#import-result').html('<div class="notice notice-error"><p>Error en la conexión</p></div>');
            }
        });
    });
});
</script>