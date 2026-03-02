<?php
/**
 * Connections template
 */

if (!defined('ABSPATH')) {
    exit;
}

$connections = get_option('awm_connections', []);
?>

<div class="wrap awm-connections">
    <h1><?php esc_html_e('Connections', 'axiom-wp-migrate'); ?></h1>

    <button type="button" class="page-title-action" id="awm-add-connection">
        <?php esc_html_e('Add New Connection', 'axiom-wp-migrate'); ?>
    </button>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Name', 'axiom-wp-migrate'); ?></th>
                <th><?php esc_html_e('URL', 'axiom-wp-migrate'); ?></th>
                <th><?php esc_html_e('Key ID', 'axiom-wp-migrate'); ?></th>
                <th><?php esc_html_e('Status', 'axiom-wp-migrate'); ?></th>
                <th><?php esc_html_e('Created', 'axiom-wp-migrate'); ?></th>
                <th><?php esc_html_e('Actions', 'axiom-wp-migrate'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($connections)) : ?>
                <tr>
                    <td colspan="6"><?php esc_html_e('No connections configured.', 'axiom-wp-migrate'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($connections as $conn) : ?>
                    <tr>
                        <td><?php echo esc_html($conn['name']); ?></td>
                        <td><?php echo esc_url($conn['url']); ?></td>
                        <td><code><?php echo esc_html(substr($conn['key_id'], 0, 8)); ?>...</code></td>
                        <td>
                            <span class="awm-connection-status" data-url="<?php echo esc_url($conn['url']); ?>">
                                <?php esc_html_e('Unknown', 'axiom-wp-migrate'); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($conn['created_at']); ?></td>
                        <td>
                            <button class="button awm-test-connection" data-id="<?php echo esc_attr($conn['id']); ?>">
                                <?php esc_html_e('Test', 'axiom-wp-migrate'); ?>
                            </button>
                            <button class="button awm-edit-connection" data-id="<?php echo esc_attr($conn['id']); ?>">
                                <?php esc_html_e('Edit', 'axiom-wp-migrate'); ?>
                            </button>
                            <button class="button awm-delete-connection" data-id="<?php echo esc_attr($conn['id']); ?>">
                                <?php esc_html_e('Delete', 'axiom-wp-migrate'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add/Edit Connection Modal -->
<div id="awm-connection-modal" style="display:none;">
    <div class="awm-modal-overlay"></div>
    <div class="awm-modal-content">
        <h2 id="awm-modal-title"><?php esc_html_e('Add New Connection', 'axiom-wp-migrate'); ?></h2>
        
        <form id="awm-connection-form" method="post">
            <?php wp_nonce_field('awm_connection', 'awm_nonce'); ?>
            <input type="hidden" name="action" value="awm_save_connection">
            <input type="hidden" name="connection_id" id="connection_id" value="">
            
            <table class="form-table">
                <tr>
                    <th><label for="connection_name"><?php esc_html_e('Name', 'axiom-wp-migrate'); ?></label></th>
                    <td>
                        <input type="text" name="name" id="connection_name" class="regular-text" required>
                        <p class="description"><?php esc_html_e('A friendly name for this connection', 'axiom-wp-migrate'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="connection_url"><?php esc_html_e('URL', 'axiom-wp-migrate'); ?></label></th>
                    <td>
                        <input type="url" name="url" id="connection_url" class="regular-text" 
                               placeholder="https://example.com" required>
                        <p class="description"><?php esc_html_e('Full URL of the remote WordPress site', 'axiom-wp-migrate'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="connection_key"><?php esc_html_e('Access Key', 'axiom-wp-migrate'); ?></label></th>
                    <td>
                        <input type="text" name="key" id="connection_key" class="regular-text" required>
                        <p class="description"><?php esc_html_e('Shared secret key for authentication', 'axiom-wp-migrate'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Save Connection', 'axiom-wp-migrate'); ?>
                </button>
                <button type="button" class="button awm-cancel-connection">
                    <?php esc_html_e('Cancel', 'axiom-wp-migrate'); ?>
                </button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test connection
    $('.awm-test-connection').click(function() {
        var $btn = $(this);
        var $status = $btn.closest('tr').find('.awm-connection-status');
        
        $status.text('<?php esc_html_e('Testing...', 'axiom-wp-migrate'); ?>');
        
        $.post(ajaxurl, {
            action: 'awm_test_connection',
            nonce: '<?php echo wp_create_nonce('awm_ajax_nonce'); ?>',
            connection_id: $btn.data('id')
        }, function(response) {
            if (response.success) {
                $status.html('<span style="color:green">✓ <?php esc_html_e('Connected', 'axiom-wp-migrate'); ?></span>');
            } else {
                $status.html('<span style="color:red">✗ ' + response.data.message + '</span>');
            }
        });
    });

    // Delete connection
    $('.awm-delete-connection').click(function() {
        if (confirm('<?php esc_html_e('Are you sure you want to delete this connection?', 'axiom-wp-migrate'); ?>')) {
            var $btn = $(this);
            
            $.post(ajaxurl, {
                action: 'awm_delete_connection',
                nonce: '<?php echo wp_create_nonce('awm_ajax_nonce'); ?>',
                connection_id: $btn.data('id')
            }, function(response) {
                if (response.success) {
                    $btn.closest('tr').remove();
                } else {
                    alert(response.data.message);
                }
            });
        }
    });
});
</script>
