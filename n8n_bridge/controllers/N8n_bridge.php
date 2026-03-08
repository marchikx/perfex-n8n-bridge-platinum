<?php
/**
 * N8N Bridge Controller - Platinum Release v2.0
 *
 * Complete controller with ALL features:
 * - v1.0: Basic settings, test connection, logging
 * - v1.1: Inbound webhooks, queue processing, CSRF/ACL security
 * - v2.0: Dashboard analytics, system health, advanced routing
 *
 * @package     N8N_Bridge
 * @category    Controller
 * @author      Perfex CRM Module
 * @version     2.0.0
 * @since       1.0.0
 */

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class N8n_bridge
 */
class N8n_bridge extends AdminController
{
    /**
     * @var string Permission feature
     */
    private const PERMISSION_FEATURE = 'settings';

    /**
     * @var array<string> Allowed event types
     */
    private const ALLOWED_EVENT_TYPES = [
        'invoice_created',
        'invoice_updated',
        'lead_created',
        'lead_updated',
        'task_created',
        'task_updated',
        'ticket_created',
        'ticket_updated',
    ];

    /**
     * @var array<string> Allowed inbound actions (v1.1)
     */
    private const ALLOWED_INBOUND_ACTIONS = [
        'update_lead_status',
        'update_lead',
        'add_task_comment',
        'update_task_status',
        'update_invoice_status',
        'add_note',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Skip permission check for incoming webhook (public endpoint with token auth)
        $method = $this->router->fetch_method();
        if ($method !== 'incoming') {
            if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
                access_denied(self::PERMISSION_FEATURE);
            }
        }

