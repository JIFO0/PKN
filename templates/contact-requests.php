<?php
if (!defined('ABSPATH')) exit;

if (!sc_is_superadmin()) {
    echo '<p>' . __('Access denied.', 'science-communities') . '</p>';
    return;
}

global $wpdb;
$table = $wpdb->prefix . 'science_contact_requests';
$communities_table = $wpdb->prefix . 'science_communities';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sc_mark_contact_done'])) {
    if (isset($_POST['sc_mark_contact_done_nonce']) && wp_verify_nonce($_POST['sc_mark_contact_done_nonce'], 'sc_mark_contact_done')) {
        $request_id = intval($_POST['request_id'] ?? 0);
        if ($request_id > 0) {
            $wpdb->update($table, array('status' => 'closed'), array('id' => $request_id), array('%s'), array('%d'));
        }
    }
}

$requests = $wpdb->get_results(
    "SELECT r.*, c.name AS community_name
    FROM $table r
    LEFT JOIN $communities_table c ON c.community_id = r.community_id
    ORDER BY r.created_at DESC"
);
?>

<div class="wrap">
    <h1><?php _e('Contact Requests', 'science-communities'); ?></h1>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th><?php _e('Date', 'science-communities'); ?></th>
                <th><?php _e('Community', 'science-communities'); ?></th>
                <th><?php _e('Requester', 'science-communities'); ?></th>
                <th><?php _e('Message', 'science-communities'); ?></th>
                <th><?php _e('Status', 'science-communities'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="5"><?php _e('No contact requests.', 'science-communities'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <?php $requester = get_user_by('id', $request->requester_id); ?>
                    <tr>
                        <td><?php echo esc_html($request->created_at); ?></td>
                        <td><?php echo esc_html(($request->community_name ?: '-') . ' (' . $request->community_id . ')'); ?></td>
                        <td><?php echo esc_html($requester ? $requester->display_name : 'Unknown'); ?></td>
                        <td><?php echo esc_html($request->message); ?></td>
                        <td>
                            <?php echo esc_html($request->status); ?>
                            <?php if ($request->status !== 'closed'): ?>
                                <form method="post" style="margin-top:8px;">
                                    <?php wp_nonce_field('sc_mark_contact_done', 'sc_mark_contact_done_nonce'); ?>
                                    <input type="hidden" name="sc_mark_contact_done" value="1">
                                    <input type="hidden" name="request_id" value="<?php echo esc_attr($request->id); ?>">
                                    <button type="submit" class="button"><?php _e('Mark closed', 'science-communities'); ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
