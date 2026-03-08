<?php
/**
 * N8N Bridge Language File (English) - Platinum Release v2.0
 *
 * Complete language strings for N8N Bridge module.
 * All strings use _l() for proper localization support.
 *
 * @package     N8N_Bridge
 * @category    Language
 * @author      Perfex CRM Module
 * @version     2.0.0
 * @since       1.0.0
 */

defined('BASEPATH') or exit('No direct script access allowed');

// =============================================================================
// MAIN MODULE STRINGS
// =============================================================================

$lang['n8n_bridge']                     = 'N8N Bridge';
$lang['n8n_integration']                = 'N8N Integration';
$lang['n8n_integration_desc']           = 'Professional automation suite connecting Perfex CRM to N8N. Real-time webhooks, advanced routing, and bi-directional data sync.';

// =============================================================================
// DASHBOARD TAB (MODULE A)
// =============================================================================

$lang['n8n_dashboard']                  = 'Dashboard';
$lang['n8n_total_webhooks']             = 'Total Webhooks';
$lang['n8n_success_rate']               = 'Success Rate';
$lang['n8n_queue_pending']              = 'Queue Pending';
$lang['n8n_last_activity']              = 'Last Activity';
$lang['n8n_success_vs_failure']         = 'Success vs Failure';
$lang['n8n_traffic_timeline']           = 'Traffic Timeline (Last 7 Days)';
$lang['n8n_top_triggers']               = 'Top Triggers';
$lang['n8n_total']                      = 'Total';
$lang['n8n_never']                      = 'Never';

// =============================================================================
// SETTINGS TAB
// =============================================================================

$lang['n8n_webhook_settings']           = 'Settings';
$lang['n8n_webhook_url']                = 'Webhook URL';
$lang['n8n_webhook_url_desc']           = 'Enter your N8N webhook URL. This is the URL from your N8N webhook trigger node.';
$lang['n8n_secret_key']                 = 'Secret Key';
$lang['n8n_secret_placeholder']         = 'Enter a secure secret key';
$lang['n8n_secret_key_desc']            = 'Optional secret key for basic authentication in N8N.';
$lang['n8n_hmac_secret']                = 'HMAC Secret (Auto-generated)';
$lang['n8n_hmac_secret_desc']           = 'HMAC-SHA256 signature secret. Used to verify webhook authenticity in N8N.';
$lang['n8n_regenerate']                 = 'Regenerate';
$lang['n8n_enable_module']              = 'Enable N8N Bridge Module';
$lang['n8n_enable_module_desc']         = 'When disabled, no webhooks will be sent.';
$lang['n8n_enable_queue']               = 'Enable Queue System';
$lang['n8n_max_retries']                = 'Max Retry Attempts';
$lang['n8n_module_status']              = 'Module Status';
$lang['n8n_save_settings']              = 'Save Settings';
$lang['n8n_settings_saved']             = 'Settings saved successfully.';

// =============================================================================
// INBOUND WEBHOOK
// =============================================================================

$lang['n8n_inbound_webhook']            = 'Inbound Webhook (N8N → Perfex)';
$lang['n8n_inbound_url']                = 'Inbound URL';
$lang['n8n_regenerate_token']           = 'Regenerate Token';
$lang['n8n_confirm_regenerate_token']   = 'This will invalidate the current token. Any N8N workflows using the old URL will stop working. Continue?';
$lang['n8n_confirm_regenerate_hmac']    = 'This will change the HMAC secret. Make sure to update it in your N8N workflows. Continue?';
$lang['n8n_copied']                     = 'Copied to clipboard!';

// =============================================================================
// TRIGGERS & ROUTING TAB (MODULE B)
// =============================================================================

$lang['n8n_triggers_routing']           = 'Triggers & Routing';
$lang['n8n_enable_triggers']            = 'Enable Triggers';
$lang['n8n_enable_triggers_desc']       = 'Select which CRM events should trigger webhooks.';
$lang['n8n_invoices_leads']             = 'Invoices & Leads';
$lang['n8n_tasks_tickets']              = 'Tasks & Tickets';

$lang['n8n_trigger_invoice_created']    = 'Invoice Created';
$lang['n8n_trigger_invoice_updated']    = 'Invoice Updated';
$lang['n8n_trigger_lead_created']       = 'Lead Created';
$lang['n8n_trigger_lead_updated']       = 'Lead Updated';
$lang['n8n_trigger_task_created']       = 'Task Created';
$lang['n8n_trigger_task_updated']       = 'Task Updated';
$lang['n8n_trigger_ticket_created']     = 'Ticket Created';
$lang['n8n_trigger_ticket_updated']     = 'Ticket Updated';

