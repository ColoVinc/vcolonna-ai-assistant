jQuery(function ($) {

    const $toggle   = $('#vcai-chat-toggle');
    const $window   = $('#vcai-chat-window');
    const $messages = $('#vcai-chat-messages');
    const $input    = $('#vcai-chat-input');
    const $send     = $('#vcai-chat-send');
    const $main     = $('#vcai-chat-main');
    const $histPanel = $('#vcai-history-panel');
    const $histList  = $('#vcai-history-list');

    const STORAGE_KEY_MESSAGES = 'vcai_messages';
    const STORAGE_KEY_SESSION  = 'vcai_session';
    const STORAGE_KEY_CONV_ID  = 'vcai_conv_id';

    let currentConversationId = 0;

    // Session check
    var savedSession = sessionStorage.getItem(STORAGE_KEY_SESSION);
    if (savedSession && savedSession !== vcai_chat.session_id) {
        sessionStorage.removeItem(STORAGE_KEY_MESSAGES);
        sessionStorage.removeItem(STORAGE_KEY_CONV_ID);
    }
    sessionStorage.setItem(STORAGE_KEY_SESSION, vcai_chat.session_id);

    const toolLabels = {
        create_post:            '✅ Post creato',
        update_post:            '✏️ Post aggiornato',
        delete_post:            '🗑️ Post eliminato',
        get_posts:              '📋 Post recuperati',
        get_media:              '🖼️ Media recuperati',
        get_categories:         '🗂️ Categorie recuperate',
        get_site_info:          '🌐 Info sito recuperate',
        get_custom_post_types:  '📦 CPT recuperati',
        create_custom_post:     '✅ CPT creato',
        update_custom_post:     '✏️ CPT aggiornato',
        get_comments:           '💬 Commenti recuperati',
        moderate_comment:       '🛡️ Commento moderato',
        reply_comment:          '💬 Risposta pubblicata',
        update_site_settings:   '⚙️ Impostazioni aggiornate',
        get_users:              '👥 Utenti recuperati',
        get_products:           '🛒 Prodotti recuperati',
        create_product:         '🛒 Prodotto creato',
        get_orders:             '📦 Ordini recuperati',
        get_menus:              '📋 Menu recuperati',
        add_menu_item:          '➕ Voce menu aggiunta',
    };

    restoreSession();

    // ── Toggle ────────────────────────────────────────────────────
    $toggle.on('click', function () {
        const isOpen = $window.is(':visible');
        $window.toggle(!isOpen);
        $('.vcai-chat-icon').toggle(isOpen);
        $('.vcai-chat-close').toggle(!isOpen);
        if (!isOpen) { $input.focus(); scrollToBottom(); }
    });

    // ── Suggerimenti ──────────────────────────────────────────────
    $(document).on('click', '.vcai-suggestion', function () {
        $input.val($(this).data('msg'));
        sendMessage();
    });

    // ── Invio ─────────────────────────────────────────────────────
    $send.on('click', sendMessage);
    $input.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    });

    // ── Nuova chat ────────────────────────────────────────────────
    $('#vcai-new-chat-btn').on('click', function () {
        currentConversationId = 0;
        sessionStorage.removeItem(STORAGE_KEY_MESSAGES);
        sessionStorage.removeItem(STORAGE_KEY_CONV_ID);
        $messages.empty();
        appendMessage('Ciao! Sono il tuo assistente AI. Come posso aiutarti oggi?', 'ai');
        $('.vcai-chat-suggestions').show();
        $histPanel.hide();
        $main.show();
    });

    // ── Cronologia ────────────────────────────────────────────────
    $('#vcai-history-btn').on('click', function () {
        $main.hide();
        $histPanel.show();
        loadConversations();
    });

    $('#vcai-history-back').on('click', function () {
        $histPanel.hide();
        $main.show();
    });

    // Click su conversazione
    $(document).on('click', '.vcai-conv-item', function () {
        var convId = $(this).data('id');
        loadConversation(convId);
    });

    // Elimina conversazione
    $(document).on('click', '.vcai-conv-delete', function (e) {
        e.stopPropagation();
        var convId = $(this).closest('.vcai-conv-item').data('id');
        if (!confirm('Eliminare questa conversazione?')) return;

        $.post(vcai_chat.ajax_url, {
            action: 'vcai_delete_conversation',
            nonce: vcai_chat.nonce,
            conversation_id: convId,
        }).done(function () {
            if (currentConversationId === convId) {
                $('#vcai-new-chat-btn').click();
            }
            loadConversations();
        });
    });

    function loadConversations() {
        $histList.html('<div class="text-center p-3 text-muted small"><i class="fa-solid fa-spinner fa-spin"></i></div>');
        $.post(vcai_chat.ajax_url, {
            action: 'vcai_get_conversations',
            nonce: vcai_chat.nonce,
        }).done(function (res) {
            if (!res.success || !res.data.length) {
                $histList.html('<div class="text-center p-3 text-muted small">Nessuna conversazione.</div>');
                return;
            }
            var html = '';
            res.data.forEach(function (c) {
                var active = (c.id == currentConversationId) ? ' vcai-conv-active' : '';
                html += '<div class="vcai-conv-item' + active + '" data-id="' + c.id + '">'
                    + '<div class="vcai-conv-title">' + escHtml(c.title) + '</div>'
                    + '<div class="vcai-conv-meta">'
                    + '<small>' + c.message_count + ' msg</small>'
                    + '<button class="vcai-conv-delete" title="Elimina"><i class="fa-solid fa-trash-can fa-xs"></i></button>'
                    + '</div></div>';
            });
            $histList.html(html);
        });
    }

    function loadConversation(convId) {
        $.post(vcai_chat.ajax_url, {
            action: 'vcai_load_conversation',
            nonce: vcai_chat.nonce,
            conversation_id: convId,
        }).done(function (res) {
            if (!res.success) return;

            currentConversationId = convId;
            $messages.empty();
            $('.vcai-chat-suggestions').hide();

            res.data.messages.forEach(function (m) {
                appendMessage(m.content, m.role === 'user' ? 'user' : 'ai');
            });

            sessionStorage.setItem(STORAGE_KEY_CONV_ID, convId);
            saveVisibleMessages();

            $histPanel.hide();
            $main.show();
            scrollToBottom();
        });
    }

    // ── Invio messaggio ───────────────────────────────────────────
    function sendMessage() {
        var msg = $input.val().trim();
        if (!msg) return;

        $('.vcai-chat-suggestions').hide();
        appendMessage(msg, 'user');
        $input.val('').prop('disabled', true);
        $send.prop('disabled', true);

        var $loading = appendMessage('⏳ Elaborazione in corso...', 'loading');

        // Costruisci URL SSE con parametri GET
        var params = new URLSearchParams({
            action: 'vcai_chat_stream',
            nonce: vcai_chat.nonce,
            message: msg,
            conversation_id: currentConversationId,
        });

        // Contesto pagina corrente (se siamo nell'editor)
        var pageContext = getPageContext();
        if (pageContext) params.set('page_context', pageContext);

        var url = vcai_chat.ajax_url + '?' + params.toString();

        var $aiMsg = null;
        var fullText = '';

        fetch(url)
        .then(function (res) {
            $loading.remove();
            var reader = res.body.getReader();
            var decoder = new TextDecoder();

            function read() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        finishStream();
                        return;
                    }
                    var lines = decoder.decode(result.value, { stream: true }).split('\n');
                    lines.forEach(function (line) {
                        line = line.trim();
                        if (line.indexOf('data: ') !== 0) return;
                        var payload = line.substring(6);
                        if (payload === '[DONE]') return;

                        try {
                            var data = JSON.parse(payload);
                        } catch (e) { return; }

                        // Metadati (conversation_id, action_taken)
                        if (data.meta) {
                            currentConversationId = data.conversation_id || currentConversationId;
                            if (data.action_taken && data.action_taken.tool) {
                                var label = toolLabels[data.action_taken.tool] || '⚡ Azione eseguita';
                                var result = data.action_taken.result || {};
                                var extraHtml = '';
                                if ((data.action_taken.tool === 'create_post' || data.action_taken.tool === 'create_custom_post') && result.edit_url) {
                                    extraHtml = ' — <a href="' + result.edit_url + '" target="_blank">Apri nell\'editor</a>';
                                }
                                appendBadge(label + extraHtml);
                                showToast(label, result.edit_url || null);
                            }
                            return;
                        }

                        // Errore
                        if (data.error) {
                            appendMessage('⚠️ ' + data.error, 'ai');
                            return;
                        }

                        // Chunk di testo
                        if (data.chunk) {
                            fullText += data.chunk;
                            if (!$aiMsg) {
                                $aiMsg = $('<div>').addClass('vcai-chat-message vcai-chat-message--ai');
                                $messages.append($aiMsg);
                            }
                            if (typeof marked !== 'undefined') {
                                $aiMsg.html(marked.parse(fullText));
                            } else {
                                $aiMsg.text(fullText);
                            }
                            scrollToBottom();
                        }
                    });
                    return read();
                });
            }
            return read();
        })
        .catch(function () {
            $loading.remove();
            appendMessage('❌ Errore di connessione.', 'ai');
            finishStream();
        });

        function finishStream() {
            saveSession();
            $input.prop('disabled', false);
            $send.prop('disabled', false);
            $input.focus();
        }
    }

    // ── Helpers ───────────────────────────────────────────────────
    function appendMessage(text, type) {
        var $msg = $('<div>').addClass('vcai-chat-message vcai-chat-message--' + type);
        if (type === 'ai' && typeof marked !== 'undefined') {
            $msg.html(marked.parse(text));
        } else {
            $msg.text(text);
        }
        $messages.append($msg);
        scrollToBottom();
        return $msg;
    }

    function appendBadge(html) {
        var $badge = $('<div class="vcai-action-badge">').html(html);
        $messages.append($badge);
        scrollToBottom();
    }

    function scrollToBottom() {
        $messages.scrollTop($messages[0].scrollHeight);
    }

    function escHtml(str) {
        return $('<span>').text(str).html();
    }

    function getPageContext() {
        // Solo nelle pagine editor
        var $body = $('body');
        if (!$body.hasClass('post-php') && !$body.hasClass('post-new-php')) return null;

        var title = '';
        var content = '';

        // Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            title = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            content = wp.data.select('core/editor').getEditedPostContent() || '';
        }
        // Classic Editor
        if (!title && $('#title').length) title = $('#title').val() || '';
        if (!content && typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
            content = tinyMCE.activeEditor.getContent({ format: 'text' }) || '';
        }
        if (!content && $('#content').length) content = $('#content').val() || '';

        if (!title && !content) return null;

        // Tronca il contenuto per non esplodere l'URL
        content = content.replace(/<[^>]+>/g, ' ').substring(0, 1500);
        return JSON.stringify({ title: title, content: content });
    }

    function showToast(label, editUrl) {
        var html = '<div class="vcai-toast-content">' + label;
        if (editUrl) html += ' <a href="' + editUrl + '" target="_blank">Apri nell\'editor →</a>';
        html += '</div><button class="vcai-toast-close">&times;</button>';
        var $toast = $('<div class="vcai-toast">').html(html).appendTo('body');
        $toast.find('.vcai-toast-close').on('click', function () { $toast.remove(); });
        setTimeout(function () { $toast.addClass('vcai-toast--visible'); }, 10);
        setTimeout(function () { $toast.removeClass('vcai-toast--visible'); setTimeout(function () { $toast.remove(); }, 300); }, 5000);
    }

    // ── Persistenza sessionStorage ────────────────────────────────
    function saveSession() {
        try {
            sessionStorage.setItem(STORAGE_KEY_CONV_ID, currentConversationId);
            saveVisibleMessages();
        } catch (e) {}
    }

    function saveVisibleMessages() {
        var ordered = [];
        $messages.children().each(function () {
            var $el = $(this);
            if ($el.hasClass('vcai-chat-message--user'))     ordered.push({ type: 'user', text: $el.text() });
            else if ($el.hasClass('vcai-chat-message--ai'))  ordered.push({ type: 'ai', html: $el.html() });
            else if ($el.hasClass('vcai-action-badge'))      ordered.push({ type: 'badge', html: $el.html() });
        });
        sessionStorage.setItem(STORAGE_KEY_MESSAGES, JSON.stringify(ordered));
    }

    function restoreSession() {
        try {
            var msgs   = sessionStorage.getItem(STORAGE_KEY_MESSAGES);
            var convId = sessionStorage.getItem(STORAGE_KEY_CONV_ID);
            if (!msgs) return;

            currentConversationId = parseInt(convId) || 0;
            var parsed = JSON.parse(msgs);
            if (!parsed.length) return;

            $messages.empty();
            $('.vcai-chat-suggestions').hide();
            parsed.forEach(function (m) {
                if (m.type === 'badge') {
                    appendBadge(m.html);
                } else if (m.type === 'ai' && m.html) {
                    var $msg = $('<div>').addClass('vcai-chat-message vcai-chat-message--ai').html(m.html);
                    $messages.append($msg);
                } else {
                    appendMessage(m.text, m.type);
                }
            });
        } catch (e) {}
    }

});
