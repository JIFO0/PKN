<?php
/**
 * Forum functionality for Science Communities plugin.
 */

if (!defined('ABSPATH')) {
    exit;
}

function sc_forum_user_can_access() {
    return is_user_logged_in() && sc_user_can_edit_any_community();
}

function sc_forum_get_user_role_label($user_id = 0) {
    $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
    if (!$user) {
        return '';
    }

    return in_array('superadmin', (array) $user->roles, true)
        ? __('Superadmin', 'science-communities')
        : __('SC admin', 'science-communities');
}

function sc_forum_get_user_communities_label($user_id = 0) {
    $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
    if (!$user) {
        return '';
    }

    if (in_array('superadmin', (array) $user->roles, true)) {
        return __('All communities', 'science-communities');
    }

    $community_ids = array();
    foreach ((array) $user->roles as $role) {
        if (strlen($role) === 11 && substr($role, -6) === '-admin') {
            $community_ids[] = substr($role, 0, 5);
        }
    }

    if (empty($community_ids)) {
        return '';
    }

    return implode(', ', $community_ids);
}

function sc_forum_get_general_thread_id() {
    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';

    return (int) $wpdb->get_var("SELECT id FROM {$threads_table} WHERE is_general = 1 ORDER BY id ASC LIMIT 1");
}

function sc_forum_ensure_general_thread() {
    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';

    $existing = sc_forum_get_general_thread_id();
    if ($existing > 0) {
        return $existing;
    }

    $wpdb->insert(
        $threads_table,
        array(
            'title' => __('General Chat', 'science-communities'),
            'created_by' => 0,
            'is_general' => 1,
            'is_closed' => 0,
        ),
        array('%s', '%d', '%d', '%d')
    );

    return (int) $wpdb->insert_id;
}

function sc_forum_get_threads() {
    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';
    $messages_table = $wpdb->prefix . 'science_forum_messages';

    return $wpdb->get_results(
        "SELECT t.id, t.title, t.created_by, t.is_general, t.is_closed, t.last_activity_at, t.created_at,
                COUNT(m.id) as message_count
         FROM {$threads_table} t
         LEFT JOIN {$messages_table} m ON m.thread_id = t.id
         GROUP BY t.id
         ORDER BY t.is_general DESC, t.last_activity_at DESC",
        ARRAY_A
    );
}

function sc_forum_get_thread($thread_id) {
    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';

    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$threads_table} WHERE id = %d", $thread_id),
        ARRAY_A
    );
}

function sc_forum_format_message_row($row) {
    $author = get_user_by('id', (int) $row['author_id']);

    return array(
        'id' => (int) $row['id'],
        'thread_id' => (int) $row['thread_id'],
        'author_id' => (int) $row['author_id'],
        'author_name' => $author ? $author->display_name : __('Unknown user', 'science-communities'),
        'role_label' => sc_forum_get_user_role_label((int) $row['author_id']),
        'community_label' => sc_forum_get_user_communities_label((int) $row['author_id']),
        'message_text' => $row['message_text'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
        'is_edited' => ($row['updated_at'] !== $row['created_at']),
        'can_edit' => ((int) $row['author_id'] === get_current_user_id())
            && (strtotime($row['created_at']) >= (time() - 5 * MINUTE_IN_SECONDS)),
        'can_delete' => sc_is_superadmin(),
    );
}

function sc_forum_get_messages($thread_id) {
    global $wpdb;
    $messages_table = $wpdb->prefix . 'science_forum_messages';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, thread_id, author_id, message_text, created_at, updated_at
             FROM {$messages_table}
             WHERE thread_id = %d
             ORDER BY created_at ASC, id ASC",
            $thread_id
        ),
        ARRAY_A
    );

    return array_map('sc_forum_format_message_row', $rows);
}

function sc_forum_ajax_require_access() {
    check_ajax_referer('science_communities_nonce', 'nonce');

    if (!sc_forum_user_can_access()) {
        wp_send_json_error(__('You do not have permission to access the forum.', 'science-communities'));
    }
}

