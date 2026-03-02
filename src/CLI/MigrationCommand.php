<?php
/**
 * WP-CLI Migration Commands
 * 
 * Command-line interface for migration operations
 */

namespace Axiom\WPMigrate\CLI;

use Axiom\WPMigrate\Application\MigrationEngine;
use Axiom\WPMigrate\Infrastructure\JobStore;
use Axiom\WPMigrate\Infrastructure\AuditLogger;
use Axiom\WPMigrate\Domain\BackupService;
use Axiom\WPMigrate\Transport\TransportClient;
use WP_CLI;
use WP_CLI_Command;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration commands for WP-CLI
 */
class MigrationCommand extends WP_CLI_Command {

    /**
     * Migration engine instance
     *
     * @var MigrationEngine
     */
    private $engine;

    /**
     * Job store instance
     *
     * @var JobStore
     */
    private $jobStore;

    /**
     * Audit logger instance
     *
     * @var AuditLogger
     */
    private $logger;

    /**
     * Backup service instance
     *
     * @var BackupService
     */
    private $backupService;

    /**
     * Constructor
     */
    public function __construct() {
        $this->jobStore = new JobStore();
        $this->logger = new AuditLogger();
        $this->backupService = new BackupService($this->logger);
        $this->engine = new MigrationEngine($this->jobStore, $this->logger, $this->backupService);
    }

