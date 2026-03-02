<?php
/**
 * Transport Server
 * 
 * Handles incoming signed API requests
 */

namespace Axiom\WPMigrate\Transport;

use Axiom\WPMigrate\Infrastructure\AuditLogger;
use Axiom\WPMigrate\Application\MigrationEngine;
use Axiom\WPMigrate\Infrastructure\JobStore;

if (!defined('ABSPATH')) {
    exit;
}

class TransportServer {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'awm/v1';

    /**
     * Nonce cache TTL (seconds)
     */
    const NONCE_TTL = 300;

    /**
     * Timestamp tolerance (seconds)
     */
    const TIMESTAMP_TOLERANCE = 300;

    /**
     * Audit logger
     *
     * @var AuditLogger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new AuditLogger();
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/handshake', [
            'methods' => 'POST',
            'callback' => [$this, 'handleHandshake'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/jobs', [
            'methods' => 'POST',
            'callback' => [$this, 'handleCreateJob'],
            'permission_callback' => [$this, 'verifyRequest'],
        ]);

        register_rest_route(self::NAMESPACE, '/jobs/(?P<id>\d+)/chunks', [
            'methods' => 'POST',
            'callback' => [$this, 'handleChunk'],
            'permission_callback' => [$this, 'verifyRequest'],
        ]);

        register_rest_route(self::NAMESPACE, '/jobs/(?P<id>\d+)/apply', [
            'methods' => 'POST',
            'callback' => [$this, 'handleApply'],
            'permission_callback' => [$this, 'verifyRequest'],
        ]);

        register_rest_route(self::NAMESPACE, '/jobs/(?P<id>\d+)/status', [
            'methods' => 'GET',
            'callback' => [$this, 'handleStatus'],
            'permission_callback' => [$this, 'verifyRequest'],
        ]);

        register_rest_route(self::NAMESPACE, '/jobs/(?P<id>\d+)/cancel', [
            'methods' => 'POST',
            'callback' => [$this, 'handleCancel'],
            'permission_callback' => [$this, 'verifyRequest'],
        ]);
    }

    /**
     * Handle handshake request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleHandshake(\WP_REST_Request $request): \WP_REST_Response {
        $keyId = $request->get_header('X-AWM-Key-Id');
        
        if (!$keyId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Missing key ID',
            ], 400);
        }

        // Validate connection exists
        $connections = get_option('awm_connections', []);
        $connectionFound = false;

        foreach ($connections as $connection) {
            if ($connection['key_id'] === $keyId) {
                $connectionFound = true;
                break;
            }
        }

        if (!$connectionFound) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Invalid key ID',
            ], 401);
        }

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Handshake successful',
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
            'timestamp' => time(),
        ]);
    }

    /**
     * Handle job creation
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleCreateJob(\WP_REST_Request $request): \WP_REST_Response {
        $jobStore = new JobStore();
        $logger = new AuditLogger();

        $params = $request->get_json_params();
        
        $jobId = $jobStore->createJob([
            'type' => $params['type'] ?? 'import',
            'status' => 'created',
            'source_env' => $params['source_env'] ?? null,
            'target_env' => $params['target_env'] ?? null,
            'created_by' => get_current_user_id(),
            'meta_json' => json_encode($params['meta'] ?? []),
        ]);

        $logger->info('remote_job_created', 'Remote job created', [
            'job_id' => $jobId,
            'type' => $params['type'] ?? 'import',
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'job_id' => $jobId,
        ]);
    }

    /**
     * Handle chunk upload
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleChunk(\WP_REST_Request $request): \WP_REST_Response {
        $jobId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        // Store chunk data
        $chunkData = $params['chunk'] ?? [];
        $chunkIndex = $params['index'] ?? 0;
        
        // Process chunk
        $jobStore = new JobStore();
        $stepId = $jobStore->createJobStep($jobId, 'chunk_' . $chunkIndex);

        $this->logger->info('chunk_received', 'Chunk received', [
            'job_id' => $jobId,
            'chunk_index' => $chunkIndex,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'chunk_index' => $chunkIndex,
        ]);
    }

    /**
     * Handle job apply
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleApply(\WP_REST_Request $request): \WP_REST_Response {
        $jobId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        $jobStore = new JobStore();
        $job = $jobStore->getJob($jobId);

        if (!$job) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }

        // Apply the migration
        $engine = new MigrationEngine($jobStore, $this->logger, new \Axiom\WPMigrate\Domain\BackupService());
        $result = $engine->runJob($jobId, !empty($params['dry_run']));

        return new \WP_REST_Response($result);
    }

    /**
     * Handle job status request
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleStatus(\WP_REST_Request $request): \WP_REST_Response {
        $jobId = (int) $request->get_param('id');

        $jobStore = new JobStore();
        $job = $jobStore->getJob($jobId);

        if (!$job) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Job not found',
            ], 404);
        }

        $progress = $jobStore->getJobProgress($jobId);

        return new \WP_REST_Response([
            'success' => true,
            'job' => [
                'id' => $job->id,
                'type' => $job->type,
                'status' => $job->status,
                'created_at' => $job->created_at,
                'updated_at' => $job->updated_at,
            ],
            'progress' => $progress,
        ]);
    }

    /**
     * Handle job cancel
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleCancel(\WP_REST_Request $request): \WP_REST_Response {
        $jobId = (int) $request->get_param('id');

        $jobStore = new JobStore();
        $jobStore->updateJobStatus($jobId, 'failed', ['cancelled' => true]);

        $this->logger->info('job_cancelled', 'Job cancelled by remote request', [
            'job_id' => $jobId,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Job cancelled',
        ]);
    }

    /**
     * Verify request signature
     *
     * @param \WP_REST_Request $request
     * @return bool
     */
    public function verifyRequest(\WP_REST_Request $request): bool {
        $keyId = $request->get_header('X-AWM-Key-Id');
        $timestamp = (int) $request->get_header('X-AWM-Timestamp');
        $nonce = $request->get_header('X-AWM-Nonce');
        $signature = $request->get_header('X-AWM-Signature');

        // Validate required headers
        if (!$keyId || !$timestamp || !$nonce || !$signature) {
            $this->logger->error('auth_failed', 'Missing authentication headers');
            return false;
        }

        // Check timestamp tolerance
        $currentTime = time();
        if (abs($currentTime - $timestamp) > self::TIMESTAMP_TOLERANCE) {
            $this->logger->error('auth_failed', 'Timestamp outside tolerance', [
                'timestamp' => $timestamp,
                'current' => $currentTime,
            ]);
            return false;
        }

        // Check nonce replay
        if ($this->isNonceUsed($nonce)) {
            $this->logger->error('auth_failed', 'Nonce replay detected', ['nonce' => $nonce]);
            return false;
        }

        // Find connection and get secret
        $connections = get_option('awm_connections', []);
        $secret = null;

        foreach ($connections as $connection) {
            if ($connection['key_id'] === $keyId) {
                $secret = $this->getConnectionSecret($connection);
                break;
            }
        }

        if (!$secret) {
            $this->logger->error('auth_failed', 'Connection not found', ['key_id' => $keyId]);
            return false;
        }

        // Verify signature
        $bodyHash = hash('sha256', $request->get_body() ?: '');
        $signatureInput = $request->get_method() . $request->get_route() . $timestamp . $nonce . $bodyHash;
        $expectedSignature = hash_hmac('sha256', $signatureInput, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            $this->logger->error('auth_failed', 'Signature mismatch');
            return false;
        }

        // Mark nonce as used
        $this->markNonceAsUsed($nonce);

        return true;
    }

    /**
     * Get connection secret
     *
     * @param array $connection
     * @return string
     */
    private function getConnectionSecret(array $connection): string {
        // In production, this would retrieve the actual secret
        // For now, using a placeholder
        return wp_hash($connection['key_id'] . wp_salt());
    }

    /**
     * Check if nonce was already used
     *
     * @param string $nonce
     * @return bool
     */
    private function isNonceUsed(string $nonce): bool {
        $usedNonces = get_transient('awm_used_nonces');
        
        if ($usedNonces === false) {
            $usedNonces = [];
        }

        return in_array($nonce, $usedNonces);
    }

    /**
     * Mark nonce as used
     *
     * @param string $nonce
     */
    private function markNonceAsUsed(string $nonce): void {
        $usedNonces = get_transient('awm_used_nonces');
        
        if ($usedNonces === false) {
            $usedNonces = [];
        }

        $usedNonces[] = $nonce;
        
        // Keep only recent nonces
        if (count($usedNonces) > 1000) {
            $usedNonces = array_slice($usedNonces, -500);
        }

        set_transient('awm_used_nonces', $usedNonces, self::NONCE_TTL);
    }
}