// Advanced Routing (MODULE B)
$lang['n8n_routing_info_title']         = 'Advanced Routing:';
$lang['n8n_routing_info_desc']          = 'You can optionally set a different webhook URL for specific events. If left empty, the global webhook URL will be used.';
$lang['n8n_override_url']               = 'Override URL';
$lang['n8n_override_url_placeholder']   = 'https://your-n8n.com/webhook/specific-workflow (optional)';
$lang['n8n_override_url_hint']          = 'Leave empty to use the global webhook URL';

// =============================================================================
// HEALTH & SUPPORT TAB (MODULE C)
// =============================================================================

$lang['n8n_health_support']             = 'Health & Support';
$lang['n8n_system_health']              = 'System Health';
$lang['n8n_health_check']               = 'Check';
$lang['n8n_health_status']              = 'Status';
$lang['n8n_health_value']               = 'Value';
$lang['n8n_health_message']             = 'Message';
$lang['n8n_refresh']                    = 'Refresh';

// Health Check Items
$lang['n8n_health_queue']               = 'Queue System';
$lang['n8n_health_queue_ok']            = '%d items pending - Queue is healthy';
$lang['n8n_health_queue_high']          = 'Queue has many pending items';
$lang['n8n_health_queue_dead']          = 'Dead items detected - consider cleanup';

$lang['n8n_health_cron']                = 'Cron Status';
$lang['n8n_health_cron_ok']             = 'Cron is running normally';
$lang['n8n_health_cron_never']          = 'Cron has never run - check Perfex cron setup';
$lang['n8n_health_cron_stale']          = 'Cron hasn\'t run in over an hour';

$lang['n8n_health_php']                 = 'PHP Version';
$lang['n8n_health_php_ok']              = 'PHP version is supported';
$lang['n8n_health_php_old']             = 'PHP version is outdated - upgrade recommended';
$lang['n8n_health_php_update']          = 'Consider upgrading to PHP 8.0+';

$lang['n8n_health_curl']                = 'cURL Extension';
$lang['n8n_health_curl_ok']             = 'cURL is installed and working';
$lang['n8n_health_curl_missing']        = 'cURL is required for webhooks';

$lang['n8n_health_webhook']             = 'Webhook URL';
$lang['n8n_health_webhook_ok']          = 'Webhook URL is configured';
$lang['n8n_health_webhook_missing']     = 'Webhook URL not configured';

$lang['n8n_health_token']               = 'Inbound Token';
$lang['n8n_health_token_ok']            = 'Inbound token is configured';
$lang['n8n_health_token_missing']       = 'Inbound token not configured';

$lang['n8n_health_stuck']               = 'Stuck Queue Items';
$lang['n8n_health_stuck_ok']            = 'No stuck items found';
$lang['n8n_health_stuck_found']         = '%d items stuck for more than 24 hours';

$lang['n8n_installed']                  = 'Installed';
$lang['n8n_missing']                    = 'Missing';
$lang['n8n_configured']                 = 'Configured';
$lang['n8n_not_configured']             = 'Not Configured';

// Auto-Fix Tools
$lang['n8n_autofix_tools']              = 'Auto-Fix Tools';
$lang['n8n_autofix_desc']               = 'Use these tools to resolve common issues automatically.';
$lang['n8n_reset_stuck_queue']          = 'Reset Stuck Queue Items';
$lang['n8n_reset_stuck_desc']           = 'Marks queue items pending for 24+ hours as failed.';
$lang['n8n_clear_dead_queue']           = 'Clear Dead Queue Items';
$lang['n8n_clear_dead_desc']            = 'Permanently removes items that exhausted all retries.';
$lang['n8n_confirm_reset_stuck']        = 'This will mark all stuck queue items as failed. Continue?';
$lang['n8n_confirm_clear_dead']         = 'This will permanently delete all dead queue items. Continue?';
$lang['n8n_stuck_reset_success']        = 'Successfully reset %d stuck queue items.';
$lang['n8n_stuck_reset_error']          = 'Failed to reset stuck queue items.';

// Queue Status
$lang['n8n_queue_status']               = 'Queue Status';
$lang['n8n_pending']                    = 'Pending';
$lang['n8n_completed']                  = 'Completed';
$lang['n8n_dead']                       = 'Dead';
$lang['n8n_last_cron']                  = 'Last Cron Run';

// Documentation
$lang['n8n_documentation']              = 'Documentation';
$lang['n8n_view_readme']                = 'View README';
$lang['n8n_download_blueprint']         = 'Download N8N Blueprint';

// =============================================================================
// CONNECTION TEST
// =============================================================================

