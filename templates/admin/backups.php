<?php
/**
 * Backups template
 */

if (!defined('ABSPATH')) {
    exit;
}

$backupService = new \Axiom\WPMigrate\Domain\BackupService();
$backups = $backupService->getBackups();
?>

<div class="wrap awm-backups">
    <h1><?php esc_html_e('Backups', 'axiom-wp-migrate'); ?></h1>

    <form method="post" class="awm-create-backup-form">
        <?php wp_nonce_field('awm_backup', 'awm_nonce'); ?>
        <input type="hidden" name="action" value="awm_create_backup">
        
        <input type="text" name="backup_name" placeholder="<?php esc_attr_e('Backup name', 'axiom-wp-migrate'); ?>" 
               value="backup_<?php echo date('Y-m-d_His'); ?>">
        <button type="submit" class="button button-primary">
            <?php esc_html_e('Create Backup', 'axiom-wp-migrate'); ?>
        </button>
    </form>

    <?php if (empty($backups)) : ?>
        <p><?php esc_html_e('No backups found.', 'axiom-wp-migrate'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Size', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Created', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Status', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Actions', 'axiom-wp-migrate'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($backups as $backup) : ?>
                    <tr>
                        <td><?php echo esc_html($backup['name']); ?></td>
                        <td><?php echo esc_html(size_format($backup['size'] ?? 0)); ?></td>
                        <td><?php echo esc_html($backup['created_at']); ?></td>
                        <td>
                            <?php if (!empty($backup['exists'])) : ?>
                                <span style="color:green"><?php esc_html_e('Available', 'axiom-wp-migrate'); ?></span>
                            <?php else : ?>
                                <span style="color:red"><?php esc_html_e('Missing', 'axiom-wp-migrate'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($backup['exists'])) : ?>
                                <button class="button awm-restore-backup" data-name="<?php echo esc_attr($backup['name']); ?>">
                                    <?php esc_html_e('Restore', 'axiom-wp-migrate'); ?>
                                </button>
                            <?php endif; ?>
                            <button class="button awm-delete-backup" data-name="<?php echo esc_attr($backup['name']); ?>">
                                <?php esc_html_e('Delete', 'axiom-wp-migrate'); ?>
                            </button>
                            <?php if (!empty($backup['exists'])) : ?>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=awm_download_backup&name=' . urlencode($backup['name'])), 'awm_download_backup')); ?>" 
                                   class="button">
                                    <?php esc_html_e('Download', 'axiom-wp-migrate'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Restore backup
    $('.awm-restore-backup').click(function() {
        var name = $(this).data('name');
        
        if (confirm('<?php esc_html_e('WARNING: This will overwrite your database. Are you sure?', 'axiom-wp-migrate'); ?>')) {
            $.post(ajaxurl, {
                action: 'awm_restore_backup',
                nonce: '<?php echo wp_create_nonce('awm_ajax_nonce'); ?>',
                backup_name: name
            }, function(response) {
                if (response.success) {
                    alert('<?php esc_html_e('Restore completed successfully', 'axiom-wp-migrate'); ?>');
                    location.reload();
                } else {
                    alert('<?php esc_html_e('Restore failed:', 'axiom-wp-migrate'); ?> ' + response.data.message);
                }
            });
        }
    });

    // Delete backup
    $('.awm-delete-backup').click(function() {
        if (confirm('<?php esc_html_e('Are you sure you want to delete this backup?', 'axiom-wp-migrate'); ?>')) {
            var $btn = $(this);
            
            $.post(ajaxurl, {
                action: 'awm_delete_backup',
                nonce: '<?php echo wp_create_nonce('awm_ajax_nonce'); ?>',
                backup_name: $btn.data('name')
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
