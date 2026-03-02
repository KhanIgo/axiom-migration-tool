<?php
/**
 * Migration Engine
 * 
 * Orchestrates database migration operations
 */

namespace Axiom\WPMigrate\Application;

use Axiom\WPMigrate\Infrastructure\JobStore;
use Axiom\WPMigrate\Infrastructure\AuditLogger;
use Axiom\WPMigrate\Domain\BackupService;

if (!defined('ABSPATH')) {
    exit;
}

class MigrationEngine {
    
    /**
     * Job states
     */
    const STATE_CREATED = 'created';
    const STATE_RUNNING = 'running';
    const STATE_PAUSED = 'paused';
    const STATE_FAILED = 'failed';
    const STATE_COMPLETED = 'completed';

    /**
     * Migration types
     */
    const TYPE_PUSH = 'push';
    const TYPE_PULL = 'pull';
    const TYPE_EXPORT = 'export';
    const TYPE_IMPORT = 'import';

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
     * Replace engine instance
     *
     * @var ReplaceEngine
     */
    private $replaceEngine;

    /**
     * Constructor
     *
     * @param JobStore $jobStore
     * @param AuditLogger $logger
     * @param BackupService $backupService
     */
    public function __construct(
        JobStore $jobStore,
        AuditLogger $logger,
        BackupService $backupService
    ) {
        $this->jobStore = $jobStore;
        $this->logger = $logger;
        $this->backupService = $backupService;
        $this->replaceEngine = new ReplaceEngine();
    }

    /**
     * Create a new migration job
     *
     * @param string $type
     * @param array $options
     * @return int Job ID
     */
    public function createJob(string $type, array $options = []): int {
        $jobId = $this->jobStore->createJob([
            'type' => $type,
            'status' => self::STATE_CREATED,
            'source_env' => $options['source_env'] ?? null,
            'target_env' => $options['target_env'] ?? null,
            'created_by' => get_current_user_id(),
            'meta_json' => json_encode($options),
        ]);

        $this->logger->info('job_created', 'Migration job created', [
            'job_id' => $jobId,
            'type' => $type,
        ]);

        return $jobId;
    }

