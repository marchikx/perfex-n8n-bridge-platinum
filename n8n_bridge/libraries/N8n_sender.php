<?php
/**
 * N8N Sender Library - Platinum Release v2.0
 *
 * Complete implementation with ALL features:
 * - v1.0: Basic webhook sending with cURL
 * - v1.1: HMAC-SHA256 signing + Queue fallback + Exponential backoff
 * - v2.0: Event-based advanced routing (per-event URLs)
 *
 * @package     N8N_Bridge
 * @category    Library
 * @author      Perfex CRM Module
 * @version     2.0.0
 * @since       1.0.0
 */

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class N8n_sender
 *
 * Enterprise-grade webhook sender with:
 * - HMAC-SHA256 signature on every request
 * - Automatic queue fallback on failure
 * - Event-based URL routing
 * - Exponential backoff retry strategy
 */
class N8n_sender
{
    /**
     * CodeIgniter instance
     *
     * @var CI_Controller
     */
    protected CI_Controller $ci;

    /**
     * Connection timeout in seconds
     *
     * @var int
     */
    protected int $connect_timeout = 3;

    /**
     * Total request timeout in seconds
     *
     * @var int
     */
    protected int $request_timeout = 5;

    /**
     * Maximum response size to store (bytes)
     *
     * @var int
     */
    protected int $max_response_size = 65535;

    /**
     * Logs table name
     *
     * @var string
     */
    protected string $logs_table;

    /**
     * Queue table name
     *
     * @var string
     */
    protected string $queue_table;

    /**
     * Exponential backoff intervals in seconds (v1.1 Enterprise)
     *
     * @var array<int, int>
     */
    protected const BACKOFF_INTERVALS = [
        1 => 300,     // Attempt 1 failed -> retry in 5 minutes
        2 => 900,     // Attempt 2 failed -> retry in 15 minutes
        3 => 3600,    // Attempt 3 failed -> retry in 1 hour
        4 => 14400,   // Attempt 4 failed -> retry in 4 hours
        5 => 43200,   // Attempt 5 failed -> retry in 12 hours (final)
    ];

