<?php
/**
 * N8N Bridge Module - Platinum Release v2.0
 *
 * Professional Bi-Directional Automation Suite with:
 * - Visual Analytics Dashboard with Chart.js
 * - HMAC-SHA256 signature authentication (Outbound)
 * - Event-based advanced routing (per-event webhook URLs)
 * - Automatic queue fallback with exponential backoff
 * - Inbound webhook receiver for N8N → Perfex data updates
 * - UI Magic Button injection for manual triggers
 * - System Health monitoring & auto-fix tools
 * - Cron-based retry processing
 * - Silent failure protection
 *
 * @package     N8N_Bridge
 * @category    Module
 * @author      Perfex CRM Module
 * @version     2.0.0
 * @since       1.0.0
 */

defined('BASEPATH') or exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| Module Constants
|--------------------------------------------------------------------------
*/
defined('N8N_BRIDGE_MODULE_NAME') or define('N8N_BRIDGE_MODULE_NAME', 'n8n_bridge');
defined('N8N_BRIDGE_MODULE_VERSION') or define('N8N_BRIDGE_MODULE_VERSION', '2.0.0');

/*
|--------------------------------------------------------------------------
| Register Module in Admin Menu
|--------------------------------------------------------------------------
*/
hooks()->add_action('admin_init', 'n8n_bridge_register_menu');
hooks()->add_action('admin_init', 'n8n_bridge_add_permissions');

/*
|--------------------------------------------------------------------------
| Register Cron Hook for Queue Processing
|--------------------------------------------------------------------------
*/
hooks()->add_action('cron_job', 'n8n_bridge_cron_process_queue');

/*
|--------------------------------------------------------------------------
| Register UI Injection Hook (Magic Button)
|--------------------------------------------------------------------------
*/
hooks()->add_action('app_admin_footer', 'n8n_bridge_inject_magic_button_script');

/**
 * Register N8N Bridge menu item in admin sidebar
 *
 * @return void
 */
function n8n_bridge_register_menu(): void
{
    if (!function_exists('has_permission') || !has_permission('settings', '', 'view')) {
        return;
    }

    if (function_exists('add_option_to_menu')) {
        add_option_to_menu(
            'setup',
            'n8n_bridge',
            'N8N Bridge',
            admin_url('n8n_bridge/settings'),
            'fa fa-plug',
            25
        );
    }
}

/**
 * Register module permissions
 *
 * @return void
 */
function n8n_bridge_add_permissions(): void
{
    // Module uses existing 'settings' permission
}

/*
|--------------------------------------------------------------------------
| Cron Queue Processing
|--------------------------------------------------------------------------
*/

/**
 * Process pending queue items via Perfex cron
 *
 * This function is called automatically by Perfex CRM's cron system.
 * It processes pending webhook deliveries with exponential backoff:
 * - Retry 1: After 5 minutes
 * - Retry 2: After 15 minutes
 * - Retry 3: After 1 hour
 * - Retry 4: After 4 hours
 * - Retry 5: After 12 hours (final)
 *
 * @return void
 */
