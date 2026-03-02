<?php
/**
 * Settings template
 */

if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('awm_settings', []);
?>

<div class="wrap awm-settings">
    <h1><?php esc_html_e('Settings', 'axiom-wp-migrate'); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('awm_settings_group'); ?>
        <?php do_settings_sections('awm_settings_group'); ?>

        <table class="form-table">
            <tr>
                <th><label for="awm_chunk_size"><?php esc_html_e('Chunk Size', 'axiom-wp-migrate'); ?></label></th>
                <td>
                    <input type="number" name="awm_settings[chunk_size]" id="awm_chunk_size" 
                           value="<?php echo esc_attr($settings['chunk_size'] ?? 5242880); ?>" 
                           class="small-text" step="1048576" min="1048576">
                    <p class="description">
                        <?php esc_html_e('Size of data chunks for transfer (in bytes). Default: 5MB', 'axiom-wp-migrate'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="awm_max_retries"><?php esc_html_e('Max Retries', 'axiom-wp-migrate'); ?></label></th>
                <td>
                    <input type="number" name="awm_settings[max_retries]" id="awm_max_retries" 
                           value="<?php echo esc_attr($settings['max_retries'] ?? 3); ?>" 
                           class="small-text" min="0" max="10">
                    <p class="description">
                        <?php esc_html_e('Number of retry attempts for failed operations', 'axiom-wp-migrate'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="awm_backup_retention_days"><?php esc_html_e('Backup Retention (Days)', 'axiom-wp-migrate'); ?></label></th>
                <td>
                    <input type="number" name="awm_settings[backup_retention_days]" id="awm_backup_retention_days" 
                           value="<?php echo esc_attr($settings['backup_retention_days'] ?? 14); ?>" 
                           class="small-text" min="1" max="365">
                    <p class="description">
                        <?php esc_html_e('Number of days to keep backups', 'axiom-wp-migrate'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="awm_backup_retention_count"><?php esc_html_e('Backup Retention (Count)', 'axiom-wp-migrate'); ?></label></th>
                <td>
                    <input type="number" name="awm_settings[backup_retention_count]" id="awm_backup_retention_count" 
                           value="<?php echo esc_attr($settings['backup_retention_count'] ?? 3); ?>" 
                           class="small-text" min="1" max="100">
                    <p class="description">
                        <?php esc_html_e('Minimum number of recent backups to keep', 'axiom-wp-migrate'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Safety Options', 'axiom-wp-migrate'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="awm_settings[require_backup]" value="1" 
                               <?php checked(!empty($settings['require_backup'])); ?>>
                        <?php esc_html_e('Require backup before migration (recommended)', 'axiom-wp-migrate'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="awm_settings[https_only]" value="1" 
                               <?php checked(!empty($settings['https_only'])); ?>>
                        <?php esc_html_e('Enforce HTTPS for remote connections', 'axiom-wp-migrate'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Plugin Information', 'axiom-wp-migrate'); ?></h2>
    <table class="form-table awm-info-table">
        <tr>
            <th><?php esc_html_e('Version', 'axiom-wp-migrate'); ?></th>
            <td><?php echo esc_html(AWM_VERSION); ?></td>
        </tr>
        <tr>
            <th><?php esc_html_e('License', 'axiom-wp-migrate'); ?></th>
            <td>GPL v2 or later</td>
        </tr>
        <tr>
            <th><?php esc_html_e('Documentation', 'axiom-wp-migrate'); ?></th>
            <td>
                <a href="#" target="_blank"><?php esc_html_e('View Documentation', 'axiom-wp-migrate'); ?></a>
            </td>
        </tr>
    </table>
</div>
