<?php
/**
 * Dashboard template
 */

if (!defined('ABSPATH')) {
    exit;
}

$jobStore = new \Axiom\WPMigrate\Infrastructure\JobStore();
$recentJobs = $jobStore->getJobs(['limit' => 5]);
?>

<div class="wrap awm-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <div class="awm-welcome-panel">
        <div class="awm-welcome-panel-content">
            <h2><?php esc_html_e('Welcome to Axiom WP Migrate', 'axiom-wp-migrate'); ?></h2>
            <p class="about-description">
                <?php esc_html_e('Safe and controllable migration of WordPress database between environments.', 'axiom-wp-migrate'); ?>
            </p>

            <div class="awm-quick-actions">
                <h3><?php esc_html_e('Quick Actions', 'axiom-wp-migrate'); ?></h3>
                
                <div class="awm-action-cards">
                    <div class="awm-action-card">
                        <h4><?php esc_html_e('New Migration', 'axiom-wp-migrate'); ?></h4>
                        <p><?php esc_html_e('Start a new push or pull migration', 'axiom-wp-migrate'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=awm-migrations'); ?>" class="button button-primary">
                            <?php esc_html_e('Go to Migrations', 'axiom-wp-migrate'); ?>
                        </a>
                    </div>

                    <div class="awm-action-card">
                        <h4><?php esc_html_e('Connections', 'axiom-wp-migrate'); ?></h4>
                        <p><?php esc_html_e('Manage remote environment connections', 'axiom-wp-migrate'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=awm-connections'); ?>" class="button">
                            <?php esc_html_e('Manage Connections', 'axiom-wp-migrate'); ?>
                        </a>
                    </div>

                    <div class="awm-action-card">
                        <h4><?php esc_html_e('Backups', 'axiom-wp-migrate'); ?></h4>
                        <p><?php esc_html_e('View and restore database backups', 'axiom-wp-migrate'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=awm-backups'); ?>" class="button">
                            <?php esc_html_e('View Backups', 'axiom-wp-migrate'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="awm-dashboard-widgets">
        <div class="awm-widget">
            <h3><?php esc_html_e('Recent Jobs', 'axiom-wp-migrate'); ?></h3>
            
            <?php if (empty($recentJobs)) : ?>
                <p><?php esc_html_e('No migration jobs yet.', 'axiom-wp-migrate'); ?></p>
            <?php else : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('ID', 'axiom-wp-migrate'); ?></th>
                            <th><?php esc_html_e('Type', 'axiom-wp-migrate'); ?></th>
                            <th><?php esc_html_e('Status', 'axiom-wp-migrate'); ?></th>
                            <th><?php esc_html_e('Created', 'axiom-wp-migrate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentJobs as $job) : ?>
                            <tr>
                                <td><?php echo esc_html($job->id); ?></td>
                                <td><?php echo esc_html($job->type); ?></td>
                                <td>
                                    <span class="awm-status awm-status-<?php echo esc_attr($job->status); ?>">
                                        <?php echo esc_html($job->status); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($job->created_at); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p class="awm-view-all">
                <a href="<?php echo admin_url('admin.php?page=awm-migrations'); ?>">
                    <?php esc_html_e('View all migrations →', 'axiom-wp-migrate'); ?>
                </a>
            </p>
        </div>

        <div class="awm-widget">
            <h3><?php esc_html_e('System Status', 'axiom-wp-migrate'); ?></h3>
            
            <table class="awm-status-table">
                <tr>
                    <th><?php esc_html_e('Plugin Version', 'axiom-wp-migrate'); ?></th>
                    <td><?php echo esc_html(AWM_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('PHP Version', 'axiom-wp-migrate'); ?></th>
                    <td><?php echo esc_html(PHP_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('WordPress Version', 'axiom-wp-migrate'); ?></th>
                    <td><?php echo esc_html($GLOBALS['wp_version']); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Database', 'axiom-wp-migrate'); ?></th>
                    <td><?php echo esc_html(get_bloginfo('charset')); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Max Execution Time', 'axiom-wp-migrate'); ?></th>
                    <td><?php echo esc_html(ini_get('max_execution_time')); ?>s</td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Memory Limit', 'axiom-wp-migrate'); ?></th>
                    <td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td>
                </tr>
            </table>
        </div>
    </div>
</div>
