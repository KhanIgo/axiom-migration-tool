<?php
/**
 * Migrations template
 */

if (!defined('ABSPATH')) {
    exit;
}

$jobStore = new \Axiom\WPMigrate\Infrastructure\JobStore();
$connections = get_option('awm_connections', []);
$allTables = $GLOBALS['wpdb']->get_col('SHOW TABLES');
?>

<div class="wrap awm-migrations">
    <h1><?php esc_html_e('Migrations', 'axiom-wp-migrate'); ?></h1>

    <div class="awm-migration-forms">
        <!-- Push Migration -->
        <div class="awm-form-card">
            <h2><?php esc_html_e('Push Database', 'axiom-wp-migrate'); ?></h2>
            <p><?php esc_html_e('Send this database to a remote environment', 'axiom-wp-migrate'); ?></p>
            
            <form id="awm-push-form" method="post">
                <?php wp_nonce_field('awm_migration', 'awm_nonce'); ?>
                <input type="hidden" name="action" value="awm_push">
                
                <table class="form-table">
                    <tr>
                        <th><label for="push_connection"><?php esc_html_e('Destination', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <select name="connection" id="push_connection" required>
                                <option value=""><?php esc_html_e('Select connection...', 'axiom-wp-migrate'); ?></option>
                                <?php foreach ($connections as $conn) : ?>
                                    <option value="<?php echo esc_attr($conn['id']); ?>">
                                        <?php echo esc_html($conn['name']); ?> (<?php echo esc_url($conn['url']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Tables', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <label>
                                <input type="radio" name="table_filter" value="all" checked>
                                <?php esc_html_e('All tables', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="table_filter" value="content_only">
                                <?php esc_html_e('Content only (posts, terms)', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="table_filter" value="no_users">
                                <?php esc_html_e('No users', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="custom_tables" id="push_custom_tables">
                                <?php esc_html_e('Custom selection', 'axiom-wp-migrate'); ?>
                            </label>
                            <div id="push_tables_select" style="display:none; margin-top:10px;">
                                <select name="include_tables[]" multiple size="8" style="width:300px;">
                                    <?php foreach ($allTables as $table) : ?>
                                        <option value="<?php echo esc_attr($table); ?>">
                                            <?php echo esc_html($table); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Options', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="dry_run" value="1">
                                <?php esc_html_e('Dry run (preview only)', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="backup" value="1" checked disabled>
                                <?php esc_html_e('Create backup before migration', 'axiom-wp-migrate'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" name="push_action" value="push">
                        <?php esc_html_e('Push Database', 'axiom-wp-migrate'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Pull Migration -->
        <div class="awm-form-card">
            <h2><?php esc_html_e('Pull Database', 'axiom-wp-migrate'); ?></h2>
            <p><?php esc_html_e('Download database from a remote environment', 'axiom-wp-migrate'); ?></p>
            
            <form id="awm-pull-form" method="post">
                <?php wp_nonce_field('awm_migration', 'awm_nonce'); ?>
                <input type="hidden" name="action" value="awm_pull">
                
                <table class="form-table">
                    <tr>
                        <th><label for="pull_connection"><?php esc_html_e('Source', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <select name="connection" id="pull_connection" required>
                                <option value=""><?php esc_html_e('Select connection...', 'axiom-wp-migrate'); ?></option>
                                <?php foreach ($connections as $conn) : ?>
                                    <option value="<?php echo esc_attr($conn['id']); ?>">
                                        <?php echo esc_html($conn['name']); ?> (<?php echo esc_url($conn['url']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Tables', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <label>
                                <input type="radio" name="table_filter" value="all" checked>
                                <?php esc_html_e('All tables', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="table_filter" value="content_only">
                                <?php esc_html_e('Content only (posts, terms)', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="radio" name="table_filter" value="no_users">
                                <?php esc_html_e('No users', 'axiom-wp-migrate'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Options', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="dry_run" value="1">
                                <?php esc_html_e('Dry run (preview only)', 'axiom-wp-migrate'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="backup" value="1" checked disabled>
                                <?php esc_html_e('Create backup before migration', 'axiom-wp-migrate'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" name="pull_action" value="pull">
                        <?php esc_html_e('Pull Database', 'axiom-wp-migrate'); ?>
                    </button>
                </p>
            </form>
        </div>

        <!-- Export/Import -->
        <div class="awm-form-card">
            <h2><?php esc_html_e('Export / Import', 'axiom-wp-migrate'); ?></h2>
            <p><?php esc_html_e('Export to SQL file or import from SQL file', 'axiom-wp-migrate'); ?></p>
            
            <h3><?php esc_html_e('Export', 'axiom-wp-migrate'); ?></h3>
            <form id="awm-export-form" method="post">
                <?php wp_nonce_field('awm_export', 'awm_nonce'); ?>
                <input type="hidden" name="action" value="awm_export">
                
                <table class="form-table">
                    <tr>
                        <th><label for="export_file"><?php esc_html_e('File Path', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <input type="text" name="file" id="export_file" class="regular-text" 
                                   value="<?php echo esc_attr(wp_upload_dir()['basedir'] . '/export-' . date('Y-m-d') . '.sql'); ?>" required>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button">
                        <?php esc_html_e('Export Database', 'axiom-wp-migrate'); ?>
                    </button>
                </p>
            </form>

            <hr>

            <h3><?php esc_html_e('Import', 'axiom-wp-migrate'); ?></h3>
            <form id="awm-import-form" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('awm_import', 'awm_nonce'); ?>
                <input type="hidden" name="action" value="awm_import">
                
                <table class="form-table">
                    <tr>
                        <th><label for="import_file"><?php esc_html_e('SQL File', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <input type="file" name="file" id="import_file" accept=".sql" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php esc_html_e('Options', 'axiom-wp-migrate'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="dry_run" value="1">
                                <?php esc_html_e('Dry run (preview only)', 'axiom-wp-migrate'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button">
                        <?php esc_html_e('Import SQL', 'axiom-wp-migrate'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <!-- Job Progress Modal -->
    <div id="awm-progress-modal" style="display:none;">
        <div class="awm-modal-content">
            <h2><?php esc_html_e('Migration Progress', 'axiom-wp-migrate'); ?></h2>
            <div class="awm-progress-bar">
                <div class="awm-progress-fill" style="width:0%"></div>
            </div>
            <p class="awm-progress-text">0%</p>
            <div id="awm-progress-details"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Custom tables toggle
    $('#push_custom_tables, #pull_custom_tables').change(function() {
        var prefix = $(this).attr('id').replace('_custom_tables', '');
        $('#' + prefix + '_tables_select').toggle(this.checked);
    });

    // Form submission with progress tracking
    $('#awm-push-form, #awm-pull-form').submit(function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var action = $form.find('input[name="action"]').val();
        
        $.post(ajaxurl, $form.serialize(), function(response) {
            if (response.success) {
                $('#awm-progress-modal').show();
                // Poll for progress updates
                pollProgress(response.data.job_id);
            } else {
                alert(response.data.message);
            }
        });
    });
});

function pollProgress(jobId) {
    // Progress polling implementation
}
</script>