function n8n_bridge_cron_process_queue(): void
{
    try {
        // Check if module and queue are enabled
        if (!function_exists('get_option')) {
            return;
        }

        if (get_option('n8n_bridge_enabled') !== '1') {
            return;
        }

        if (get_option('n8n_bridge_queue_enabled') !== '1') {
            return;
        }

        $CI =& get_instance();

        // Load the controller and execute queue processing
        $CI->load->library('n8n_sender');

        // Get pending items
        $items = $CI->n8n_sender->get_pending_queue_items(10);

        if (empty($items)) {
            // Update last run time even if no items
            update_option('n8n_bridge_last_cron_run', date('Y-m-d H:i:s'));
            return;
        }

        $processed = 0;
        $successful = 0;
        $failed = 0;

        foreach ($items as $item) {
            $processed++;

            // Send the webhook
            $result = $CI->n8n_sender->send_queued_webhook(
                (int) $item->id,
                $item->webhook_url,
                $item->payload,
                $item->event_type
            );

            // Update queue item based on result
            $CI->n8n_sender->update_queue_item(
                (int) $item->id,
                $result['success'],
                $result['message'],
                $result['http_code']
            );

            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }

            // Rate limiting - 100ms between requests
            usleep(100000);
        }

        // Clean old completed entries (older than 30 days)
        $CI->n8n_sender->clean_old_queue_entries(30);

        // Update last run time
        update_option('n8n_bridge_last_cron_run', date('Y-m-d H:i:s'));

        // Log results
        if ($processed > 0) {
            log_message('info', sprintf(
                'N8N Bridge Cron: Processed %d items - %d successful, %d failed',
                $processed,
                $successful,
                $failed
            ));
        }

    } catch (Throwable $e) {
        n8n_bridge_log_error('Cron error: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| UI Injection - Magic Button
|--------------------------------------------------------------------------
*/

/**
 * Inject Magic Button JavaScript into admin footer
 *
 * Adds a "⚡ Run N8N" button to Lead and Invoice detail views.
 * Button triggers manual webhook dispatch with SweetAlert2 feedback.
 *
 * @return void
 */
function n8n_bridge_inject_magic_button_script(): void
{
    // Only inject if module is enabled and user has permission
    if (!function_exists('get_option') || get_option('n8n_bridge_enabled') !== '1') {
        return;
    }

    if (!function_exists('has_permission') || !has_permission('settings', '', 'view')) {
        return;
    }

    // Get CSRF token for AJAX requests
    $CI =& get_instance();
    $csrf_name = $CI->security->get_csrf_token_name();
    $csrf_hash = $CI->security->get_csrf_hash();
    $admin_url = admin_url('n8n_bridge/manual_trigger');

    ?>
    <script type="text/javascript">
    (function() {
        'use strict';

        // Configuration
        var N8N_CONFIG = {
            csrfName: <?php echo json_encode($csrf_name); ?>,
            csrfHash: <?php echo json_encode($csrf_hash); ?>,
            triggerUrl: <?php echo json_encode($admin_url); ?>
        };

        /**
         * Initialize N8N Magic Button injection
         */
        function initN8NMagicButton() {
            var currentUrl = window.location.href;
            var context = detectContext(currentUrl);

            if (context) {
                injectButton(context);
            }
        }

        /**
         * Detect page context (Lead or Invoice)
         * @param {string} url Current page URL
         * @returns {object|null} Context object with type and ID
         */
        function detectContext(url) {
            // Match Lead detail view: /admin/leads/index/{id} or /admin/leads/{id}
            var leadMatch = url.match(/\/admin\/leads(?:\/index)?\/(\d+)/);
            if (leadMatch && leadMatch[1]) {
                return { type: 'lead', id: parseInt(leadMatch[1], 10), event: 'lead_updated' };
            }

            // Match Invoice detail view: /admin/invoices/list_invoices/{id}
            var invoiceMatch = url.match(/\/admin\/invoices\/list_invoices\/(\d+)/);
            if (invoiceMatch && invoiceMatch[1]) {
                return { type: 'invoice', id: parseInt(invoiceMatch[1], 10), event: 'invoice_updated' };
            }

            return null;
        }

        /**
         * Inject the Magic Button into page
         * @param {object} context Page context with type and ID
         */
        function injectButton(context) {
            // Find the appropriate action container
            var actionContainers = [
                '.panel-heading .btn-group',
                '.panel-heading .pull-right',
                '._buttons',
                '.btn-toolbar',
                '.page-title .btn-group'
            ];

            var container = null;
            for (var i = 0; i < actionContainers.length; i++) {
                container = document.querySelector(actionContainers[i]);
                if (container) break;
            }

            // Fallback: create floating button if no container found
            if (!container) {
                createFloatingButton(context);
                return;
            }

            // Check if button already exists
            if (document.getElementById('n8n-magic-btn')) {
                return;
            }

            // Create button element
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'n8n-magic-btn';
            btn.className = 'btn btn-default mleft5';
            btn.innerHTML = '<i class="fa fa-bolt"></i> Run N8N';
            btn.title = 'Trigger N8N workflow for this ' + context.type;
            btn.setAttribute('data-context-type', context.type);
            btn.setAttribute('data-context-id', context.id);
            btn.setAttribute('data-event', context.event);

            // Attach click handler
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                triggerN8NWorkflow(context);
            });

            // Insert button
            container.appendChild(btn);
        }

        /**
         * Create floating button as fallback
         * @param {object} context Page context
         */
        function createFloatingButton(context) {
            if (document.getElementById('n8n-magic-btn-float')) {
                return;
            }

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'n8n-magic-btn-float';
            btn.className = 'btn btn-info';
            btn.innerHTML = '<i class="fa fa-bolt"></i> Run N8N';
            btn.title = 'Trigger N8N workflow for this ' + context.type;
            btn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,0.2);border-radius:4px;';

            btn.addEventListener('click', function(e) {
                e.preventDefault();
                triggerN8NWorkflow(context);
            });

            document.body.appendChild(btn);
        }

        /**
         * Trigger N8N workflow via AJAX
         * @param {object} context Page context with type, id, and event
         */
        function triggerN8NWorkflow(context) {
            // Show loading spinner with SweetAlert2
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Triggering N8N...',
                    html: 'Sending <strong>' + context.type + '</strong> data to N8N workflow.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
            } else if (typeof alert_float !== 'undefined') {
                alert_float('info', 'Triggering N8N workflow...');
            }

            // Prepare request data
            var formData = new FormData();
            formData.append(N8N_CONFIG.csrfName, N8N_CONFIG.csrfHash);
            formData.append('entity_type', context.type);
            formData.append('entity_id', context.id);
            formData.append('event', context.event);

            // Send AJAX request
            fetch(N8N_CONFIG.triggerUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (typeof Swal !== 'undefined') {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message || 'N8N workflow triggered successfully.',
                            timer: 3000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: data.message || 'Failed to trigger N8N workflow.'
                        });
                    }
                } else if (typeof alert_float !== 'undefined') {
                    alert_float(data.success ? 'success' : 'danger', data.message || 'Operation completed.');
                }

                // Update CSRF token if provided
                if (data.csrf_hash) {
                    N8N_CONFIG.csrfHash = data.csrf_hash;
                }
            })
            .catch(function(error) {
                console.error('N8N Trigger Error:', error);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Network error occurred. Please try again.'
                    });
                } else if (typeof alert_float !== 'undefined') {
                    alert_float('danger', 'Network error occurred.');
                }
            });
        }

        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initN8NMagicButton);
        } else {
            initN8NMagicButton();
        }

        // Also initialize on AJAX page loads (for Perfex SPA-like navigation)
        if (typeof $ !== 'undefined') {
            $(document).on('ajaxComplete', function(event, xhr, settings) {
                setTimeout(initN8NMagicButton, 100);
            });
        }
    })();
    </script>
    <?php
}

