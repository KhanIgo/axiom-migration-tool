<?php
/**
 * Audit Logger
 * 
 * Centralized structured logging for migration operations
 */

namespace Axiom\WPMigrate\Infrastructure;

if (!defined('ABSPATH')) {
    exit;
}

class AuditLogger {
    
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Table name
     *
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'awm_logs';
    }

    /**
     * Log debug message
     *
     * @param string $action
     * @param string $message
     * @param array $context
     * @param int|null $jobId
     */
    public function debug(string $action, string $message, array $context = [], ?int $jobId = null): void {
        $this->log(self::LEVEL_DEBUG, $action, $message, $context, $jobId);
    }

    /**
     * Log info message
     *
     * @param string $action
     * @param string $message
     * @param array $context
     * @param int|null $jobId
     */
    public function info(string $action, string $message, array $context = [], ?int $jobId = null): void {
        $this->log(self::LEVEL_INFO, $action, $message, $context, $jobId);
    }

    /**
     * Log warning message
     *
     * @param string $action
     * @param string $message
     * @param array $context
     * @param int|null $jobId
     */
    public function warning(string $action, string $message, array $context = [], ?int $jobId = null): void {
        $this->log(self::LEVEL_WARNING, $action, $message, $context, $jobId);
    }

    /**
     * Log error message
     *
     * @param string $action
     * @param string $message
     * @param array $context
     * @param int|null $jobId
     */
    public function error(string $action, string $message, array $context = [], ?int $jobId = null): void {
        $this->log(self::LEVEL_ERROR, $action, $message, $context, $jobId);
    }

    /**
     * Log critical message
     *
     * @param string $action
     * @param string $message
     * @param array $context
     * @param int|null $jobId
     */
    public function critical(string $action, string $message, array $context = [], ?int $jobId = null): void {
        $this->log(self::LEVEL_CRITICAL, $action, $message, $context, $jobId);
    }

    /**
     * Write log entry
     *
     * @param string $level
     * @param string $action
     * @param string $message
     * @param array $context
     * @param int|null $jobId
     */
    private function log(string $level, string $action, string $message, array $context = [], ?int $jobId = null): void {
        global $wpdb;

        $wpdb->insert($this->table, [
            'job_id' => $jobId,
            'level' => $level,
            'action' => $action,
            'message' => $message,
            'context_json' => !empty($context) ? json_encode($context) : null,
        ]);
    }

    /**
     * Get logs
     *
     * @param array $filters
     * @return array
     */
    public function getLogs(array $filters = []): array {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (isset($filters['job_id'])) {
            $where[] = 'job_id = %d';
            $values[] = (int) $filters['job_id'];
        }

        if (!empty($filters['level'])) {
            $where[] = 'level = %s';
            $values[] = $filters['level'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = %s';
            $values[] = $filters['action'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $values[] = $filters['date_to'];
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT %d';
            $values[] = (int) $filters['limit'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Get logs for job
     *
     * @param int $jobId
     * @param string|null $level
     * @return array
     */
    public function getJobLogs(int $jobId, ?string $level = null): array {
        $filters = ['job_id' => $jobId];
        
        if ($level) {
            $filters['level'] = $level;
        }

        return $this->getLogs($filters);
    }

    /**
     * Get error logs
     *
     * @param int $limit
     * @return array
     */
    public function getErrorLogs(int $limit = 100): array {
        return $this->getLogs([
            'level' => self::LEVEL_ERROR,
            'limit' => $limit,
        ]);
    }

    /**
     * Clear old logs
     *
     * @param int $daysToKeep
     * @return bool
     */
    public function clearOldLogs(int $daysToKeep = 30): bool {
        global $wpdb;

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table} WHERE created_at < %s",
            $cutoff
        )) !== false;
    }

    /**
     * Export logs to array
     *
     * @param array $filters
     * @return array
     */
    public function exportLogs(array $filters = []): array {
        $logs = $this->getLogs($filters);
        $export = [];

        foreach ($logs as $log) {
            $export[] = [
                'id' => $log->id,
                'job_id' => $log->job_id,
                'level' => $log->level,
                'action' => $log->action,
                'message' => $log->message,
                'context' => $log->context_json ? json_decode($log->context_json, true) : [],
                'created_at' => $log->created_at,
            ];
        }

        return $export;
    }

    /**
     * Export logs to CSV
     *
     * @param array $filters
     * @param string $filePath
     * @return bool
     */
    public function exportToCSV(array $filters, string $filePath): bool {
        $logs = $this->exportLogs($filters);

        if (empty($logs)) {
            return false;
        }

        $handle = fopen($filePath, 'w');
        
        if ($handle === false) {
            return false;
        }

        // Write header
        fputcsv($handle, ['ID', 'Job ID', 'Level', 'Action', 'Message', 'Context', 'Created At']);

        // Write rows
        foreach ($logs as $log) {
            fputcsv($handle, [
                $log['id'],
                $log['job_id'] ?? '',
                $log['level'],
                $log['action'],
                $log['message'],
                json_encode($log['context']),
                $log['created_at'],
            ]);
        }

        fclose($handle);
        return true;
    }
}
