<?php
/**
 * N8N Bridge Logs View
 *
 * Enterprise Security Grade logs page with:
 * - Server-side DataTable processing for performance
 * - CSRF token protection on all actions
 * - XSS-safe output escaping
 * - Proper use of _l() for all strings
 *
 * @package     N8N_Bridge
 * @category    View
 * @author      Perfex CRM Module
 * @version     2.0.0
 * @since       1.0.0
 *
 * @var string $title Page title
 */

defined('BASEPATH') or exit('No direct script access allowed');
?>

<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <!-- Page Header -->
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h4 class="no-margin font-bold">
                                    <i class="fa fa-list"></i> <?php echo html_escape($title); ?>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('n8n_bridge/settings'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> <?php echo _l('n8n_back_to_settings'); ?>
                                </a>
                                <?php if (has_permission('settings', '', 'delete')): ?>
                                <?php
                                // SECURITY: form_open() with csrf_protection=TRUE automatically includes CSRF token
                                echo form_open(admin_url('n8n_bridge/clear_logs'), [
                                    'id'    => 'clear-logs-form',
                                    'style' => 'display: inline-block;',
                                ]);
                                ?>
                                <button type="button" class="btn btn-danger" id="btn-clear-logs">
                                    <i class="fa fa-trash"></i> <?php echo _l('n8n_clear_logs'); ?>
                                </button>
                                <?php echo form_close(); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table id="n8n-logs-table" class="table table-striped dt-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 180px;">
                                            <?php echo _l('n8n_log_date'); ?>
                                        </th>
                                        <th style="width: 180px;">
                                            <?php echo _l('n8n_log_event'); ?>
                                        </th>
                                        <th class="text-center" style="width: 100px;">
                                            <?php echo _l('n8n_log_status'); ?>
                                        </th>
                                        <th class="text-center" style="width: 100px;">
                                            <?php echo _l('n8n_log_response_code'); ?>
                                        </th>
                                        <th class="text-center" style="width: 180px;">
                                            <?php echo _l('n8n_log_actions'); ?>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Server-side DataTable will populate this -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Payload Modal -->
<div class="modal fade" id="payload-modal" tabindex="-1" role="dialog" aria-labelledby="payload-modal-title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo _l('close'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="payload-modal-title">
                    <i class="fa fa-code"></i> <?php echo _l('n8n_view_payload'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li role="presentation" class="active">
                        <a href="#payload-tab" aria-controls="payload-tab" role="tab" data-toggle="tab">
                            <i class="fa fa-arrow-right"></i> <?php echo _l('n8n_payload'); ?>
                        </a>
                    </li>
                    <li role="presentation">
                        <a href="#response-tab" aria-controls="response-tab" role="tab" data-toggle="tab">
                            <i class="fa fa-arrow-left"></i> <?php echo _l('n8n_response'); ?>
                        </a>
                    </li>
                </ul>
                <div class="tab-content mtop15">
                    <div role="tabpanel" class="tab-pane active" id="payload-tab">
                        <pre id="payload-content" class="json-viewer"></pre>
                    </div>
                    <div role="tabpanel" class="tab-pane" id="response-tab">
                        <pre id="response-content" class="json-viewer"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?php echo _l('close'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php init_foot(); ?>

<style>
.json-viewer {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
    font-size: 12px;
    line-height: 1.5;
    max-height: 450px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.label-status {
    display: inline-block;
    min-width: 70px;
    text-align: center;
}

#n8n-logs-table tbody td {
    vertical-align: middle;
}
</style>

<script>
$(document).ready(function() {
    'use strict';

    // SECURITY: Store CSRF token for AJAX requests
    var csrfTokenName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

    // Initialize DataTable with server-side processing
    var logsTable = $('#n8n-logs-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: admin_url + 'n8n_bridge/get_logs_ajax',
            type: 'POST',
            data: function(d) {
                // SECURITY: Include CSRF token
                d[csrfTokenName] = csrfHash;
                return d;
            },
            error: function(xhr, error, thrown) {
                console.error('DataTable AJAX error:', error);
                alert_float('danger', '<?php echo _l('n8n_error_loading_logs'); ?>');
            }
        },
        columns: [
            {
                data: 'date',
                className: 'text-center',
                render: function(data) {
                    if (!data) return '-';
                    // Format date
                    try {
                        var date = new Date(data);
                        return date.toLocaleString();
                    } catch (e) {
                        return escapeHtml(data);
                    }
                }
            },
            {
                data: 'event',
                render: function(data) {
                    // SECURITY: Escape output
                    return '<code>' + escapeHtml(data || '') + '</code>';
                }
            },
            {
                data: 'status',
                className: 'text-center',
                render: function(data) {
                    // SECURITY: Whitelist allowed values
                    var labelClass = 'label-default';
                    var status = String(data || '').toLowerCase();

                    if (status === 'success') {
                        labelClass = 'label-success';
                    } else if (status === 'failed') {
                        labelClass = 'label-danger';
                    } else if (status === 'pending') {
                        labelClass = 'label-warning';
                    }

                    return '<span class="label label-status ' + labelClass + '">' + escapeHtml(data || 'unknown') + '</span>';
                }
            },
            {
                data: 'response_code',
                className: 'text-center',
                render: function(data) {
                    if (!data || data === 0) return '<span class="text-muted">-</span>';

                    var code = parseInt(data, 10);
                    var labelClass = (code >= 200 && code < 300) ? 'label-success' : 'label-danger';

                    return '<span class="label ' + labelClass + '">' + code + '</span>';
                }
            },
            {
                data: 'id',
                className: 'text-center',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var id = parseInt(data, 10);
                    return '<div class="btn-group btn-group-xs">' +
                        '<button type="button" class="btn btn-info btn-view-payload" data-id="' + id + '" title="<?php echo _l('n8n_view_payload'); ?>">' +
                        '<i class="fa fa-eye"></i></button> ' +
                        '<button type="button" class="btn btn-success btn-resend" data-id="' + id + '" title="<?php echo _l('n8n_resend'); ?>">' +
                        '<i class="fa fa-refresh"></i></button>' +
                        '</div>';
                }
            }
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        language: {
            emptyTable: '<?php echo _l('n8n_no_logs'); ?>',
            processing: '<i class="fa fa-spinner fa-spin"></i> <?php echo _l('processing'); ?>',
            search: '<?php echo _l('n8n_search'); ?>:',
            lengthMenu: '<?php echo _l('n8n_show_entries'); ?>',
            info: '<?php echo _l('n8n_showing_entries'); ?>',
            paginate: {
                first: '<?php echo _l('first'); ?>',
                last: '<?php echo _l('last'); ?>',
                next: '<?php echo _l('next'); ?>',
                previous: '<?php echo _l('previous'); ?>'
            }
        },
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rt<"row"<"col-sm-5"i><"col-sm-7"p>>'
    });

    /**
     * Escape HTML to prevent XSS
     * SECURITY: Always escape user-generated content
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    /**
     * Format JSON for display
     */
    function formatJson(str) {
        try {
            var obj = JSON.parse(str);
            return JSON.stringify(obj, null, 2);
        } catch (e) {
            return str || '';
        }
    }

    // View Payload button
    $(document).on('click', '.btn-view-payload', function() {
        var logId = parseInt($(this).data('id'), 10);

        if (!logId || logId <= 0) {
            alert_float('danger', '<?php echo _l('n8n_invalid_log_id'); ?>');
            return;
        }

        // Show loading in modal
        $('#payload-content').text('<?php echo _l('n8n_loading'); ?>...');
        $('#response-content').text('<?php echo _l('n8n_loading'); ?>...');
        $('#payload-modal').modal('show');

        $.ajax({
            url: admin_url + 'n8n_bridge/get_log_payload',
            type: 'GET',
            data: { id: logId },
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response.success) {
                    // SECURITY: Use .text() to prevent XSS
                    $('#payload-content').text(formatJson(response.payload || '{}'));
                    $('#response-content').text(response.response || '<?php echo _l('n8n_no_response'); ?>');
                } else {
                    $('#payload-content').text('<?php echo _l('n8n_error'); ?>: ' + (response.message || '<?php echo _l('n8n_unknown_error'); ?>'));
                    $('#response-content').text('');
                }
            },
            error: function() {
                $('#payload-content').text('<?php echo _l('n8n_connection_error'); ?>');
                $('#response-content').text('');
            }
        });
    });

    // Resend button
    $(document).on('click', '.btn-resend', function() {
        var $btn = $(this);
        var logId = parseInt($btn.data('id'), 10);

        if (!logId || logId <= 0) {
            alert_float('danger', '<?php echo _l('n8n_invalid_log_id'); ?>');
            return;
        }

        // Confirm action
        if (!confirm('<?php echo _l('n8n_confirm_resend'); ?>')) {
            return;
        }

        // Disable button
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        // SECURITY: Include CSRF token
        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/resend_webhook/' + logId,
            type: 'POST',
            data: postData,
            dataType: 'json',
            timeout: 15000,
            success: function(response) {
                $btn.prop('disabled', false).html(originalHtml);

                // Update CSRF hash for next request
                if (response.csrf_hash) {
                    csrfHash = response.csrf_hash;
                }

                if (response.success) {
                    alert_float('success', response.message || '<?php echo _l('n8n_webhook_resent'); ?>');
                    // Reload table
                    logsTable.ajax.reload(null, false);
                } else {
                    alert_float('danger', response.message || '<?php echo _l('n8n_resend_failed'); ?>');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                alert_float('danger', '<?php echo _l('n8n_connection_error'); ?>');
            }
        });
    });

    // Clear logs button
    $('#btn-clear-logs').on('click', function() {
        if (confirm('<?php echo _l('n8n_confirm_clear_logs'); ?>')) {
            $('#clear-logs-form').submit();
        }
    });
});
</script>

</body>
</html>
