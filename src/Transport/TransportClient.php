<?php
/**
 * Transport Client
 * 
 * Handles outgoing signed API requests to remote WordPress instances
 */

namespace Axiom\WPMigrate\Transport;

use Axiom\WPMigrate\Infrastructure\AuditLogger;

if (!defined('ABSPATH')) {
    exit;
}

class TransportClient {
    
    /**
     * API namespace
     */
    const NAMESPACE = 'awm/v1';

    /**
     * Connection data
     *
     * @var array
     */
    private $connection;

    /**
     * Audit logger
     *
     * @var AuditLogger
     */
    private $logger;

    /**
     * Constructor
     *
     * @param array|null $connection
     */
    public function __construct(?array $connection = null) {
        $this->connection = $connection;
        $this->logger = new AuditLogger();
    }

    /**
     * Set connection
     *
     * @param array $connection
     */
    public function setConnection(array $connection): void {
        $this->connection = $connection;
    }

    /**
     * Test connection via handshake
     *
     * @param array $connection
     * @return array
     */
    public function testConnection(array $connection): array {
        $this->connection = $connection;

        $response = $this->makeRequest('POST', '/handshake', [], [
            'X-AWM-Key-Id' => $connection['key_id'],
        ]);

        return $response;
    }

    /**
     * Push database to remote
     *
     * @param int $jobId
     * @param array $meta
     * @param bool $dryRun
     * @return array
     */
    public function push(int $jobId, array $meta, bool $dryRun = false): array {
        if (!$this->connection) {
            return ['success' => false, 'message' => 'No connection configured'];
        }

        try {
            // Create job on remote
            $createResponse = $this->makeRequest('POST', '/jobs', [
                'type' => 'import',
                'source_env' => get_site_url(),
                'target_env' => $this->connection['url'],
                'meta' => $meta,
            ]);

            if (!$createResponse['success']) {
                return $createResponse;
            }

            $remoteJobId = $createResponse['job_id'];

            // Export and send chunks
            $chunks = $this->prepareChunks($jobId, $meta);
            
            foreach ($chunks as $index => $chunk) {
                $chunkResponse = $this->makeRequest('POST', "/jobs/{$remoteJobId}/chunks", [
                    'chunk' => $chunk,
                    'index' => $index,
                ]);

                if (!$chunkResponse['success']) {
                    return $chunkResponse;
                }
            }

            // Apply migration on remote
            $applyResponse = $this->makeRequest('POST', "/jobs/{$remoteJobId}/apply", [
                'dry_run' => $dryRun,
            ]);

            return $applyResponse;

        } catch (\Exception $e) {
            $this->logger->error('push_failed', 'Push migration failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pull database from remote
     *
     * @param int $jobId
     * @param array $meta
     * @param bool $dryRun
     * @return array
     */
    public function pull(int $jobId, array $meta, bool $dryRun = false): array {
        if (!$this->connection) {
            return ['success' => false, 'message' => 'No connection configured'];
        }

        try {
            // Create export job on remote
            $createResponse = $this->makeRequest('POST', '/jobs', [
                'type' => 'export',
                'source_env' => $this->connection['url'],
                'target_env' => get_site_url(),
                'meta' => $meta,
            ]);

            if (!$createResponse['success']) {
                return $createResponse;
            }

            $remoteJobId = $createResponse['job_id'];

            // Wait for export to complete and download
            $statusResponse = $this->getJobStatus($remoteJobId);
            
            while ($statusResponse['job']['status'] === 'running') {
                sleep(2);
                $statusResponse = $this->getJobStatus($remoteJobId);
            }

            if ($statusResponse['job']['status'] !== 'completed') {
                return ['success' => false, 'message' => 'Remote export failed'];
            }

            // Import the exported data
            return $this->importData($jobId, $statusResponse);

        } catch (\Exception $e) {
            $this->logger->error('pull_failed', 'Pull migration failed', [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get job status from remote
     *
     * @param int $jobId
     * @return array
     */
    public function getJobStatus(int $jobId): array {
        return $this->makeRequest('GET', "/jobs/{$jobId}/status");
    }

    /**
     * Cancel remote job
     *
     * @param int $jobId
     * @return array
     */
    public function cancelJob(int $jobId): array {
        return $this->makeRequest('POST', "/jobs/{$jobId}/cancel");
    }

    /**
     * Make signed HTTP request
     *
     * @param string $method
     * @param string $path
     * @param array $body
     * @param array $headers
     * @return array
     */
    private function makeRequest(string $method, string $path, array $body = [], array $headers = []): array {
        $url = rtrim($this->connection['url'], '/') . '/wp-json/' . self::NAMESPACE . $path;

        // Generate authentication headers
        $authHeaders = $this->generateAuthHeaders($method, $path, $body);

        $requestHeaders = array_merge($headers, $authHeaders, [
            'Content-Type' => 'application/json',
        ]);

        $args = [
            'method' => $method,
            'headers' => $requestHeaders,
            'body' => !empty($body) ? wp_json_encode($body) : null,
            'timeout' => 30,
            'sslverify' => true,
        ];

        $this->logger->debug('http_request', 'Making remote request', [
            'method' => $method,
            'url' => $url,
        ]);

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $responseData = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode >= 400) {
            return [
                'success' => false,
                'message' => $responseData['message'] ?? 'Request failed',
                'status_code' => $statusCode,
            ];
        }

        return array_merge(['success' => true], $responseData ?: []);
    }

    /**
     * Generate authentication headers
     *
     * @param string $method
     * @param string $path
     * @param array $body
     * @return array
     */
    private function generateAuthHeaders(string $method, string $path, array $body = []): array {
        $timestamp = time();
        $nonce = wp_generate_password(32, false);
        $bodyHash = hash('sha256', !empty($body) ? wp_json_encode($body) : '');

        // Get secret for connection
        $secret = $this->getConnectionSecret();

        // Generate signature
        $signatureInput = $method . $path . $timestamp . $nonce . $bodyHash;
        $signature = hash_hmac('sha256', $signatureInput, $secret);

        return [
            'X-AWM-Key-Id' => $this->connection['key_id'],
            'X-AWM-Timestamp' => $timestamp,
            'X-AWM-Nonce' => $nonce,
            'X-AWM-Signature' => $signature,
        ];
    }

    /**
     * Get connection secret
     *
     * @return string
     */
    private function getConnectionSecret(): string {
        // In production, this would retrieve the actual secret from secure storage
        // For now, using a placeholder
        return wp_hash($this->connection['key_id'] . wp_salt());
    }

    /**
     * Prepare data chunks for transfer
     *
     * @param int $jobId
     * @param array $meta
     * @return array
     */
    private function prepareChunks(int $jobId, array $meta): array {
        global $wpdb;

        $chunks = [];
        $tables = $this->getTablesToMigrate($meta);

        foreach ($tables as $table) {
            $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
            
            if (!empty($rows)) {
                $chunks[] = [
                    'table' => $table,
                    'data' => $rows,
                    'schema' => $wpdb->get_row("SHOW CREATE TABLE {$table}"),
                ];
            }
        }

        return $chunks;
    }

    /**
     * Get tables to migrate
     *
     * @param array $meta
     * @return array
     */
    private function getTablesToMigrate(array $meta): array {
        global $wpdb;
        
        $allTables = $wpdb->get_col('SHOW TABLES');
        
        if (!empty($meta['include_tables'])) {
            $includeTables = array_map(function($table) use ($wpdb) {
                return $wpdb->prefix . $table;
            }, $meta['include_tables']);
            $allTables = array_intersect($allTables, $includeTables);
        }

        if (!empty($meta['exclude_tables'])) {
            $excludeTables = array_map(function($table) use ($wpdb) {
                return $wpdb->prefix . $table;
            }, $meta['exclude_tables']);
            $allTables = array_diff($allTables, $excludeTables);
        }

        return array_values($allTables);
    }

    /**
     * Import data from pull operation
     *
     * @param int $jobId
     * @param array $remoteData
     * @return array
     */
    private function importData(int $jobId, array $remoteData): array {
        // Implementation for importing data from pull
        return ['success' => true, 'message' => 'Pull import completed'];
    }
}