    /**
     * Export database to SQL file
     *
     * ## OPTIONS
     *
     * --file=<path>
     * : Path to the output SQL file
     *
     * [--include-tables=<tables>]
     * : Comma-separated list of tables to include
     *
     * [--exclude-tables=<tables>]
     * : Comma-separated list of tables to exclude
     *
     * [--preset=<preset>]
     * : Preset filter: content_only, no_users
     *
     * ## EXAMPLES
     *
     *     wp awm export --file=/path/to/backup.sql
     *     wp awm export --file=backup.sql --include-tables=posts,postmeta
     *     wp awm export --file=backup.sql --preset=content_only
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function export(array $args, array $assoc_args): void {
        $file = \WP_CLI\Utils\get_flag_value($assoc_args, 'file');

        if (empty($file)) {
            WP_CLI::error('Missing required --file argument');
        }

        $meta = [
            'file' => $file,
            'include_tables' => $this->parseTableList(\WP_CLI\Utils\get_flag_value($assoc_args, 'include-tables', '')),
            'exclude_tables' => $this->parseTableList(\WP_CLI\Utils\get_flag_value($assoc_args, 'exclude-tables', '')),
            'preset' => \WP_CLI\Utils\get_flag_value($assoc_args, 'preset', null),
        ];

        $jobId = $this->engine->createJob(MigrationEngine::TYPE_EXPORT, $meta);

        WP_CLI::log("Starting export (Job ID: {$jobId})...");

        $result = $this->engine->runJob($jobId, false);

        if ($result['success']) {
            WP_CLI::success("Export completed: {$file}");
            
            if (isset($result['data']['total_rows'])) {
                WP_CLI::log("Total rows exported: {$result['data']['total_rows']}");
            }
        } else {
            WP_CLI::error("Export failed: {$result['message']}");
        }
    }

    /**
     * Import database from SQL file
     *
     * ## OPTIONS
     *
     * --file=<path>
     * : Path to the SQL file to import
     *
     * [--dry-run]
     * : Preview changes without applying them
     *
     * [--replace=<old,new>]
     * : URL/path replacement pairs (can be used multiple times)
     *
     * ## EXAMPLES
     *
     *     wp awm import --file=/path/to/backup.sql
     *     wp awm import --file=backup.sql --dry-run
     *     wp awm import --file=backup.sql --replace=http://old.com,http://new.com
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function import(array $args, array $assoc_args): void {
        $file = \WP_CLI\Utils\get_flag_value($assoc_args, 'file');

        if (empty($file)) {
            WP_CLI::error('Missing required --file argument');
        }

        if (!file_exists($file)) {
            WP_CLI::error("File not found: {$file}");
        }

        $dryRun = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);
        
        $replacements = $this->parseReplacements(\WP_CLI\Utils\get_flag_value($assoc_args, 'replace', []));

        $meta = [
            'file' => $file,
            'replacements' => $replacements,
        ];

        $jobId = $this->engine->createJob(MigrationEngine::TYPE_IMPORT, $meta);

        $mode = $dryRun ? 'Dry run' : 'Import';
        WP_CLI::log("{$mode} started (Job ID: {$jobId})...");

        $result = $this->engine->runJob($jobId, $dryRun);

        if ($result['success']) {
            if ($dryRun) {
                WP_CLI::success("Dry run completed successfully");
                $this->displayDryRunResults($result);
            } else {
                WP_CLI::success("Import completed successfully");
                if (isset($result['queries_executed'])) {
                    WP_CLI::log("Queries executed: {$result['queries_executed']}");
                }
            }
        } else {
            WP_CLI::error("Import failed: {$result['message']}");
        }
    }

    /**
     * Push database to remote environment
     *
     * ## OPTIONS
     *
     * --connection=<id>
     * : Connection ID to push to
     *
     * [--include-tables=<tables>]
     * : Comma-separated list of tables to include
     *
     * [--exclude-tables=<tables>]
     * : Comma-separated list of tables to exclude
     *
     * [--dry-run]
     * : Preview changes without applying them
     *
     * ## EXAMPLES
     *
     *     wp awm migrate push --connection=production
     *     wp awm migrate push --connection=staging --dry-run
     *     wp awm migrate push --connection=prod --include-tables=posts,postmeta
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function push(array $args, array $assoc_args): void {
        $connectionId = \WP_CLI\Utils\get_flag_value($assoc_args, 'connection');

        if (empty($connectionId)) {
            WP_CLI::error('Missing required --connection argument');
        }

        $connection = $this->getConnection($connectionId);

        if (!$connection) {
            WP_CLI::error("Connection not found: {$connectionId}");
        }

        $dryRun = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);

        $meta = [
            'connection' => $connection,
            'include_tables' => $this->parseTableList(\WP_CLI\Utils\get_flag_value($assoc_args, 'include-tables', '')),
            'exclude_tables' => $this->parseTableList(\WP_CLI\Utils\get_flag_value($assoc_args, 'exclude-tables', '')),
        ];

        $jobId = $this->engine->createJob(MigrationEngine::TYPE_PUSH, $meta);

        $mode = $dryRun ? 'Dry run' : 'Push';
        WP_CLI::log("{$mode} to {$connection['name']} started (Job ID: {$jobId})...");

        $client = new TransportClient($connection);
        $result = $client->push($jobId, $meta, $dryRun);

        if ($result['success']) {
            WP_CLI::success("{$mode} completed successfully");
        } else {
            WP_CLI::error("{$mode} failed: {$result['message']}");
        }
    }

    /**
     * Pull database from remote environment
     *
     * ## OPTIONS
     *
     * --connection=<id>
     * : Connection ID to pull from
     *
     * [--include-tables=<tables>]
     * : Comma-separated list of tables to include
     *
     * [--exclude-tables=<tables>]
     * : Comma-separated list of tables to exclude
     *
     * [--dry-run]
     * : Preview changes without applying them
     *
     * ## EXAMPLES
     *
     *     wp awm migrate pull --connection=production
     *     wp awm migrate pull --connection=staging --dry-run
     *     wp awm migrate pull --connection=prod --include-tables=posts,postmeta
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function pull(array $args, array $assoc_args): void {
        $connectionId = \WP_CLI\Utils\get_flag_value($assoc_args, 'connection');

        if (empty($connectionId)) {
            WP_CLI::error('Missing required --connection argument');
        }

        $connection = $this->getConnection($connectionId);

        if (!$connection) {
            WP_CLI::error("Connection not found: {$connectionId}");
        }

        $dryRun = \WP_CLI\Utils\get_flag_value($assoc_args, 'dry-run', false);

        $meta = [
            'connection' => $connection,
            'include_tables' => $this->parseTableList(\WP_CLI\Utils\get_flag_value($assoc_args, 'include-tables', '')),
            'exclude_tables' => $this->parseTableList(\WP_CLI\Utils\get_flag_value($assoc_args, 'exclude-tables', '')),
        ];

        $jobId = $this->engine->createJob(MigrationEngine::TYPE_PULL, $meta);

        $mode = $dryRun ? 'Dry run' : 'Pull';
        WP_CLI::log("{$mode} from {$connection['name']} started (Job ID: {$jobId})...");

        $client = new TransportClient($connection);
        $result = $client->pull($jobId, $meta, $dryRun);

        if ($result['success']) {
            WP_CLI::success("{$mode} completed successfully");
        } else {
            WP_CLI::error("{$mode} failed: {$result['message']}");
        }
    }

    /**
     * Show job status
     *
     * ## OPTIONS
     *
     * [--job-id=<id>]
     * : Job ID to check (default: latest)
     *
     * [--format=<format>]
     * : Output format: table, json, csv
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     wp awm jobs status
     *     wp awm jobs status --job-id=123
     *     wp awm jobs status --format=json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function status(array $args, array $assoc_args): void {
        $jobId = \WP_CLI\Utils\get_flag_value($assoc_args, 'job-id', null);
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        if ($jobId) {
            $job = $this->jobStore->getJob((int) $jobId);
            
            if (!$job) {
                WP_CLI::error("Job not found: {$jobId}");
            }

            $this->displayJob($job, $format);
        } else {
            $jobs = $this->jobStore->getJobs(['limit' => 10]);
            
            if (empty($jobs)) {
                WP_CLI::log('No jobs found');
                return;
            }

            $this->displayJobs($jobs, $format);
        }
    }

    /**
     * List all jobs
     *
     * ## OPTIONS
     *
     * [--status=<status>]
     * : Filter by status
     *
     * [--type=<type>]
     * : Filter by type
     *
     * [--limit=<limit>]
     * : Number of jobs to show
     * ---
     * default: 20
     * ---
     *
     * [--format=<format>]
     * : Output format: table, json, csv
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     wp awm jobs list
     *     wp awm jobs list --status=completed
     *     wp awm jobs list --type=push --format=json
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function list_jobs(array $args, array $assoc_args): void {
        $filters = [
            'limit' => (int) \WP_CLI\Utils\get_flag_value($assoc_args, 'limit', 20),
        ];

        $status = \WP_CLI\Utils\get_flag_value($assoc_args, 'status', null);
        if ($status) {
            $filters['status'] = $status;
        }

        $type = \WP_CLI\Utils\get_flag_value($assoc_args, 'type', null);
        if ($type) {
            $filters['type'] = $type;
        }

        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        $jobs = $this->jobStore->getJobs($filters);

        if (empty($jobs)) {
            WP_CLI::log('No jobs found');
            return;
        }

        $this->displayJobs($jobs, $format);
    }

    /**
     * Create a database backup
     *
     * ## OPTIONS
     *
     * [--name=<name>]
     * : Backup name (default: auto-generated)
     *
     * ## EXAMPLES
     *
     *     wp awm backup create
     *     wp awm backup create --name=pre-deployment
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function backup(array $args, array $assoc_args): void {
        $name = \WP_CLI\Utils\get_flag_value($assoc_args, 'name', 'manual_backup');

        WP_CLI::log("Creating backup: {$name}...");

        $result = $this->backupService->createBackup($name);

        if ($result['success']) {
            WP_CLI::success("Backup created: {$result['backup']['name']}");
            WP_CLI::log("Size: " . size_format($result['backup']['size']));
        } else {
            WP_CLI::error("Backup failed: {$result['message']}");
        }
    }

    /**
     * List available backups
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format: table, json
     * ---
     * default: table
     * ---
     *
     * ## EXAMPLES
     *
     *     wp awm backup list
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function backup_list(array $args, array $assoc_args): void {
        $format = \WP_CLI\Utils\get_flag_value($assoc_args, 'format', 'table');

        $backups = $this->backupService->getBackups();

        if (empty($backups)) {
            WP_CLI::log('No backups found');
            return;
        }

        $this->displayBackups($backups, $format);
    }

    /**
     * Restore from backup
     *
     * ## OPTIONS
     *
     * --name=<name>
     * : Backup name to restore
     *
     * [--yes]
     * : Skip confirmation
     *
     * ## EXAMPLES
     *
     *     wp awm backup restore --name=backup_1234567890.sql
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function backup_restore(array $args, array $assoc_args): void {
        $name = \WP_CLI\Utils\get_flag_value($assoc_args, 'name');

        if (empty($name)) {
            WP_CLI::error('Missing required --name argument');
        }

        if (!\WP_CLI\Utils\get_flag_value($assoc_args, 'yes', false)) {
            WP_CLI::confirm("Are you sure you want to restore from backup '{$name}'? This will overwrite your database.");
        }

        WP_CLI::log("Restoring from backup: {$name}...");

        $result = $this->backupService->restoreBackup($name);

        if ($result['success']) {
            WP_CLI::success("Restore completed successfully");
        } else {
            WP_CLI::error("Restore failed: {$result['message']}");
        }
    }

    /**
     * Parse comma-separated table list
     *
     * @param string $list
     * @return array
     */
    private function parseTableList(string $list): array {
        if (empty($list)) {
            return [];
        }
        return array_map('trim', explode(',', $list));
    }