    /**
     * Maximum retry attempts
     *
     * @var int
     */
    protected const MAX_ATTEMPTS = 5;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->ci =& get_instance();
        $this->ci->load->database();
        $this->logs_table = db_prefix() . 'n8n_logs';
        $this->queue_table = db_prefix() . 'n8n_queue';
    }

    // =========================================================================
    // v2.0 PLATINUM: Event-Based Advanced Routing
    // =========================================================================

    /**
     * Get the target webhook URL for an event
     *
     * Checks for event-specific override URL first, then falls back to global URL.
     *
     * @param string $event_type Event type identifier
     * @return string Target webhook URL (empty if not configured)
     */
    public function get_event_webhook_url(string $event_type): string
    {
        // Check for event-specific override URL
        $override_key = 'n8n_trigger_' . $event_type . '_url';
        $override_url = get_option($override_key);

        if (!empty($override_url) && filter_var($override_url, FILTER_VALIDATE_URL)) {
            log_message('debug', "N8N Bridge: Using override URL for event '{$event_type}'");
            return $override_url;
        }

        // Fall back to global webhook URL
        return get_option('n8n_bridge_webhook_url') ?: '';
    }

    // =========================================================================
    // MAIN SEND METHOD (v1.0 + v1.1 + v2.0)
    // =========================================================================

    /**
     * Send webhook to N8N with automatic queue fallback
     *
     * Features:
     * - v1.0: Basic HTTP POST with JSON payload
     * - v1.1: HMAC-SHA256 signature + Queue fallback on failure
     * - v2.0: Event-based URL routing
     *
     * @param string               $url           Webhook URL (empty = use event routing)
     * @param string               $event_type    Event type identifier
     * @param array<string, mixed> $payload_data  Event payload data
     * @param string               $secret_key    HMAC secret key (optional)
     * @param bool                 $queue_on_fail Whether to queue on failure
     * @return array{success: bool, message: string, queued?: bool, http_code?: int}
     */
    public function send_webhook(
        string $url,
        string $event_type,
        array $payload_data,
        string $secret_key = '',
        bool $queue_on_fail = true
    ): array {
        $log_id = 0;
        $http_code = 0;
        $response_message = '';
        $json = '';

        try {
            // STEP 1: Determine target URL (v2.0 Routing)
            if (empty($url)) {
                $url = $this->get_event_webhook_url($event_type);
            }

            // STEP 2: Validate URL
            if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
                $error_msg = 'Invalid webhook URL';
                $this->log_failed_request($event_type, null, 0, $error_msg);
                return ['success' => false, 'message' => $error_msg, 'http_code' => 0];
            }

            // Validate protocol
            $parsed = parse_url($url);
            if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
                $error_msg = 'Invalid URL protocol - only HTTP/HTTPS allowed';
                $this->log_failed_request($event_type, null, 0, $error_msg);
                return ['success' => false, 'message' => $error_msg, 'http_code' => 0];
            }

            // STEP 3: Build payload (v1.0)
            $payload = [
                'event'     => $event_type,
                'timestamp' => time(),
                'source'    => 'perfex_crm',
                'version'   => '2.0.0',
                'data'      => $payload_data,
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            // STEP 4: Generate HMAC-SHA256 signature (v1.1 Enterprise Security)
            $hmac_secret = $this->get_hmac_secret($secret_key);
            $signature = $this->generate_signature($json, $hmac_secret);

            // STEP 5: Create pending log entry
            $log_id = $this->insert_pending_log($event_type, $json);

            // STEP 6: Build headers with signature
            $headers = $this->build_headers($event_type, $signature);

            // STEP 7: Execute cURL request
            $response = $this->execute_curl_request($url, $json, $headers);

            $http_code = $response['http_code'];
            $response_message = $response['message'];
            $success = $response['success'];

        } catch (JsonException $e) {
            $response_message = 'JSON encoding error: ' . $e->getMessage();
            $success = false;
            log_message('error', 'N8N Bridge: JSON encoding failed - ' . $e->getMessage());

        } catch (Throwable $e) {
            $response_message = 'Unexpected error: ' . $e->getMessage();
            $success = false;
            log_message('error', 'N8N Bridge: Unexpected error - ' . $e->getMessage());
        }

        // STEP 8: Update log with result
        $status = $success ? 'success' : 'failed';
        $this->update_log($log_id, $http_code, $response_message, $status);

        // STEP 9: Queue on failure (v1.1 Enterprise)
        $queued = false;
        if (!$success && $queue_on_fail && !empty($json) && $this->is_queue_enabled()) {
            $queued = $this->add_to_queue($event_type, $json, $url, $response_message, $http_code);
            if ($queued) {
                log_message('info', "N8N Bridge: Payload queued for retry - Event: {$event_type}");
            }
        }

        return [
            'success'   => $success,
            'message'   => $success
                ? sprintf('Webhook delivered successfully (HTTP %d)', $http_code)
                : ($response_message ?: 'Webhook delivery failed'),
            'queued'    => $queued,
            'http_code' => $http_code,
        ];
    }

    /**
     * Send webhook from queue (used by retry processor)
     *
     * @param int    $queue_id   Queue entry ID
     * @param string $url        Webhook URL
     * @param string $json       Pre-encoded JSON payload
     * @param string $event_type Event type
     * @return array{success: bool, http_code: int, message: string}
     */
    public function send_queued_webhook(int $queue_id, string $url, string $json, string $event_type): array
    {
        try {
            // Check for override URL (v2.0)
            $override_url = $this->get_event_webhook_url($event_type);
            if (!empty($override_url)) {
                $url = $override_url;
            }

            // Generate HMAC signature (v1.1)
            $hmac_secret = $this->get_hmac_secret('');
            $signature = $this->generate_signature($json, $hmac_secret);

            // Build headers
            $headers = $this->build_headers($event_type, $signature);
            $headers[] = 'X-Perfex-Queue-Id: ' . $queue_id;
            $headers[] = 'X-Perfex-Retry: true';

            // Execute request
            $response = $this->execute_curl_request($url, $json, $headers);

            // Log successful retry
            if ($response['success']) {
                $log_id = $this->insert_pending_log($event_type . '.retry', $json);
                if ($log_id > 0) {
                    $this->update_log($log_id, $response['http_code'], $response['message'], 'success');
                }
            }

            return $response;

        } catch (Throwable $e) {
            return [
                'success'   => false,
                'http_code' => 0,
                'message'   => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // v1.1 ENTERPRISE: HMAC-SHA256 Signature
    // =========================================================================

    /**
     * Generate HMAC-SHA256 signature
     *
     * @param string $payload    JSON payload
     * @param string $secret_key HMAC secret
     * @return string HMAC-SHA256 signature (hex)
     */
    public function generate_signature(string $payload, string $secret_key): string
    {
        return hash_hmac('sha256', $payload, $secret_key);
    }

    /**
     * Get HMAC secret key
     *
     * @param string $provided_secret Optional provided secret
     * @return string HMAC secret key
     */
    protected function get_hmac_secret(string $provided_secret = ''): string
    {
        if (!empty($provided_secret)) {
            return $provided_secret;
        }

        // Use dedicated HMAC secret if available
        $hmac_secret = get_option('n8n_bridge_hmac_secret');
        if (!empty($hmac_secret)) {
            return $hmac_secret;
        }

        // Fall back to general secret
        return get_option('n8n_bridge_secret') ?: 'default_n8n_bridge_secret';
    }

    /**
     * Build HTTP headers with HMAC signature
     *
     * @param string $event_type Event type
     * @param string $signature  HMAC signature
     * @return array<string>
     */
    protected function build_headers(string $event_type, string $signature): array
    {
        return [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'X-Perfex-Event: ' . $event_type,
            'X-Perfex-Timestamp: ' . time(),
            'X-Perfex-Signature: ' . $signature,
            'X-Perfex-Version: 2.0.0',
        ];
    }

    // =========================================================================
    // v1.1 ENTERPRISE: Queue System
    // =========================================================================

    /**
     * Check if queue system is enabled
     *
     * @return bool
     */
    protected function is_queue_enabled(): bool
    {
        return get_option('n8n_bridge_queue_enabled') === '1';
    }

    /**
     * Add failed delivery to queue for retry
     *
     * @param string $event_type    Event type
     * @param string $payload_json  JSON payload
     * @param string $webhook_url   Target URL
     * @param string $error_message Error message
     * @param int    $response_code HTTP response code
     * @return bool True if queued successfully
     */
    public function add_to_queue(
        string $event_type,
        string $payload_json,
        string $webhook_url,
        string $error_message = '',
        int $response_code = 0
    ): bool {
        try {
            // Calculate checksum for deduplication
            $checksum = hash('sha256', $event_type . $payload_json . $webhook_url);

            // Check for duplicate within last hour
            $this->ci->db->where('checksum', $checksum);
            $this->ci->db->where('status', 'pending');
            $this->ci->db->where('created_at >', date('Y-m-d H:i:s', strtotime('-1 hour')));
            $existing = $this->ci->db->get($this->queue_table)->row();

            if ($existing) {
                log_message('debug', 'N8N Bridge: Duplicate queue entry skipped');
                return false;
            }

            // Calculate next attempt time
            $next_attempt = date('Y-m-d H:i:s', time() + self::BACKOFF_INTERVALS[1]);

            $max_retries = (int) get_option('n8n_bridge_queue_max_retries');
            if ($max_retries < 1 || $max_retries > 10) {
                $max_retries = self::MAX_ATTEMPTS;
            }

            $data = [
                'event_type'         => $event_type,
                'payload'            => $payload_json,
                'webhook_url'        => $webhook_url,
                'status'             => 'pending',
                'attempts'           => 0,
                'max_attempts'       => $max_retries,
                'last_attempt_at'    => date('Y-m-d H:i:s'),
                'next_attempt_at'    => $next_attempt,
                'last_error'         => mb_substr($error_message, 0, 65535),
                'last_response_code' => $response_code,
                'created_at'         => date('Y-m-d H:i:s'),
                'completed_at'       => null,
                'priority'           => 5,
                'checksum'           => $checksum,
            ];

            $this->ci->db->insert($this->queue_table, $data);
            return $this->ci->db->insert_id() > 0;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to add to queue - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get pending queue items ready for retry
     *
     * @param int $limit Maximum items to fetch
     * @return array Queue items
     */
    public function get_pending_queue_items(int $limit = 10): array
    {
        try {
            $now = date('Y-m-d H:i:s');

            return $this->ci->db
                ->where('status', 'pending')
                ->where('next_attempt_at <=', $now)
                ->order_by('priority', 'ASC')
                ->order_by('next_attempt_at', 'ASC')
                ->limit($limit)
                ->get($this->queue_table)
                ->result();

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to get pending queue items - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Update queue item status after retry attempt
     *
     * @param int    $queue_id      Queue item ID
     * @param bool   $success       Whether delivery succeeded
     * @param string $error_message Error message if failed
     * @param int    $response_code HTTP response code
     * @return bool
     */
    public function update_queue_item(
        int $queue_id,
        bool $success,
        string $error_message = '',
        int $response_code = 0
    ): bool {
        try {
            $item = $this->ci->db->where('id', $queue_id)->get($this->queue_table)->row();
            if (!$item) {
                return false;
            }

            $attempts = (int) $item->attempts + 1;
            $max_attempts = (int) $item->max_attempts;

            if ($success) {
                // Success
                $update_data = [
                    'status'             => 'completed',
                    'attempts'           => $attempts,
                    'last_attempt_at'    => date('Y-m-d H:i:s'),
                    'completed_at'       => date('Y-m-d H:i:s'),
                    'last_response_code' => $response_code,
                    'last_error'         => null,
                ];
            } elseif ($attempts >= $max_attempts) {
                // Max attempts reached - mark as dead
                $update_data = [
                    'status'             => 'dead',
                    'attempts'           => $attempts,
                    'last_attempt_at'    => date('Y-m-d H:i:s'),
                    'last_error'         => mb_substr($error_message, 0, 65535),
                    'last_response_code' => $response_code,
                ];
                log_message('error', "N8N Bridge: Queue item {$queue_id} marked as dead after {$attempts} attempts");
            } else {
                // Schedule retry with exponential backoff
                $backoff_index = min($attempts + 1, count(self::BACKOFF_INTERVALS));
                $backoff_seconds = self::BACKOFF_INTERVALS[$backoff_index] ?? 43200;
                $next_attempt = date('Y-m-d H:i:s', time() + $backoff_seconds);

                $update_data = [
                    'status'             => 'pending',
                    'attempts'           => $attempts,
                    'last_attempt_at'    => date('Y-m-d H:i:s'),
                    'next_attempt_at'    => $next_attempt,
                    'last_error'         => mb_substr($error_message, 0, 65535),
                    'last_response_code' => $response_code,
                ];
                log_message('info', "N8N Bridge: Queue item {$queue_id} rescheduled for {$next_attempt}");
            }

            $this->ci->db->where('id', $queue_id);
            $this->ci->db->update($this->queue_table, $update_data);

            return $this->ci->db->affected_rows() > 0;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to update queue item - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get queue statistics
     *
     * @return array{pending: int, processing: int, completed: int, failed: int, dead: int, total: int}
     */
    public function get_queue_stats(): array
    {
        $stats = [
            'pending'    => 0,
            'processing' => 0,
            'completed'  => 0,
            'failed'     => 0,
            'dead'       => 0,
            'total'      => 0,
        ];

        try {
            $result = $this->ci->db
                ->select('status, COUNT(*) as count')
                ->group_by('status')
                ->get($this->queue_table)
                ->result();

            foreach ($result as $row) {
                if (isset($stats[$row->status])) {
                    $stats[$row->status] = (int) $row->count;
                }
                $stats['total'] += (int) $row->count;
            }
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to get queue stats - ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Clean old completed/dead queue entries
     *
     * @param int $days_old Remove entries older than this many days
     * @return int Number of entries removed
     */
    public function clean_old_queue_entries(int $days_old = 30): int
    {
        try {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));

            $this->ci->db->where_in('status', ['completed', 'dead']);
            $this->ci->db->where('created_at <', $cutoff);
            $this->ci->db->delete($this->queue_table);

            return $this->ci->db->affected_rows();

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to clean old queue entries - ' . $e->getMessage());
            return 0;
        }
    }

    // =========================================================================
    // v1.0 BASE: cURL Execution
    // =========================================================================

    /**
     * Execute cURL request
     *
     * @param string        $url     Target URL
     * @param string        $json    JSON payload
     * @param array<string> $headers HTTP headers
     * @return array{success: bool, http_code: int, message: string}
     */
    protected function execute_curl_request(string $url, string $json, array $headers): array
    {
        try {
            $ch = curl_init();

            if ($ch === false) {
                return [
                    'success'   => false,
                    'http_code' => 0,
                    'message'   => 'Failed to initialize cURL',
                ];
            }

            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
                CURLOPT_TIMEOUT        => $this->request_timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS      => 0,
                CURLOPT_PROXY          => '',
                CURLOPT_USERAGENT      => 'Perfex-CRM-N8N-Bridge/2.0',
                CURLOPT_DNS_CACHE_TIMEOUT => 120,
            ]);

            $response_body = curl_exec($ch);
            $curl_errno = curl_errno($ch);
            $curl_error = curl_error($ch);
            $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            if ($curl_errno !== 0) {
                return [
                    'success'   => false,
                    'http_code' => 0,
                    'message'   => $this->get_curl_error_message($curl_errno, $curl_error),
                ];
            }

            $response_body = is_string($response_body) 
                ? mb_substr($response_body, 0, $this->max_response_size) 
                : '';

            return [
                'success'   => ($http_code >= 200 && $http_code < 300),
                'http_code' => $http_code,
                'message'   => $response_body,
            ];

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: cURL exception - ' . $e->getMessage());
            return [
                'success'   => false,
                'http_code' => 0,
                'message'   => 'cURL exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get human-readable cURL error message
     *
     * @param int    $errno cURL error number
     * @param string $error cURL error string
     * @return string
     */
    protected function get_curl_error_message(int $errno, string $error): string
    {
        $messages = [
            CURLE_COULDNT_CONNECT      => 'Could not connect to server',
            CURLE_COULDNT_RESOLVE_HOST => 'Could not resolve hostname',
            CURLE_OPERATION_TIMEDOUT   => 'Connection timed out',
            CURLE_SSL_CONNECT_ERROR    => 'SSL connection error',
            CURLE_SSL_CERTPROBLEM      => 'SSL certificate problem',
            CURLE_SSL_CACERT           => 'SSL CA certificate error',
        ];

        return $messages[$errno] ?? (!empty($error) ? $error : 'cURL error #' . $errno);
    }

    // =========================================================================
    // LOGGING HELPERS
    // =========================================================================

    /**
     * Insert pending log entry
     *
     * @param string $event_type   Event type
     * @param string $payload_json JSON payload
     * @return int Log ID
     */
    protected function insert_pending_log(string $event_type, string $payload_json): int
    {
        try {
            $this->ci->db->insert($this->logs_table, [
                'event_type'       => $event_type,
                'payload'          => $payload_json,
                'response_code'    => null,
                'response_message' => null,
                'status'           => 'pending',
                'retry_count'      => 0,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
            return (int) $this->ci->db->insert_id();
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to insert pending log - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update log entry
     *
     * @param int    $log_id           Log ID
     * @param int    $response_code    HTTP response code
     * @param string $response_message Response message
     * @param string $status           Status
     * @return void
     */
    protected function update_log(int $log_id, int $response_code, string $response_message, string $status): void
    {
        if ($log_id <= 0) {
            return;
        }

        try {
            $this->ci->db->where('id', $log_id);
            $this->ci->db->update($this->logs_table, [
                'response_code'    => $response_code,
                'response_message' => $response_message,
                'status'           => $status,
            ]);
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to update log - ' . $e->getMessage());
        }
    }

    /**
     * Log failed request
     *
     * @param string      $event_type       Event type
     * @param string|null $payload_json     JSON payload
     * @param int         $response_code    HTTP response code
     * @param string      $response_message Error message
     * @return void
     */
    protected function log_failed_request(
        string $event_type,
        ?string $payload_json,
        int $response_code,
        string $response_message
    ): void {
        try {
            $this->ci->db->insert($this->logs_table, [
                'event_type'       => $event_type,
                'payload'          => $payload_json,
                'response_code'    => $response_code,
                'response_message' => $response_message,
                'status'           => 'failed',
                'retry_count'      => 0,
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Failed to log failed request - ' . $e->getMessage());
        }
    }

    // =========================================================================
    // CONFIGURATION SETTERS
    // =========================================================================

    /**
     * Set connection timeout
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function set_connect_timeout(int $seconds): self
    {
        $this->connect_timeout = max(1, min(30, $seconds));
        return $this;
    }

    /**
     * Set request timeout
     *
     * @param int $seconds Timeout in seconds
     * @return self
     */
    public function set_request_timeout(int $seconds): self
    {
        $this->request_timeout = max(1, min(60, $seconds));
        return $this;
    }
}