/*
|--------------------------------------------------------------------------
| Event Processing Functions
|--------------------------------------------------------------------------
*/

/**
 * Safe event processor wrapper
 *
 * CRITICAL: This function ensures the CRM NEVER crashes if the module fails.
 *
 * @param string $event_name Event name
 * @param int    $id         Entity ID
 * @return void
 */
function n8n_bridge_safe_process_event(string $event_name, int $id): void
{
    if ($id <= 0) {
        return;
    }

    try {
        if (!function_exists('get_option')) {
            return;
        }

        $enabled = get_option('n8n_bridge_enabled');
        if ($enabled !== '1') {
            return;
        }

        $CI =& get_instance();
        $CI->load->model('n8n_bridge/N8n_bridge_model', 'n8n_bridge_model');
        $CI->n8n_bridge_model->process_event($event_name, $id);

    } catch (Throwable $e) {
        n8n_bridge_log_error('Hook error: ' . $e->getMessage());
    }
}

/**
 * Log error message safely
 *
 * @param string $message Error message
 * @return void
 */
function n8n_bridge_log_error(string $message): void
{
    try {
        if (function_exists('log_message')) {
            log_message('error', 'N8N Bridge: ' . $message);
        }
    } catch (Throwable $e) {
        // Silent failure
    }
}

