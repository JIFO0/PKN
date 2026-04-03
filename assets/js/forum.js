(function ($) {
    'use strict';

    const $forum = $('.sc-forum');
    if (!$forum.length) {
        return;
    }

    let activeThreadId = parseInt($forum.data('active-thread'), 10) || 0;
    let threadPage = parseInt($forum.data('thread-page'), 10) || 1;
    let threadTotalPages = parseInt($forum.data('thread-total-pages'), 10) || 1;

    function ajax(action, data) {
        return $.post(scForumData.ajaxUrl, Object.assign({
            action: action,
            nonce: scForumData.nonce
        }, data || {}));
    }

    function escapeHtml(value) {
        return $('<div>').text(value || '').html();
    }

    function renderMessages(payload) {
        const thread = payload.thread;
        const messages = payload.messages || [];
        const $list = $('#sc-message-list');
        const $title = $('#sc-thread-title');
        const $form = $('#sc-forum-message-form');

        $title.text(thread.title + (parseInt(thread.is_closed, 10) === 1 ? ' (Closed)' : ''));
        $forum.find('#sc-forum-thread').attr('data-thread-id', thread.id);

        if (parseInt(thread.is_closed, 10) === 1) {
            $form.hide();
        } else {
            $form.show();
        }

        if (!messages.length) {
            $list.html('<p>No messages yet.</p>');
            return;
        }

        const html = messages.map((m) => {
            const editBtn = m.can_edit ? `<button type="button" class="button-link sc-message-edit" data-message-id="${m.id}">Edit</button>` : '';
            const deleteBtn = m.can_delete ? `<button type="button" class="button-link sc-message-delete" data-message-id="${m.id}">Delete</button>` : '';
            const reportBtn = scForumData.isSuperadmin ? '' : `<button type="button" class="button-link sc-message-report" data-message-id="${m.id}">Report</button>`;
            return `
                <div class="sc-message-item" data-message-id="${m.id}">
                    <div class="sc-message-meta">
                        <strong>${escapeHtml(m.author_name)}</strong>
                        <span>${escapeHtml(m.role_label)}</span>
                        <span>${escapeHtml(m.community_label)}</span>
                        <span>${escapeHtml(m.created_at)}</span>
                        ${m.is_edited ? '<em>edited</em>' : ''}
                    </div>
                    <div class="sc-message-text">${escapeHtml(m.message_text).replace(/\n/g, '<br>')}</div>
                    <div class="sc-message-actions">${editBtn}${deleteBtn}${reportBtn}</div>
                </div>`;
        }).join('');

        $list.html(html);
    }

    function renderPagination() {
        const text = `Page ${threadPage}/${threadTotalPages}`;
        const $root = $forum.find('.sc-thread-pagination');
        $root.find('span').text(text);
        $root.find('.sc-thread-page-prev').prop('disabled', threadPage <= 1);
        $root.find('.sc-thread-page-next').prop('disabled', threadPage >= threadTotalPages);
    }

    function loadThreads(targetPage) {
        const requestedPage = targetPage || threadPage || 1;

        ajax('sc_forum_get_threads', { page: requestedPage }).done(function (response) {
            if (!response.success) {
                return;
            }

            const payload = response.data || {};
            const threads = payload.threads || [];
            threadPage = parseInt(payload.page, 10) || 1;
            threadTotalPages = parseInt(payload.total_pages, 10) || 1;
            const html = threads.map((thread) => {
                const isActive = parseInt(thread.id, 10) === activeThreadId ? 'is-active' : '';
                const tags = [
                    parseInt(thread.is_general, 10) === 1 ? '<strong>General Chat</strong>' : '',
                    parseInt(thread.is_closed, 10) === 1 ? '<em>Closed</em>' : ''
                ].filter(Boolean).join(' ');

                return `<li><button type="button" class="sc-thread-item ${isActive}" data-thread-id="${thread.id}"><span class="sc-thread-title">${escapeHtml(thread.title)}</span><span class="sc-thread-meta">${tags}</span></button></li>`;
            }).join('');

            $('#sc-thread-list').html(html);
            renderPagination();

            if (activeThreadId <= 0 && threads.length) {
                activeThreadId = parseInt(threads[0].id, 10);
                loadMessages();
            }
        });
    }

    function loadMessages() {
        if (!activeThreadId) {
            return;
        }

        ajax('sc_forum_get_messages', { thread_id: activeThreadId }).done(function (response) {
            if (!response.success) {
                return;
            }
            renderMessages(response.data);
        });
    }

    $forum.on('click', '.sc-thread-item', function () {
        activeThreadId = parseInt($(this).data('thread-id'), 10);
        $('.sc-thread-item').removeClass('is-active');
        $(this).addClass('is-active');
        loadMessages();
    });

    $forum.on('submit', '.sc-forum-create-thread', function (e) {
        e.preventDefault();
        const data = $(this).serializeArray().reduce((acc, item) => {
            acc[item.name] = item.value;
            return acc;
        }, {});

        ajax('sc_forum_create_thread', data).done(function (response) {
            if (!response.success) {
                alert(response.data || 'Could not create thread.');
                return;
            }
            activeThreadId = parseInt(response.data.thread_id, 10);
            threadPage = 1;
            $('.sc-forum-create-thread')[0].reset();
            loadThreads(1);
            loadMessages();
        });
    });

    $forum.on('submit', '#sc-forum-message-form', function (e) {
        e.preventDefault();
        const message = $(this).find('textarea[name="message"]').val();
        ajax('sc_forum_post_message', { thread_id: activeThreadId, message: message }).done(function (response) {
            if (!response.success) {
                alert(response.data || 'Could not send message.');
                return;
            }
            $('#sc-forum-message-form')[0].reset();
            loadThreads(threadPage);
            loadMessages();
        });
    });

    $forum.on('click', '.sc-message-edit', function () {
        const messageId = parseInt($(this).data('message-id'), 10);
        const oldText = $(this).closest('.sc-message-item').find('.sc-message-text').text();
        const newText = window.prompt('Edit message:', oldText);
        if (!newText) {
            return;
        }

        ajax('sc_forum_edit_message', { message_id: messageId, message: newText }).done(function (response) {
            if (!response.success) {
                alert(response.data || 'Could not edit message.');
                return;
            }
            loadMessages();
        });
    });

    $forum.on('click', '.sc-message-delete', function () {
        if (!window.confirm('Delete this message?')) {
            return;
        }
        const messageId = parseInt($(this).data('message-id'), 10);
        ajax('sc_forum_delete_message', { message_id: messageId }).done(function (response) {
            if (!response.success) {
                alert(response.data || 'Could not delete message.');
                return;
            }
            loadMessages();
        });
    });

    $forum.on('click', '.sc-message-report', function () {
        const messageId = parseInt($(this).data('message-id'), 10);
        const reason = window.prompt('Report reason (optional):', '');
        ajax('sc_forum_report_message', { message_id: messageId, reason: reason || '' }).done(function (response) {
            if (!response.success) {
                alert(response.data || 'Could not report message.');
                return;
            }
            alert('Message reported.');
        });
    });

    $forum.on('click', '.sc-forum-close-thread', function () {
        if (!window.confirm('Close this thread?')) {
            return;
        }

        ajax('sc_forum_close_thread', { thread_id: activeThreadId }).done(function (response) {
            if (!response.success) {
                alert(response.data || 'Could not close thread.');
                return;
            }
            loadThreads(threadPage);
            loadMessages();
        });
    });

    $forum.on('click', '.sc-forum-refresh-threads', loadThreads);
    $forum.on('click', '.sc-forum-refresh-messages', loadMessages);
    $forum.on('click', '.sc-thread-page-prev', function () {
        if (threadPage > 1) {
            loadThreads(threadPage - 1);
        }
    });
    $forum.on('click', '.sc-thread-page-next', function () {
        if (threadPage < threadTotalPages) {
            loadThreads(threadPage + 1);
        }
    });

    setInterval(function () {
        loadThreads(threadPage);
        loadMessages();
    }, 60000);

    renderPagination();
    loadMessages();
})(jQuery);