    /**
     * Parse replacement pairs
     *
     * @param array $replacements
     * @return array
     */
    private function parseReplacements(array $replacements): array {
        $parsed = [];

        foreach ($replacements as $replacement) {
            $parts = explode(',', $replacement, 2);
            
            if (count($parts) === 2) {
                $parsed[] = [
                    'search' => $parts[0],
                    'replace' => $parts[1],
                ];
            }
        }

        return $parsed;
    }

    /**
     * Get connection by ID
     *
     * @param string $id
     * @return array|null
     */
    private function getConnection(string $id): ?array {
        $connections = get_option('awm_connections', []);

        foreach ($connections as $connection) {
            if ($connection['id'] === $id || $connection['name'] === $id) {
                return $connection;
            }
        }

        return null;
    }

    /**
     * Display single job
     *
     * @param object $job
     * @param string $format
     */
    private function displayJob(object $job, string $format): void {
        $progress = $this->jobStore->getJobProgress($job->id);

        if ($format === 'json') {
            WP_CLI::line(json_encode([
                'job' => $job,
                'progress' => $progress,
            ], JSON_PRETTY_PRINT));
        } else {
            WP_CLI::log("Job ID: {$job->id}");
            WP_CLI::log("Type: {$job->type}");
            WP_CLI::log("Status: {$job->status}");
            WP_CLI::log("Created: {$job->created_at}");
            WP_CLI::log("Progress: {$progress['percentage']}%");
        }
    }