    /**
     * Run migration job
     *
     * @param int $jobId
     * @param bool $dryRun
     * @return array Result
     */
    public function runJob(int $jobId, bool $dryRun = false): array {
        $job = $this->jobStore->getJob($jobId);
        
        if (!$job) {
            return ['success' => false, 'message' => 'Job not found'];
        }

        $this->jobStore->updateJobStatus($jobId, self::STATE_RUNNING);

        try {
            // Create backup if required
            if (!$dryRun && $this->shouldCreateBackup()) {
                $this->logger->info('backup_start', 'Creating pre-migration backup', ['job_id' => $jobId]);
                $backupResult = $this->backupService->createBackup('pre_migration_' . $jobId);
                if (!$backupResult['success']) {
                    throw new \Exception('Backup failed: ' . $backupResult['message']);
                }
            }

            // Execute migration based on type
            $result = $this->executeMigration($jobId, $job, $dryRun);

            if ($result['success']) {
                $this->jobStore->updateJobStatus($jobId, self::STATE_COMPLETED);
                $this->logger->info('job_completed', 'Migration job completed', ['job_id' => $jobId]);
            } else {
                throw new \Exception($result['message']);
            }

            return $result;

        } catch (\Exception $e) {
            $this->jobStore->updateJobStatus($jobId, self::STATE_FAILED);
            $this->logger->error('job_failed', 'Migration job failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Execute migration based on job type
     *
     * @param int $jobId
     * @param object $job
     * @param bool $dryRun
     * @return array
     */
    private function executeMigration(int $jobId, object $job, bool $dryRun): array {
        $meta = json_decode($job->meta_json, true);

        switch ($job->type) {
            case self::TYPE_EXPORT:
                return $this->executeExport($jobId, $meta, $dryRun);
            
            case self::TYPE_IMPORT:
                return $this->executeImport($jobId, $meta, $dryRun);
            
            case self::TYPE_PUSH:
                return $this->executePush($jobId, $meta, $dryRun);
            
            case self::TYPE_PULL:
                return $this->executePull($jobId, $meta, $dryRun);
            
            default:
                return ['success' => false, 'message' => 'Unknown migration type'];
        }
    }

    /**
     * Execute export migration
     *
     * @param int $jobId
     * @param array $meta
     * @param bool $dryRun
     * @return array
     */
    private function executeExport(int $jobId, array $meta, bool $dryRun): array {
        global $wpdb;

        $tables = $this->getTablesToMigrate($meta);
        $result = ['tables' => [], 'total_rows' => 0, 'sql_size' => 0];

        foreach ($tables as $table) {
            $this->jobStore->createJobStep($jobId, 'export_' . $table);
            
            $rowCount = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $result['tables'][$table] = $rowCount;
            $result['total_rows'] += $rowCount;

            if ($dryRun) {
                continue;
            }

            // Export table data
            $this->exportTable($table, $jobId);
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Execute import migration
     *
     * @param int $jobId
     * @param array $meta
     * @param bool $dryRun
     * @return array
     */
    private function executeImport(int $jobId, array $meta, bool $dryRun): array {
        $file = $meta['file'] ?? null;
        
        if (!$file || !file_exists($file)) {
            return ['success' => false, 'message' => 'SQL file not found'];
        }

        $sql = file_get_contents($file);
        
        // Apply replacements
        $sql = $this->applyReplacementsToSQL($sql, $meta);

        if ($dryRun) {
            return $this->analyzeSQL($sql);
        }

        // Execute SQL
        return $this->executeSQL($sql, $jobId);
    }

    /**
     * Execute push migration
     *
     * @param int $jobId
     * @param array $meta
     * @param bool $dryRun
     * @return array
     */
    private function executePush(int $jobId, array $meta, bool $dryRun): array {
        // Push implementation using TransportClient
        $client = new \Axiom\WPMigrate\Transport\TransportClient();
        return $client->push($jobId, $meta, $dryRun);
    }

    /**
     * Execute pull migration
     *
     * @param int $jobId
     * @param array $meta
     * @param bool $dryRun
     * @return array
     */
    private function executePull(int $jobId, array $meta, bool $dryRun): array {
        // Pull implementation using TransportClient
        $client = new \Axiom\WPMigrate\Transport\TransportClient();
        return $client->pull($jobId, $meta, $dryRun);
    }

    /**
     * Get tables to migrate based on filters
     *
     * @param array $meta
     * @return array
     */
    private function getTablesToMigrate(array $meta): array {
        global $wpdb;
        
        $allTables = $wpdb->get_col('SHOW TABLES');
        
        // Apply include filter
        if (!empty($meta['include_tables'])) {
            $includeTables = array_map(function($table) use ($wpdb) {
                return $wpdb->prefix . $table;
            }, $meta['include_tables']);
            $allTables = array_intersect($allTables, $includeTables);
        }

        // Apply exclude filter
        if (!empty($meta['exclude_tables'])) {
            $excludeTables = array_map(function($table) use ($wpdb) {
                return $wpdb->prefix . $table;
            }, $meta['exclude_tables']);
            $allTables = array_diff($allTables, $excludeTables);
        }

        // Apply presets
        if (!empty($meta['preset'])) {
            switch ($meta['preset']) {
                case 'content_only':
                    $allTables = array_filter($allTables, function($table) use ($wpdb) {
                        return strpos($table, $wpdb->prefix . 'wp_') === 0 
                            || strpos($table, $wpdb->prefix . 'posts') !== false
                            || strpos($table, $wpdb->prefix . 'postmeta') !== false
                            || strpos($table, $wpdb->prefix . 'terms') !== false;
                    });
                    break;
                
                case 'no_users':
                    $allTables = array_filter($allTables, function($table) use ($wpdb) {
                        return strpos($table, $wpdb->prefix . 'users') === false
                            && strpos($table, $wpdb->prefix . 'usermeta') === false;
                    });
                    break;
            }
        }

        return array_values($allTables);
    }

    /**
     * Export single table
     *
     * @param string $table
     * @param int $jobId
     */
    private function exportTable(string $table, int $jobId): void {
        // Implementation for table export
    }

    /**
     * Apply replacements to SQL
     *
     * @param string $sql
     * @param array $meta
     * @return string
     */
    private function applyReplacementsToSQL(string $sql, array $meta): string {
        $replacements = $meta['replacements'] ?? [];
        
        foreach ($replacements as $rule) {
            $sql = str_replace($rule['search'], $rule['replace'], $sql);
        }

        return $sql;
    }

    /**
     * Analyze SQL for dry run
     *
     * @param string $sql
     * @return array
     */
    private function analyzeSQL(string $sql): array {
        $lines = explode("\n", $sql);
        $tables = [];
        $currentTable = null;

        foreach ($lines as $line) {
            if (preg_match('/CREATE TABLE.*`?(\w+)`?\s*\(/i', $line, $matches)) {
                $currentTable = $matches[1];
                $tables[$currentTable] = ['rows' => 0, 'size' => strlen($line)];
            } elseif (preg_match('/INSERT INTO.*`?(\w+)`?\s*/i', $line, $matches)) {
                $table = $matches[1];
                if (isset($tables[$table])) {
                    $tables[$table]['rows']++;
                }
                $tables[$table]['size'] += strlen($line);
            }
        }

        return [
            'success' => true,
            'dry_run' => true,
            'tables' => $tables,
            'total_size' => array_sum(array_column($tables, 'size')),
        ];
    }

    /**
     * Execute SQL
     *
     * @param string $sql
     * @param int $jobId
     * @return array
     */
    private function executeSQL(string $sql, int $jobId): array {
        global $wpdb;

        $queries = array_filter(array_map('trim', explode(';', $sql)));
        $executed = 0;
        $errors = [];

        foreach ($queries as $query) {
            if (empty($query)) {
                continue;
            }

            $result = $wpdb->query($query);
            
            if ($result === false) {
                $errors[] = $wpdb->last_error;
            } else {
                $executed++;
            }
        }

        return [
            'success' => empty($errors),
            'queries_executed' => $executed,
            'errors' => $errors,
        ];
    }

    /**
     * Check if backup should be created
     *
     * @return bool
     */
    private function shouldCreateBackup(): bool {
        $settings = get_option('awm_settings', []);
        return !empty($settings['require_backup']);
    }

    /**
     * Set replacement rules
     *
     * @param array $rules
     */
    public function setReplacementRules(array $rules): void {
        $this->replaceEngine = new ReplaceEngine($rules);
    }
}
