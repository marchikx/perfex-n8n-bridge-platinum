<?php
/**
 * N8N Bridge Model - Enterprise Grade
 *
 * Data model with:
 * - Query Binding for SQL Injection prevention
 * - Queue table management
 * - Strict type hints
 * - Optimized queries
 *
 * @package     N8N_Bridge
 * @category    Model
 * @author      Perfex CRM Module
 * @version     3.0.0
 * @since       1.0.0
 */

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Class N8n_bridge_model
 *
 * Handles all database operations for the N8N Bridge module.
 */
class N8n_bridge_model extends CI_Model
{
    /**
     * Logs table name
     *
     * @var string
     */
    private string $logs_table;

    /**
     * Queue table name
     *
     * @var string
     */
    private string $queue_table;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->logs_table = db_prefix() . 'n8n_logs';
        $this->queue_table = db_prefix() . 'n8n_queue';
    }

    /**
     * Get full invoice data with all related entities
     *
     * @param int $id Invoice ID
     * @return array<string, mixed>|null
     */
    public function get_full_invoice_data(int $id): ?array
    {
        try {
            if ($id <= 0) {
                return null;
            }

            $this->load->model('invoices_model');

            $invoice = $this->invoices_model->get($id);
            if (!$invoice) {
                return null;
            }

            // Get client data
            $client = null;
            $client_id = (int) ($invoice->clientid ?? 0);
            if ($client_id > 0) {
                $query = $this->db->query(
                    'SELECT userid, company, vat, address, city, state, zip, country, email, phonenumber
                     FROM ' . db_prefix() . 'clients
                     WHERE userid = ?
                     LIMIT 1',
                    [$client_id]
                );
                $client = $query->row();
            }

            // Get invoice items
            $items = [];
            if (method_exists($this->invoices_model, 'get_invoice_items')) {
                $invoice_items = $this->invoices_model->get_invoice_items($id);
                if (!empty($invoice_items)) {
                    foreach ($invoice_items as $item) {
                        $items[] = [
                            'id'               => (int) ($item->id ?? 0),
                            'description'      => (string) ($item->description ?? ''),
                            'long_description' => (string) ($item->long_description ?? ''),
                            'qty'              => (float) ($item->qty ?? 0),
                            'rate'             => (float) ($item->rate ?? 0),
                            'taxname'          => $item->taxname ?? null,
                            'taxrate'          => isset($item->taxrate) ? (float) $item->taxrate : null,
                            'amount'           => (float) ($item->amount ?? 0),
                            'item_order'       => (int) ($item->item_order ?? 0),
                        ];
                    }
                }
            }

            // Get currency symbol
            $currency_symbol = '';
            $currency_id = (int) ($invoice->currency ?? 0);
            if ($currency_id > 0) {
                $query = $this->db->query(
                    'SELECT symbol, name FROM ' . db_prefix() . 'currencies WHERE id = ? LIMIT 1',
                    [$currency_id]
                );
                $currency = $query->row();
                if ($currency) {
                    $currency_symbol = (string) ($currency->symbol ?? '');
                }
            }

            // Generate public invoice link
            $public_link = '';
            if (!empty($invoice->hash)) {
                $public_link = site_url('invoice/' . $id . '/' . $invoice->hash);
            }

            return [
                'invoice'  => [
                    'id'               => (int) $invoice->id,
                    'number'           => (string) ($invoice->number ?? ''),
                    'prefix'           => (string) ($invoice->prefix ?? ''),
                    'date'             => $invoice->date ?? null,
                    'duedate'          => $invoice->duedate ?? null,
                    'status'           => (int) ($invoice->status ?? 0),
                    'subtotal'         => (float) ($invoice->subtotal ?? 0),
                    'total_tax'        => (float) ($invoice->total_tax ?? 0),
                    'total'            => (float) ($invoice->total ?? 0),
                    'discount_percent' => (float) ($invoice->discount_percent ?? 0),
                    'discount_total'   => (float) ($invoice->discount_total ?? 0),
                    'adjustment'       => (float) ($invoice->adjustment ?? 0),
                    'recurring'        => (int) ($invoice->recurring ?? 0),
                    'recurring_type'   => $invoice->recurring_type ?? null,
                    'terms'            => (string) ($invoice->terms ?? ''),
                    'adminnote'        => (string) ($invoice->adminnote ?? ''),
                    'clientnote'       => (string) ($invoice->clientnote ?? ''),
                    'datecreated'      => $invoice->datecreated ?? null,
                    'public_link'      => $public_link,
                ],
                'client'   => $client ? [
                    'id'          => (int) $client->userid,
                    'company'     => (string) ($client->company ?? ''),
                    'vat'         => (string) ($client->vat ?? ''),
                    'email'       => (string) ($client->email ?? ''),
                    'phonenumber' => (string) ($client->phonenumber ?? ''),
                    'address'     => (string) ($client->address ?? ''),
                    'city'        => (string) ($client->city ?? ''),
                    'state'       => (string) ($client->state ?? ''),
                    'zip'         => (string) ($client->zip ?? ''),
                    'country'     => (string) ($client->country ?? ''),
                ] : null,
                'items'    => $items,
                'currency' => [
                    'id'     => $currency_id,
                    'symbol' => $currency_symbol,
                ],
            ];
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting invoice data - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get full lead data with all related entities
     *
     * @param int $id Lead ID
     * @return array<string, mixed>|null
     */
    public function get_full_lead_data(int $id): ?array
    {
        try {
            if ($id <= 0) {
                return null;
            }

            $this->load->model('leads_model');

            $lead = $this->leads_model->get($id);
            if (!$lead) {
                return null;
            }

            // Get status name
            $status_name = '';
            $status_id = (int) ($lead->status ?? 0);
            if ($status_id > 0) {
                $query = $this->db->query(
                    'SELECT name FROM ' . db_prefix() . 'leads_status WHERE id = ? LIMIT 1',
                    [$status_id]
                );
                $status = $query->row();
                $status_name = $status ? (string) ($status->name ?? '') : '';
            }

            // Get source name
            $source_name = '';
            $source_id = (int) ($lead->source ?? 0);
            if ($source_id > 0) {
                $query = $this->db->query(
                    'SELECT name FROM ' . db_prefix() . 'leads_sources WHERE id = ? LIMIT 1',
                    [$source_id]
                );
                $source = $query->row();
                $source_name = $source ? (string) ($source->name ?? '') : '';
            }

            // Get assigned staff info
            $assigned_staff = null;
            $assigned_id = (int) ($lead->assigned ?? 0);
            if ($assigned_id > 0) {
                $this->load->model('staff_model');
                $staff = $this->staff_model->get($assigned_id);
                if ($staff) {
                    $assigned_staff = [
                        'id'        => (int) $staff->staffid,
                        'firstname' => (string) ($staff->firstname ?? ''),
                        'lastname'  => (string) ($staff->lastname ?? ''),
                        'email'     => (string) ($staff->email ?? ''),
                        'full_name' => trim(($staff->firstname ?? '') . ' ' . ($staff->lastname ?? '')),
                    ];
                }
            }

            // Get custom fields
            $custom_fields = [];
            if (function_exists('get_custom_fields_values')) {
                $cf_values = get_custom_fields_values($id, 'leads');
                if (is_array($cf_values)) {
                    foreach ($cf_values as $field) {
                        $custom_fields[] = [
                            'id'    => $field['id'] ?? null,
                            'name'  => (string) ($field['name'] ?? ''),
                            'value' => (string) ($field['value'] ?? ''),
                        ];
                    }
                }
            }

            return [
                'lead'           => [
                    'id'                 => (int) $lead->id,
                    'name'               => (string) ($lead->name ?? ''),
                    'title'              => (string) ($lead->title ?? ''),
                    'email'              => (string) ($lead->email ?? ''),
                    'phonenumber'        => (string) ($lead->phonenumber ?? ''),
                    'company'            => (string) ($lead->company ?? ''),
                    'address'            => (string) ($lead->address ?? ''),
                    'city'               => (string) ($lead->city ?? ''),
                    'state'              => (string) ($lead->state ?? ''),
                    'zip'                => (string) ($lead->zip ?? ''),
                    'country'            => (string) ($lead->country ?? ''),
                    'website'            => (string) ($lead->website ?? ''),
                    'description'        => (string) ($lead->description ?? ''),
                    'dateadded'          => $lead->dateadded ?? null,
                    'lastcontact'        => $lead->lastcontact ?? null,
                    'dateassigned'       => $lead->dateassigned ?? null,
                    'last_status_change' => $lead->last_status_change ?? null,
                    'addedfrom'          => (int) ($lead->addedfrom ?? 0),
                    'is_public'          => (int) ($lead->is_public ?? 0),
                    'lost'               => (int) ($lead->lost ?? 0),
                    'junk'               => (int) ($lead->junk ?? 0),
                ],
                'status'         => [
                    'id'   => $status_id,
                    'name' => $status_name,
                ],
                'source'         => [
                    'id'   => $source_id,
                    'name' => $source_name,
                ],
                'assigned_staff' => $assigned_staff,
                'custom_fields'  => $custom_fields,
            ];
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting lead data - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get full task data with all related entities
     *
     * @param int $id Task ID
     * @return array<string, mixed>|null
     */
    public function get_full_task_data(int $id): ?array
    {
        try {
            if ($id <= 0) {
                return null;
            }

            $this->load->model('tasks_model');

            $task = $this->tasks_model->get($id);
            if (!$task) {
                return null;
            }

            // Get assignees
            $assignees = [];
            if (method_exists($this->tasks_model, 'get_task_assignees')) {
                $task_assignees = $this->tasks_model->get_task_assignees($id);
                if (!empty($task_assignees)) {
                    $this->load->model('staff_model');
                    foreach ($task_assignees as $assignee) {
                        $staff_id = (int) ($assignee->assigneeid ?? $assignee->staffid ?? 0);
                        if ($staff_id > 0) {
                            $staff = $this->staff_model->get($staff_id);
                            if ($staff) {
                                $assignees[] = [
                                    'id'        => (int) $staff->staffid,
                                    'firstname' => (string) ($staff->firstname ?? ''),
                                    'lastname'  => (string) ($staff->lastname ?? ''),
                                    'email'     => (string) ($staff->email ?? ''),
                                    'full_name' => trim(($staff->firstname ?? '') . ' ' . ($staff->lastname ?? '')),
                                ];
                            }
                        }
                    }
                }
            }

            // Get related entity name
            $related_to = null;
            $rel_type = (string) ($task->rel_type ?? '');
            $rel_id = (int) ($task->rel_id ?? 0);

            if (!empty($rel_type) && $rel_id > 0) {
                $related_to = [
                    'type' => $rel_type,
                    'id'   => $rel_id,
                    'name' => '',
                ];

                if ($rel_type === 'project') {
                    $this->load->model('projects_model');
                    $project = $this->projects_model->get($rel_id);
                    if ($project) {
                        $related_to['name'] = (string) ($project->name ?? '');
                    }
                } elseif (in_array($rel_type, ['customer', 'client'], true)) {
                    $this->load->model('clients_model');
                    $client = $this->clients_model->get($rel_id);
                    if ($client) {
                        $related_to['name'] = (string) ($client->company ?? '');
                    }
                }
            }

            $admin_link = admin_url('tasks/view/' . $id);

            return [
                'task'       => [
                    'id'                    => (int) $task->id,
                    'name'                  => (string) ($task->name ?? ''),
                    'description'           => (string) ($task->description ?? ''),
                    'status'                => (int) ($task->status ?? 0),
                    'priority'              => (int) ($task->priority ?? 0),
                    'dateadded'             => $task->dateadded ?? null,
                    'startdate'             => $task->startdate ?? null,
                    'duedate'               => $task->duedate ?? null,
                    'datefinished'          => $task->datefinished ?? null,
                    'addedfrom'             => (int) ($task->addedfrom ?? 0),
                    'is_added_from_contact' => (int) ($task->is_added_from_contact ?? 0),
                    'billable'              => (int) ($task->billable ?? 0),
                    'billed'                => (int) ($task->billed ?? 0),
                    'hourly_rate'           => (float) ($task->hourly_rate ?? 0),
                    'milestone'             => (int) ($task->milestone ?? 0),
                    'visible_to_client'     => (int) ($task->visible_to_client ?? 0),
                    'admin_link'            => $admin_link,
                ],
                'assignees'  => $assignees,
                'related_to' => $related_to,
            ];
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting task data - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get full ticket data
     *
     * @param int $id Ticket ID
     * @return array<string, mixed>|null
     */
    public function get_full_ticket_data(int $id): ?array
    {
        try {
            if ($id <= 0) {
                return null;
            }

            $this->load->model('tickets_model');

            $ticket = $this->tickets_model->get($id);
            if (!$ticket) {
                return null;
            }

            // Get department name
            $department_name = '';
            $department_id = (int) ($ticket->department ?? 0);
            if ($department_id > 0) {
                $query = $this->db->query(
                    'SELECT name FROM ' . db_prefix() . 'departments WHERE departmentid = ? LIMIT 1',
                    [$department_id]
                );
                $dept = $query->row();
                $department_name = $dept ? (string) ($dept->name ?? '') : '';
            }

            $admin_link = admin_url('tickets/ticket/' . $id);

            return [
                'ticket'     => [
                    'id'         => (int) ($ticket->ticketid ?? $id),
                    'subject'    => (string) ($ticket->subject ?? ''),
                    'message'    => (string) ($ticket->message ?? ''),
                    'status'     => (int) ($ticket->status ?? 0),
                    'priority'   => (int) ($ticket->priority ?? 0),
                    'date'       => $ticket->date ?? null,
                    'lastreply'  => $ticket->lastreply ?? null,
                    'admin_link' => $admin_link,
                ],
                'department' => [
                    'id'   => $department_id,
                    'name' => $department_name,
                ],
            ];
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting ticket data - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process webhook event
     *
     * MODULE B: Event-Based Advanced Routing Support
     * The N8n_sender library handles URL routing based on event type.
     *
     * @param string $event_name Event name
     * @param int    $entity_id  Entity ID
     * @return array<string, mixed>
     */
    public function process_event(string $event_name, int $entity_id): array
    {
        try {
            if (!function_exists('get_option')) {
                return ['success' => false, 'message' => 'Perfex options not available'];
            }

            $allowed_events = [
                'invoice_created', 'invoice_updated',
                'lead_created', 'lead_updated',
                'task_created', 'task_updated',
                'ticket_created', 'ticket_updated',
            ];

            if (!in_array($event_name, $allowed_events, true)) {
                return ['success' => false, 'message' => 'Unknown event type'];
            }

            $trigger_key = 'n8n_trigger_' . $event_name;
            if (get_option($trigger_key) !== '1') {
                return ['success' => false, 'message' => 'Trigger disabled'];
            }

            // MODULE B: Check for event-specific URL first, then fall back to global
            $override_url = get_option($trigger_key . '_url');
            $global_url = get_option('n8n_bridge_webhook_url');
            
            // Use override URL if set, otherwise use global URL
            $target_url = !empty($override_url) ? $override_url : $global_url;
            $secret = get_option('n8n_bridge_secret') ?: '';

            if (empty($target_url)) {
                return ['success' => false, 'message' => 'Webhook URL not configured'];
            }

            // Gather data based on event type
            $data = null;
            switch ($event_name) {
                case 'invoice_created':
                case 'invoice_updated':
                    $data = $this->get_full_invoice_data($entity_id);
                    break;

                case 'lead_created':
                case 'lead_updated':
                    $data = $this->get_full_lead_data($entity_id);
                    break;

                case 'task_created':
                case 'task_updated':
                    $data = $this->get_full_task_data($entity_id);
                    break;

                case 'ticket_created':
                case 'ticket_updated':
                    $data = $this->get_full_ticket_data($entity_id);
                    break;
            }

            if (empty($data)) {
                return ['success' => false, 'message' => 'Entity not found'];
            }

            $this->load->library('n8n_sender');
            
            // Pass empty URL to let N8n_sender handle routing, or pass target_url directly
            return $this->n8n_sender->send_webhook($target_url, $event_name, $data, $secret);

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error processing event - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Internal error'];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Log Management Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get logs with pagination
     *
     * @param int    $offset       Offset
     * @param int    $limit        Limit
     * @param string $order_column Order column
     * @param string $order_dir    Order direction
     * @param string $search       Search term
     * @return array<object>
     */
    public function get_logs_paginated(
        int $offset = 0,
        int $limit = 25,
        string $order_column = 'created_at',
        string $order_dir = 'DESC',
        string $search = ''
    ): array {
        try {
            $allowed_columns = ['id', 'event_type', 'status', 'response_code', 'created_at'];
            if (!in_array($order_column, $allowed_columns, true)) {
                $order_column = 'created_at';
            }

            $order_dir = strtoupper($order_dir) === 'ASC' ? 'ASC' : 'DESC';

            $this->db->select('id, event_type, status, response_code, response_message, created_at');
            $this->db->from($this->logs_table);

            if (!empty($search)) {
                $search = '%' . $this->db->escape_like_str($search) . '%';
                $this->db->group_start();
                $this->db->like('event_type', $search, 'none', false);
                $this->db->or_like('status', $search, 'none', false);
                $this->db->or_like('response_message', $search, 'none', false);
                $this->db->group_end();
            }

            $this->db->order_by($order_column, $order_dir);
            $this->db->limit($limit, $offset);

            $query = $this->db->get();
            return $query ? $query->result() : [];

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting logs - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count total logs
     *
     * @param string $search Search term
     * @return int
     */
    public function count_logs(string $search = ''): int
    {
        try {
            $this->db->from($this->logs_table);

            if (!empty($search)) {
                $search = '%' . $this->db->escape_like_str($search) . '%';
                $this->db->group_start();
                $this->db->like('event_type', $search, 'none', false);
                $this->db->or_like('status', $search, 'none', false);
                $this->db->or_like('response_message', $search, 'none', false);
                $this->db->group_end();
            }

            return $this->db->count_all_results();

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error counting logs - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get logs (legacy)
     *
     * @param int $limit  Limit
     * @param int $offset Offset
     * @return array<object>
     */
    public function get_logs(int $limit = 50, int $offset = 0): array
    {
        return $this->get_logs_paginated($offset, $limit, 'created_at', 'DESC', '');
    }

    /**
     * Get single log
     *
     * @param int $id Log ID
     * @return object|null
     */
    public function get_log(int $id): ?object
    {
        try {
            if ($id <= 0) {
                return null;
            }

            $query = $this->db->query(
                'SELECT * FROM ' . $this->logs_table . ' WHERE id = ? LIMIT 1',
                [$id]
            );

            return $query && $query->num_rows() > 0 ? $query->row() : null;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting log - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Increment retry count
     *
     * @param int $log_id Log ID
     * @return bool
     */
    public function increment_retry_count(int $log_id): bool
    {
        try {
            if ($log_id <= 0) {
                return false;
            }

            $this->db->query(
                'UPDATE ' . $this->logs_table . ' SET retry_count = retry_count + 1 WHERE id = ?',
                [$log_id]
            );

            return $this->db->affected_rows() > 0;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error incrementing retry count - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear all logs
     *
     * @return bool
     */
    public function clear_all_logs(): bool
    {
        try {
            $this->db->truncate($this->logs_table);
            return true;
        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error clearing logs - ' . $e->getMessage());
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Queue Management Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get queue items with pagination
     *
     * @param int    $offset       Offset
     * @param int    $limit        Limit
     * @param string $order_column Order column
     * @param string $order_dir    Order direction
     * @param string $search       Search term
     * @return array<object>
     */
    public function get_queue_paginated(
        int $offset = 0,
        int $limit = 25,
        string $order_column = 'next_attempt_at',
        string $order_dir = 'ASC',
        string $search = ''
    ): array {
        try {
            $allowed_columns = ['id', 'event_type', 'status', 'attempts', 'next_attempt_at', 'created_at'];
            if (!in_array($order_column, $allowed_columns, true)) {
                $order_column = 'next_attempt_at';
            }

            $order_dir = strtoupper($order_dir) === 'DESC' ? 'DESC' : 'ASC';

            $this->db->select('id, event_type, status, attempts, max_attempts, next_attempt_at, last_error, last_response_code, created_at');
            $this->db->from($this->queue_table);

            if (!empty($search)) {
                $search = '%' . $this->db->escape_like_str($search) . '%';
                $this->db->group_start();
                $this->db->like('event_type', $search, 'none', false);
                $this->db->or_like('status', $search, 'none', false);
                $this->db->or_like('last_error', $search, 'none', false);
                $this->db->group_end();
            }

            $this->db->order_by($order_column, $order_dir);
            $this->db->limit($limit, $offset);

            $query = $this->db->get();
            return $query ? $query->result() : [];

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting queue items - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Count queue items
     *
     * @param string $search Search term
     * @return int
     */
    public function count_queue(string $search = ''): int
    {
        try {
            $this->db->from($this->queue_table);

            if (!empty($search)) {
                $search = '%' . $this->db->escape_like_str($search) . '%';
                $this->db->group_start();
                $this->db->like('event_type', $search, 'none', false);
                $this->db->or_like('status', $search, 'none', false);
                $this->db->or_like('last_error', $search, 'none', false);
                $this->db->group_end();
            }

            return $this->db->count_all_results();

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error counting queue items - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get single queue item
     *
     * @param int $id Queue item ID
     * @return object|null
     */
    public function get_queue_item(int $id): ?object
    {
        try {
            if ($id <= 0) {
                return null;
            }

            $query = $this->db->query(
                'SELECT * FROM ' . $this->queue_table . ' WHERE id = ? LIMIT 1',
                [$id]
            );

            return $query && $query->num_rows() > 0 ? $query->row() : null;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting queue item - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete queue item
     *
     * @param int $id Queue item ID
     * @return bool
     */
    public function delete_queue_item(int $id): bool
    {
        try {
            if ($id <= 0) {
                return false;
            }

            $this->db->where('id', $id);
            $this->db->delete($this->queue_table);

            return $this->db->affected_rows() > 0;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error deleting queue item - ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear dead queue items
     *
     * @return int Number of items deleted
     */
    public function clear_dead_queue_items(): int
    {
        try {
            $this->db->where('status', 'dead');
            $this->db->delete($this->queue_table);

            return $this->db->affected_rows();

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error clearing dead queue items - ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get queue statistics
     *
     * @return array<string, int>
     */
    public function get_queue_stats(): array
    {
        try {
            $stats = [
                'pending'    => 0,
                'processing' => 0,
                'completed'  => 0,
                'failed'     => 0,
                'dead'       => 0,
                'total'      => 0,
            ];

            $result = $this->db
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

            return $stats;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error getting queue stats - ' . $e->getMessage());
            return [
                'pending'    => 0,
                'processing' => 0,
                'completed'  => 0,
                'failed'     => 0,
                'dead'       => 0,
                'total'      => 0,
            ];
        }
    }

    /**
     * Reset queue item for retry
     *
     * @param int $id Queue item ID
     * @return bool
     */
    public function reset_queue_item(int $id): bool
    {
        try {
            if ($id <= 0) {
                return false;
            }

            $this->db->where('id', $id);
            $this->db->update($this->queue_table, [
                'status'          => 'pending',
                'attempts'        => 0,
                'next_attempt_at' => date('Y-m-d H:i:s'),
                'last_error'      => null,
            ]);

            return $this->db->affected_rows() > 0;

        } catch (Throwable $e) {
            log_message('error', 'N8N Bridge: Error resetting queue item - ' . $e->getMessage());
            return false;
        }
    }
}