    /**
     * Display jobs list
     *
     * @param array $jobs
     * @param string $format
     */
    private function displayJobs(array $jobs, string $format): void {
        if ($format === 'json') {
            WP_CLI::line(json_encode($jobs, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            foreach ($jobs as $job) {
                WP_CLI::line("{$job->id},{$job->type},{$job->status},{$job->created_at}");
            }
        } else {
            \WP_CLI\Utils\format_items('table', $jobs, ['id', 'type', 'status', 'created_at']);
        }
    }

    /**
     * Display backups list
     *
     * @param array $backups
     * @param string $format
     */
    private function displayBackups(array $backups, string $format): void {
        if ($format === 'json') {
            WP_CLI::line(json_encode($backups, JSON_PRETTY_PRINT));
        } else {
            \WP_CLI\Utils\format_items('table', $backups, ['name', 'created_at', 'size']);
        }
    }

    /**
     * Display dry run results
     *
     * @param array $result
     */
    private function displayDryRunResults(array $result): void {
        if (isset($result['data'])) {
            $data = $result['data'];
            
            if (isset($data['tables'])) {
                WP_CLI::log("\nTables to be migrated:");
                foreach ($data['tables'] as $table => $rows) {
                    WP_CLI::log("  - {$table}: {$rows} rows");
                }
            }

            if (isset($data['total_rows'])) {
                WP_CLI::log("\nTotal rows: {$data['total_rows']}");
            }
        }
    }
}