$lang['n8n_test_connection']            = 'Test Connection';
$lang['n8n_testing']                    = 'Testing...';
$lang['n8n_connection_success']         = 'Connection Successful';
$lang['n8n_connection_success_text']    = 'N8N responded successfully. Your webhook connection is working properly.';
$lang['n8n_connection_failed']          = 'Connection Failed';
$lang['n8n_connection_failed_text']     = 'Failed to connect to N8N webhook. Please verify your URL and try again.';
$lang['n8n_connection_error']           = 'An error occurred while connecting. Please try again.';
$lang['n8n_connection_timeout']         = 'Connection timed out. N8N server may be slow or unreachable.';
$lang['n8n_webhook_url_not_configured'] = 'Webhook URL is not configured. Please configure it in settings.';

// =============================================================================
// VALIDATION MESSAGES
// =============================================================================

$lang['n8n_url_required']               = 'Webhook URL is required.';
$lang['n8n_invalid_url']                = 'Invalid webhook URL format. Please enter a valid URL.';
$lang['n8n_invalid_url_protocol']       = 'Invalid URL protocol. Only HTTP and HTTPS are allowed.';
$lang['n8n_invalid_payload_format']     = 'Invalid payload format. Cannot resend this webhook.';
$lang['n8n_invalid_event_type']         = 'Invalid event type detected.';
$lang['n8n_invalid_log_id']             = 'Invalid log ID.';
$lang['csrf_token_invalid']             = 'Security token expired. Please refresh the page and try again.';

// =============================================================================
// LOGS PAGE
// =============================================================================

$lang['n8n_logs']                       = 'Webhook Logs';
$lang['n8n_view_logs']                  = 'View Logs';
$lang['n8n_back_to_settings']           = 'Back to Settings';
$lang['n8n_clear_logs']                 = 'Clear All Logs';
$lang['n8n_confirm_clear_logs']         = 'Are you sure you want to delete all webhook logs? This action cannot be undone.';
$lang['n8n_logs_cleared']               = 'All logs have been cleared successfully.';
$lang['n8n_logs_clear_error']           = 'Failed to clear logs. Please try again.';
$lang['n8n_no_logs']                    = 'No webhook logs found.';
$lang['n8n_error_loading_logs']         = 'Error loading logs. Please refresh the page.';

// =============================================================================
// LOG TABLE COLUMNS
// =============================================================================

$lang['n8n_log_date']                   = 'Date/Time';
$lang['n8n_log_event']                  = 'Event Type';
$lang['n8n_log_status']                 = 'Status';
$lang['n8n_log_response_code']          = 'HTTP Code';
$lang['n8n_log_actions']                = 'Actions';

// =============================================================================
// LOG ACTIONS
// =============================================================================

$lang['n8n_view_payload']               = 'View Payload';
$lang['n8n_payload']                    = 'Request Payload';
$lang['n8n_response']                   = 'Server Response';
$lang['n8n_resend']                     = 'Resend';
$lang['n8n_confirm_resend']             = 'Are you sure you want to resend this webhook?';
$lang['n8n_webhook_resent']             = 'Webhook resent successfully.';
$lang['n8n_resend_failed']              = 'Failed to resend webhook.';
$lang['n8n_log_not_found']              = 'Log entry not found.';
$lang['n8n_no_response']                = 'No response data available.';
$lang['n8n_loading']                    = 'Loading...';

// =============================================================================
// QUEUE PAGE
// =============================================================================

$lang['n8n_queue']                      = 'Queue';
$lang['n8n_queue_management']           = 'Queue Management';
$lang['n8n_process_queue']              = 'Process Queue Now';
$lang['n8n_queue_empty']                = 'Queue is empty.';
$lang['n8n_attempts']                   = 'Attempts';
$lang['n8n_next_attempt']               = 'Next Attempt';
$lang['n8n_last_error']                 = 'Last Error';
$lang['n8n_confirm_delete']             = 'Are you sure you want to delete this item?';
$lang['delete']                         = 'Delete';

// =============================================================================
// STATUS LABELS
// =============================================================================

$lang['n8n_status_success']             = 'Success';
$lang['n8n_status_failed']              = 'Failed';
$lang['n8n_status_pending']             = 'Pending';
$lang['n8n_status_dead']                = 'Dead';
$lang['n8n_status_completed']           = 'Completed';

// =============================================================================
// DATATABLE STRINGS
// =============================================================================

$lang['n8n_search']                     = 'Search';
$lang['n8n_show_entries']               = 'Show _MENU_ entries';
$lang['n8n_showing_entries']            = 'Showing _START_ to _END_ of _TOTAL_ entries';

// =============================================================================
// GENERAL STRINGS
// =============================================================================

$lang['n8n_error']                      = 'Error';
$lang['n8n_success']                    = 'Success';
$lang['n8n_unknown_error']              = 'An unknown error occurred.';
$lang['close']                          = 'Close';
$lang['cancel']                         = 'Cancel';
$lang['processing']                     = 'Processing...';
$lang['first']                          = 'First';
$lang['last']                           = 'Last';
$lang['next']                           = 'Next';
$lang['previous']                       = 'Previous';
$lang['access_denied']                  = 'Access Denied';
