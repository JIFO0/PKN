<?php
if (!defined('ABSPATH')) {
    exit;
}

$page = isset($_GET['forum_page']) ? max(1, intval($_GET['forum_page'])) : 1;
$threads_data = sc_forum_get_threads_paginated($page, 10);
$threads = $threads_data['threads'];
$active_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : 0;
if ($active_thread_id <= 0 && !empty($threads)) {
    $active_thread_id = (int) $threads[0]['id'];
}

$active_thread = $active_thread_id ? sc_forum_get_thread($active_thread_id) : null;
?>

<div
    class="sc-forum"
    data-active-thread="<?php echo esc_attr($active_thread_id); ?>"
    data-thread-page="<?php echo esc_attr($threads_data['page']); ?>"
    data-thread-total-pages="<?php echo esc_attr($threads_data['total_pages']); ?>"
>
    <div class="sc-forum-sidebar">
        <div class="sc-forum-sidebar-header">
            <h2><?php esc_html_e('Forum', 'science-communities'); ?></h2>
            <button type="button" class="button sc-forum-refresh-threads"><?php esc_html_e('Refresh', 'science-communities'); ?></button>
        </div>

        <div class="sc-thread-pagination">
            <span><?php echo esc_html(sprintf(__('Page %d/%d', 'science-communities'), $threads_data['page'], $threads_data['total_pages'])); ?></span>
            <div class="sc-thread-pagination-actions">
                <button type="button" class="button sc-thread-page-prev" <?php disabled($threads_data['page'] <= 1); ?>><?php esc_html_e('Prev', 'science-communities'); ?></button>
                <button type="button" class="button sc-thread-page-next" <?php disabled($threads_data['page'] >= $threads_data['total_pages']); ?>><?php esc_html_e('Next', 'science-communities'); ?></button>
            </div>
        </div>

        <ul class="sc-thread-list" id="sc-thread-list">
            <?php foreach ($threads as $thread): ?>
                <li>
                    <button
                        type="button"
                        class="sc-thread-item <?php echo ((int) $thread['id'] === $active_thread_id) ? 'is-active' : ''; ?>"
                        data-thread-id="<?php echo esc_attr($thread['id']); ?>"
                    >
                        <span class="sc-thread-title"><?php echo esc_html($thread['title']); ?></span>
                        <span class="sc-thread-meta">
                            <?php if ((int) $thread['is_general'] === 1): ?>
                                <strong><?php esc_html_e('General Chat', 'science-communities'); ?></strong>
                            <?php endif; ?>
                            <?php if ((int) $thread['is_closed'] === 1): ?>
                                <em><?php esc_html_e('Closed', 'science-communities'); ?></em>
                            <?php endif; ?>
                        </span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <form class="sc-forum-create-thread">
            <h3><?php esc_html_e('New thread', 'science-communities'); ?></h3>
            <input type="text" name="title" maxlength="255" required placeholder="<?php esc_attr_e('Thread title', 'science-communities'); ?>">
            <textarea name="message" maxlength="2000" required placeholder="<?php esc_attr_e('First message', 'science-communities'); ?>"></textarea>
            <button type="submit" class="button button-primary"><?php esc_html_e('Create thread', 'science-communities'); ?></button>
        </form>
    </div>

    <div class="sc-forum-thread" id="sc-forum-thread" data-thread-id="<?php echo esc_attr($active_thread_id); ?>">
        <div class="sc-forum-thread-header">
            <h3 id="sc-thread-title"><?php echo $active_thread ? esc_html($active_thread['title']) : ''; ?></h3>
            <div class="sc-forum-thread-actions">
                <button type="button" class="button sc-forum-refresh-messages"><?php esc_html_e('Refresh', 'science-communities'); ?></button>
                <?php if (sc_is_superadmin()): ?>
                    <button type="button" class="button sc-forum-close-thread"><?php esc_html_e('Close thread', 'science-communities'); ?></button>
                <?php endif; ?>
            </div>
        </div>

        <div class="sc-message-list" id="sc-message-list"></div>

        <form class="sc-forum-message-form" id="sc-forum-message-form">
            <textarea name="message" maxlength="2000" required placeholder="<?php esc_attr_e('Write a message...', 'science-communities'); ?>"></textarea>
            <button type="submit" class="button button-primary"><?php esc_html_e('Send', 'science-communities'); ?></button>
        </form>
    </div>
</div>