function sc_forum_ajax_get_threads() {
    sc_forum_ajax_require_access();
    $threads = sc_forum_get_threads();
    wp_send_json_success($threads);
}

function sc_forum_ajax_get_messages() {
    sc_forum_ajax_require_access();

    $thread_id = isset($_POST['thread_id']) ? intval($_POST['thread_id']) : 0;
    if ($thread_id <= 0) {
        wp_send_json_error(__('Invalid thread.', 'science-communities'));
    }

    $thread = sc_forum_get_thread($thread_id);
    if (!$thread) {
        wp_send_json_error(__('Thread not found.', 'science-communities'));
    }

    wp_send_json_success(array(
        'thread' => $thread,
        'messages' => sc_forum_get_messages($thread_id),
    ));
}

function sc_forum_user_can_create_thread($user_id) {
    if (sc_is_superadmin()) {
        return array('allowed' => true, 'count' => 0);
    }

    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';

    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$threads_table}
         WHERE created_by = %d
         AND is_general = 0
         AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
        $user_id
    ));

    return array('allowed' => ($count < 2), 'count' => $count);
}

function sc_forum_ajax_create_thread() {
    sc_forum_ajax_require_access();

    $user_id = get_current_user_id();
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    if (empty($title) || empty($message)) {
        wp_send_json_error(__('Thread title and first message are required.', 'science-communities'));
    }

    if (mb_strlen($message) > 2000) {
        wp_send_json_error(__('Message cannot exceed 2000 characters.', 'science-communities'));
    }

    $limit = sc_forum_user_can_create_thread($user_id);
    if (!$limit['allowed']) {
        wp_send_json_error(__('Thread creation limit reached (2 threads per 24h).', 'science-communities'));
    }

    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';
    $messages_table = $wpdb->prefix . 'science_forum_messages';

    $wpdb->insert(
        $threads_table,
        array(
            'title' => $title,
            'created_by' => $user_id,
            'is_general' => 0,
            'is_closed' => 0,
        ),
        array('%s', '%d', '%d', '%d')
    );

    $thread_id = (int) $wpdb->insert_id;

    $wpdb->insert(
        $messages_table,
        array(
            'thread_id' => $thread_id,
            'author_id' => $user_id,
            'message_text' => $message,
        ),
        array('%d', '%d', '%s')
    );

    $wpdb->query($wpdb->prepare(
        "UPDATE {$threads_table} SET last_activity_at = NOW() WHERE id = %d",
        $thread_id
    ));

    wp_send_json_success(array('thread_id' => $thread_id));
}

function sc_forum_user_can_post_message_now($user_id) {
    if (sc_is_superadmin()) {
        return true;
    }

    global $wpdb;
    $messages_table = $wpdb->prefix . 'science_forum_messages';

    $last_created = $wpdb->get_var($wpdb->prepare(
        "SELECT created_at FROM {$messages_table}
         WHERE author_id = %d
         ORDER BY created_at DESC, id DESC
         LIMIT 1",
        $user_id
    ));

    if (empty($last_created)) {
        return true;
    }

    return (strtotime($last_created) <= (time() - 5));
}

function sc_forum_ajax_post_message() {
    sc_forum_ajax_require_access();

    $thread_id = isset($_POST['thread_id']) ? intval($_POST['thread_id']) : 0;
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    if ($thread_id <= 0 || empty($message)) {
        wp_send_json_error(__('Thread and message are required.', 'science-communities'));
    }

    if (mb_strlen($message) > 2000) {
        wp_send_json_error(__('Message cannot exceed 2000 characters.', 'science-communities'));
    }

    $thread = sc_forum_get_thread($thread_id);
    if (!$thread) {
        wp_send_json_error(__('Thread not found.', 'science-communities'));
    }

    if ((int) $thread['is_closed'] === 1) {
        wp_send_json_error(__('Thread is closed.', 'science-communities'));
    }

    $user_id = get_current_user_id();
    if (!sc_forum_user_can_post_message_now($user_id)) {
        wp_send_json_error(__('Please wait a few seconds before sending another message.', 'science-communities'));
    }

    global $wpdb;
    $messages_table = $wpdb->prefix . 'science_forum_messages';
    $threads_table = $wpdb->prefix . 'science_forum_threads';

    $wpdb->insert(
        $messages_table,
        array(
            'thread_id' => $thread_id,
            'author_id' => $user_id,
            'message_text' => $message,
        ),
        array('%d', '%d', '%s')
    );

    $wpdb->query($wpdb->prepare(
        "UPDATE {$threads_table} SET last_activity_at = NOW() WHERE id = %d",
        $thread_id
    ));

    wp_send_json_success(array('message_id' => (int) $wpdb->insert_id));
}

