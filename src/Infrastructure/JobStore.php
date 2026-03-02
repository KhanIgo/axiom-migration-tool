<?php
/**
 * Job Store
 * 
 * Handles persistence of migration jobs
 */

namespace Axiom\WPMigrate\Infrastructure;

if (!defined('ABSPATH')) {
    exit;
}

class JobStore {
    
    /**
     * Table name
     *
     * @var string
     */
    private $table;

    /**
     * Steps table name
     *
     * @var string
     */
    private $stepsTable;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'awm_jobs';
        $this->stepsTable = $wpdb->prefix . 'awm_job_steps';
    }

    /**
     * Create a new job
     *
     * @param array $data
     * @return int Job ID
     */
    public function createJob(array $data): int {
        global $wpdb;

        $wpdb->insert($this->table, [
            'type' => $data['type'],
            'status' => $data['status'] ?? 'created',
            'source_env' => $data['source_env'] ?? null,
            'target_env' => $data['target_env'] ?? null,
            'created_by' => $data['created_by'] ?? get_current_user_id(),
            'meta_json' => $data['meta_json'] ?? null,
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Get job by ID
     *
     * @param int $jobId
     * @return object|null
     */
    public function getJob(int $jobId): ?object {
        global $wpdb;

        $job = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d",
            $jobId
        ));

        return $job ?: null;
    }

    /**
     * Get all jobs
     *
     * @param array $filters
     * @return array
     */
    public function getJobs(array $filters = []): array {
        global $wpdb;

        $where = ['1=1'];
        $values = [];

        if (!empty($filters['status'])) {
            $where[] = 'status = %s';
            $values[] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'type = %s';
            $values[] = $filters['type'];
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT %d';
            $values[] = $filters['limit'];
        }

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Update job status
     *
     * @param int $jobId
     * @param string $status
     * @param array $meta
     * @return bool
     */
    public function updateJobStatus(int $jobId, string $status, array $meta = []): bool {
        global $wpdb;

        $data = ['status' => $status];
        
        if (!empty($meta)) {
            $job = $this->getJob($jobId);
            if ($job) {
                $currentMeta = json_decode($job->meta_json, true) ?: [];
                $data['meta_json'] = json_encode(array_merge($currentMeta, $meta));
            }
        }

        return $wpdb->update(
            $this->table,
            $data,
            ['id' => $jobId]
        ) !== false;
    }

    /**
     * Create job step
     *
     * @param int $jobId
     * @param string $stepName
     * @return int Step ID
     */
    public function createJobStep(int $jobId, string $stepName): int {
        global $wpdb;

        $wpdb->insert($this->stepsTable, [
            'job_id' => $jobId,
            'step_name' => $stepName,
            'step_status' => 'pending',
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Get job steps
     *
     * @param int $jobId
     * @return array
     */
    public function getJobSteps(int $jobId): array {
        global $wpdb;

        $steps = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->stepsTable} WHERE job_id = %d ORDER BY id ASC",
            $jobId
        ));

        return $steps ?: [];
    }

    /**
     * Update job step
     *
     * @param int $stepId
     * @param array $data
     * @return bool
     */
    public function updateJobStep(int $stepId, array $data): bool {
        global $wpdb;

        return $wpdb->update(
            $this->stepsTable,
            $data,
            ['id' => $stepId]
        ) !== false;
    }

    /**
     * Update job step status
     *
     * @param int $stepId
     * @param string $status
     * @param array $checkpoint
     * @return bool
     */
    public function updateStepStatus(int $stepId, string $status, array $checkpoint = []): bool {
        global $wpdb;

        $data = [
            'step_status' => $status,
        ];

        if ($status === 'running' && empty($data['started_at'])) {
            $data['started_at'] = current_time('mysql');
        }

        if (in_array($status, ['completed', 'failed'])) {
            $data['finished_at'] = current_time('mysql');
        }

        if (!empty($checkpoint)) {
            $data['checkpoint_json'] = json_encode($checkpoint);
        }

        return $wpdb->update(
            $this->stepsTable,
            $data,
            ['id' => $stepId]
        ) !== false;
    }

    /**
     * Delete job
     *
     * @param int $jobId
     * @return bool
     */
    public function deleteJob(int $jobId): bool {
        global $wpdb;

        // Delete steps first
        $wpdb->delete($this->stepsTable, ['job_id' => $jobId]);
        
        // Delete job
        return $wpdb->delete($this->table, ['id' => $jobId]) !== false;
    }

    /**
     * Get job progress
     *
     * @param int $jobId
     * @return array
     */
    public function getJobProgress(int $jobId): array {
        $steps = $this->getJobSteps($jobId);
        
        $total = count($steps);
        $completed = 0;
        $stepProgress = [];

        foreach ($steps as $step) {
            $stepProgress[] = [
                'name' => $step->step_name,
                'status' => $step->step_status,
                'started_at' => $step->started_at,
                'finished_at' => $step->finished_at,
            ];

            if ($step->step_status === 'completed') {
                $completed++;
            }
        }

        return [
            'total_steps' => $total,
            'completed_steps' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
            'steps' => $stepProgress,
        ];
    }
}
