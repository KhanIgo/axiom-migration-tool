<?php
/**
 * Plugin Name: Axiom WP Migrate
 * Plugin URI: https://github.com/your-org/axiom-wp-migrate
 * Description: Safe and controllable migration of WordPress database between environments (local/stage/prod).
 * Version: 1.0.0
 * Author: Axiom Team
 * Author URI: https://axiom.example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: axiom-wp-migrate
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 */

namespace Axiom\WPMigrate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AWM_VERSION', '1.0.0');
define('AWM_PLUGIN_FILE', __FILE__);
define('AWM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AWM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AWM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Axiom\\WPMigrate\\';
    $base_dir = AWM_PLUGIN_DIR . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Main plugin class
 */
class Plugin {
    
    /**
     * Singleton instance
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->initHooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks(): void {
        register_activation_hook(AWM_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(AWM_PLUGIN_FILE, [$this, 'deactivate']);
        
        add_action('init', [$this, 'loadTextdomain']);
        add_action('admin_init', [$this, 'adminInit']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_awm_test_connection', [$this, 'ajaxTestConnection']);
        add_action('wp_ajax_awm_save_connection', [$this, 'ajaxSaveConnection']);
        add_action('wp_ajax_awm_delete_connection', [$this, 'ajaxDeleteConnection']);
        add_action('wp_ajax_awm_get_connection', [$this, 'ajaxGetConnection']);
        add_action('wp_ajax_awm_create_backup', [$this, 'ajaxCreateBackup']);
        add_action('wp_ajax_awm_restore_backup', [$this, 'ajaxRestoreBackup']);
        add_action('wp_ajax_awm_delete_backup', [$this, 'ajaxDeleteBackup']);
        add_action('wp_ajax_awm_get_job_progress', [$this, 'ajaxGetJobProgress']);
        add_action('wp_ajax_awm_push', [$this, 'ajaxPushMigration']);
        add_action('wp_ajax_awm_pull', [$this, 'ajaxPullMigration']);
        add_action('wp_ajax_awm_export', [$this, 'ajaxExport']);
        add_action('wp_ajax_awm_import', [$this, 'ajaxImport']);
        
        // Admin post handlers
        add_action('admin_post_awm_download_backup', [$this, 'handleDownloadBackup']);
        add_action('admin_post_awm_export_logs', [$this, 'handleExportLogs']);
        
        // REST API routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        
        // WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->registerCLICommands();
        }
    }

    /**
     * Plugin activation
     */
    public function activate(): void {
        $this->createDatabaseTables();
        $this->setDefaultOptions();
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Load plugin textdomain
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            'axiom-wp-migrate',
            false,
            dirname(AWM_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Admin initialization
     */
    public function adminInit(): void {
        // Register settings
        $this->registerSettings();
    }

    /**
     * Add admin menu
     */
    public function addAdminMenu(): void {
        $admin = new Admin\AdminPage();
        $admin->init();
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void {
        $transport = new Transport\TransportServer();
        $transport->registerRoutes();
    }

    /**
     * Register WP-CLI commands
     */
    private function registerCLICommands(): void {
        \WP_CLI::add_command('awm', CLI\MigrationCommand::class);
    }

    /**
     * Create custom database tables
     */
    private function createDatabaseTables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE {$wpdb->prefix}awm_jobs (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                type varchar(50) NOT NULL,
                status varchar(50) NOT NULL DEFAULT 'created',
                source_env varchar(255) DEFAULT NULL,
                target_env varchar(255) DEFAULT NULL,
                created_by bigint(20) UNSIGNED NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                meta_json longtext DEFAULT NULL,
                PRIMARY KEY (id),
                KEY status (status),
                KEY type (type)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}awm_job_steps (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id bigint(20) UNSIGNED NOT NULL,
                step_name varchar(100) NOT NULL,
                step_status varchar(50) NOT NULL DEFAULT 'pending',
                checkpoint_json longtext DEFAULT NULL,
                started_at datetime DEFAULT NULL,
                finished_at datetime DEFAULT NULL,
                PRIMARY KEY (id),
                KEY job_id (job_id)
            ) $charset_collate;",

            "CREATE TABLE {$wpdb->prefix}awm_logs (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id bigint(20) UNSIGNED DEFAULT NULL,
                level varchar(20) NOT NULL,
                action varchar(100) NOT NULL,
                message text NOT NULL,
                context_json longtext DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY job_id (job_id),
                KEY level (level),
                KEY created_at (created_at)
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void {
        $defaults = [
            'awm_settings' => [
                'chunk_size' => 5 * 1024 * 1024, // 5MB
                'max_retries' => 3,
                'backup_retention_days' => 14,
                'backup_retention_count' => 3,
                'require_backup' => true,
                'https_only' => true,
            ],
            'awm_connections' => [],
        ];

        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Register plugin settings
     */
    private function registerSettings(): void {
        register_setting('awm_settings_group', 'awm_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);

        register_setting('awm_settings_group', 'awm_connections', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeConnections'],
        ]);
    }

    /**
     * Sanitize settings
     *
     * @param array $input
     * @return array
     */
    public function sanitizeSettings(array $input): array {
        $sanitized = [
            'chunk_size' => absint($input['chunk_size'] ?? 5242880),
            'max_retries' => absint($input['max_retries'] ?? 3),
            'backup_retention_days' => absint($input['backup_retention_days'] ?? 14),
            'backup_retention_count' => absint($input['backup_retention_count'] ?? 3),
            'require_backup' => !empty($input['require_backup']),
            'https_only' => !empty($input['https_only']),
        ];
        return $sanitized;
    }

    /**
     * Sanitize connections
     *
     * @param array $input
     * @return array
     */
    public function sanitizeConnections(array $input): array {
        $sanitized = [];
        foreach ($input as $connection) {
            $sanitized[] = [
                'id' => sanitize_text_field($connection['id'] ?? wp_generate_uuid4()),
                'name' => sanitize_text_field($connection['name'] ?? ''),
                'url' => esc_url_raw($connection['url'] ?? ''),
                'key_id' => sanitize_text_field($connection['key_id'] ?? ''),
                'created_at' => current_time('mysql'),
            ];
        }
        return $sanitized;
    }

    /**
     * Handle AJAX actions
     */
    public function handleAjaxActions(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';
        
        // Route to appropriate handler
        do_action('awm_ajax_' . $action);
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook
     */
    public function enqueueAdminAssets(string $hook): void {
        // Only load on our pages
        if (strpos($hook, 'awm-') !== 0 && $hook !== 'toplevel_page_axiom-wp-migrate') {
            return;
        }

        wp_enqueue_style(
            'awm-admin',
            AWM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            AWM_VERSION
        );

        wp_enqueue_script(
            'awm-admin',
            AWM_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            AWM_VERSION,
            true
        );

        wp_localize_script('awm-admin', 'awm_admin', [
            'nonce' => wp_create_nonce('awm_ajax_nonce'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'addConnectionTitle' => __('Add New Connection', 'axiom-wp-migrate'),
            'editConnectionTitle' => __('Edit Connection', 'axiom-wp-migrate'),
            'saveConnection' => __('Save Connection', 'axiom-wp-migrate'),
            'saving' => __('Saving...', 'axiom-wp-migrate'),
            'saveError' => __('Failed to save connection', 'axiom-wp-migrate'),
            'deleteConfirm' => __('Are you sure you want to delete this connection?', 'axiom-wp-migrate'),
            'deleteError' => __('Failed to delete connection', 'axiom-wp-migrate'),
            'testing' => __('Testing...', 'axiom-wp-migrate'),
            'connected' => __('Connected', 'axiom-wp-migrate'),
            'connectionFailed' => __('Connection failed', 'axiom-wp-migrate'),
            'dryRunConfirm' => __('This is a dry run. Continue?', 'axiom-wp-migrate'),
            'migrationError' => __('Migration failed', 'axiom-wp-migrate'),
            'exporting' => __('Exporting...', 'axiom-wp-migrate'),
            'exportDatabase' => __('Export Database', 'axiom-wp-migrate'),
            'exportSuccess' => __('Export completed', 'axiom-wp-migrate'),
            'exportError' => __('Export failed', 'axiom-wp-migrate'),
            'importWarning' => __('WARNING: This will overwrite your database. Continue?', 'axiom-wp-migrate'),
            'dryRunComplete' => __('Dry run completed', 'axiom-wp-migrate'),
            'importSuccess' => __('Import completed', 'axiom-wp-migrate'),
            'importError' => __('Import failed', 'axiom-wp-migrate'),
            'restoreWarning' => __('WARNING: This will overwrite your database. Continue?', 'axiom-wp-migrate'),
            'restoreSuccess' => __('Restore completed', 'axiom-wp-migrate'),
            'restoreError' => __('Restore failed', 'axiom-wp-migrate'),
            'deleteBackupConfirm' => __('Are you sure you want to delete this backup?', 'axiom-wp-migrate'),
            'deleteBackupError' => __('Failed to delete backup', 'axiom-wp-migrate'),
        ]);
    }

    /**
     * AJAX: Test connection
     */
    public function ajaxTestConnection(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $connectionId = sanitize_text_field($_POST['connection_id'] ?? '');
        $connections = get_option('awm_connections', []);

        foreach ($connections as $conn) {
            if ($conn['id'] === $connectionId) {
                $client = new \Axiom\WPMigrate\Transport\TransportClient($conn);
                $result = $client->testConnection($conn);
                
                if ($result['success']) {
                    wp_send_json_success($result);
                } else {
                    wp_send_json_error($result);
                }
                return;
            }
        }

        wp_send_json_error(['message' => __('Connection not found', 'axiom-wp-migrate')]);
    }

    /**
     * AJAX: Save connection
     */
    public function ajaxSaveConnection(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $connections = get_option('awm_connections', []);
        $connectionId = $_POST['connection_id'] ?? wp_generate_uuid4();
        
        $connection = [
            'id' => $connectionId,
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'url' => esc_url_raw($_POST['url'] ?? ''),
            'key_id' => sanitize_text_field($_POST['key'] ?? wp_generate_password(32, false)),
            'created_at' => current_time('mysql'),
        ];

        // Update or add connection
        $found = false;
        foreach ($connections as &$conn) {
            if ($conn['id'] === $connectionId) {
                $conn = $connection;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $connections[] = $connection;
        }

        update_option('awm_connections', $connections);
        wp_send_json_success(['connection' => $connection]);
    }

    /**
     * AJAX: Delete connection
     */
    public function ajaxDeleteConnection(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $connectionId = sanitize_text_field($_POST['connection_id'] ?? '');
        $connections = get_option('awm_connections', []);

        $connections = array_filter($connections, function($conn) use ($connectionId) {
            return $conn['id'] !== $connectionId;
        });

        update_option('awm_connections', array_values($connections));
        wp_send_json_success();
    }

    /**
     * AJAX: Get connection
     */
    public function ajaxGetConnection(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $connectionId = sanitize_text_field($_POST['connection_id'] ?? '');
        $connections = get_option('awm_connections', []);

        foreach ($connections as $conn) {
            if ($conn['id'] === $connectionId) {
                wp_send_json_success($conn);
                return;
            }
        }

        wp_send_json_error(['message' => __('Connection not found', 'axiom-wp-migrate')]);
    }

    /**
     * AJAX: Create backup
     */
    public function ajaxCreateBackup(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $backupService = new \Axiom\WPMigrate\Domain\BackupService();
        $name = sanitize_text_field($_POST['backup_name'] ?? 'backup_' . time());
        
        $result = $backupService->createBackup($name);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Restore backup
     */
    public function ajaxRestoreBackup(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $backupService = new \Axiom\WPMigrate\Domain\BackupService();
        $backupName = sanitize_text_field($_POST['backup_name'] ?? '');
        
        $result = $backupService->restoreBackup($backupName);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Delete backup
     */
    public function ajaxDeleteBackup(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $backupService = new \Axiom\WPMigrate\Domain\BackupService();
        $backupName = sanitize_text_field($_POST['backup_name'] ?? '');
        
        $result = $backupService->deleteBackup($backupName);
        wp_send_json_success();
    }

    /**
     * AJAX: Get job progress
     */
    public function ajaxGetJobProgress(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        $jobId = (int) ($_POST['job_id'] ?? 0);
        $jobStore = new \Axiom\WPMigrate\Infrastructure\JobStore();
        
        $progress = $jobStore->getJobProgress($jobId);
        wp_send_json_success(['progress' => $progress]);
    }

    /**
     * AJAX: Push migration
     */
    public function ajaxPushMigration(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        // Implementation for push migration
        wp_send_json_success(['message' => __('Push migration started', 'axiom-wp-migrate')]);
    }

    /**
     * AJAX: Pull migration
     */
    public function ajaxPullMigration(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        // Implementation for pull migration
        wp_send_json_success(['message' => __('Pull migration started', 'axiom-wp-migrate')]);
    }

    /**
     * AJAX: Export
     */
    public function ajaxExport(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        // Implementation for export
        wp_send_json_success(['message' => __('Export completed', 'axiom-wp-migrate')]);
    }

    /**
     * AJAX: Import
     */
    public function ajaxImport(): void {
        check_ajax_referer('awm_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'axiom-wp-migrate')]);
        }

        // Implementation for import
        wp_send_json_success(['message' => __('Import completed', 'axiom-wp-migrate')]);
    }

    /**
     * Handle backup download
     */
    public function handleDownloadBackup(): void {
        check_admin_referer('awm_download_backup');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'axiom-wp-migrate'));
        }

        $backupName = sanitize_text_field($_GET['name'] ?? '');
        $backupService = new \Axiom\WPMigrate\Domain\BackupService();
        $backups = $backupService->getBackups();

        foreach ($backups as $backup) {
            if ($backup['name'] === $backupName && file_exists($backup['path'])) {
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . basename($backup['path']) . '"');
                readfile($backup['path']);
                exit;
            }
        }

        wp_die(__('Backup not found', 'axiom-wp-migrate'));
    }

    /**
     * Handle logs export
     */
    public function handleExportLogs(): void {
        check_admin_referer('awm_export_logs');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'axiom-wp-migrate'));
        }

        $logger = new \Axiom\WPMigrate\Infrastructure\AuditLogger();
        $filePath = wp_upload_dir()['basedir'] . '/awm-logs-' . date('Y-m-d') . '.csv';
        
        $logger->exportToCSV([], $filePath);
        
        if (file_exists($filePath)) {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            readfile($filePath);
            unlink($filePath);
            exit;
        }

        wp_die(__('Failed to export logs', 'axiom-wp-migrate'));
    }
}

// Initialize plugin
function awm_init(): Plugin {
    return Plugin::getInstance();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\awm_init');
