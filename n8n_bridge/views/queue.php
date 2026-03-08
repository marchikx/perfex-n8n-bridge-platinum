<?php
/**
 * N8N Bridge Queue View - Platinum Release v2.0
 *
 * Queue management page with:
 * - Server-side DataTable processing
 * - CSRF token protection on all actions
 * - XSS-safe output escaping
 * - Retry and delete capabilities
 *
 * @package     N8N_Bridge
 * @category    View
 * @author      Perfex CRM Module
 * @version     2.0.0
 *
 * @var string $title Page title
 * @var array  $queue_stats Queue statistics
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
                                    <i class="fa fa-clock-o"></i> <?php echo html_escape($title); ?>
                                </h4>
                            </div>
                            <div class="col-md-6 text-right">
                                <a href="<?php echo admin_url('n8n_bridge/settings'); ?>" class="btn btn-default">
                                    <i class="fa fa-arrow-left"></i> <?php echo _l('n8n_back_to_settings'); ?>
                                </a>
                                <?php if (has_permission('settings', '', 'edit')): ?>
                                <button type="button" class="btn btn-primary" id="btn-process-queue">
                                    <i class="fa fa-play"></i> <?php echo _l('n8n_process_queue'); ?>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Queue Stats -->
                <div class="row">
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="no-margin text-warning"><?php echo (int) $queue_stats['pending']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('n8n_pending'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="no-margin text-info"><?php echo (int) $queue_stats['processing']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('processing'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="no-margin text-success"><?php echo (int) $queue_stats['completed']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('n8n_completed'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="no-margin text-danger"><?php echo (int) $queue_stats['failed']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('n8n_status_failed'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="no-margin text-muted"><?php echo (int) $queue_stats['dead']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('n8n_dead'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <div class="panel_s">
                            <div class="panel-body text-center">
                                <h3 class="no-margin"><?php echo (int) $queue_stats['total']; ?></h3>
                                <p class="text-muted no-margin"><?php echo _l('n8n_total'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Queue Table -->
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table id="n8n-queue-table" class="table table-striped dt-table">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 60px;">ID</th>
                                        <th style="width: 160px;"><?php echo _l('n8n_log_event'); ?></th>
                                        <th class="text-center" style="width: 100px;"><?php echo _l('n8n_log_status'); ?></th>
                                        <th class="text-center" style="width: 100px;"><?php echo _l('n8n_attempts'); ?></th>
                                        <th style="width: 160px;"><?php echo _l('n8n_next_attempt'); ?></th>
                                        <th><?php echo _l('n8n_last_error'); ?></th>
                                        <th class="text-center" style="width: 140px;"><?php echo _l('n8n_log_actions'); ?></th>
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

<?php init_foot(); ?>

<script>
$(document).ready(function() {
    'use strict';

    var csrfTokenName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

    // Initialize DataTable
    var queueTable = $('#n8n-queue-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: admin_url + 'n8n_bridge/get_queue_ajax',
            type: 'POST',
            data: function(d) {
                d[csrfTokenName] = csrfHash;
                return d;
            },
            error: function(xhr, error, thrown) {
                console.error('DataTable AJAX error:', error);
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        },
        columns: [
            { data: 'id', className: 'text-center' },
            {
                data: 'event_type',
                render: function(data) {
                    return '<code>' + escapeHtml(data || '') + '</code>';
                }
            },
            {
                data: 'status',
                className: 'text-center',
                render: function(data) {
                    var labelClass = 'label-default';
                    var status = String(data || '').toLowerCase();
                    if (status === 'completed') labelClass = 'label-success';
                    else if (status === 'pending') labelClass = 'label-warning';
                    else if (status === 'dead') labelClass = 'label-danger';
                    else if (status === 'failed') labelClass = 'label-danger';
                    return '<span class="label ' + labelClass + '">' + escapeHtml(data) + '</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                render: function(data, type, row) {
                    return row.attempts + ' / ' + row.max_attempts;
                }
            },
            {
                data: 'next_attempt_at',
                render: function(data) {
                    if (!data) return '-';
                    try {
                        var date = new Date(data);
                        return date.toLocaleString();
                    } catch (e) {
                        return escapeHtml(data);
                    }
                }
            },
            {
                data: 'last_error',
                render: function(data) {
                    if (!data) return '<span class="text-muted">-</span>';
                    var text = String(data);
                    if (text.length > 80) text = text.substring(0, 80) + '...';
                    return '<small class="text-danger">' + escapeHtml(text) + '</small>';
                }
            },
            {
                data: 'id',
                className: 'text-center',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    var id = parseInt(data, 10);
                    var html = '<div class="btn-group btn-group-xs">';
                    if (row.status === 'pending' || row.status === 'dead') {
                        html += '<button type="button" class="btn btn-success btn-retry" data-id="' + id + '" title="<?php echo _l('n8n_resend'); ?>"><i class="fa fa-refresh"></i></button> ';
                    }
                    html += '<button type="button" class="btn btn-danger btn-delete" data-id="' + id + '" title="<?php echo _l('delete'); ?>"><i class="fa fa-trash"></i></button>';
                    html += '</div>';
                    return html;
                }
            }
        ],
        order: [[4, 'asc']],
        pageLength: 25,
        language: {
            emptyTable: '<?php echo _l('n8n_queue_empty'); ?>',
            processing: '<i class="fa fa-spinner fa-spin"></i> <?php echo _l('processing'); ?>'
        }
    });

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Process Queue
    $('#btn-process-queue').on('click', function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l('processing'); ?>');

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/process_queue',
            type: 'POST',
            data: postData,
            dataType: 'json',
            timeout: 60000,
            success: function(response) {
                $btn.prop('disabled', false).html(originalHtml);
                alert_float(response.success ? 'success' : 'warning', response.message);
                queueTable.ajax.reload(null, false);
                setTimeout(function() { location.reload(); }, 1500);
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        });
    });

    // Retry Queue Item
    $(document).on('click', '.btn-retry', function() {
        var $btn = $(this);
        var id = parseInt($btn.data('id'), 10);
        if (!id) return;

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/retry_queue_item/' + id,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i>');
                alert_float(response.success ? 'success' : 'danger', response.message);
                queueTable.ajax.reload(null, false);
            },
            error: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i>');
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        });
    });

    // Delete Queue Item
    $(document).on('click', '.btn-delete', function() {
        var $btn = $(this);
        var id = parseInt($btn.data('id'), 10);
        if (!id) return;

        if (!confirm('<?php echo _l('n8n_confirm_delete'); ?>')) return;

        $btn.prop('disabled', true);

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/delete_queue_item/' + id,
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                alert_float(response.success ? 'success' : 'danger', response.message);
                queueTable.ajax.reload(null, false);
            },
            error: function() {
                $btn.prop('disabled', false);
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        });
    });
});
</script>

</body>
</html>
