<?php
/**
 * Debug information display
 * Add [science_communities_debug] shortcode to show this
 */

if (!defined('ABSPATH')) exit;

if (!current_user_can('manage_options')) {
    echo '<p>Only administrators can view debug information.</p>';
    return;
}

$checks = sc_check_system_requirements();
?>

<div style="background: white; padding: 20px; margin: 20px 0; border: 2px solid #0073aa;">
    <h2>PKN Backend Debug Information</h2>
    
    <h3>System Checks</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f0f0f0;">
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Check</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Status</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Value</th>
                <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Required</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($checks as $check_name => $check): ?>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($check_name); ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php if ($check['status']): ?>
                        <span style="color: green; font-weight: bold;">✓ PASS</span>
                    <?php else: ?>
                        <span style="color: red; font-weight: bold;">✗ FAIL</span>
                    <?php endif; ?>
                </td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($check['value']); ?></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($check['required']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h3 style="margin-top: 30px;">Current User Information</h3>
    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><?php
        $user = wp_get_current_user();
        print_r(array(
            'ID' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'roles' => $user->roles,
            'is_superadmin' => sc_is_superadmin(),
            'can_access_admin' => sc_can_access_admin_panel()
        ));
    ?></pre>
    
    <h3>Server Information</h3>
    <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto;"><?php
        print_r(array(
            'PHP Version' => PHP_VERSION,
            'WordPress Version' => get_bloginfo('version'),
            'Plugin Path' => SC_PLUGIN_PATH,
            'Plugin URL' => SC_PLUGIN_URL,
            'Home URL' => home_url(),
            'Site URL' => site_url(),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time')
        ));
    ?></pre>
    
    <p style="margin-top: 20px;">
        <a href="?page=sc-error-log" class="button button-primary">View Error Log</a>
    </p>
</div>