function sc_forum_ajax_edit_message() {
    sc_forum_ajax_require_access();

    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

    if ($message_id <= 0 || empty($message)) {
        wp_send_json_error(__('Invalid message.', 'science-communities'));
    }

    if (mb_strlen($message) > 2000) {
        wp_send_json_error(__('Message cannot exceed 2000 characters.', 'science-communities'));
    }

    global $wpdb;
    $messages_table = $wpdb->prefix . 'science_forum_messages';

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$messages_table} WHERE id = %d", $message_id), ARRAY_A);
    if (!$row) {
        wp_send_json_error(__('Message not found.', 'science-communities'));
    }

    if ((int) $row['author_id'] !== get_current_user_id()) {
        wp_send_json_error(__('You can edit only your own messages.', 'science-communities'));
    }

    if (strtotime($row['created_at']) < (time() - 5 * MINUTE_IN_SECONDS)) {
        wp_send_json_error(__('Editing window has expired (5 minutes).', 'science-communities'));
    }

    $wpdb->update(
        $messages_table,
        array('message_text' => $message),
        array('id' => $message_id),
        array('%s'),
        array('%d')
    );

    wp_send_json_success(true);
}

function sc_forum_ajax_delete_message() {
    sc_forum_ajax_require_access();

    if (!sc_is_superadmin()) {
        wp_send_json_error(__('Only superadmins can delete messages.', 'science-communities'));
    }

    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if ($message_id <= 0) {
        wp_send_json_error(__('Invalid message.', 'science-communities'));
    }

    global $wpdb;
    $messages_table = $wpdb->prefix . 'science_forum_messages';
    $wpdb->delete($messages_table, array('id' => $message_id), array('%d'));

    wp_send_json_success(true);
}

function sc_forum_ajax_close_thread() {
    sc_forum_ajax_require_access();

    if (!sc_is_superadmin()) {
        wp_send_json_error(__('Only superadmins can close threads.', 'science-communities'));
    }

    $thread_id = isset($_POST['thread_id']) ? intval($_POST['thread_id']) : 0;
    if ($thread_id <= 0) {
        wp_send_json_error(__('Invalid thread.', 'science-communities'));
    }

    global $wpdb;
    $threads_table = $wpdb->prefix . 'science_forum_threads';
    $wpdb->update(
        $threads_table,
        array('is_closed' => 1),
        array('id' => $thread_id),
        array('%d'),
        array('%d')
    );

    wp_send_json_success(true);
}

function sc_forum_ajax_report_message() {
    sc_forum_ajax_require_access();

    if (sc_is_superadmin()) {
        wp_send_json_error(__('Superadmins should moderate directly instead of reporting.', 'science-communities'));
    }

    $message_id = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $reason = isset($_POST['reason']) ? sanitize_textarea_field(wp_unslash($_POST['reason'])) : '';

    if ($message_id <= 0) {
        wp_send_json_error(__('Invalid message.', 'science-communities'));
    }

    if (empty($reason)) {
        $reason = __('No reason provided.', 'science-communities');
    }

    $communities = sc_get_user_admin_communities();
    $community_id = !empty($communities) ? $communities[0] : 'FORUM';

    $message = sprintf(
        "Forum message report\nmessage_id: %d\nReporter ID: %d\nReason: %s",
        $message_id,
        get_current_user_id(),
        $reason
    );

    sc_create_contact_request($community_id, $message, get_current_user_id());
    wp_send_json_success(true);
}
