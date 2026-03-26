jQuery(document).ready(function($) {
    class RAGChat {
        constructor() {
            this.widget   = $('#rag-chat-widget');
            this.button   = $('#rag-chat-button');
            this.window   = $('#rag-chat-window');
            this.closeBtn = $('#rag-chat-close');
            this.messages = $('#rag-chat-messages');
            this.input    = $('#rag-chat-input');
            this.sendBtn  = $('#rag-chat-send');

            this.isOpen   = false;
            this.isTyping = false;

            this.init();
        }

        init() {
            this.sendBtn.html('<svg viewBox="0 0 24 24" width="20" height="20" fill="white"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>');
            this.addTooltip();
            this.bindEvents();
            this.loadHistory();
        }
        
        addTooltip() {
            if (!$('.rag-chat-tooltip').length) {
                this.tooltip = $('<div class="rag-chat-tooltip">¡Hola! ¿Necesitas ayuda? 👋</div>');
                this.widget.append(this.tooltip);
                this.tooltip.on('click', (e) => {
                    e.stopPropagation();
                    this.toggleChat();
                });
            }
        }
        
        bindEvents() {
            this.button.on('click', (e) => {
                e.stopPropagation();
                this.toggleChat();
            });

            this.closeBtn.on('click', () => this.toggleChat());
            this.sendBtn.on('click', () => this.sendMessage());
            
            this.input.on('keypress', (e) => {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });

            $(document).on('click', (e) => {
                if (this.isOpen && !$(e.target).closest('#rag-chat-widget').length) {
                    this.toggleChat();
                }
            });

            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) this.toggleChat();
            });
        }

        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.window.fadeIn(300);
                this.input.trigger('focus');
                if (this.tooltip) this.tooltip.fadeOut(200);
                setTimeout(() => this.scrollToBottom(), 100);
            } else {
                this.window.fadeOut(200);
                if (this.tooltip) this.tooltip.fadeIn(200);
            }
        }

        sendMessage() {
            const message = this.input.val().trim();
            if (!message || this.isTyping) return;

            // 🔥 LOG: Pregunta enviada
            console.log('===========================================');
            console.log('📤 PREGUNTA ENVIADA:', message);
            console.log('===========================================');

            this.addMessage(message, 'user');
            this.input.val('');
            this.showTyping(true);

            $.ajax({
                url:  chat_rag.ajax_url,
                type: 'POST',
                data: {
                    action:   'chat_rag_query',
                    question: message,
                    nonce:    chat_rag.nonce
                },
                success: (response) => {
                    this.showTyping(false);

                    console.log('📥 RESPUESTA RECIBIDA (RAW):', response);

                    if (response.success) {
                        const answer = response.data.answer;
                        const context = response.data.debug_context;  
                        
                        // Mostrar mensaje
                        this.addMessage(answer, 'bot');
                        
                    } else {
                        const errMsg = (typeof response.data === 'object' && response.data.message)
                            ? response.data.message
                            : (response.data || 'Error desconocido');
                        this.addMessage('⚠️ ' + errMsg, 'bot error');
                    }
                },
                error: (xhr, status, error) => {
                    this.showTyping(false);
                    console.error('❌ AJAX ERROR:', status, error);
                    console.error('Response:', xhr.responseText);
                    this.addMessage('⚠️ Error de conexión con el servidor.', 'bot error');
                }
            });
        }

        addMessage(text, type) {
            const messageDiv = $('<div>').addClass(`rag-message ${type}`);
            messageDiv.html(this.formatMessage(text));
            const time = $('<div>').addClass('message-time').text(this.getCurrentTime());
            messageDiv.append(time);
            this.messages.append(messageDiv);
            this.scrollToBottom();
        }

        formatMessage(text) {
    if (!text) return '';
    let formatted = String(text);
    
    // 🔥 1. LIMPIEZA EXTREMA DE ESPACIOS
    // Eliminar líneas vacías al inicio
    formatted = formatted.replace(/^\s*\n+/, '');
    // Reducir múltiples saltos de línea a máximo 1
    formatted = formatted.replace(/\n\s*\n/g, '\n');
    // Eliminar espacios al inicio de cada línea
    formatted = formatted.replace(/^[ \t]+/gm, '');
    // Eliminar líneas vacías al final
    formatted = formatted.replace(/\n\s*$/, '');
    // Eliminar espacios entre número y punto (1.  Producto -> 1. Producto)
    formatted = formatted.replace(/(\d+)\.\s+/g, '$1. ');
    // Eliminar espacios después de viñetas
    formatted = formatted.replace(/([•\-*])\s+/g, '$1 ');
    
    // 2. Convertir URLs a links clickables
    formatted = formatted.replace(/(https?:\/\/[^\s<>"]+)/g, function(url) {
        let cleanUrl = url.replace(/[)]+$/, '');
        return `<a href="${cleanUrl}" target="_blank" class="pbt-product-link">🔗 Ver producto</a>`;
    });
    
    // 3. Markdown básico
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/_(.*?)_/g, '<em>$1</em>');
    
    // 4. Procesar líneas para crear HTML limpio
    const lines = formatted.split('\n');
    let result = '';
    let inOrderedList = false;
    let inUnorderedList = false;
    let lastWasEmpty = false;
    
    for (let i = 0; i < lines.length; i++) {
        let line = lines[i];
        let trimmed = line.trim();
        
        // Saltar líneas vacías (pero controlar para no crear espacios extra)
        if (trimmed === '') {
            lastWasEmpty = true;
            continue;
        }
        
        // Detectar lista numerada (1., 2., etc.)
        if (trimmed.match(/^\d+\.\s/)) {
            if (inUnorderedList) {
                result += '</ul>';
                inUnorderedList = false;
            }
            if (!inOrderedList) {
                if (lastWasEmpty && result.endsWith('</p>')) {
                    // No agregar espacio extra
                }
                result += '<ol>';
                inOrderedList = true;
            }
            const content = trimmed.replace(/^\d+\.\s+/, '');
            result += `<li>${content}</li>`;
            lastWasEmpty = false;
        }
        // Detectar viñetas (•, -, *)
        else if (trimmed.match(/^[•\-*]\s/)) {
            if (inOrderedList) {
                result += '</ol>';
                inOrderedList = false;
            }
            if (!inUnorderedList) {
                if (lastWasEmpty && result.endsWith('</p>')) {
                    // No agregar espacio extra
                }
                result += '<ul>';
                inUnorderedList = true;
            }
            const content = trimmed.replace(/^[•\-*]\s+/, '');
            result += `<li>${content}</li>`;
            lastWasEmpty = false;
        }
        // Detectar el separador ---
        else if (trimmed === '---') {
            if (inOrderedList) {
                result += '</ol>';
                inOrderedList = false;
            }
            if (inUnorderedList) {
                result += '</ul>';
                inUnorderedList = false;
            }
            result += '<hr class="separator">';
            lastWasEmpty = false;
        }
        // Línea normal
        else {
            if (inOrderedList) {
                result += '</ol>';
                inOrderedList = false;
            }
            if (inUnorderedList) {
                result += '</ul>';
                inUnorderedList = false;
            }
            result += `<p>${trimmed}</p>`;
            lastWasEmpty = false;
        }
    }
    
    // Cerrar listas si quedaron abiertas
    if (inOrderedList) result += '</ol>';
    if (inUnorderedList) result += '</ul>';
    
    // 5. Limpiar elementos vacíos
    result = result.replace(/<p><\/p>/g, '');
    result = result.replace(/<ul><\/ul>/g, '');
    result = result.replace(/<ol><\/ol>/g, '');
    
    // 6. Eliminar espacios entre etiquetas consecutivas
    result = result.replace(/<\/p>\s*<p>/g, '</p><p>');
    result = result.replace(/<\/ul>\s*<p>/g, '</ul><p>');
    result = result.replace(/<\/ol>\s*<p>/g, '</ol><p>');
    result = result.replace(/<p>\s*<ul>/g, '<p><ul>');
    result = result.replace(/<p>\s*<ol>/g, '<p><ol>');
    
    return result;
}

        showTyping(show) {
            if (show && !this.isTyping) {
                this.isTyping = true;
                const typingDiv = $('<div>').attr('id', 'rag-chat-typing').addClass('rag-message bot');
                typingDiv.append('<span class="typing-dot"></span>'.repeat(3));
                this.messages.append(typingDiv);
                this.scrollToBottom();
            } else if (!show && this.isTyping) {
                this.isTyping = false;
                $('#rag-chat-typing').remove();
            }
        }

        scrollToBottom() {
            if (this.messages.length) {
                this.messages.animate({ scrollTop: this.messages[0].scrollHeight }, 300);
            }
        }

        getCurrentTime() {
            return new Date().toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' });
        }

        loadHistory() {
            const welcome = '**¡Hola!** 👋\n\nSoy el asistente virtual de **PBTechnologies**.\n\n¿En qué puedo ayudarte hoy?';
            this.addMessage(welcome, 'bot');
        }
    }

    // Asegurarse de que el HTML esté presente antes de iniciar
    if (!$('#rag-chat-widget').length) {
        $('body').append(`
            <div id="rag-chat-widget">
                <div id="rag-chat-button"></div>
                <div id="rag-chat-window" style="display:none;">
                    <div id="rag-chat-header">
                        <span>Asistente PBT</span>
                        <button id="rag-chat-close">×</button>
                    </div>
                    <div id="rag-chat-messages"></div>
                    <div id="rag-chat-input-area">
                        <input type="text" id="rag-chat-input" placeholder="Escribe tu pregunta...">
                        <button id="rag-chat-send"></button>
                    </div>
                </div>
            </div>
        `);
    }

    new RAGChat();
});