        $this->load->model('n8n_bridge/N8n_bridge_model', 'n8n_model');
        $this->load->language('n8n_bridge/n8n_bridge');
    }

    // =========================================================================
    // v2.0 PLATINUM: Dashboard Analytics
    // =========================================================================

    /**
     * Get dashboard statistics for Chart.js
     *
     * @return array Dashboard data
     */
    public function get_dashboard_stats(): array
    {
        $stats = [
            'success_failure' => ['success' => 0, 'failed' => 0, 'pending' => 0],
            'timeline'        => [],
            'top_triggers'    => [],
            'summary'         => [
                'total_webhooks'  => 0,
                'success_rate'    => 0,
                'queue_pending'   => 0,
                'last_activity'   => null,
            ],
        ];

        try {
            $logs_table = db_prefix() . 'n8n_logs';
            $queue_table = db_prefix() . 'n8n_queue';

            // Success vs Failure counts
            $status_query = $this->db->query("
                SELECT status, COUNT(*) as count 
                FROM {$logs_table} 
                GROUP BY status
            ");
            
            if ($status_query && $status_query->num_rows() > 0) {
                foreach ($status_query->result() as $row) {
                    if (isset($stats['success_failure'][$row->status])) {
                        $stats['success_failure'][$row->status] = (int) $row->count;
                    }
                }
            }

            // Traffic Timeline - Last 7 days
            $timeline_query = $this->db->query("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM {$logs_table}
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");

            // Initialize all 7 days
            $timeline = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $timeline[$date] = [
                    'date'    => $date,
                    'label'   => date('M d', strtotime($date)),
                    'total'   => 0,
                    'success' => 0,
                    'failed'  => 0,
                ];
            }

            if ($timeline_query && $timeline_query->num_rows() > 0) {
                foreach ($timeline_query->result() as $row) {
                    if (isset($timeline[$row->date])) {
                        $timeline[$row->date]['total'] = (int) $row->total;
                        $timeline[$row->date]['success'] = (int) $row->success;
                        $timeline[$row->date]['failed'] = (int) $row->failed;
                    }
                }
            }
            $stats['timeline'] = array_values($timeline);

            // Top Triggers
            $triggers_query = $this->db->query("
                SELECT 
                    event_type, 
                    COUNT(*) as count,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count
                FROM {$logs_table}
                WHERE event_type NOT LIKE 'test.%'
                GROUP BY event_type
                ORDER BY count DESC
                LIMIT 8
            ");

            if ($triggers_query && $triggers_query->num_rows() > 0) {
                foreach ($triggers_query->result() as $row) {
                    $stats['top_triggers'][] = [
                        'event'   => $this->format_event_label($row->event_type),
                        'key'     => $row->event_type,
                        'count'   => (int) $row->count,
                        'success' => (int) $row->success_count,
                    ];
                }
            }

            // Summary
            $total = $stats['success_failure']['success'] + $stats['success_failure']['failed'] + $stats['success_failure']['pending'];
            $stats['summary']['total_webhooks'] = $total;
            $stats['summary']['success_rate'] = $total > 0 
                ? round(($stats['success_failure']['success'] / $total) * 100, 1) 
                : 0;

            // Queue pending
            $queue_query = $this->db->query("SELECT COUNT(*) as pending FROM {$queue_table} WHERE status = 'pending'");
            if ($queue_query && $queue_query->num_rows() > 0) {
                $stats['summary']['queue_pending'] = (int) $queue_query->row()->pending;
            }

            // Last activity
            $last_query = $this->db->query("SELECT created_at FROM {$logs_table} ORDER BY created_at DESC LIMIT 1");
            if ($last_query && $last_query->num_rows() > 0) {
                $stats['summary']['last_activity'] = $last_query->row()->created_at;
            }

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Dashboard stats error - ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Format event type to human-readable label
     *
     * @param string $event_type Event type key
     * @return string
     */
    private function format_event_label(string $event_type): string
    {
        $labels = [
            'invoice_created' => _l('n8n_trigger_invoice_created'),
            'invoice_updated' => _l('n8n_trigger_invoice_updated'),
            'lead_created'    => _l('n8n_trigger_lead_created'),
            'lead_updated'    => _l('n8n_trigger_lead_updated'),
            'task_created'    => _l('n8n_trigger_task_created'),
            'task_updated'    => _l('n8n_trigger_task_updated'),
            'ticket_created'  => _l('n8n_trigger_ticket_created'),
            'ticket_updated'  => _l('n8n_trigger_ticket_updated'),
        ];
        return $labels[$event_type] ?? ucwords(str_replace('_', ' ', $event_type));
    }

    /**
     * AJAX: Get dashboard statistics
     */
    public function dashboard_stats_ajax(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }
        $this->output_json(['success' => true, 'data' => $this->get_dashboard_stats()]);
    }

    // =========================================================================
    // v2.0 PLATINUM: System Health & Auto-Fix
    // =========================================================================

    /**
     * Get system health status
     *
     * @return array Health data
     */
    public function get_system_health(): array
    {
        $health = [
            'overall_status' => 'healthy',
            'checks'         => [],
        ];

        // Load queue stats
        $this->load->library('n8n_sender');
        $queue_stats = $this->n8n_sender->get_queue_stats();

        // 1. Queue Status
        $queue_status = 'healthy';
        $queue_message = sprintf(_l('n8n_health_queue_ok'), $queue_stats['pending']);
        if ($queue_stats['pending'] > 100) {
            $queue_status = 'warning';
            $queue_message = _l('n8n_health_queue_high');
        }
        if ($queue_stats['dead'] > 10) {
            $queue_status = 'critical';
            $queue_message = _l('n8n_health_queue_dead');
        }
        $health['checks']['queue'] = [
            'name'    => _l('n8n_health_queue'),
            'status'  => $queue_status,
            'message' => $queue_message,
            'value'   => $queue_stats['pending'] . ' ' . _l('n8n_pending'),
        ];

        // 2. Cron Status
        $last_cron = get_option('n8n_bridge_last_cron_run');
        $cron_status = 'healthy';
        $cron_message = _l('n8n_health_cron_ok');
        if (empty($last_cron)) {
            $cron_status = 'warning';
            $cron_message = _l('n8n_health_cron_never');
        } elseif (strtotime($last_cron) < strtotime('-1 hour')) {
            $cron_status = 'warning';
            $cron_message = _l('n8n_health_cron_stale');
        }
        $health['checks']['cron'] = [
            'name'    => _l('n8n_health_cron'),
            'status'  => $cron_status,
            'message' => $cron_message,
            'value'   => $last_cron ?: _l('n8n_never'),
        ];

        // 3. PHP Version
        $php_version = PHP_VERSION;
        $php_status = 'healthy';
        $php_message = _l('n8n_health_php_ok');
        if (version_compare($php_version, '7.4', '<')) {
            $php_status = 'critical';
            $php_message = _l('n8n_health_php_old');
        } elseif (version_compare($php_version, '8.0', '<')) {
            $php_status = 'warning';
            $php_message = _l('n8n_health_php_update');
        }
        $health['checks']['php'] = [
            'name'    => _l('n8n_health_php'),
            'status'  => $php_status,
            'message' => $php_message,
            'value'   => $php_version,
        ];

        // 4. cURL Extension
        $curl_status = extension_loaded('curl') ? 'healthy' : 'critical';
        $health['checks']['curl'] = [
            'name'    => _l('n8n_health_curl'),
            'status'  => $curl_status,
            'message' => $curl_status === 'healthy' ? _l('n8n_health_curl_ok') : _l('n8n_health_curl_missing'),
            'value'   => $curl_status === 'healthy' ? _l('n8n_installed') : _l('n8n_missing'),
        ];

        // 5. Webhook URL
        $webhook_url = get_option('n8n_bridge_webhook_url');
        $url_status = !empty($webhook_url) ? 'healthy' : 'warning';
        $health['checks']['webhook_url'] = [
            'name'    => _l('n8n_health_webhook'),
            'status'  => $url_status,
            'message' => $url_status === 'healthy' ? _l('n8n_health_webhook_ok') : _l('n8n_health_webhook_missing'),
            'value'   => $url_status === 'healthy' ? _l('n8n_configured') : _l('n8n_not_configured'),
        ];

        // 6. Inbound Token
        $inbound_token = get_option('n8n_inbound_token');
        $token_status = !empty($inbound_token) ? 'healthy' : 'warning';
        $health['checks']['inbound_token'] = [
            'name'    => _l('n8n_health_token'),
            'status'  => $token_status,
            'message' => $token_status === 'healthy' ? _l('n8n_health_token_ok') : _l('n8n_health_token_missing'),
            'value'   => $token_status === 'healthy' ? _l('n8n_configured') : _l('n8n_not_configured'),
        ];

        // 7. Stuck Queue Items
        $stuck_count = $this->count_stuck_queue_items();
        $stuck_status = $stuck_count === 0 ? 'healthy' : ($stuck_count < 10 ? 'warning' : 'critical');
        $health['checks']['stuck_queue'] = [
            'name'    => _l('n8n_health_stuck'),
            'status'  => $stuck_status,
            'message' => $stuck_count === 0 ? _l('n8n_health_stuck_ok') : sprintf(_l('n8n_health_stuck_found'), $stuck_count),
            'value'   => (string) $stuck_count,
        ];

        // Calculate overall status
        foreach ($health['checks'] as $check) {
            if ($check['status'] === 'critical') {
                $health['overall_status'] = 'critical';
                break;
            }
            if ($check['status'] === 'warning' && $health['overall_status'] !== 'critical') {
                $health['overall_status'] = 'warning';
            }
        }

        return $health;
    }

    /**
     * Count stuck queue items (pending > 24 hours)
     *
     * @return int
     */
    private function count_stuck_queue_items(): int
    {
        try {
            $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $query = $this->db->query(
                'SELECT COUNT(*) as count FROM ' . db_prefix() . 'n8n_queue WHERE status = ? AND created_at < ?',
                ['pending', $cutoff]
            );
            return $query && $query->num_rows() > 0 ? (int) $query->row()->count : 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Auto-Fix: Reset stuck queue items
     */
    public function reset_stuck_queue(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        try {
            $cutoff = date('Y-m-d H:i:s', strtotime('-24 hours'));
            
            $this->db->where('status', 'pending');
            $this->db->where('created_at <', $cutoff);
            $this->db->update(db_prefix() . 'n8n_queue', [
                'status'     => 'failed',
                'last_error' => 'Auto-reset: Stuck for more than 24 hours',
            ]);

            $affected = $this->db->affected_rows();

            if (function_exists('log_activity')) {
                log_activity('N8N Bridge: Reset ' . $affected . ' stuck queue items');
            }

            $this->output_json([
                'success' => true,
                'message' => sprintf(_l('n8n_stuck_reset_success'), $affected),
                'count'   => $affected,
            ]);

        } catch (Throwable $e) {
            $this->output_json(['success' => false, 'message' => _l('n8n_stuck_reset_error')]);
        }
    }

    /**
     * AJAX: Get health status
     */
    public function health_check_ajax(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }
        $this->output_json(['success' => true, 'data' => $this->get_system_health()]);
    }

    // =========================================================================
    // v1.1 ENTERPRISE: Inbound Webhook (N8N → Perfex)
    // =========================================================================

    /**
     * Incoming webhook endpoint - N8N → Perfex
     *
     * @param string|null $token Authentication token
     */
    public function incoming($token = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        // Validate method
        if ($this->input->method(true) !== 'POST') {
            $this->output_json_raw(['success' => false, 'error' => 'method_not_allowed', 'message' => 'Only POST requests are accepted'], 405);
            return;
        }

        // Validate token
        if (empty($token)) {
            $this->output_json_raw(['success' => false, 'error' => 'missing_token', 'message' => 'Authentication token is required'], 401);
            return;
        }

        $stored_token = get_option('n8n_inbound_token');
        if (empty($stored_token)) {
            $this->output_json_raw(['success' => false, 'error' => 'not_configured', 'message' => 'Inbound webhook not configured'], 503);
            return;
        }

        // Constant-time comparison
        if (!hash_equals($stored_token, $token)) {
            log_message('warning', 'N8N Bridge: Invalid token attempt from IP: ' . $this->input->ip_address());
            $this->output_json_raw(['success' => false, 'error' => 'invalid_token', 'message' => 'Invalid authentication token'], 401);
            return;
        }

        // Parse payload
        $raw_input = file_get_contents('php://input');
        if (empty($raw_input)) {
            $this->output_json_raw(['success' => false, 'error' => 'empty_payload', 'message' => 'Request body is empty'], 400);
            return;
        }

        $payload = json_decode($raw_input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output_json_raw(['success' => false, 'error' => 'invalid_json', 'message' => 'Invalid JSON: ' . json_last_error_msg()], 400);
            return;
        }

        // Validate action
        if (empty($payload['action'])) {
            $this->output_json_raw(['success' => false, 'error' => 'missing_action', 'message' => 'Action field is required'], 400);
            return;
        }

        $action = $this->security->xss_clean($payload['action']);

        if (!in_array($action, self::ALLOWED_INBOUND_ACTIONS, true)) {
            $this->output_json_raw(['success' => false, 'error' => 'invalid_action', 'message' => 'Action not supported: ' . $action], 400);
            return;
        }

        // Process action
        try {
            $result = $this->process_inbound_action($action, $payload);
            log_message('info', "N8N Bridge Incoming: Action '{$action}' processed from IP: " . $this->input->ip_address());
            $this->output_json_raw(['success' => true, 'action' => $action, 'result' => $result, 'message' => 'Action processed successfully'], 200);
        } catch (InvalidArgumentException $e) {
            $this->output_json_raw(['success' => false, 'error' => 'validation_error', 'message' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge Incoming: Error - ' . $e->getMessage());
            $this->output_json_raw(['success' => false, 'error' => 'processing_error', 'message' => 'Failed to process action'], 500);
        }
    }

    /**
     * Process inbound action
     *
     * @param string $action  Action name
     * @param array  $payload Payload data
     * @return array Result
     */
    private function process_inbound_action(string $action, array $payload): array
    {
        $data = $payload['data'] ?? [];

        switch ($action) {
            case 'update_lead_status':
                return $this->action_update_lead_status($data);
            case 'update_lead':
                return $this->action_update_lead($data);
            case 'add_task_comment':
                return $this->action_add_task_comment($data);
            case 'update_task_status':
                return $this->action_update_task_status($data);
            case 'update_invoice_status':
                return $this->action_update_invoice_status($data);
            case 'add_note':
                return $this->action_add_note($data);
            default:
                throw new InvalidArgumentException('Unknown action: ' . $action);
        }
    }

    private function action_update_lead_status(array $data): array
    {
        $lead_id = (int) ($data['lead_id'] ?? 0);
        $status_id = (int) ($data['status_id'] ?? 0);

        if ($lead_id <= 0) throw new InvalidArgumentException('lead_id is required');
        if ($status_id <= 0) throw new InvalidArgumentException('status_id is required');

        $this->load->model('leads_model');
        if (!$this->leads_model->get($lead_id)) throw new InvalidArgumentException('Lead not found');

        $this->db->where('id', $lead_id)->update(db_prefix() . 'leads', ['status' => $status_id]);
        return ['lead_id' => $lead_id, 'status_id' => $status_id, 'updated' => $this->db->affected_rows() > 0];
    }

    private function action_update_lead(array $data): array
    {
        $lead_id = (int) ($data['lead_id'] ?? 0);
        if ($lead_id <= 0) throw new InvalidArgumentException('lead_id is required');

        $this->load->model('leads_model');
        if (!$this->leads_model->get($lead_id)) throw new InvalidArgumentException('Lead not found');

        $allowed = ['name', 'email', 'phonenumber', 'company', 'address', 'city', 'state', 'country', 'zip', 'website', 'description', 'status', 'source', 'assigned', 'is_public'];
        $update = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) $update[$field] = $this->security->xss_clean($data[$field]);
        }
        if (empty($update)) throw new InvalidArgumentException('No valid fields provided');

        $this->db->where('id', $lead_id)->update(db_prefix() . 'leads', $update);
        return ['lead_id' => $lead_id, 'updated_fields' => array_keys($update), 'updated' => $this->db->affected_rows() > 0];
    }

    private function action_add_task_comment(array $data): array
    {
        $task_id = (int) ($data['task_id'] ?? 0);
        $content = trim($data['content'] ?? '');
        $staff_id = (int) ($data['staff_id'] ?? 1);

        if ($task_id <= 0) throw new InvalidArgumentException('task_id is required');
        if (empty($content)) throw new InvalidArgumentException('content is required');

        $this->load->model('tasks_model');
        if (!$this->tasks_model->get($task_id)) throw new InvalidArgumentException('Task not found');

        $this->db->insert(db_prefix() . 'task_comments', [
            'content' => $this->security->xss_clean($content),
            'taskid' => $task_id,
            'staffid' => $staff_id,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);
        return ['task_id' => $task_id, 'comment_id' => (int) $this->db->insert_id(), 'added' => true];
    }

    private function action_update_task_status(array $data): array
    {
        $task_id = (int) ($data['task_id'] ?? 0);
        $status = (int) ($data['status'] ?? -1);

        if ($task_id <= 0) throw new InvalidArgumentException('task_id is required');
        if ($status < 1 || $status > 5) throw new InvalidArgumentException('status must be 1-5');

        $this->load->model('tasks_model');
        if (!$this->tasks_model->get($task_id)) throw new InvalidArgumentException('Task not found');

        $update = ['status' => $status];
        if ($status == 5) $update['datefinished'] = date('Y-m-d H:i:s');

        $this->db->where('id', $task_id)->update(db_prefix() . 'tasks', $update);
        return ['task_id' => $task_id, 'status' => $status, 'updated' => $this->db->affected_rows() > 0];
    }

    private function action_update_invoice_status(array $data): array
    {
        $invoice_id = (int) ($data['invoice_id'] ?? 0);
        $status = (int) ($data['status'] ?? -1);

        if ($invoice_id <= 0) throw new InvalidArgumentException('invoice_id is required');
        if ($status < 1 || $status > 6) throw new InvalidArgumentException('status must be 1-6');

        $this->load->model('invoices_model');
        if (!$this->invoices_model->get($invoice_id)) throw new InvalidArgumentException('Invoice not found');

        $this->db->where('id', $invoice_id)->update(db_prefix() . 'invoices', ['status' => $status]);
        return ['invoice_id' => $invoice_id, 'status' => $status, 'updated' => $this->db->affected_rows() > 0];
    }

    private function action_add_note(array $data): array
    {
        $rel_id = (int) ($data['rel_id'] ?? 0);
        $rel_type = trim($data['rel_type'] ?? '');
        $description = trim($data['description'] ?? '');
        $staff_id = (int) ($data['staff_id'] ?? 1);

        if ($rel_id <= 0) throw new InvalidArgumentException('rel_id is required');
        if (empty($rel_type)) throw new InvalidArgumentException('rel_type is required');
        if (empty($description)) throw new InvalidArgumentException('description is required');

        $allowed_types = ['lead', 'customer', 'invoice', 'task', 'ticket', 'project', 'contract'];
        if (!in_array($rel_type, $allowed_types, true)) throw new InvalidArgumentException('Invalid rel_type');

        $this->db->insert(db_prefix() . 'notes', [
            'rel_id' => $rel_id,
            'rel_type' => $this->security->xss_clean($rel_type),
            'description' => $this->security->xss_clean($description),
            'addedfrom' => $staff_id,
            'dateadded' => date('Y-m-d H:i:s'),
        ]);
        return ['rel_id' => $rel_id, 'rel_type' => $rel_type, 'note_id' => (int) $this->db->insert_id(), 'added' => true];
    }

    // =========================================================================
    // v1.0 + v1.1: Magic Button Manual Trigger
    // =========================================================================

    /**
     * Manual trigger endpoint for Magic Button
     */
    public function manual_trigger(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid'), 'csrf_hash' => $this->security->get_csrf_hash()]);
            return;
        }

        $entity_type = $this->security->xss_clean($this->input->post('entity_type', true));
        $entity_id = (int) $this->input->post('entity_id', true);
        $event = $this->security->xss_clean($this->input->post('event', true));

        if (empty($entity_type) || $entity_id <= 0) {
            $this->output_json(['success' => false, 'message' => 'Invalid entity', 'csrf_hash' => $this->security->get_csrf_hash()]);
            return;
        }

        $allowed_types = ['lead', 'invoice', 'task', 'ticket'];
        if (!in_array($entity_type, $allowed_types, true)) {
            $this->output_json(['success' => false, 'message' => 'Unsupported entity type', 'csrf_hash' => $this->security->get_csrf_hash()]);
            return;
        }

        if (empty($event)) $event = $entity_type . '_updated';

        if (!in_array($event, self::ALLOWED_EVENT_TYPES, true)) {
            $this->output_json(['success' => false, 'message' => 'Invalid event type', 'csrf_hash' => $this->security->get_csrf_hash()]);
            return;
        }

        if (get_option('n8n_bridge_enabled') !== '1') {
            $this->output_json(['success' => false, 'message' => 'N8N Bridge is disabled', 'csrf_hash' => $this->security->get_csrf_hash()]);
            return;
        }

        try {
            $this->n8n_model->process_event($event, $entity_id);
            $this->output_json(['success' => true, 'message' => sprintf('N8N workflow triggered for %s #%d', ucfirst($entity_type), $entity_id), 'csrf_hash' => $this->security->get_csrf_hash()]);
        } catch (Throwable $e) {
            $this->output_json(['success' => false, 'message' => 'Failed: ' . $e->getMessage(), 'csrf_hash' => $this->security->get_csrf_hash()]);
        }
    }

    // =========================================================================
    // SETTINGS PAGE (All versions combined)
    // =========================================================================

    /**
     * Settings page
     */
    public function settings(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            access_denied(self::PERMISSION_FEATURE);
        }

        // Handle POST
        if ($this->input->post()) {
            if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
                access_denied(self::PERMISSION_FEATURE);
            }

            if (!$this->security->csrf_verify()) {
                set_alert('danger', _l('csrf_token_invalid'));
                redirect(admin_url('n8n_bridge/settings'));
                return;
            }

            // Validate URL
            $webhook_url = trim($this->input->post('webhook_url', true));
            if (!empty($webhook_url) && !filter_var($webhook_url, FILTER_VALIDATE_URL)) {
                set_alert('danger', _l('n8n_invalid_url'));
                redirect(admin_url('n8n_bridge/settings'));
                return;
            }

            // Save settings
            update_option('n8n_bridge_webhook_url', $webhook_url);
            update_option('n8n_bridge_secret', trim($this->input->post('secret', true)));
            update_option('n8n_bridge_hmac_secret', trim($this->input->post('hmac_secret', true)));
            update_option('n8n_bridge_enabled', $this->input->post('enabled') ? '1' : '0');
            update_option('n8n_bridge_queue_enabled', $this->input->post('queue_enabled') ? '1' : '0');
            update_option('n8n_bridge_queue_max_retries', (string) max(1, min(10, (int) $this->input->post('queue_max_retries'))));

            // Triggers + Override URLs
            foreach (self::ALLOWED_EVENT_TYPES as $event) {
                $trigger_key = 'n8n_trigger_' . $event;
                update_option($trigger_key, $this->input->post($trigger_key) ? '1' : '0');

                $url_key = $trigger_key . '_url';
                $override_url = trim($this->input->post($url_key, true));
                if (!empty($override_url) && !filter_var($override_url, FILTER_VALIDATE_URL)) {
                    $override_url = '';
                }
                update_option($url_key, $override_url);
            }

            if (function_exists('log_activity')) {
                log_activity('N8N Bridge settings updated');
            }

            set_alert('success', _l('n8n_settings_saved'));
            redirect(admin_url('n8n_bridge/settings'));
            return;
        }

        // Load data
        $this->load->library('n8n_sender');
        $queue_stats = $this->n8n_sender->get_queue_stats();

        $inbound_token = get_option('n8n_inbound_token') ?: '';
        $inbound_url = !empty($inbound_token) ? site_url('n8n_bridge/incoming/' . $inbound_token) : '';

        // Build triggers with override URLs
        $triggers = [];
        foreach (self::ALLOWED_EVENT_TYPES as $event) {
            $triggers[$event] = [
                'enabled'      => get_option('n8n_trigger_' . $event) ?: '0',
                'override_url' => get_option('n8n_trigger_' . $event . '_url') ?: '',
            ];
        }

        $data = [
            'title'             => _l('n8n_integration'),
            'webhook_url'       => get_option('n8n_bridge_webhook_url') ?: '',
            'secret'            => get_option('n8n_bridge_secret') ?: '',
            'hmac_secret'       => get_option('n8n_bridge_hmac_secret') ?: '',
            'enabled'           => get_option('n8n_bridge_enabled') ?: '0',
            'queue_enabled'     => get_option('n8n_bridge_queue_enabled') ?: '1',
            'queue_max_retries' => get_option('n8n_bridge_queue_max_retries') ?: '5',
            'queue_stats'       => $queue_stats,
            'last_cron_run'     => get_option('n8n_bridge_last_cron_run') ?: 'Never',
            'inbound_token'     => $inbound_token,
            'inbound_url'       => $inbound_url,
            'triggers'          => $triggers,
            'dashboard_stats'   => $this->get_dashboard_stats(),
            'health_status'     => $this->get_system_health(),
        ];

        $this->load->view('n8n_bridge/settings', $data);
    }

    /**
     * Test webhook connection
     */
    public function test_connection(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        $url = trim($this->input->post('url', true));
        $secret = trim($this->input->post('secret', true)) ?: '';

        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->output_json(['success' => false, 'message' => _l('n8n_invalid_url')]);
            return;
        }

        $this->load->library('n8n_sender');
        $result = $this->n8n_sender->send_webhook($url, 'test.ping', ['event' => 'ping', 'message' => 'Hello from Perfex CRM'], $secret, false);
        $this->output_json($result);
    }

    /**
     * Regenerate inbound token
     */
    public function regenerate_inbound_token(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        try {
            $new_token = bin2hex(random_bytes(16));
            update_option('n8n_inbound_token', $new_token);
            $this->output_json(['success' => true, 'token' => $new_token, 'url' => site_url('n8n_bridge/incoming/' . $new_token), 'message' => 'Token regenerated']);
        } catch (Throwable $e) {
            $this->output_json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Regenerate HMAC secret
     */
    public function regenerate_hmac_secret(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        try {
            $new_secret = bin2hex(random_bytes(32));
            update_option('n8n_bridge_hmac_secret', $new_secret);
            $this->output_json(['success' => true, 'secret' => $new_secret, 'message' => 'HMAC secret regenerated']);
        } catch (Throwable $e) {
            $this->output_json(['success' => false, 'message' => 'Failed: ' . $e->getMessage()]);
        }
    }

    // =========================================================================
    // LOGS PAGE
    // =========================================================================

    public function logs(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            access_denied(self::PERMISSION_FEATURE);
        }
        $this->load->view('n8n_bridge/logs', ['title' => _l('n8n_logs')]);
    }

    public function get_logs_ajax(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
            return;
        }

        $draw = (int) $this->input->get_post('draw');
        $start = max(0, (int) $this->input->get_post('start'));
        $length = min(100, max(10, (int) $this->input->get_post('length')));

        $search_value = '';
        $search = $this->input->get_post('search');
        if (is_array($search) && isset($search['value'])) {
            $search_value = trim($this->security->xss_clean($search['value']));
        }

        $total_records = $this->n8n_model->count_logs();
        $filtered_records = $this->n8n_model->count_logs($search_value);
        $logs = $this->n8n_model->get_logs_paginated($start, $length, 'created_at', 'DESC', $search_value);

        $data = [];
        foreach ($logs as $log) {
            $data[] = [
                'id' => (int) $log->id,
                'date' => html_escape($log->created_at ?? ''),
                'event' => html_escape($log->event_type ?? ''),
                'status' => html_escape($log->status ?? ''),
                'response_code' => (int) ($log->response_code ?? 0),
                'response_message' => html_escape(mb_substr($log->response_message ?? '', 0, 200)),
            ];
        }

        $this->output_json(['draw' => $draw, 'recordsTotal' => $total_records, 'recordsFiltered' => $filtered_records, 'data' => $data]);
    }

    public function get_log_payload(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        $log_id = (int) $this->input->get('id', true);
        $log = $this->n8n_model->get_log($log_id);

        if (!$log) {
            $this->output_json(['success' => false, 'message' => _l('n8n_log_not_found')]);
            return;
        }

        $this->output_json(['success' => true, 'payload' => $log->payload ?? '', 'response' => html_escape($log->response_message ?? '')]);
    }

    public function resend_webhook($log_id = null): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        $log = $this->n8n_model->get_log((int) $log_id);
        if (!$log) {
            $this->output_json(['success' => false, 'message' => _l('n8n_log_not_found')]);
            return;
        }

        $payload_data = json_decode($log->payload ?? '', true);
        if (!is_array($payload_data) || !isset($payload_data['data'])) {
            $this->output_json(['success' => false, 'message' => _l('n8n_invalid_payload_format')]);
            return;
        }

        $webhook_url = get_option('n8n_bridge_webhook_url');
        if (empty($webhook_url)) {
            $this->output_json(['success' => false, 'message' => _l('n8n_webhook_url_not_configured')]);
            return;
        }

        $this->load->library('n8n_sender');
        $result = $this->n8n_sender->send_webhook($webhook_url, $log->event_type ?? '', $payload_data['data'], get_option('n8n_bridge_secret') ?: '');

        if ($result['success']) {
            $this->n8n_model->increment_retry_count((int) $log_id);
        }

        $this->output_json($result);
    }

    public function clear_logs(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'delete')) {
            access_denied(self::PERMISSION_FEATURE);
        }

        if ($this->input->method(true) !== 'POST') {
            show_404();
            return;
        }

        $this->n8n_model->clear_all_logs();
        set_alert('success', _l('n8n_logs_cleared'));
        redirect(admin_url('n8n_bridge/logs'));
    }

    // =========================================================================
    // QUEUE PAGE
    // =========================================================================

    public function queue(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            access_denied(self::PERMISSION_FEATURE);
        }

        $this->load->library('n8n_sender');
        $this->load->view('n8n_bridge/queue', [
            'title' => _l('n8n_queue'),
            'queue_stats' => $this->n8n_sender->get_queue_stats(),
        ]);
    }

    public function get_queue_ajax(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'view')) {
            $this->output_json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
            return;
        }

        $draw = (int) $this->input->get_post('draw');
        $start = max(0, (int) $this->input->get_post('start'));
        $length = min(100, max(10, (int) $this->input->get_post('length')));

        $search_value = '';
        $search = $this->input->get_post('search');
        if (is_array($search) && isset($search['value'])) {
            $search_value = trim($this->security->xss_clean($search['value']));
        }

        $total_records = $this->n8n_model->count_queue();
        $filtered_records = $this->n8n_model->count_queue($search_value);
        $items = $this->n8n_model->get_queue_paginated($start, $length, 'next_attempt_at', 'ASC', $search_value);

        $data = [];
        foreach ($items as $item) {
            $data[] = [
                'id' => (int) $item->id,
                'event_type' => html_escape($item->event_type ?? ''),
                'status' => html_escape($item->status ?? ''),
                'attempts' => (int) ($item->attempts ?? 0),
                'max_attempts' => (int) ($item->max_attempts ?? 5),
                'next_attempt_at' => html_escape($item->next_attempt_at ?? ''),
                'last_error' => html_escape(mb_substr($item->last_error ?? '', 0, 100)),
                'created_at' => html_escape($item->created_at ?? ''),
            ];
        }

        $this->output_json(['draw' => $draw, 'recordsTotal' => $total_records, 'recordsFiltered' => $filtered_records, 'data' => $data]);
    }

    public function process_queue(): void
    {
        $is_cron = defined('CRON') && CRON === true;
        $is_cli = $this->input->is_cli_request();

        if (!$is_cron && !$is_cli) {
            if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
                $this->output_json(['success' => false, 'message' => _l('access_denied')]);
                return;
            }

            if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
                $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
                return;
            }
        }

        $result = $this->execute_queue_processing();
        update_option('n8n_bridge_last_cron_run', date('Y-m-d H:i:s'));

        if ($this->input->is_ajax_request()) {
            $this->output_json($result);
            return;
        }

        if ($is_cron || $is_cli) {
            log_message('info', 'N8N Bridge Queue: ' . json_encode($result));
        }
    }

    public function execute_queue_processing(int $batch_size = 10): array
    {
        $processed = $successful = $failed = 0;

        try {
            if (get_option('n8n_bridge_queue_enabled') !== '1') {
                return ['success' => false, 'processed' => 0, 'successful' => 0, 'failed' => 0, 'message' => 'Queue disabled'];
            }

            $this->load->library('n8n_sender');
            $items = $this->n8n_sender->get_pending_queue_items($batch_size);

            if (empty($items)) {
                return ['success' => true, 'processed' => 0, 'successful' => 0, 'failed' => 0, 'message' => 'No pending items'];
            }

            foreach ($items as $item) {
                $processed++;
                $result = $this->n8n_sender->send_queued_webhook((int) $item->id, $item->webhook_url, $item->payload, $item->event_type);
                $this->n8n_sender->update_queue_item((int) $item->id, $result['success'], $result['message'], $result['http_code']);

                if ($result['success']) $successful++;
                else $failed++;

                usleep(100000);
            }

            $this->n8n_sender->clean_old_queue_entries(30);

            return ['success' => true, 'processed' => $processed, 'successful' => $successful, 'failed' => $failed, 'message' => "Processed {$processed}: {$successful} success, {$failed} failed"];

        } catch (Throwable $e) {
            return ['success' => false, 'processed' => $processed, 'successful' => $successful, 'failed' => $failed, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function retry_queue_item($queue_id = null): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'edit')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        $item = $this->n8n_model->get_queue_item((int) $queue_id);
        if (!$item) {
            $this->output_json(['success' => false, 'message' => 'Queue item not found']);
            return;
        }

        $this->load->library('n8n_sender');
        $result = $this->n8n_sender->send_queued_webhook((int) $queue_id, $item->webhook_url, $item->payload, $item->event_type);
        $this->n8n_sender->update_queue_item((int) $queue_id, $result['success'], $result['message'], $result['http_code']);

        $this->output_json(['success' => $result['success'], 'message' => $result['success'] ? 'Delivery successful' : 'Failed: ' . $result['message']]);
    }

    public function delete_queue_item($queue_id = null): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'delete')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        $result = $this->n8n_model->delete_queue_item((int) $queue_id);
        $this->output_json(['success' => $result, 'message' => $result ? 'Item deleted' : 'Failed to delete']);
    }

    public function clear_dead_queue(): void
    {
        if (!has_permission(self::PERMISSION_FEATURE, '', 'delete')) {
            $this->output_json(['success' => false, 'message' => _l('access_denied')]);
            return;
        }

        if ($this->input->method(true) !== 'POST' || !$this->verify_csrf_token()) {
            $this->output_json(['success' => false, 'message' => _l('csrf_token_invalid')]);
            return;
        }

        $deleted = $this->n8n_model->clear_dead_queue_items();
        $this->output_json(['success' => true, 'message' => sprintf('Cleared %d dead items', $deleted), 'deleted' => $deleted]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function verify_csrf_token(): bool
    {
        $token_name = $this->security->get_csrf_token_name();
        $token_value = $this->input->get_post($token_name);
        return !empty($token_value) && hash_equals($this->security->get_csrf_hash(), $token_value);
    }

    private function output_json(array $data): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function output_json_raw(array $data, int $status_code = 200): void
    {
        http_response_code($status_code);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
/* |--------------------------------------------------------------------------
| AUTO SEEDER (ГЕНЕРАТОР ЛОГІВ)
|--------------------------------------------------------------------------
| Цей код дозволяє згенерувати фейкові логи, просто додавши ?run_seed=true
| до адреси сторінки налаштувань.
*/

hooks()->add_action('admin_init', 'n8n_bridge_auto_seeder');

function n8n_bridge_auto_seeder() {
    // Перевіряємо, чи є параметр ?run_seed=1 в URL
    if (isset($_GET['run_seed']) && $_GET['run_seed'] == '1') {
        
        // Перевіряємо, чи користувач адмін
        if (!is_admin()) return;

        $CI =& get_instance();
        $CI->load->database();
        
        // 1. ОЧИЩЕННЯ ТАБЛИЦІ (Щоб логи були свіжі)
        $CI->db->query("TRUNCATE TABLE " . db_prefix() . "n8n_logs");
        
        // 2. ГЕНЕРАЦІЯ 30 ЗАПИСІВ
        $events = ['invoice_created', 'lead_created', 'task_updated', 'ticket_created', 'lead_status_changed'];
        // URL вашого n8n (не важливо чи він працює, це просто текст для логів)
        $fake_webhook_url = 'http://localhost:5678/webhook/test-connection'; 
        
        for ($i = 0; $i < 30; $i++) {
             // 80% успіху, 20% помилок для реалістичності
             $is_success = (rand(1, 100) > 20);
             $http_code = $is_success ? 200 : (rand(0,1) ? 404 : 500);
             
             $data = [
                'webhook_url'   => $fake_webhook_url,
                'event_name'    => $events[array_rand($events)],
                'payload'       => json_encode([
                    'id' => rand(100,9999), 
                    'source' => 'perfex', 
                    'timestamp' => time()
                ]),
                'response_code' => $http_code,
                'response_body' => $is_success 
                    ? json_encode(['status' => 'success', 'msg' => 'Workflow started']) 
                    : json_encode(['status' => 'error', 'msg' => 'Bad Gateway']),
                // Випадковий час за останні 24 години
                'recorded_at'   => date('Y-m-d H:i:s', strtotime('-'.rand(1, 1440).' minutes')),
                'duration'      => rand(50, 1200) / 1000 // імітація часу (0.05с - 1.2с)
            ];
            
            $CI->db->insert(db_prefix() . 'n8n_logs', $data);
        }
        
        // 3. ПОВІДОМЛЕННЯ І ПЕРЕАДРЕСАЦІЯ
        set_alert('success', 'Успішно згенеровано 30 логів для скріншотів!');
        // Перенаправляємо назад на логи, прибираючи ?run_seed=1 щоб не зациклило
        redirect(admin_url('n8n_bridge/settings?group=logs'));
    }
}