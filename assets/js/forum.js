(function ($) {
    'use strict';

    const $forum = $('.sc-forum');
    if (!$forum.length) {
        return;
    }

	const i18n = scForumData.i18n || {};
    const t = (key, fallback) => i18n[key] || fallback;
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
		const closed = parseInt(thread.is_closed, 10) === 1;
		
        $title.text(thread.title + (closed ? ` (${t('closed', 'Closed')})` : ''));
        $forum.find('#sc-forum-thread').attr('data-thread-id', thread.id);

        $('.sc-forum-close-thread').toggle(scForumData.isSuperadmin && !closed);
        $('.sc-forum-delete-thread').toggle(scForumData.isSuperadmin && closed && parseInt(thread.is_general, 10) !== 1);
        $form.toggle(!closed);

        if (!messages.length) {
            $list.html(`<p>${escapeHtml(t('noMessages', 'No messages yet.'))}</p>`);
            return;
        }

        const html = messages.map((m) => {
            const editBtn = m.can_edit ? `<button type="button" class="sc-message-action-button sc-message-edit" data-message-id="${m.id}">${escapeHtml(t('edit', 'Edit'))}</button>` : '';
            const deleteBtn = m.can_delete ? `<button type="button" class="sc-message-action-button sc-message-delete" data-message-id="${m.id}">${escapeHtml(t('delete', 'Delete'))}</button>` : '';
            const reportBtn = scForumData.isSuperadmin ? '' : `<button type="button" class="sc-message-action-button sc-message-report" data-message-id="${m.id}">${escapeHtml(t('report', 'Report'))}</button>`;
            const image = m.message_image_url ? `<a href="${escapeHtml(m.message_image_url)}" target="_blank" rel="noopener"><img class="sc-message-image" src="${escapeHtml(m.message_image_url)}" alt=""></a>` : '';
            return `
                <div class="sc-message-item" data-message-id="${m.id}">
                    <div class="sc-message-meta">
                        <strong>${escapeHtml(m.author_name)}</strong>
                        <span>${escapeHtml(m.role_label)}</span>
                        <span>${escapeHtml(m.community_label)}</span>
                        <span>${escapeHtml(m.created_at)}</span>
                        ${m.is_edited ? `<em>${escapeHtml(t('edited', 'edited'))}</em>` : ''}
                    </div>
                    <div class="sc-message-text">${escapeHtml(m.message_text).replace(/\n/g, '<br>')}</div>
					${image}
                    <div class="sc-message-actions">${editBtn}${deleteBtn}${reportBtn}</div>
                </div>`;
        }).join('');

        $list.html(html);
		$list.scrollTop($list[0].scrollHeight);
    }

    function renderPagination() {
        const text = `${t('page', 'Page')} ${threadPage}/${threadTotalPages}`;
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
                    parseInt(thread.is_general, 10) === 1 ? `<strong>${escapeHtml(t('generalChat', 'General Chat'))}</strong>` : '',
                    parseInt(thread.is_closed, 10) === 1 ? `<em>${escapeHtml(t('closed', 'Closed'))}</em>` : ''
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
        if (!activeThreadId) return;
        ajax('sc_forum_get_messages', { thread_id: activeThreadId }).done(function (response) {
            if (response.success) renderMessages(response.data);
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
        const data = $(this).serializeArray().reduce((acc, item) => { acc[item.name] = item.value; return acc; }, {});

        ajax('sc_forum_create_thread', data).done(function (response) {
            if (!response.success) { alert(response.data || 'Could not create thread.'); return; }
            activeThreadId = parseInt(response.data.thread_id, 10);
            threadPage = 1;
            $('.sc-forum-create-thread')[0].reset();
            loadThreads(1);
            loadMessages();
        });
    });

    $forum.on('change', '.sc-forum-image-input', function () {
        const file = this.files && this.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('action', 'sc_forum_upload_image');
        formData.append('nonce', scForumData.nonce);
        formData.append('forum_image', file);
        $('.sc-forum-upload-status').text(t('uploadingImage', 'Uploading image...'));
        $.ajax({ url: scForumData.ajaxUrl, method: 'POST', data: formData, processData: false, contentType: false }).done(function (response) {
            if (!response.success || !response.data.url) {
                alert(response.data || t('couldNotUploadImage', 'Could not upload image.'));
                return;
            }
			$('.sc-forum-image-url').val(response.data.url);
            $('.sc-forum-upload-status').text(t('imageAttached', 'Image attached'));
            $('.sc-forum-remove-image').show();
        });
    });

    $forum.on('click', '.sc-forum-remove-image', function () {
        $('.sc-forum-image-url').val('');
        $('.sc-forum-image-input').val('');
        $('.sc-forum-upload-status').text('');
        $(this).hide();
    });

    $forum.on('submit', '#sc-forum-message-form', function (e) {
        e.preventDefault();
        const $form = $(this);
        ajax('sc_forum_post_message', { thread_id: activeThreadId, message: $form.find('textarea[name="message"]').val(), image_url: $form.find('.sc-forum-image-url').val() }).done(function (response) {
            if (!response.success) { alert(response.data || t('couldNotSendMessage', 'Could not send message.')); return; }
            $('#sc-forum-message-form')[0].reset();
			$('.sc-forum-image-url').val('');
            $('.sc-forum-upload-status').text('');
            $('.sc-forum-remove-image').hide();
            loadThreads(threadPage);
            loadMessages();
        });
    });

    $forum.on('click', '.sc-message-edit', function () {
        const messageId = parseInt($(this).data('message-id'), 10);
        const oldText = $(this).closest('.sc-message-item').find('.sc-message-text').text();
        const newText = window.prompt(t('editMessagePrompt', 'Edit message:'), oldText);
        if (!newText) return;

        ajax('sc_forum_edit_message', { message_id: messageId, message: newText }).done(function (response) {
            if (!response.success) { alert(response.data || 'Could not edit message.'); return; }
            loadMessages();
        });
    });

    $forum.on('click', '.sc-message-delete', function () {
        if (!window.confirm(t('deleteMessageConfirm', 'Delete this message?'))) return;
        ajax('sc_forum_delete_message', { message_id: parseInt($(this).data('message-id'), 10) }).done(function (response) {
            if (!response.success) { alert(response.data || 'Could not delete message.'); return; }
            loadMessages();
        });
    });

    $forum.on('click', '.sc-message-report', function () {
        const reason = window.prompt(t('reportReasonPrompt', 'Report reason (optional):'), '');
        ajax('sc_forum_report_message', { message_id: parseInt($(this).data('message-id'), 10), reason: reason || '' }).done(function (response) {
            if (!response.success) { alert(response.data || 'Could not report message.'); return; }
            alert(t('messageReported', 'Message reported.'));
        });
    });

    $forum.on('click', '.sc-forum-close-thread', function () {
        if (!window.confirm('Close this thread?')) return;

        ajax('sc_forum_close_thread', { thread_id: activeThreadId }).done(function (response) {
            if (!response.success) { alert(response.data || 'Could not close thread.'); return; }
            loadThreads(threadPage); loadMessages();
        });
    });

    $forum.on('click', '.sc-forum-delete-thread', function () {
        if (!window.confirm(t('deleteThreadConfirm', 'Delete this closed thread?'))) return;
        ajax('sc_forum_delete_thread', { thread_id: activeThreadId }).done(function (response) {
            if (!response.success) { alert(response.data || t('couldNotDeleteThread', 'Could not delete thread.')); return; }
            activeThreadId = 0;
            loadThreads(threadPage);
            loadMessages();
        });
    });

    $forum.on('click', '.sc-forum-refresh-threads', loadThreads);
    $forum.on('click', '.sc-forum-refresh-messages', loadMessages);
    $forum.on('click', '.sc-thread-page-prev', function () { if (threadPage > 1) loadThreads(threadPage - 1); });
    $forum.on('click', '.sc-thread-page-next', function () { if (threadPage < threadTotalPages) loadThreads(threadPage + 1); });
	setInterval(function () { loadThreads(threadPage); loadMessages(); }, 60000);
    renderPagination();
    loadMessages();
})(jQuery);
