<?php
/**
 * Logs template
 */

if (!defined('ABSPATH')) {
    exit;
}

$logger = new \Axiom\WPMigrate\Infrastructure\AuditLogger();

// Get filter parameters
$filters = [
    'limit' => 100,
];

if (!empty($_GET['job_id'])) {
    $filters['job_id'] = (int) $_GET['job_id'];
}

if (!empty($_GET['level'])) {
    $filters['level'] = sanitize_text_field($_GET['level']);
}

$logs = $logger->getLogs($filters);
?>

<div class="wrap awm-logs">
    <h1><?php esc_html_e('Migration Logs', 'axiom-wp-migrate'); ?></h1>

    <form method="get" class="awm-log-filters">
        <input type="hidden" name="page" value="awm-logs">
        
        <select name="level">
            <option value=""><?php esc_html_e('All Levels', 'axiom-wp-migrate'); ?></option>
            <option value="debug" <?php selected($filters['level'] ?? '', 'debug'); ?>><?php esc_html_e('Debug', 'axiom-wp-migrate'); ?></option>
            <option value="info" <?php selected($filters['level'] ?? '', 'info'); ?>><?php esc_html_e('Info', 'axiom-wp-migrate'); ?></option>
            <option value="warning" <?php selected($filters['level'] ?? '', 'warning'); ?>><?php esc_html_e('Warning', 'axiom-wp-migrate'); ?></option>
            <option value="error" <?php selected($filters['level'] ?? '', 'error'); ?>><?php esc_html_e('Error', 'axiom-wp-migrate'); ?></option>
            <option value="critical" <?php selected($filters['level'] ?? '', 'critical'); ?>><?php esc_html_e('Critical', 'axiom-wp-migrate'); ?></option>
        </select>

        <input type="number" name="job_id" placeholder="<?php esc_attr_e('Job ID', 'axiom-wp-migrate'); ?>" 
               value="<?php echo esc_attr($filters['job_id'] ?? ''); ?>">

        <button type="submit" class="button">
            <?php esc_html_e('Filter', 'axiom-wp-migrate'); ?>
        </button>

        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=awm_export_logs'), 'awm_export_logs')); ?>" 
           class="button">
            <?php esc_html_e('Export CSV', 'axiom-wp-migrate'); ?>
        </a>
    </form>

    <?php if (empty($logs)) : ?>
        <p><?php esc_html_e('No logs found.', 'axiom-wp-migrate'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped awm-logs-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Timestamp', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Level', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Job ID', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Action', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Message', 'axiom-wp-migrate'); ?></th>
                    <th><?php esc_html_e('Context', 'axiom-wp-migrate'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr class="awm-log-level-<?php echo esc_attr($log->level); ?>">
                        <td><?php echo esc_html($log->created_at); ?></td>
                        <td>
                            <span class="awm-log-badge awm-log-<?php echo esc_attr($log->level); ?>">
                                <?php echo esc_html(strtoupper($log->level)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->job_id) : ?>
                                <a href="?page=awm-logs&job_id=<?php echo esc_attr($log->job_id); ?>">
                                    #<?php echo esc_html($log->job_id); ?>
                                </a>
                            <?php else : ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($log->action); ?></td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <?php if ($log->context_json) : ?>
                                <code><?php echo esc_html(substr($log->context_json, 0, 50)); ?>...</code>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.awm-log-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
}

.awm-log-debug { background: #f0f0f1; color: #666; }
.awm-log-info { background: #e5f5fa; color: #0073aa; }
.awm-log-warning { background: #fff8e5; color: #f0b849; }
.awm-log-error { background: #fbeaea; color: #d63638; }
.awm-log-critical { background: #d63638; color: #fff; }

.awm-logs-table code {
    display: block;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
