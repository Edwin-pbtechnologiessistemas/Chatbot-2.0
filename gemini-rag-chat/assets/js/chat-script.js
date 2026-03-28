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
            
            // Variables para control de scroll
            this.userScrolled = false;
            this.scrollTimeout = null;
            
            // Variables para notificaciones
            this.unreadCount = 0;
            this.lastMessageTime = null;
            this.notificationSound = null;
            this.isWindowFocused = true;
            
            this.init();
        }

        init() {
            this.sendBtn.html(`
                <svg viewBox="0 0 24 22" width="20" height="20" fill="white">
                    <path d="M2 21L23 12L2 3V10L17 12L2 14V21Z"></path>
                </svg>
            `);
            
            this.initNotifications();
            this.addNotificationBadge();
            this.detectWindowFocus();
            this.addTooltip();
            this.bindEvents();
            this.loadHistory();
            this.detectUserScroll();
        }
        
        // 🔥 MÉTODO PARA MOSTRAR ERRORES EN EL CHAT
        showErrorMessage(errorType) {
            let message = '';
            
            switch(errorType) {
                case 'timeout':
                    message = '⏱️ El servidor tardó demasiado en responder. Por favor, intenta de nuevo.';
                    break;
                case 'network':
                    message = '📡 Error de conexión. Verifica tu conexión a internet e intenta de nuevo.';
                    break;
                case 'server_500':
                    message = '⚠️ Error interno del servidor (500). Nuestro equipo ya fue notificado.';
                    break;
                case 'server_404':
                    message = '🔍 El servicio de asistente no está disponible temporalmente.';
                    break;
                case 'server_403':
                    message = '🔒 Acceso denegado. Por favor, contacta al administrador.';
                    break;
                case 'server_429':
                    message = '📊 Demasiadas consultas. Por favor, espera un momento.';
                    break;
                case 'empty_response':
                    message = '⚠️ El asistente no pudo generar una respuesta. Por favor, intenta de nuevo.';
                    break;
                default:
                    message = '⚠️ Error de conexión con el servidor. Por favor, intenta de nuevo.';
            }
            
            this.addMessage(message, 'bot error');
            
            // Log en consola para depuración (solo para desarrolladores)
            console.error(`❌ ERROR [${errorType}]`);
        }
        
        initNotifications() {
            window.addEventListener('blur', () => {
                this.isWindowFocused = false;
            });
            
            window.addEventListener('focus', () => {
                this.isWindowFocused = true;
                if (this.isOpen) {
                    this.resetUnreadCount();
                }
            });
        }
        
        addNotificationBadge() {
            if (!$('#rag-chat-notification-badge').length) {
                this.badge = $(`
                    <div id="rag-chat-notification-badge" style="display: none;">
                        <span class="badge-count">0</span>
                    </div>
                `);
                this.widget.append(this.badge);
            } else {
                this.badge = $('#rag-chat-notification-badge');
            }
        }
        
        updateNotificationBadge() {
            if (this.unreadCount > 0) {
                this.badge.find('.badge-count').text(this.unreadCount);
                this.badge.fadeIn(200);
                this.updateTabTitle();
                this.button.addClass('pulse-animation');
            } else {
                this.badge.fadeOut(200);
                this.button.removeClass('pulse-animation');
                this.resetTabTitle();
            }
        }
        
        updateTabTitle() {
            const originalTitle = document.title;
            if (!this.originalTitle) {
                this.originalTitle = originalTitle;
            }
            
            if (this.unreadCount > 0 && !document.title.includes('(')) {
                document.title = `(${this.unreadCount}) ${this.originalTitle}`;
            }
        }
        
        resetTabTitle() {
            if (this.originalTitle) {
                document.title = this.originalTitle;
            }
        }
        
        resetUnreadCount() {
            this.unreadCount = 0;
            this.updateNotificationBadge();
        }
        
        incrementUnreadCount(message) {
            if (!this.isOpen || !this.isWindowFocused) {
                this.unreadCount++;
                this.updateNotificationBadge();
                this.showDesktopNotification(message);
            }
        }
        
        showDesktopNotification(message) {
            if (!("Notification" in window)) return;
            
            if (Notification.permission === "granted") {
                const notification = new Notification("Nuevo mensaje de PBT Assistant", {
                    body: message.substring(0, 100),
                    icon: chat_rag.icon_url || '',
                    silent: false
                });
                
                notification.onclick = () => {
                    window.focus();
                    this.toggleChat();
                    notification.close();
                };
            } else if (Notification.permission !== "denied") {
                Notification.requestPermission();
            }
        }
        
        detectWindowFocus() {
            $(window).on('focus', () => {
                this.isWindowFocused = true;
                if (this.isOpen) {
                    this.resetUnreadCount();
                }
            });
            
            $(window).on('blur', () => {
                this.isWindowFocused = false;
            });
        }
        
        truncateText(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        }
        
        detectUserScroll() {
            const self = this;
            this.messages.on('scroll', function() {
                self.userScrolled = true;
                
                clearTimeout(self.scrollTimeout);
                self.scrollTimeout = setTimeout(() => {
                    self.userScrolled = false;
                }, 2000);
                
                if (self.isUserAtBottom()) {
                    self.resetUnreadCount();
                }
            });
        }
        
        isUserAtBottom() {
            const scrollPosition = this.messages.scrollTop() + this.messages.innerHeight();
            const scrollHeight = this.messages[0].scrollHeight;
            const threshold = 100;
            return scrollPosition >= scrollHeight - threshold;
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
                this.resetUnreadCount();
            } else {
                this.window.fadeOut(200);
                if (this.tooltip) this.tooltip.fadeIn(200);
            }
        }

        sendMessage() {
            const message = this.input.val().trim();
            if (!message || this.isTyping) return;

            this.addMessage(message, 'user');
            this.input.val('');
            this.showTyping(true);

            let timeoutId = setTimeout(() => {
                if (this.isTyping) {
                    this.showTyping(false);
                    this.showErrorMessage('timeout');
                }
            }, 30000);

            $.ajax({
                url: chat_rag.ajax_url,
                type: 'POST',
                timeout: 35000,
                data: {
                    action: 'chat_rag_query',
                    question: message,
                    nonce: chat_rag.nonce
                },
                success: (response) => {
                    clearTimeout(timeoutId);
                    this.showTyping(false);
                    
                    if (!response) {
                        this.showErrorMessage('empty_response');
                        return;
                    }
                    
                    if (response.success) {
                        const answer = response.data.answer;
                        if (answer && answer.trim()) {
                            this.addMessage(answer, 'bot');
                        } else {
                            this.showErrorMessage('empty_response');
                        }
                    } else {
                        const errMsg = (typeof response.data === 'object' && response.data.message)
                            ? response.data.message
                            : (response.data || 'Error desconocido');
                        this.showErrorMessage('invalid_response');
                    }
                },
                error: (xhr, status, error) => {
                    clearTimeout(timeoutId);
                    this.showTyping(false);
                    
                    // Clasificar error para mostrar mensaje adecuado
                    if (status === 'timeout') {
                        this.showErrorMessage('timeout');
                    } else if (status === 'error' && !navigator.onLine) {
                        this.showErrorMessage('network');
                    } else if (xhr.status === 500) {
                        this.showErrorMessage('server_500');
                    } else if (xhr.status === 404) {
                        this.showErrorMessage('server_404');
                    } else if (xhr.status === 403) {
                        this.showErrorMessage('server_403');
                    } else if (xhr.status === 429) {
                        this.showErrorMessage('server_429');
                    } else {
                        this.showErrorMessage('network');
                    }
                }
            });
        }
        
        addMessage(text, type) {
            const messageDiv = $('<div>').addClass(`rag-message ${type}`);
            messageDiv.html(this.formatMessage(text));
            const time = $('<div>').addClass('message-time').text(this.getCurrentTime());
            messageDiv.append(time);
            this.messages.append(messageDiv);
            
            if (type === 'bot' && (!this.isOpen || !this.isWindowFocused)) {
                this.incrementUnreadCount(text);
            }
            
            if (!this.userScrolled && this.isUserAtBottom()) {
                this.scrollToBottom();
            }
            
            return messageDiv;
        }

        formatMessage(text) {
            if (!text) return '';
            let formatted = String(text);
            
            formatted = formatted.replace(/^\s*\n+/, '');
            formatted = formatted.replace(/\n\s*\n/g, '\n');
            formatted = formatted.replace(/^[ \t]+/gm, '');
            formatted = formatted.replace(/\n\s*$/, '');
            formatted = formatted.replace(/(\d+)\.\s+/g, '$1. ');
            formatted = formatted.replace(/([•\-*])\s+/g, '$1 ');
            
            formatted = formatted.replace(/(https?:\/\/[^\s<>"]+)/g, function(url) {
                let cleanUrl = url.replace(/[)]+$/, '');
                return `<a href="${cleanUrl}" target="_blank" class="pbt-product-link">🔗 Ver producto</a>`;
            });
            
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            formatted = formatted.replace(/_(.*?)_/g, '<em>$1</em>');
            
            const lines = formatted.split('\n');
            let result = '';
            let inOrderedList = false;
            let inUnorderedList = false;
            let lastWasEmpty = false;
            
            for (let i = 0; i < lines.length; i++) {
                let line = lines[i];
                let trimmed = line.trim();
                
                if (trimmed === '') {
                    lastWasEmpty = true;
                    continue;
                }
                
                if (trimmed.match(/^\d+\.\s/)) {
                    if (inUnorderedList) {
                        result += '</ul>';
                        inUnorderedList = false;
                    }
                    if (!inOrderedList) {
                        result += '<ol>';
                        inOrderedList = true;
                    }
                    const content = trimmed.replace(/^\d+\.\s+/, '');
                    result += `<li>${content}</li>`;
                    lastWasEmpty = false;
                }
                else if (trimmed.match(/^[•\-*]\s/)) {
                    if (inOrderedList) {
                        result += '</ol>';
                        inOrderedList = false;
                    }
                    if (!inUnorderedList) {
                        result += '<ul>';
                        inUnorderedList = true;
                    }
                    const content = trimmed.replace(/^[•\-*]\s+/, '');
                    result += `<li>${content}</li>`;
                    lastWasEmpty = false;
                }
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
            
            if (inOrderedList) result += '</ol>';
            if (inUnorderedList) result += '</ul>';
            
            result = result.replace(/<p><\/p>/g, '');
            result = result.replace(/<\/p>\s*<p>/g, '</p><p>');
            
            return result;
        }

        showTyping(show) {
            if (show && !this.isTyping) {
                this.isTyping = true;
                const typingDiv = $('<div>').attr('id', 'rag-chat-typing').addClass('rag-message bot');
                typingDiv.append('<span class="typing-dot"></span>'.repeat(3));
                this.messages.append(typingDiv);
                
                if (!this.userScrolled && this.isUserAtBottom()) {
                    this.scrollToBottom();
                }
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
                        <input type="text" id="rag-chat-input" placeholder="Escribe tu pregunta..." maxlength="250">
                        <button id="rag-chat-send"></button>
                    </div>
                </div>
            </div>
        `);
    }
    
    $('#rag-chat-input').on('input', function() {
        const isInvalid = $(this).val().trim().length === 0;
        $('#rag-chat-send').prop('disabled', isInvalid).css('opacity', isInvalid ? '0.5' : '1');
    });
    
    if ("Notification" in window && Notification.permission === "default") {
        setTimeout(() => {
            Notification.requestPermission();
        }, 5000);
    }

    new RAGChat();
});