<?php
/**
 * Backup Service
 * 
 * Handles database backup operations
 */

namespace Axiom\WPMigrate\Domain;

use Axiom\WPMigrate\Infrastructure\AuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

class BackupService {
    
    /**
     * Backup directory
     *
     * @var string
     */
    private $backupDir;

    /**
     * Audit logger
     *
     * @var AuditLogger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param AuditLogger|null $logger
     */
    public function __construct(?AuditLogger $logger = null) {
        $this->backupDir = wp_upload_dir()['basedir'] . '/awm-backups/';
        $this->logger = $logger ?? new AuditLogger();
        
        // Ensure backup directory exists
        wp_mkdir_p($this->backupDir);
    }

    /**
     * Create database backup
     *
     * @param string $name
     * @param array $options
     * @return array
     */
    public function createBackup(string $name, array $options = []): array {
        global $wpdb;

        $name = sanitize_file_name($name . '_' . time() . '.sql');
        $filePath = $this->backupDir . $name;

        try {
            $tables = $options['tables'] ?? $this->getAllTables();
            $sql = $this->exportDatabase($tables);

            if (file_put_contents($filePath, $sql) === false) {
                throw new \Exception('Failed to write backup file');
            }

            $backupMeta = [
                'name' => $name,
                'path' => $filePath,
                'size' => filesize($filePath),
                'tables' => $tables,
                'created_at' => current_time('mysql'),
                'created_by' => get_current_user_id(),
            ];

            // Store backup metadata
            $this->storeBackupMeta($backupMeta);

            $this->logger->info('backup_created', 'Database backup created', [
                'name' => $name,
                'size' => $backupMeta['size'],
            ]);

            // Apply retention policy
            $this->applyRetentionPolicy();

            return [
                'success' => true,
                'backup' => $backupMeta,
            ];

        } catch (\Exception $e) {
            $this->logger->error('backup_failed', 'Database backup failed', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Restore from backup
     *
     * @param string $backupName
     * @return array
     */
    public function restoreBackup(string $backupName): array {
        $meta = $this->getBackupMeta($backupName);

        if (!$meta || !file_exists($meta['path'])) {
            return [
                'success' => false,
                'message' => 'Backup not found',
            ];
        }

        try {
            $sql = file_get_contents($meta['path']);
            
            if ($sql === false) {
                throw new \Exception('Failed to read backup file');
            }

            $this->logger->info('restore_started', 'Database restore started', [
                'backup' => $backupName,
            ]);

            // Execute SQL
            $result = $this->executeSQL($sql);

            if ($result['success']) {
                $this->logger->info('restore_completed', 'Database restore completed', [
                    'backup' => $backupName,
                    'queries' => $result['queries_executed'],
                ]);
            } else {
                throw new \Exception('SQL execution failed: ' . implode(', ', $result['errors']));
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('restore_failed', 'Database restore failed', [
                'backup' => $backupName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get all backups
     *
     * @return array
     */
    public function getBackups(): array {
        $backups = get_option('awm_backups', []);
        
        // Add file existence check and size
        foreach ($backups as &$backup) {
            if (file_exists($backup['path'])) {
                $backup['exists'] = true;
                $backup['size'] = filesize($backup['path']);
            } else {
                $backup['exists'] = false;
            }
        }

        return array_reverse($backups);
    }

    /**
     * Delete backup
     *
     * @param string $backupName
     * @return bool
     */
    public function deleteBackup(string $backupName): bool {
        $meta = $this->getBackupMeta($backupName);

        if ($meta && file_exists($meta['path'])) {
            unlink($meta['path']);
        }

        // Remove from metadata
        $backups = get_option('awm_backups', []);
        $backups = array_filter($backups, function($backup) use ($backupName) {
            return $backup['name'] !== $backupName;
        });

        update_option('awm_backups', array_values($backups));

        $this->logger->info('backup_deleted', 'Backup deleted', ['name' => $backupName]);

        return true;
    }

    /**
     * Export database to SQL
     *
     * @param array $tables
     * @return string
     */
    private function exportDatabase(array $tables): string {
        global $wpdb;

        $sql = "-- Axiom WP Migrate Backup\n";
        $sql .= "-- Generated: " . current_time('mysql') . "\n";
        $sql .= "-- Site: " . get_site_url() . "\n\n";

        foreach ($tables as $table) {
            $sql .= $this->exportTable($table);
        }

        return $sql;
    }

    /**
     * Export single table
     *
     * @param string $table
     * @return string
     */
    private function exportTable(string $table): string {
        global $wpdb;

        $sql = "-- Table: {$table}\n";

        // Get CREATE TABLE statement
        $create = $wpdb->get_row("SHOW CREATE TABLE {$table}");
        if ($create) {
            $sql .= $create->{'Create Table'} . ";\n\n";
        }

        // Get table data
        $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);

        if (!empty($rows)) {
            foreach ($rows as $row) {
                $sql .= $this->buildInsertStatement($table, $row);
            }
            $sql .= "\n";
        }

        return $sql;
    }

    /**
     * Build INSERT statement
     *
     * @param string $table
     * @param array $row
     * @return string
     */
    private function buildInsertStatement(string $table, array $row): string {
        global $wpdb;

        $columns = implode(', ', array_map([$wpdb, 'escape_identifier'], array_keys($row)));
        $values = [];

        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $wpdb->prepare('%s', $value);
            }
        }

        return "INSERT INTO {$table} ({$columns}) VALUES (" . implode(', ', $values) . ");\n";
    }

    /**
     * Execute SQL
     *
     * @param string $sql
     * @return array
     */
    private function executeSQL(string $sql): array {
        global $wpdb;

        $queries = array_filter(array_map('trim', explode(';', $sql)));
        $executed = 0;
        $errors = [];

        foreach ($queries as $query) {
            if (empty($query) || strpos($query, '--') === 0) {
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
     * Get all WordPress tables
     *
     * @return array
     */
    private function getAllTables(): array {
        global $wpdb;
        return $wpdb->get_col('SHOW TABLES');
    }

    /**
     * Store backup metadata
     *
     * @param array $meta
     */
    private function storeBackupMeta(array $meta): void {
        $backups = get_option('awm_backups', []);
        $backups[] = $meta;
        update_option('awm_backups', $backups);
    }

    /**
     * Get backup metadata
     *
     * @param string $backupName
     * @return array|null
     */
    private function getBackupMeta(string $backupName): ?array {
        $backups = get_option('awm_backups', []);
        
        foreach ($backups as $backup) {
            if ($backup['name'] === $backupName) {
                return $backup;
            }
        }

        return null;
    }

    /**
     * Apply retention policy
     */
    private function applyRetentionPolicy(): void {
        $settings = get_option('awm_settings', []);
        $retentionDays = $settings['backup_retention_days'] ?? 14;
        $retentionCount = $settings['backup_retention_count'] ?? 3;

        $backups = get_option('awm_backups', []);

        // Remove backups older than retention days
        $cutoffTime = strtotime("-{$retentionDays} days");
        
        foreach ($backups as $key => $backup) {
            $backupTime = strtotime($backup['created_at']);
            
            if ($backupTime < $cutoffTime) {
                $this->deleteBackup($backup['name']);
                unset($backups[$key]);
            }
        }

        // Keep only the most recent backups based on count
        if (count($backups) > $retentionCount) {
            usort($backups, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            $backupsToKeep = array_slice($backups, 0, $retentionCount);
            $backupsToDelete = array_slice($backups, $retentionCount);

            foreach ($backupsToDelete as $backup) {
                $this->deleteBackup($backup['name']);
            }

            update_option('awm_backups', $backupsToKeep);
        }
    }
}
