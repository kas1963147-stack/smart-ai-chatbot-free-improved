/**
 * Quarksol AI Chatbot Frontend JavaScript
 * Enhanced with: Multi-Agent Support, Chat History, Proactive Engagement, 
 * Quick Reply Buttons, Voice Input, Product Comparison, and Streaming
 */
(function ($) {
    'use strict';

    const SWCChatbot = {
        context: '',
        orderId: '',
        chatHistory: [],
        proactiveShown: false,
        compareList: [],

        // Multi-Agent System Properties
        currentAgent: null,
        availableAgents: [],
        sessionId: null,
        visitorId: null,
        isStreaming: false,
        soundEnabled: true,

        init: function () {
            this.generateVisitorId();
            this.loadChatHistory();
            this.loadSoundPreference();
            this.applyAppearanceSettings(); // Apply appearance from server settings
            this.bindEvents();
            this.bindControlEvents();

            // Resolve agents for current page
            this.resolveAgents().then(() => {
                this.initProactiveEngagement();

                // Show welcome if no history
                if (this.chatHistory.length === 0) {
                    this.showWelcome();
                } else {
                    this.restoreChatHistory();
                }
            });
        },

        /**
         * Apply appearance settings from server configuration
         * CSS variables are already injected via PHP inline styles
         * This handles JavaScript-side appearance config
         */
        applyAppearanceSettings: function () {
            const settings = window.swcChatbot || {};
            const appearance = settings.appearance || {};
            const controls = settings.controls || {};

            // Apply sound preference from server if not overridden locally
            if (appearance.enableSounds !== undefined) {
                const localSoundPref = localStorage.getItem('swc_sound_enabled');
                if (localSoundPref === null) {
                    this.soundEnabled = appearance.enableSounds;
                }
            }

            // Apply header controls
            this.renderHeaderControls(controls.header || ['close']);

            // Apply footer controls  
            this.renderFooterControls(controls.footer || ['voice']);

            // Store appearance for later use
            this.appearance = appearance;
        },

        /**
         * Render dynamic header controls based on settings
         */
        renderHeaderControls: function (enabledControls) {
            if (!Array.isArray(enabledControls) || enabledControls.length === 0) return;

            const $headerActions = $('.swc-header-actions');
            if ($headerActions.length === 0) return;

            let controlsHtml = '';

            if (enabledControls.includes('reset')) {
                controlsHtml += '<button class="swc-header-ctrl-btn" data-action="reset" title="Reset conversation"></button>';
            }
            if (enabledControls.includes('sound')) {
                const soundIcon = this.soundEnabled ? '' : '';
                controlsHtml += `<button class="swc-header-ctrl-btn swc-sound-btn" data-action="sound" title="Toggle sound">${soundIcon}</button>`;
            }
            if (enabledControls.includes('minimize')) {
                controlsHtml += '<button class="swc-header-ctrl-btn" data-action="minimize" title="Minimize">−</button>';
            }

            if (controlsHtml) {
                $headerActions.prepend(controlsHtml);
            }
        },

        /**
         * Render dynamic footer controls based on settings
         */
        renderFooterControls: function (enabledControls) {
            if (!Array.isArray(enabledControls) || enabledControls.length === 0) return;

            const $inputArea = $('.swc-input-area');
            if ($inputArea.length === 0) return;

            // Voice button is always rendered in HTML, just toggle visibility
            if (!enabledControls.includes('voice')) {
                $('.swc-voice-btn').hide();
            }
        },


        // ============ MULTI-AGENT SYSTEM ============

        generateVisitorId: function () {
            try {
                // Migration: check for old key and migrate
                let visitorId = localStorage.getItem('swc_visitor_id');
                if (!visitorId) {
                    visitorId = 'v_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
                    localStorage.setItem('swc_visitor_id', visitorId);
                }
                this.visitorId = visitorId;
            } catch (e) {
                this.visitorId = 'v_' + Date.now();
            }
        },

        resolveAgents: async function () {
            const self = this;
            const settings = window.swcChatbot || {};
            const restUrl = settings.restUrl || settings.apiUrl || '/wp-json/smart-ai-chatbot/v1';

            try {
                // Build page context
                const params = new URLSearchParams({
                    url: window.location.href,
                    page_id: settings.pageId || '',
                    post_type: settings.postType || '',
                    is_cart: settings.isCart || 'false',
                    is_checkout: settings.isCheckout || 'false',
                    is_account: settings.isAccount || 'false'
                });

                const response = await fetch(restUrl + '/resolve?' + params.toString(), {
                    headers: {
                        'X-WP-Nonce': settings.nonce
                    }
                });

                if (!response.ok) throw new Error('Failed to resolve agents');

                const data = await response.json();
                self.availableAgents = data.agents || [];

                // If multiple agents, show selector; otherwise use first
                if (data.requires_selection && self.availableAgents.length > 1) {
                    self.showAgentSelector();
                } else if (self.availableAgents.length > 0) {
                    self.selectAgent(self.availableAgents[0]);
                }

            } catch (e) {
                console.warn('Agent resolution failed:', e);
                // Continue with default behavior
            }
        },

        showAgentSelector: function () {
            const self = this;

            let html = `
                <div class="smart-ai-chatbot-selector">
                    <div class="smart-ai-chatbot-selector-header">
                        <h4>Choose an assistant</h4>
                        <p>Select who you'd like to chat with:</p>
                    </div>
                    <div class="smart-ai-chatbot-options">
            `;

            this.availableAgents.forEach(agent => {
                // Normalize avatar: support multiple formats
                const avatar = agent.avatar_emoji || agent.avatar || '';
                html += `
                    <button class="smart-ai-chatbot-option" data-agent-id="${agent.id}">
                        <span class="smart-ai-chatbot-avatar">${avatar}</span>
                        <span class="smart-ai-chatbot-name">${this.escapeHtml(agent.name)}</span>
                    </button>
                `;
            });

            html += `
                    </div>
                </div>
            `;

            const $messages = $('.swc-messages');
            $messages.append(`<div class="swc-message swc-bot">${html}</div>`);
            $messages.scrollTop($messages[0].scrollHeight);

            // Bind selector events
            $('.smart-ai-chatbot-option').on('click', function () {
                const agentId = $(this).data('agent-id');
                const agent = self.availableAgents.find(a => a.id === agentId);
                if (agent) {
                    $('.smart-ai-chatbot-selector').closest('.swc-message').remove();
                    self.selectAgent(agent);
                }
            });
        },

        selectAgent: function (agent) {
            this.currentAgent = agent;

            // Update header with agent info
            this.updateHeaderForAgent(agent);

            // Show agent's welcome message (support both formats)
            const welcomeMsg = agent.welcome_message || agent.welcomeMessage;
            if (welcomeMsg && this.chatHistory.length === 0) {
                this.addBotMessage(welcomeMsg, false);
            }

            // Update quick actions (support both formats)
            const quickActions = agent.quick_actions || agent.quickActions || [];
            this.updateQuickActions(quickActions);

            // Create/restore session
            this.initSession();
        },

        updateHeaderForAgent: function (agent) {
            const $header = $('.swc-header');

            if (agent) {
                // Support multiple avatar formats
                const avatar = agent.avatar_emoji || agent.avatar || '';
                $header.find('.swc-avatar').text(avatar);
                $header.find('.swc-bot-name').text(agent.name);
            }
        },

        updateQuickActions: function (quickActions) {
            if (!quickActions || quickActions.length === 0) return;

            const $container = $('.swc-quick-actions');
            if ($container.length === 0) return;

            $container.empty();

            quickActions.forEach(action => {
                // Support both action.action/action.label and action.id/action.name
                const actionId = action.action || action.id;
                const actionLabel = action.label || action.name;
                $container.append(`
                    <button class="swc-quick-btn" data-action="${actionId}">
                        ${this.escapeHtml(actionLabel)}
                    </button>
                `);
            });
        },

        initSession: async function () {
            const self = this;
            const settings = window.swcChatbot || {};
            const restUrl = settings.restUrl || settings.apiUrl || '/wp-json/smart-ai-chatbot/v1';

            if (!this.currentAgent) return;

            try {
                const response = await fetch(restUrl + '/sessions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': settings.nonce
                    },
                    body: JSON.stringify({
                        agent_id: this.currentAgent.id,
                        visitor_id: this.visitorId,
                        url: window.location.href
                    })
                });

                if (!response.ok) throw new Error('Failed to create session');

                const data = await response.json();
                // Support multiple session ID formats
                self.sessionId = data.session?.id || data.session_id || data.id;

                // Restore server-side messages if any
                const messages = data.messages || data.session?.messages || [];
                if (messages.length > 0) {
                    // Session has history on server
                }

            } catch (e) {
                console.warn('Session init failed:', e);
            }
        },

        // ============ CHAT HISTORY PERSISTENCE ============

        loadChatHistory: function () {
            try {
                let saved = localStorage.getItem('swc_chat_history');
                if (saved) {
                    const data = JSON.parse(saved);
                    // Only restore if less than 24 hours old
                    if (data.timestamp && (Date.now() - data.timestamp) < 86400000) {
                        this.chatHistory = data.messages || [];
                    } else {
                        localStorage.removeItem('swc_chat_history');
                    }
                }
            } catch (e) {
                console.warn('Could not load chat history:', e);
            }
        },

        saveChatHistory: function () {
            try {
                const data = {
                    timestamp: Date.now(),
                    messages: this.chatHistory.slice(-50) // Keep last 50 messages
                };
                localStorage.setItem('swc_chat_history', JSON.stringify(data));
            } catch (e) {
                console.warn('Could not save chat history:', e);
            }
        },

        restoreChatHistory: function () {
            const $messages = $('.swc-messages');
            $messages.empty();

            // Add welcome back message
            this.addBotMessage("Welcome back! I remember our previous conversation. How can I help you today?", false);

            // Restore last 10 messages
            const recentMessages = this.chatHistory.slice(-10);
            recentMessages.forEach(msg => {
                if (msg.type === 'user') {
                    this.addUserMessage(msg.text, false);
                } else {
                    this.addBotMessage(msg.text, false);
                }
            });
        },

        clearChatHistory: function () {
            this.chatHistory = [];
            localStorage.removeItem('swc_chat_history');
            $('.swc-messages').empty();
            this.showWelcome();
        },

        // ============ PROACTIVE ENGAGEMENT ============

        initProactiveEngagement: function () {
            const self = this;
            const settings = window.swcChatbot || {};
            const appSettings = settings.settings || {};
            const proactiveEnabled =
                appSettings.enable_proactive ??
                appSettings.proactive_enabled ??
                settings.enable_proactive ??
                settings.proactiveEnabled ??
                false;

            if (!proactiveEnabled) {
                return;
            }

            let delay = appSettings.proactive_delay ?? appSettings.proactiveDelay ?? 30;
            delay = parseInt(delay, 10);
            if (Number.isNaN(delay)) {
                delay = 30;
            }
            if (delay < 1000) {
                delay = delay * 1000;
            }

            // Don't show if chat was already opened or proactive was shown
            // Check both new and legacy keys for backwards compatibility
            if (localStorage.getItem('swc_proactive_shown') || localStorage.getItem('starter_proactive_shown')) {
                return;
            }

            setTimeout(function () {
                if (!$('#smart-ai-chatbot').hasClass('swc-open') && !self.proactiveShown) {
                    self.showProactivePopup();
                }
            }, delay);
        },

        showProactivePopup: function () {
            this.proactiveShown = true;
            localStorage.setItem('swc_proactive_shown', 'true');

            const messages = [
                "Need help finding something?",
                "Looking for a great deal?",
                "Have any questions? I'm here to help!"
            ];
            const randomMessage = messages[Math.floor(Math.random() * messages.length)];

            // Create popup bubble
            const popup = $(`
                <div class="swc-proactive-popup">
                    <div class="swc-proactive-content">
                        <span class="swc-proactive-close">×</span>
                        <p>${randomMessage}</p>
                        <button class="swc-proactive-btn">Chat Now</button>
                    </div>
                </div>
            `);

            $('#smart-ai-chatbot').append(popup);

            // Animate in
            setTimeout(() => popup.addClass('swc-show'), 100);

            // Auto-hide after 10 seconds
            setTimeout(() => {
                popup.removeClass('swc-show');
                setTimeout(() => popup.remove(), 300);
            }, 10000);
        },

        // ============ EVENTS ============

        bindEvents: function () {
            const self = this;

            // Keyboard navigation - Escape to close
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape' && $('#smart-ai-chatbot').hasClass('swc-open')) {
                    $('#smart-ai-chatbot').removeClass('swc-open');
                    $('.swc-toggle').focus();
                    self.announceToScreenReader('Chat closed');
                }
            });

            // Toggle chat
            $('.swc-toggle').on('click', function () {
                const isOpening = !$('#smart-ai-chatbot').hasClass('swc-open');
                $('#smart-ai-chatbot').toggleClass('swc-open');
                $('.swc-proactive-popup').remove();

                if (isOpening) {
                    // Focus on input when opening
                    setTimeout(() => $('.swc-input').focus(), 300);
                    self.announceToScreenReader('Chat opened');
                }
            });

            // Close button
            $('.swc-close').on('click', function () {
                $('#smart-ai-chatbot').removeClass('swc-open');
            });

            // Send message
            $('.swc-send').on('click', function () {
                self.sendMessage();
            });

            // Enter key - send message (unless Shift is held for new line)
            $('.swc-input').on('keydown', function (e) {
                if (e.which === 13) {
                    if (e.shiftKey) {
                        // Shift+Enter: insert new line (do nothing, browser handles it)
                        return true;
                    } else {
                        // Enter: send message
                        e.preventDefault();
                        self.sendMessage();
                    }
                }
            });

            // Quick actions
            $(document).on('click', '.swc-quick-btn', function () {
                const action = $(this).data('action');
                self.handleQuickAction(action);
            });

            // Delegate events for dynamic content
            $(document).on('click', '.swc-add-to-cart', function () {
                self.addToCart($(this));
            });

            $(document).on('click', '.swc-category-pill', function () {
                const category = $(this).data('slug');
                self.searchByCategory(category);
            });

            // Clickable time-slot buttons (appointment booking)
            $(document).on('click', '.swc-slot-btn', function (e) {
                e.preventDefault();
                const value = $(this).data('value');
                if (value) {
                    // Highlight selected button
                    $(this).closest('.swc-slot-actions').find('.swc-slot-btn').css({
                        opacity: '0.5',
                        pointerEvents: 'none'
                    });
                    $(this).css({
                        opacity: '1',
                        background: 'var(--swc-primary, #6366f1)',
                        color: '#fff',
                        borderColor: 'var(--swc-primary, #6366f1)'
                    });
                    // Send the booking message
                    $('.swc-input').val(value);
                    self.sendMessage();
                }
            });

            // Quick reply buttons (dynamic)
            $(document).on('click', '.swc-quick-reply', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const action = $(this).data('action');
                const productId = $(this).data('product-id');
                // Skip view action - handled by .swc-view-btn handler
                if (action !== 'view') {
                    self.handleQuickReply(action, productId, $(this));
                }
            });

            // Proactive popup events
            $(document).on('click', '.swc-proactive-close', function () {
                $(this).closest('.swc-proactive-popup').remove();
            });

            $(document).on('click', '.swc-proactive-btn', function () {
                $('#smart-ai-chatbot').addClass('swc-open');
                $('.swc-proactive-popup').remove();
            });

            // Compare checkbox
            $(document).on('change', '.swc-compare-check', function () {
                const productId = $(this).val();
                if ($(this).is(':checked')) {
                    if (self.compareList.length < 3) {
                        self.compareList.push(productId);
                    } else {
                        $(this).prop('checked', false);
                        self.addBotMessage("You can only compare up to 3 products at a time.");
                    }
                } else {
                    self.compareList = self.compareList.filter(id => id !== productId);
                }
                self.updateCompareButton();
            });

            // Compare button
            $(document).on('click', '.swc-compare-btn', function () {
                self.compareProducts();
            });

            // View product button - opens in NEW WINDOW only
            $(document).on('click', '.swc-view-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                const productId = $btn.data('product-id') || $btn.closest('.swc-product-card').data('product-id');
                let url = $btn.attr('data-url') || $btn.data('url');

                // Try to get URL from product card's link
                if (!url || url === '' || url === 'undefined') {
                    url = $btn.closest('.swc-product-card').find('.swc-product-name a').attr('href');
                }

                // If we have a URL, open it in new window
                if (url && url !== '' && url !== 'undefined') {
                    window.open(url, '_blank');
                    return;
                }

                // If no URL but we have product ID, fetch URL via AJAX
                if (productId) {
                    $btn.text('Loading...');
                    $.ajax({
                        url: swcChatbot.ajaxurl,
                        method: 'POST',
                        data: {
                            action: 'swc_get_product_url',
                            nonce: swcChatbot.nonce,
                            product_id: productId
                        },
                        success: function (response) {
                            if (response.success && response.data.url) {
                                window.open(response.data.url, '_blank');
                            } else {
                                alert('Could not find product page.');
                            }
                            $btn.text('View');
                        },
                        error: function () {
                            alert('Error loading product page.');
                            $btn.text('View');
                        }
                    });
                } else {
                    alert('Product not found. Please try again.');
                }
            });

            // Clear history button
            $(document).on('click', '.swc-clear-history', function () {
                self.clearChatHistory();
            });

            // Voice input
            $(document).on('click', '.swc-voice-btn', function () {
                self.startVoiceInput();
            });

            // Wishlist button
            $(document).on('click', '.swc-wishlist-btn', function () {
                const productId = $(this).data('product-id');
                self.addToWishlist(productId, $(this));
            });

            // Stock alert button
            $(document).on('click', '.swc-stock-alert-btn', function () {
                const productId = $(this).data('product-id');
                self.showStockAlertForm(productId);
            });

            // Stock alert submit
            $(document).on('submit', '.swc-stock-alert-form', function (e) {
                e.preventDefault();
                self.submitStockAlert($(this));
            });

            // ============ MESSAGE CONTROL BUTTON EVENTS ============

            // Message control button clicks
            $(document).on('click', '.swc-msg-ctrl-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const action = $(this).data('action');
                const messageId = $(this).data('msg-id');

                switch (action) {
                    case 'copy':
                        self.copyMessage(messageId);
                        break;
                    case 'edit':
                        self.editMessage(messageId);
                        break;
                    case 'delete':
                        self.deleteMessage(messageId);
                        break;
                    case 'regenerate':
                        self.regenerateResponse(messageId);
                        break;
                    case 'feedback-up':
                        self.submitFeedback(messageId, 'up');
                        break;
                    case 'feedback-down':
                        self.submitFeedback(messageId, 'down');
                        break;
                }
            });

            // Header control buttons
            $(document).on('click', '.swc-header-ctrl-btn', function (e) {
                e.preventDefault();
                const action = $(this).data('action');

                switch (action) {
                    case 'reset':
                        self.resetConversation();
                        break;
                    case 'sound':
                        self.toggleSound();
                        break;
                    case 'minimize':
                        $('#smart-ai-chatbot').removeClass('swc-open');
                        break;
                }
            });

            // Footer control buttons
            $(document).on('click', '.swc-footer-ctrl-btn', function (e) {
                e.preventDefault();
                const action = $(this).data('action');

                switch (action) {
                    case 'clear':
                        self.resetConversation();
                        break;
                    case 'export':
                        self.exportConversation('txt');
                        break;
                }
            });

            // Scroll to bottom button
            $(document).on('click', '.swc-scroll-bottom', function () {
                const $messages = $('.swc-messages');
                $messages.scrollTop($messages[0].scrollHeight);
            });
        },

        // ============ QUICK REPLY BUTTONS ============

        handleQuickReply: function (action, productId, $btn) {
            switch (action) {
                case 'view':
                    const url = $btn.data('url') || $btn.attr('data-url');
                    if (url) {
                        window.open(url, '_blank');
                    } else {
                        // Fallback: try to get URL from parent product card
                        const $card = $btn.closest('.swc-product-card');
                        const productLink = $card.find('.swc-product-name a').attr('href');
                        if (productLink) {
                            window.open(productLink, '_blank');
                        } else {
                            this.addBotMessage("Sorry, couldn't find the product link. Try clicking the product name instead.");
                        }
                    }
                    break;
                case 'similar':
                    $('.swc-input').val('Show me similar products to product ID ' + productId);
                    this.sendMessage();
                    break;
                case 'compare':
                    this.addToCompare(productId);
                    break;
                case 'wishlist':
                    this.addToWishlist(productId, $btn);
                    break;
            }
        },

        // ============ PRODUCT COMPARISON ============

        addToCompare: function (productId) {
            if (this.compareList.length >= 3) {
                this.addBotMessage("You can only compare up to 3 products. Remove one first!");
                return;
            }

            if (!this.compareList.includes(productId)) {
                this.compareList.push(productId);
                this.addBotMessage(`Product added to compare list (${this.compareList.length}/3). Add more products or say "compare products" to see comparison!`);
            } else {
                this.addBotMessage("This product is already in your compare list.");
            }
        },

        updateCompareButton: function () {
            const count = this.compareList.length;
            if (count >= 2) {
                if (!$('.swc-compare-float-btn').length) {
                    const btn = $(`<button class="swc-compare-float-btn">Compare (${count})</button>`);
                    $('.swc-messages').append(btn);
                }
                $('.swc-compare-float-btn').text(`Compare (${count})`);
            } else {
                $('.swc-compare-float-btn').remove();
            }
        },

        compareProducts: function () {
            if (this.compareList.length < 2) {
                this.addBotMessage("Please add at least 2 products to compare. You can say 'compare this' on any product.");
                return;
            }

            const productIds = this.compareList.join(',');
            $('.swc-input').val('Compare products: ' + productIds);
            this.sendMessage();
        },

        // ============ WISHLIST ============

        addToWishlist: function (productId, $btn) {
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: swcChatbot.ajaxurl,
                method: 'POST',
                data: {
                    action: 'swc_add_to_wishlist',
                    nonce: swcChatbot.nonce,
                    product_id: productId
                },
                success: (response) => {
                    if (response.success) {
                        $btn.addClass('swc-added').text('In Wishlist');
                        this.addBotMessage(response.data.message);
                    } else {
                        $btn.text(originalText).prop('disabled', false);
                        this.addBotMessage(response.data || 'Could not add to wishlist');
                    }
                },
                error: () => {
                    $btn.text(originalText).prop('disabled', false);
                    this.addBotMessage('Error adding to wishlist. Please try again.');
                }
            });
        },

        // ============ STOCK ALERTS ============

        showStockAlertForm: function (productId) {
            const html = `
                <div class="swc-message swc-bot">
                    <div class="swc-message-content">
                        <p>Enter your email to get notified when this is back in stock:</p>
                        <form class="swc-stock-alert-form">
                            <input type="hidden" name="product_id" value="${productId}">
                            <input type="email" name="email" placeholder="your@email.com" required class="swc-input-field">
                            <button type="submit" class="swc-submit-btn">Notify Me</button>
                        </form>
                    </div>
                </div>
            `;
            this.appendMessage(html);
        },

        submitStockAlert: function ($form) {
            const productId = $form.find('[name="product_id"]').val();
            const email = $form.find('[name="email"]').val();

            $.ajax({
                url: swcChatbot.ajaxurl,
                method: 'POST',
                data: {
                    action: 'swc_stock_alert',
                    nonce: swcChatbot.nonce,
                    product_id: productId,
                    email: email
                },
                success: (response) => {
                    if (response.success) {
                        $form.replaceWith('<p class="swc-success"> ' + response.data.message + '</p>');
                    } else {
                        this.addBotMessage(response.data || 'Could not register alert');
                    }
                }
            });
        },

        // ============ VOICE INPUT ============

        startVoiceInput: function () {
            if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
                this.addBotMessage("Sorry, voice input is not supported in your browser. Try Chrome or Edge!");
                return;
            }

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            const recognition = new SpeechRecognition();

            recognition.lang = 'en-US';
            recognition.interimResults = false;
            recognition.maxAlternatives = 1;
            recognition.continuous = false;

            const $voiceBtn = $('.swc-voice-btn');
            const self = this;

            $voiceBtn.addClass('swc-listening');
            this.addBotMessage(" Listening... Speak now! (Speak clearly within 5 seconds)");

            recognition.onresult = function (event) {
                const transcript = event.results[0][0].transcript;
                const confidence = Math.round(event.results[0][0].confidence * 100);
                $('.swc-input').val(transcript);
                self.addBotMessage(" Heard: \"" + transcript + "\" (" + confidence + "% confidence)");
                self.sendMessage();
                $voiceBtn.removeClass('swc-listening');
            };

            recognition.onerror = function (event) {
                $voiceBtn.removeClass('swc-listening');

                let errorMsg = "";
                switch (event.error) {
                    case 'no-speech':
                        errorMsg = " No speech detected. Please speak louder and try again.";
                        break;
                    case 'audio-capture':
                        errorMsg = " No microphone found. Please connect a microphone and try again.";
                        break;
                    case 'not-allowed':
                        errorMsg = " Microphone access denied. Please allow microphone permission in your browser settings.";
                        break;
                    case 'network':
                        errorMsg = " Network error. Speech recognition requires internet connection.";
                        break;
                    case 'aborted':
                        errorMsg = "Voice input cancelled.";
                        break;
                    default:
                        errorMsg = "Sorry, voice input failed (" + event.error + "). Please try again or type your message.";
                }
                self.addBotMessage(errorMsg);
            };

            recognition.onend = function () {
                $voiceBtn.removeClass('swc-listening');
            };

            try {
                recognition.start();
            } catch (e) {
                $voiceBtn.removeClass('swc-listening');
                this.addBotMessage("️ Could not start voice input. Make sure you're on HTTPS or localhost.");
            }
        },

        showWelcome: function () {
            const settings = swcChatbot.settings;
            this.addBotMessage(settings.welcomeMessage);
        },

        sendMessage: function (retryMessage) {
            const $input = $('.swc-input');
            const message = retryMessage || $input.val().trim();

            if (!message) return;

            // Store for retry
            this._lastMessage = message;

            // Add user message (only if not a retry)
            if (!retryMessage) {
                this.addUserMessage(message);
                $input.val('');
            }

            // Hide quick action buttons after user sends a message
            $('.swc-quick-actions').fadeOut(300);

            // Show typing indicator
            this.showTyping();

            // Check if streaming is enabled
            const settings = window.swcChatbot || {};
            const appSettings = settings.settings || {};
            const streamingEnabled =
                appSettings.enable_streaming ??
                appSettings.streaming_enabled ??
                settings.enable_streaming ??
                settings.streamingEnabled ??
                true;

            if (streamingEnabled) {
                this.sendMessageWithStreaming(message);
            } else {
                this.sendMessageWithAjax(message);
            }
        },

        // Send message with SSE streaming
        sendMessageWithStreaming: function (message) {
            const self = this;
            const settings = window.swcChatbot || {};
            const restUrl = settings.restUrl || settings.apiUrl || '/wp-json/smart-ai-chatbot/v1';

            // Create streaming message container
            const messageId = this.generateMessageId();
            const controls = this.getMessageControlsHtml(messageId, 'bot');
            const $streamingMsg = $(`
                <div class="swc-message swc-bot swc-streaming" data-message-id="${messageId}">
                    <div class="swc-message-content"><span class="swc-stream-text"></span><span class="swc-cursor">▌</span></div>
                    ${controls}
                </div>
            `);

            const $messages = $('.swc-messages');
            $messages.append($streamingMsg);
            this.scrollToBottom();

            this.isStreaming = true;
            let fullText = '';
            let buffer = '';
            let currentEvent = 'chunk';
            let finalHandled = false;
            let streamFinished = false;

            const finishStream = () => {
                if (streamFinished) {
                    return;
                }
                streamFinished = true;

                self.hideTyping();
                self.isStreaming = false;
                $streamingMsg.removeClass('swc-streaming');
                $streamingMsg.find('.swc-cursor').remove();

                if (fullText) {
                    self.chatHistory.push({ id: messageId, type: 'bot', text: fullText });
                    self.saveChatHistory();
                }

                self.playNotificationSound();
            };

            const handleStreamEvent = (eventName, data) => {
                if (!data || typeof data !== 'object') {
                    return;
                }

                if (eventName === 'error' || data.error) {
                    const errorMsg = data.message || data.error_message || 'An error occurred';
                    const renderedError = 'Error: ' + errorMsg;
                    fullText = fullText || renderedError;
                    $streamingMsg.find('.swc-stream-text').text(renderedError);
                    return;
                }

                if ((eventName === 'final' || data.final || data.response) && !finalHandled) {
                    finalHandled = true;
                    const responseData = data.response || data.payload || data;
                    if (responseData && typeof responseData === 'object') {
                        const response = responseData.response || responseData;
                        self.handleResponse({
                            ...response,
                            streamed: data.streamed ?? response.streamed ?? true
                        });
                    }
                    return;
                }

                if (eventName === 'done' || data.done || data.finished) {
                    finishStream();
                    return;
                }

                const textChunk = data.text || data.content || data.delta?.content || '';
                if (textChunk) {
                    fullText += textChunk;
                    $streamingMsg.find('.swc-stream-text').text(fullText);
                    self.scrollToBottom();
                }
            };

            // Prepare request body
            const body = JSON.stringify({
                message: message,
                session_id: this.sessionId || '',
                agent_id: this.currentAgent?.id || 0,
                context: this.context || '',
                visitor_id: this.visitorId || '',
                url: window.location.href
            });

            // Use fetch with streaming
            const streamUrl = restUrl + '/chat/stream';

            fetch(streamUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || ''
                },
                body: body
            }).then(response => {
                if (!response.ok) {
                    throw new Error('Stream request failed');
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                function processStream() {
                    return reader.read().then(({ done, value }) => {
                        if (done) {
                            finishStream();
                            return;
                        }

                        // Parse SSE data
                        buffer += decoder.decode(value, { stream: true });
                        const lines = buffer.split('\n');
                        buffer = lines.pop() || '';

                        lines.forEach(line => {
                            const trimmed = line.trim();
                            if (!trimmed) {
                                return;
                            }
                            if (trimmed.startsWith('event:')) {
                                currentEvent = trimmed.substring(6).trim() || 'chunk';
                                return;
                            }
                            if (trimmed.startsWith('data:')) {
                                const payload = trimmed.substring(5).trim();
                                if (!payload) {
                                    return;
                                }
                                try {
                                    const data = JSON.parse(payload);
                                    handleStreamEvent(currentEvent, data);
                                } catch (e) {
                                    // Ignore parse errors for partial chunks
                                }
                            }
                        });

                        // Continue reading
                        return processStream();
                    });
                }

                return processStream();

            }).catch(error => {
                console.error('Streaming failed:', error);
                self.hideTyping();
                self.isStreaming = false;
                $streamingMsg.remove();

                // Fall back to AJAX
                self.sendMessageWithAjax(message);
            });
        },

        // Original AJAX method (fallback)
        sendMessageWithAjax: function (message) {
            const requestData = {
                action: 'swc_chat_message',
                nonce: swcChatbot.nonce,
                message: message,
                context: this.context,
                order_id: this.orderId,
                visitor_id: this.visitorId || '',
                url: window.location.href
            };

            // Include agent info if available
            if (this.currentAgent && this.currentAgent.id) {
                requestData.agent_id = this.currentAgent.id;
            }
            if (this.sessionId) {
                requestData.session_id = this.sessionId;
            }

            // Send to server
            $.ajax({
                url: swcChatbot.ajaxurl,
                method: 'POST',
                data: requestData,
                success: (response) => {
                    this.hideTyping();

                    if (response.success) {
                        this.handleResponse(response.data);
                    } else {
                        this.addBotMessage('Sorry, something went wrong. Please try again.');
                    }
                },
                error: () => {
                    this.hideTyping();
                    this.addBotMessage('Connection error. Please try again.');
                }
            });
        },

        handleResponse: function (data) {
            if (data.session_id && data.session_id !== this.sessionId) {
                this.sessionId = data.session_id;
            }
            // Update context
            this.context = data.context || '';
            this.orderId = data.order_id || '';

            // Add main message
            if (data.message && !data.streamed) {
                this.addBotMessage(data.message);
            }

            // Handle different response types
            switch (data.type) {
                case 'error':
                    // Display error with optional retry button
                    this.renderError(data);
                    break;
                case 'products':
                    this.renderProducts(data.products);
                    break;
                case 'categories':
                    this.renderCategories(data.categories);
                    break;
                case 'order':
                    this.renderOrder(data.order);
                    break;
                case 'orders':
                    this.renderOrders(data.orders);
                    break;
                case 'cart':
                    this.renderCart(data.cart);
                    break;
                case 'comparison':
                    this.renderComparison(data.products);
                    break;
            }
        },

        renderError: function (data) {
            const $messages = $('.swc-messages');
            const retryable = data.retryable || false;
            const retryDelay = data.retry_after || 2000;

            let html = '<div class="swc-error-message" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin: 8px 0;">';
            html += '<span style="color: #dc2626;">' + (data.message || 'Something went wrong') + '</span>';

            if (retryable && this._lastMessage) {
                html += '<button class="swc-retry-btn" style="margin-left: 10px; background: #3b82f6; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;"> Retry</button>';
            }

            html += '</div>';

            const $error = $(html);
            $messages.append($error);

            // Handle retry button click
            if (retryable) {
                const self = this;
                $error.find('.swc-retry-btn').on('click', function () {
                    $(this).prop('disabled', true).text('Retrying...');
                    setTimeout(function () {
                        $error.remove();
                        if (self._lastMessage) {
                            self.sendMessage(self._lastMessage);
                        }
                    }, retryDelay);
                });
            }

            this.scrollToBottom();
        },

        handleQuickAction: function (action) {
            const messages = {
                'products': 'Show me all products',
                'bestsellers': 'Show me best sellers',
                'sale': 'What products are on sale?',
                'search': 'I want to search for products',
                'track': 'I want to track my order',
                'faq': 'What are your frequently asked questions?',
                'categories': 'Show me categories',
                'new': 'Show me new arrivals',
                'available': 'Show me available products',
                'delivery': 'Do you deliver to my location?'
            };

            if (messages[action]) {
                $('.swc-input').val(messages[action]);
                this.sendMessage();
            }
        },

        searchByCategory: function (categorySlug) {
            const message = 'products of ' + categorySlug;
            $('.swc-input').val(message);
            this.sendMessage();
        },

        addUserMessage: function (text, save = true) {
            const messageId = this.generateMessageId();
            const controls = this.getMessageControlsHtml(messageId, 'user');
            const html = `
                <div class="swc-message swc-user" data-message-id="${messageId}">
                    <div class="swc-message-content">${this.escapeHtml(text)}</div>
                    ${controls}
                </div>
            `;
            this.appendMessage(html);

            if (save) {
                this.chatHistory.push({ id: messageId, type: 'user', text: text });
                this.saveChatHistory();
            }
        },

        addBotMessage: function (text, save = true) {
            const messageId = this.generateMessageId();
            // Simple markdown parsing
            const parsedText = this.parseMarkdown(text);
            const controls = this.getMessageControlsHtml(messageId, 'bot');

            const html = `
                <div class="swc-message swc-bot" data-message-id="${messageId}">
                    <div class="swc-message-content">${parsedText}</div>
                    ${controls}
                </div>
            `;
            this.appendMessage(html);

            // Render clickable slot actions if present in the message text
            this.renderSlotActions(text, messageId);

            // Play notification sound if enabled
            this.playNotificationSound();

            if (save) {
                this.chatHistory.push({ id: messageId, type: 'bot', text: text });
                this.saveChatHistory();
            }
        },

        /**
         * Render clickable time-slot buttons when AI provides available slots
         * Detects JSON blocks with slot_actions or available_slots patterns
         */
        renderSlotActions: function (text, messageId) {
            if (!text) return;

            // Try to extract slot data from tool response embedded in the message
            let slotActions = null;

            // Pattern 1: Look for slot_actions JSON in the text
            try {
                const jsonMatch = text.match(/\{[\s\S]*?"slot_actions"\s*:\s*\[([\s\S]*?)\][\s\S]*?\}/);
                if (jsonMatch) {
                    const parsed = JSON.parse(jsonMatch[0]);
                    if (parsed.slot_actions && parsed.slot_actions.length > 0) {
                        slotActions = parsed.slot_actions;
                    }
                }
            } catch (e) { /* not JSON */ }

            // Pattern 2: Look for time patterns like "9:00 AM - 10:00 AM" listed in the response
            if (!slotActions) {
                const timePattern = /(\d{1,2}:\d{2}\s*(?:AM|PM))\s*(?:-|to|–)\s*(\d{1,2}:\d{2}\s*(?:AM|PM))/gi;
                const matches = [...text.matchAll(timePattern)];
                if (matches.length >= 3) {
                    // Extract date context from the message
                    const dateMatch = text.match(/(\w+day,?\s+\w+\s+\d{1,2}(?:st|nd|rd|th)?|\d{4}-\d{2}-\d{2})/);
                    const dateContext = dateMatch ? dateMatch[1] : 'the requested date';

                    slotActions = matches.slice(0, 10).map(m => ({
                        label: m[1] + ' - ' + m[2],
                        value: `Book the ${m[1]} slot on ${dateContext}`
                    }));
                }
            }

            if (!slotActions || slotActions.length === 0) return;

            // Render pill buttons
            let buttonsHtml = '<div class="swc-slot-actions" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;padding:4px 0;">';
            slotActions.forEach(slot => {
                const label = this.escapeHtml(slot.label || slot.display || slot.time);
                const value = this.escapeHtml(slot.value || `Book appointment at ${slot.time}`);
                buttonsHtml += `<button class="swc-slot-btn" data-value="${value}" style="
                    display:inline-flex;align-items:center;gap:4px;
                    padding:8px 14px;border-radius:20px;
                    border:1px solid var(--swc-primary, #6366f1);
                    background:transparent;
                    color:var(--swc-primary, #6366f1);
                    font-size:13px;font-weight:500;
                    cursor:pointer;
                    transition:all 0.2s;
                    white-space:nowrap;
                ">🕐 ${label}</button>`;
            });
            buttonsHtml += '</div>';

            // Append buttons after the message content
            const $msg = $(`.swc-message[data-message-id="${messageId}"]`);
            $msg.find('.swc-message-content').append(buttonsHtml);
            this.scrollToBottom();
        },

        appendMessage: function (html) {
            const $messages = $('.swc-messages');
            $messages.append(html);
            this.scrollToBottom();
        },

        scrollToBottom: function (smooth = true) {
            const $messages = $('.swc-messages');
            if ($messages.length && $messages[0]) {
                if (smooth && 'scrollBehavior' in document.documentElement.style) {
                    $messages[0].scrollTo({
                        top: $messages[0].scrollHeight,
                        behavior: 'smooth'
                    });
                } else {
                    $messages.scrollTop($messages[0].scrollHeight);
                }
            }
        },

        announceToScreenReader: function (message) {
            let $liveRegion = $('#swc-live-region');
            if (!$liveRegion.length) {
                $liveRegion = $('<div id="swc-live-region" class="swc-sr-only" aria-live="polite" aria-atomic="true"></div>');
                $('body').append($liveRegion);
            }
            // Clear and set to trigger announcement
            $liveRegion.text('');
            setTimeout(() => $liveRegion.text(message), 100);
        },

        showTyping: function () {
            // Use bouncing dots to mimic React widget ActiveToolIndicator
            const html = `
                <div class="swc-message swc-bot swc-typing-message">
                    <div class="swc-message-content" style="padding: 12px 16px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 13px; font-weight: 500; color: #475569;">AI is thinking...</span>
                            <span style="display: flex; gap: 4px; margin-left: 4px;">
                                <style>
                                    @keyframes swc-bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }
                                </style>
                                <span style="width: 6px; height: 6px; background-color: var(--swc-primary, #6366f1); border-radius: 50%; opacity: 0.8; animation: swc-bounce 1s infinite; animation-delay: 0ms;"></span>
                                <span style="width: 6px; height: 6px; background-color: var(--swc-primary, #6366f1); border-radius: 50%; opacity: 0.8; animation: swc-bounce 1s infinite; animation-delay: 150ms;"></span>
                                <span style="width: 6px; height: 6px; background-color: var(--swc-primary, #6366f1); border-radius: 50%; opacity: 0.8; animation: swc-bounce 1s infinite; animation-delay: 300ms;"></span>
                            </span>
                        </div>
                    </div>
                </div>
            `;
            this.appendMessage(html);

            // Announce to screen readers
            this.announceToScreenReader('Assistant is typing');
        },

        hideTyping: function () {
            $('.swc-typing-message').remove();
        },

        renderProducts: function (products) {
            let html = '<div class="swc-products">';

            products.forEach(product => {
                const stockClass = product.in_stock ? '' : 'out-of-stock';
                const btnText = product.in_stock ? ' Add to Cart' : 'Out of Stock';
                const btnDisabled = product.in_stock ? '' : 'disabled';

                html += `
                    <div class="swc-product-card" data-product-id="${product.id}">
                        <img src="${product.image}" alt="${this.escapeHtml(product.name)}" class="swc-product-image">
                        <div class="swc-product-info">
                            <div class="swc-product-name">
                                <a href="${product.url}" target="_blank">${this.escapeHtml(product.name)}</a>
                            </div>
                            <div class="swc-product-price">${product.price}</div>
                            <button class="swc-add-to-cart ${stockClass}" data-product-id="${product.id}" ${btnDisabled}>
                                ${btnText}
                            </button>
                            <div class="swc-product-actions">
                                <button class="swc-quick-reply swc-btn-sm swc-view-btn" data-action="view" data-url="${product.url || ''}" data-product-id="${product.id}">️ View</button>
                                <button class="swc-quick-reply swc-btn-sm" data-action="similar" data-product-id="${product.id}"> Similar</button>
                                ${!product.in_stock ? `<button class="swc-stock-alert-btn swc-btn-sm" data-product-id="${product.id}"> Notify</button>` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            this.appendMessage(`<div class="swc-message swc-bot">${html}</div>`);
        },

        renderCategories: function (categories) {
            let html = '<div class="swc-categories">';

            categories.forEach(cat => {
                html += `
                    <button class="swc-category-pill" data-slug="${cat.slug}">
                        ${this.escapeHtml(cat.name)} (${cat.count})
                    </button>
                `;
            });

            html += '</div>';
            this.appendMessage(`<div class="swc-message swc-bot">${html}</div>`);
        },

        renderOrder: function (order) {
            const statusClass = order.status === 'completed' ? 'completed' :
                order.status === 'processing' ? 'processing' : '';

            let itemsList = order.items.map(item =>
                `${item.name} × ${item.quantity}`
            ).join(', ');

            let html = `
                <div class="swc-order-card">
                    <div class="swc-order-header">
                        <span class="swc-order-number">Order #${order.order_number}</span>
                        <span class="swc-order-status ${statusClass}">${order.status_label}</span>
                    </div>
                    <div class="swc-order-date"> ${order.date}</div>
                    <div class="swc-order-items"> ${itemsList}</div>
                    <div class="swc-order-total">Total: ${order.total}</div>
            `;

            if (order.tracking) {
                html += `
                    <div class="swc-order-tracking">
                         ${order.tracking.carrier}: ${order.tracking.number}
                    </div>
                `;
            }

            html += '</div>';
            this.appendMessage(`<div class="swc-message swc-bot">${html}</div>`);
        },

        renderOrders: function (orders) {
            orders.forEach(order => {
                this.renderOrder(order);
            });
        },

        renderCart: function (cart) {
            const html = `
                <a href="${swcChatbot.checkoutUrl}" class="swc-cart-btn" target="_blank">
                     Go to Checkout (${cart.total})
                </a>
            `;
            this.appendMessage(`<div class="swc-message swc-bot">${html}</div>`);
        },

        renderComparison: function (products) {
            if (!products || products.length < 2) {
                this.addBotMessage("Not enough products to compare.");
                return;
            }

            let html = '<div class="swc-comparison-table"><table>';

            // Header row with product names
            html += '<tr><th>Feature</th>';
            products.forEach(p => {
                html += `<th>${this.escapeHtml(p.name)}</th>`;
            });
            html += '</tr>';

            // Image row
            html += '<tr><td>Image</td>';
            products.forEach(p => {
                html += `<td><img src="${p.image}" class="swc-compare-img"></td>`;
            });
            html += '</tr>';

            // Price row
            html += '<tr><td>Price</td>';
            products.forEach(p => {
                html += `<td class="swc-compare-price">${p.price}</td>`;
            });
            html += '</tr>';

            // Stock row
            html += '<tr><td>Availability</td>';
            products.forEach(p => {
                html += `<td>${p.in_stock ? ' In Stock' : ' Out of Stock'}</td>`;
            });
            html += '</tr>';

            // Rating row (if available)
            html += '<tr><td>Rating</td>';
            products.forEach(p => {
                html += `<td>${p.rating || 'N/A'} ⭐</td>`;
            });
            html += '</tr>';

            // Add to cart row
            html += '<tr><td>Action</td>';
            products.forEach(p => {
                if (p.in_stock) {
                    html += `<td><button class="swc-add-to-cart" data-product-id="${p.id}"> Add</button></td>`;
                } else {
                    html += `<td><button class="swc-stock-alert-btn" data-product-id="${p.id}"> Notify</button></td>`;
                }
            });
            html += '</tr>';

            html += '</table></div>';
            this.appendMessage(`<div class="swc-message swc-bot">${html}</div>`);

            // Clear compare list after showing
            this.compareList = [];
        },

        addToCart: function ($btn) {
            const productId = $btn.data('product-id');
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Adding...');

            $.ajax({
                url: swcChatbot.ajaxurl,
                method: 'POST',
                data: {
                    action: 'swc_add_to_cart',
                    nonce: swcChatbot.nonce,
                    product_id: productId,
                    quantity: 1
                },
                success: (response) => {
                    if (response.success) {
                        $btn.addClass('swc-added').text(' Added!');
                        this.addBotMessage(response.data.message);

                        setTimeout(() => {
                            $btn.removeClass('swc-added').text(originalText).prop('disabled', false);
                        }, 2000);
                    } else {
                        $btn.text(originalText).prop('disabled', false);
                        this.addBotMessage('Could not add to cart: ' + response.data);
                    }
                },
                error: () => {
                    $btn.text(originalText).prop('disabled', false);
                    this.addBotMessage('Error adding to cart. Please try again.');
                }
            });
        },

        parseMarkdown: function (text) {
            // Bold
            text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            // Links [text](url)
            text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
            // Newlines
            text = text.replace(/\n/g, '<br>');
            // Lists
            text = text.replace(/• (.+?)(?:<br>|$)/g, '<li>$1</li>');
            if (text.includes('<li>')) {
                text = '<ul>' + text + '</ul>';
            }
            return text;
        },

        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // ============ CHAT CONTROL METHODS ============

        /**
         * Get control settings from server configuration
         */
        getControlSettings: function () {
            return {
                header: window.swcChatbot?.controls?.header || ['close'],
                message: window.swcChatbot?.controls?.message || [],
                footer: window.swcChatbot?.controls?.footer || ['voice'],
                floating: window.swcChatbot?.controls?.floating || []
            };
        },

        /**
         * Generate unique message ID
         */
        generateMessageId: function () {
            return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        },

        /**
         * Edit a user message
         */
        editMessage: function (messageId) {
            const $message = $(`.swc-message[data-message-id="${messageId}"]`);
            if (!$message.length || !$message.hasClass('swc-user')) return;

            const $content = $message.find('.swc-message-content');
            const currentText = $content.text();

            // Create inline edit form
            const editHtml = `
                <div class="swc-edit-form">
                    <textarea class="swc-edit-input">${this.escapeHtml(currentText)}</textarea>
                    <div class="swc-edit-actions">
                        <button class="swc-edit-save">Save</button>
                        <button class="swc-edit-cancel">Cancel</button>
                    </div>
                </div>
            `;

            $content.hide();
            $message.append(editHtml);

            const $textarea = $message.find('.swc-edit-input');
            $textarea.focus().select();

            // Save handler
            $message.find('.swc-edit-save').on('click', () => {
                const newText = $textarea.val().trim();
                if (newText && newText !== currentText) {
                    $content.text(newText).show();
                    $message.find('.swc-edit-form').remove();

                    // Update history
                    const msgIndex = this.chatHistory.findIndex(m => m.id === messageId);
                    if (msgIndex > -1) {
                        this.chatHistory[msgIndex].text = newText;
                        this.saveChatHistory();
                    }
                } else {
                    $content.show();
                    $message.find('.swc-edit-form').remove();
                }
            });

            // Cancel handler
            $message.find('.swc-edit-cancel').on('click', () => {
                $content.show();
                $message.find('.swc-edit-form').remove();
            });
        },

        /**
         * Delete a single message
         */
        deleteMessage: function (messageId) {
            const $message = $(`.swc-message[data-message-id="${messageId}"]`);
            if (!$message.length) return;

            // Confirmation
            if (!confirm('Delete this message?')) return;

            $message.fadeOut(200, function () {
                $(this).remove();
            });

            // Update history
            this.chatHistory = this.chatHistory.filter(m => m.id !== messageId);
            this.saveChatHistory();
        },

        /**
         * Copy message text to clipboard
         */
        copyMessage: function (messageId) {
            const $message = $(`.swc-message[data-message-id="${messageId}"]`);
            if (!$message.length) return;

            const text = $message.find('.swc-message-content').text();

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast('Copied to clipboard!');
                }).catch(() => {
                    this.fallbackCopy(text);
                });
            } else {
                this.fallbackCopy(text);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function (text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            this.showToast('Copied to clipboard!');
        },

        /**
         * Regenerate AI response for a message
         */
        regenerateResponse: function (messageId) {
            const msgIndex = this.chatHistory.findIndex(m => m.id === messageId);
            if (msgIndex === -1 || msgIndex === 0) return;

            // Find the user message that triggered this response
            const userMessage = this.chatHistory[msgIndex - 1];
            if (!userMessage || userMessage.type !== 'user') return;

            // Remove the current bot message
            const $message = $(`.swc-message[data-message-id="${messageId}"]`);
            $message.remove();

            // Remove from history
            this.chatHistory = this.chatHistory.filter(m => m.id !== messageId);
            this.saveChatHistory();

            // Resend the user message
            this.sendMessage(userMessage.text);
        },

        /**
         * Export conversation to file
         */
        exportConversation: function (format = 'txt') {
            let content = '';
            const botName = window.swcChatbot?.settings?.botName || 'Assistant';

            if (format === 'json') {
                content = JSON.stringify({
                    exported: new Date().toISOString(),
                    messages: this.chatHistory
                }, null, 2);
            } else {
                // TXT format
                content = `Chat Export - ${new Date().toLocaleString()}\n`;
                content += '='.repeat(50) + '\n\n';

                this.chatHistory.forEach(msg => {
                    const sender = msg.type === 'user' ? 'You' : botName;
                    content += `${sender}:\n${msg.text}\n\n`;
                });
            }

            // Create and trigger download
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `chat-export-${Date.now()}.${format}`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            this.showToast('Chat exported!');
        },

        /**
         * Reset conversation with confirmation
         */
        resetConversation: function () {
            if (!confirm('Start a new conversation? This will clear all messages.')) return;

            this.chatHistory = [];
            localStorage.removeItem('swc_chat_history');
            $('.swc-messages').empty();
            this.showWelcome();
            this.showToast('Conversation reset!');
        },

        /**
         * Submit feedback (thumbs up/down) for a message
         */
        submitFeedback: function (messageId, type) {
            const $message = $(`.swc-message[data-message-id="${messageId}"]`);
            if (!$message.length) return;

            // Visual feedback
            const $thumbs = $message.find('.swc-feedback-btns button');
            $thumbs.removeClass('active');
            $message.find(`.swc-feedback-${type}`).addClass('active');

            // Send to server
            $.ajax({
                url: swcChatbot.ajaxurl,
                method: 'POST',
                data: {
                    action: 'swc_message_feedback',
                    nonce: swcChatbot.nonce,
                    message_id: messageId,
                    session_id: this.sessionId,
                    feedback_type: type
                },
                success: (response) => {
                    if (response.success) {
                        this.showToast('Thanks for your feedback!');
                    }
                }
            });
        },

        /**
         * Toggle sound notifications
         */
        toggleSound: function () {
            this.soundEnabled = !this.soundEnabled;
            localStorage.setItem('swc_sound_enabled', this.soundEnabled);
            this.showToast(this.soundEnabled ? 'Sound on' : 'Sound off');
        },

        /**
         * Show toast notification
         */
        showToast: function (message) {
            const $toast = $(`<div class="swc-toast">${this.escapeHtml(message)}</div>`);
            $('body').append($toast);

            setTimeout(() => $toast.addClass('swc-toast-show'), 10);
            setTimeout(() => {
                $toast.removeClass('swc-toast-show');
                setTimeout(() => $toast.remove(), 300);
            }, 2000);
        },

        /**
         * Add message control buttons based on settings
         */
        getMessageControlsHtml: function (messageId, messageType) {
            const controls = this.getControlSettings().message;
            if (!controls.length) return '';

            let html = '<div class="swc-msg-controls">';

            if (controls.includes('copy')) {
                html += `<button class="swc-msg-ctrl-btn" data-action="copy" data-msg-id="${messageId}" title="Copy"></button>`;
            }
            if (controls.includes('edit') && messageType === 'user') {
                html += `<button class="swc-msg-ctrl-btn" data-action="edit" data-msg-id="${messageId}" title="Edit">️</button>`;
            }
            if (controls.includes('delete')) {
                html += `<button class="swc-msg-ctrl-btn" data-action="delete" data-msg-id="${messageId}" title="Delete">️</button>`;
            }
            if (controls.includes('regenerate') && messageType === 'bot') {
                html += `<button class="swc-msg-ctrl-btn" data-action="regenerate" data-msg-id="${messageId}" title="Regenerate"></button>`;
            }
            if (controls.includes('feedback') && messageType === 'bot') {
                html += `
                    <span class="swc-feedback-btns">
                        <button class="swc-msg-ctrl-btn swc-feedback-up" data-action="feedback-up" data-msg-id="${messageId}" title="Good"></button>
                        <button class="swc-msg-ctrl-btn swc-feedback-down" data-action="feedback-down" data-msg-id="${messageId}" title="Bad"></button>
                    </span>
                `;
            }

            html += '</div>';
            return html;
        },

        /**
         * Load sound preference from localStorage
         */
        loadSoundPreference: function () {
            // Try new key first, fallback to legacy
            let saved = localStorage.getItem('swc_sound_enabled');
            if (saved === null) {
                saved = localStorage.getItem('starter_sound_enabled');
                if (saved !== null) {
                    localStorage.setItem('swc_sound_enabled', saved);
                    localStorage.removeItem('starter_sound_enabled');
                }
            }
            this.soundEnabled = saved !== 'false';
        },

        /**
         * Play notification sound when new message arrives
         */
        playNotificationSound: function () {
            if (!this.soundEnabled) return;
            if (!$('#smart-ai-chatbot').hasClass('swc-open')) {
                // Only play if chat is minimized
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();

                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);

                    oscillator.frequency.value = 800;
                    oscillator.type = 'sine';
                    gainNode.gain.value = 0.1;

                    oscillator.start();
                    oscillator.stop(audioContext.currentTime + 0.1);
                } catch (e) {
                    // Audio not supported
                }
            }
        },

        /**
         * Bind message control button events
         */
        bindControlEvents: function () {
            const self = this;

            // Delegate control button clicks
            $(document).on('click', '.swc-msg-ctrl-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const $btn = $(this);
                const action = $btn.data('action');
                const messageId = $btn.data('msg-id');

                switch (action) {
                    case 'copy':
                        self.copyMessage(messageId);
                        break;
                    case 'edit':
                        self.editMessage(messageId);
                        break;
                    case 'delete':
                        self.deleteMessage(messageId);
                        break;
                    case 'regenerate':
                        self.regenerateResponse(messageId);
                        break;
                    case 'feedback-up':
                        self.submitFeedback(messageId, 'up');
                        break;
                    case 'feedback-down':
                        self.submitFeedback(messageId, 'down');
                        break;
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        SWCChatbot.init();
    });

    // ============ WIDGET SDK (Public API) ============
    /**
     * Quarksol Chat Widget SDK
     * 
     * Provides programmatic control over the chat widget for developers.
     * Allows opening, closing, sending messages, setting user data, and listening to events.
     * 
     * @example
     * // Open the chat
     * swcChatbot.open();
     * 
     * // Send a message
     * swcChatbot.sendMessage('Hello!');
     * 
     * // Set user data
     * swcChatbot.setUser({ name: 'John', email: 'john@example.com' });
     * 
     * // Listen for events
     * swcChatbot.on('message:received', (data) => console.log(data));
     */
    window.swcChatbot = {
        /**
         * Open the chat widget
         * @returns {void}
         */
        open: function () {
            $('#smart-ai-chatbot').addClass('swc-open');
            setTimeout(() => $('.swc-input').focus(), 300);
            this._trigger('open');
        },

        /**
         * Close the chat widget
         * @returns {void}
         */
        close: function () {
            $('#smart-ai-chatbot').removeClass('swc-open');
            this._trigger('close');
        },

        /**
         * Toggle the chat widget open/closed state
         * @returns {void}
         */
        toggle: function () {
            if (this.isOpen()) {
                this.close();
            } else {
                this.open();
            }
        },

        /**
         * Check if widget is currently open
         * @returns {boolean}
         */
        isOpen: function () {
            return $('#smart-ai-chatbot').hasClass('swc-open');
        },

        /**
         * Show the widget (make visible, but not necessarily open)
         * @returns {void}
         */
        show: function () {
            $('#smart-ai-chatbot').show();
            this._trigger('show');
        },

        /**
         * Hide the widget completely
         * @returns {void}
         */
        hide: function () {
            $('#smart-ai-chatbot').hide();
            this._trigger('hide');
        },

        /**
         * Send a message programmatically
         * @param {string} message - Message text to send
         * @returns {void}
         */
        sendMessage: function (message) {
            if (!message || typeof message !== 'string') return;

            // Ensure widget is open
            if (!this.isOpen()) {
                this.open();
            }

            // Set the message and trigger send
            $('.swc-input').val(message);
            SWCChatbot.sendMessage();

            this._trigger('message:sent', { message: message });
        },

        /**
         * Set user data for personalization and analytics
         * @param {Object} userData - User data object
         * @param {string} [userData.id] - User identifier
         * @param {string} [userData.name] - User display name
         * @param {string} [userData.email] - User email
         * @param {string} [userData.phone] - User phone number
         * @param {Object} [userData.properties] - Custom properties
         * @returns {void}
         */
        setUser: function (userData) {
            if (!userData || typeof userData !== 'object') return;

            this._userData = { ...this._userData, ...userData };

            // Send to server for session association
            const settings = window.swcChatbot || {};
            const restUrl = settings.restUrl || settings.apiUrl || '/wp-json/smart-ai-chatbot/v1';

            fetch(restUrl + '/user', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': settings.nonce || ''
                },
                body: JSON.stringify(userData)
            }).catch(function (e) {
                console.warn('Failed to set user data:', e);
            });

            this._trigger('user:set', userData);
        },

        /**
         * Get current user data
         * @returns {Object|null}
         */
        getUser: function () {
            return this._userData || null;
        },

        /**
         * Set a custom property for the current visitor
         * @param {string} key - Property key
         * @param {*} value - Property value
         * @returns {void}
         */
        setProperty: function (key, value) {
            if (!key || typeof key !== 'string') return;
            this._properties = this._properties || {};
            this._properties[key] = value;
        },

        /**
         * Get a custom property
         * @param {string} key - Property key
         * @returns {*}
         */
        getProperty: function (key) {
            return this._properties?.[key];
        },

        /**
         * Get all custom properties
         * @returns {Object}
         */
        getProperties: function () {
            return { ...this._properties };
        },

        /**
         * Clear conversation and start fresh
         * @returns {void}
         */
        reset: function () {
            SWCChatbot.clearChatHistory();
            this._trigger('reset');
        },

        /**
         * Get current session ID
         * @returns {string|null}
         */
        getSessionId: function () {
            return SWCChatbot.sessionId || null;
        },

        /**
         * Get current agent info
         * @returns {Object|null}
         */
        getCurrentAgent: function () {
            return SWCChatbot.currentAgent || null;
        },

        /**
         * Register event listener
         * @param {string} event - Event name (open, close, message:sent, message:received, etc)
         * @param {Function} callback - Callback function
         * @returns {void}
         */
        on: function (event, callback) {
            if (!event || typeof callback !== 'function') return;
            this._events = this._events || {};
            this._events[event] = this._events[event] || [];
            this._events[event].push(callback);
        },

        /**
         * Remove event listener
         * @param {string} event - Event name
         * @param {Function} callback - Callback function to remove
         * @returns {void}
         */
        off: function (event, callback) {
            if (!this._events?.[event]) return;
            this._events[event] = this._events[event].filter(cb => cb !== callback);
        },

        /**
         * Trigger event (internal use)
         * @private
         * @param {string} event - Event name
         * @param {*} [data] - Event data
         */
        _trigger: function (event, data) {
            if (!this._events?.[event]) return;
            this._events[event].forEach(function (cb) {
                try {
                    cb(data);
                } catch (e) {
                    console.error('Event callback error:', e);
                }
            });
        },

        // Internal storage
        _userData: null,
        _properties: {},
        _events: {},

        // Version info
        version: '1.0.0'
    };

    // Trigger SDK ready event
    if (typeof window.CustomEvent === 'function') {
        window.dispatchEvent(new CustomEvent('swcChatbotReady', { detail: window.swcChatbot }));
    }

})(jQuery);