/*
|--------------------------------------------------------------------------
| Hook Callback Functions
|--------------------------------------------------------------------------
*/

/**
 * Hook: After invoice added
 *
 * @param mixed $invoice_id Invoice ID
 * @return void
 */
function n8n_bridge_after_invoice_added($invoice_id): void
{
    n8n_bridge_safe_process_event('invoice_created', (int) $invoice_id);
}

/**
 * Hook: Invoice updated
 *
 * @param mixed $invoice_id Invoice ID
 * @return void
 */
function n8n_bridge_invoice_updated($invoice_id): void
{
    n8n_bridge_safe_process_event('invoice_updated', (int) $invoice_id);
}

/**
 * Hook: After lead added
 *
 * @param mixed $lead_id Lead ID
 * @return void
 */
function n8n_bridge_after_lead_added($lead_id): void
{
    n8n_bridge_safe_process_event('lead_created', (int) $lead_id);
}

/**
 * Hook: Lead updated
 *
 * @param mixed $lead_id Lead ID
 * @return void
 */
function n8n_bridge_after_lead_updated($lead_id): void
{
    n8n_bridge_safe_process_event('lead_updated', (int) $lead_id);
}

/**
 * Hook: After task added
 *
 * @param mixed $task_id Task ID
 * @return void
 */
function n8n_bridge_after_task_added($task_id): void
{
    n8n_bridge_safe_process_event('task_created', (int) $task_id);
}

/**
 * Hook: Task updated
 *
 * @param mixed $task_id Task ID
 * @return void
 */
function n8n_bridge_after_task_updated($task_id): void
{
    n8n_bridge_safe_process_event('task_updated', (int) $task_id);
}

/**
 * Hook: Ticket created
 *
 * @param mixed $ticket_id Ticket ID
 * @return void
 */
function n8n_bridge_ticket_created($ticket_id): void
{
    n8n_bridge_safe_process_event('ticket_created', (int) $ticket_id);
}

/**
 * Hook: Ticket updated
 *
 * @param mixed $ticket_id Ticket ID
 * @return void
 */
function n8n_bridge_ticket_updated($ticket_id): void
{
    n8n_bridge_safe_process_event('ticket_updated', (int) $ticket_id);
}

/*
|--------------------------------------------------------------------------
| Register Hooks
|--------------------------------------------------------------------------
*/

if (function_exists('hooks')) {
    // Modern Perfex hook system (3.x+)
    hooks()->add_action('after_invoice_added', 'n8n_bridge_after_invoice_added');
    hooks()->add_action('invoice_updated', 'n8n_bridge_invoice_updated');
    hooks()->add_action('after_lead_added', 'n8n_bridge_after_lead_added');
    hooks()->add_action('after_lead_updated', 'n8n_bridge_after_lead_updated');
    hooks()->add_action('after_task_added', 'n8n_bridge_after_task_added');
    hooks()->add_action('after_task_updated', 'n8n_bridge_after_task_updated');
    hooks()->add_action('ticket_created', 'n8n_bridge_ticket_created');
    hooks()->add_action('ticket_updated', 'n8n_bridge_ticket_updated');

} elseif (function_exists('register_action_hook')) {
    // Legacy hook registration (2.x compatibility)
    register_action_hook('after_invoice_added', 'n8n_bridge_after_invoice_added');
    register_action_hook('invoice_updated', 'n8n_bridge_invoice_updated');
    register_action_hook('after_lead_added', 'n8n_bridge_after_lead_added');
    register_action_hook('after_lead_updated', 'n8n_bridge_after_lead_updated');
    register_action_hook('after_task_added', 'n8n_bridge_after_task_added');
    register_action_hook('after_task_updated', 'n8n_bridge_after_task_updated');
    register_action_hook('ticket_created', 'n8n_bridge_ticket_created');
    register_action_hook('ticket_updated', 'n8n_bridge_ticket_updated');
}
