<?php
/**
 * N8N Bridge Settings View - Platinum Release v2.0
 *
 * Professional UI with:
 * - Visual Analytics Dashboard (Chart.js) - DEFAULT TAB
 * - Event-Based Advanced Routing
 * - System Health & Support Suite
 * - CSRF token protection on all forms
 * - XSS-safe output escaping
 * - Proper use of _l() for all strings
 *
 * @package     N8N_Bridge
 * @category    View
 * @author      Perfex CRM Module
 * @version     2.0.0
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
                            <div class="col-md-8">
                                <h4 class="no-margin font-bold">
                                    <i class="fa fa-plug text-primary"></i> <?php echo html_escape($title); ?>
                                    <span class="badge bg-primary mbot5" style="font-size: 11px; vertical-align: middle;">v2.0.0</span>
                                </h4>
                                <p class="text-muted mtop5 no-margin">
                                    <?php echo _l('n8n_integration_desc'); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-right">
                                <a href="<?php echo admin_url('n8n_bridge/logs'); ?>" class="btn btn-default">
                                    <i class="fa fa-list"></i> <?php echo _l('n8n_view_logs'); ?>
                                </a>
                                <a href="<?php echo admin_url('n8n_bridge/queue'); ?>" class="btn btn-default">
                                    <i class="fa fa-clock-o"></i> <?php echo _l('n8n_queue'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Tabs -->
                <div class="panel_s">
                    <div class="panel-body">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation" class="active">
                                <a href="#tab-dashboard" aria-controls="tab-dashboard" role="tab" data-toggle="tab">
                                    <i class="fa fa-dashboard"></i> <?php echo _l('n8n_dashboard'); ?>
                                </a>
                            </li>
                            <li role="presentation">
                                <a href="#tab-settings" aria-controls="tab-settings" role="tab" data-toggle="tab">
                                    <i class="fa fa-cogs"></i> <?php echo _l('n8n_webhook_settings'); ?>
                                </a>
                            </li>
                            <li role="presentation">
                                <a href="#tab-triggers" aria-controls="tab-triggers" role="tab" data-toggle="tab">
                                    <i class="fa fa-bolt"></i> <?php echo _l('n8n_triggers_routing'); ?>
                                </a>
                            </li>
                            <li role="presentation">
                                <a href="#tab-health" aria-controls="tab-health" role="tab" data-toggle="tab">
                                    <i class="fa fa-heartbeat"></i> <?php echo _l('n8n_health_support'); ?>
                                </a>
                            </li>
                        </ul>

                        <!-- Tab Content -->
                        <div class="tab-content mtop20">
                            
                            <!-- ============================================================= -->
                            <!-- TAB: DASHBOARD (DEFAULT) -->
                            <!-- ============================================================= -->
                            <div role="tabpanel" class="tab-pane active" id="tab-dashboard">
                                <!-- Summary Cards -->
                                <div class="row">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="panel_s">
                                            <div class="panel-body text-center">
                                                <h3 class="no-margin text-primary" id="stat-total">
                                                    <?php echo number_format($dashboard_stats['summary']['total_webhooks']); ?>
                                                </h3>
                                                <p class="text-muted no-margin"><?php echo _l('n8n_total_webhooks'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="panel_s">
                                            <div class="panel-body text-center">
                                                <h3 class="no-margin text-success" id="stat-success-rate">
                                                    <?php echo $dashboard_stats['summary']['success_rate']; ?>%
                                                </h3>
                                                <p class="text-muted no-margin"><?php echo _l('n8n_success_rate'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="panel_s">
                                            <div class="panel-body text-center">
                                                <h3 class="no-margin text-warning" id="stat-queue">
                                                    <?php echo number_format($dashboard_stats['summary']['queue_pending']); ?>
                                                </h3>
                                                <p class="text-muted no-margin"><?php echo _l('n8n_queue_pending'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="panel_s">
                                            <div class="panel-body text-center">
                                                <h3 class="no-margin text-info" style="font-size: 16px;" id="stat-last-activity">
                                                    <?php echo $dashboard_stats['summary']['last_activity'] 
                                                        ? date('M d, H:i', strtotime($dashboard_stats['summary']['last_activity'])) 
                                                        : _l('n8n_never'); ?>
                                                </h3>
                                                <p class="text-muted no-margin"><?php echo _l('n8n_last_activity'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Charts Row -->
                                <div class="row">
                                    <!-- Success vs Failure Doughnut -->
                                    <div class="col-md-4">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">
                                                    <i class="fa fa-pie-chart"></i> <?php echo _l('n8n_success_vs_failure'); ?>
                                                </h4>
                                            </div>
                                            <div class="panel-body" style="height: 280px;">
                                                <canvas id="chart-success-failure"></canvas>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Traffic Timeline Line Chart -->
                                    <div class="col-md-8">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">
                                                    <i class="fa fa-line-chart"></i> <?php echo _l('n8n_traffic_timeline'); ?>
                                                </h4>
                                            </div>
                                            <div class="panel-body" style="height: 280px;">
                                                <canvas id="chart-timeline"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Top Triggers Bar Chart -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="panel_s">
                                            <div class="panel-heading">
                                                <h4 class="panel-title">
                                                    <i class="fa fa-bar-chart"></i> <?php echo _l('n8n_top_triggers'); ?>
                                                </h4>
                                            </div>
                                            <div class="panel-body" style="height: 300px;">
                                                <canvas id="chart-triggers"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ============================================================= -->
                            <!-- TAB: WEBHOOK SETTINGS -->
                            <!-- ============================================================= -->
                            <div role="tabpanel" class="tab-pane" id="tab-settings">
                                <?php echo form_open(admin_url('n8n_bridge/settings'), ['id' => 'n8n-settings-form', 'autocomplete' => 'off']); ?>

                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- Webhook URL -->
                                        <div class="form-group">
                                            <label for="webhook_url" class="control-label">
                                                <?php echo _l('n8n_webhook_url'); ?>
                                                <span class="text-danger">*</span>
                                            </label>
                                            <div class="input-group">
                                                <input type="url"
                                                       id="webhook_url"
                                                       name="webhook_url"
                                                       class="form-control"
                                                       value="<?php echo html_escape($webhook_url); ?>"
                                                       placeholder="https://your-n8n-instance.com/webhook/your-webhook-id"
                                                       pattern="https?://.+">
                                                <span class="input-group-btn">
                                                    <button type="button" class="btn btn-info" id="btn-test-connection">
                                                        <i class="fa fa-plug"></i> <?php echo _l('n8n_test_connection'); ?>
                                                    </button>
                                                </span>
                                            </div>
                                            <small class="help-block text-muted"><?php echo _l('n8n_webhook_url_desc'); ?></small>
                                        </div>

                                        <!-- Secret Key -->
                                        <div class="form-group">
                                            <label for="secret" class="control-label"><?php echo _l('n8n_secret_key'); ?></label>
                                            <div class="input-group">
                                                <input type="password"
                                                       id="secret"
                                                       name="secret"
                                                       class="form-control"
                                                       value="<?php echo html_escape($secret); ?>"
                                                       placeholder="<?php echo _l('n8n_secret_placeholder'); ?>"
                                                       autocomplete="new-password">
                                                <span class="input-group-btn">
                                                    <button type="button" class="btn btn-default" id="btn-toggle-secret">
                                                        <i class="fa fa-eye"></i>
                                                    </button>
                                                </span>
                                            </div>
                                            <small class="help-block text-muted"><?php echo _l('n8n_secret_key_desc'); ?></small>
                                        </div>

                                        <!-- HMAC Secret -->
                                        <div class="form-group">
                                            <label for="hmac_secret" class="control-label"><?php echo _l('n8n_hmac_secret'); ?></label>
                                            <div class="input-group">
                                                <input type="text"
                                                       id="hmac_secret"
                                                       name="hmac_secret"
                                                       class="form-control"
                                                       value="<?php echo html_escape($hmac_secret); ?>"
                                                       readonly>
                                                <span class="input-group-btn">
                                                    <button type="button" class="btn btn-warning" id="btn-regenerate-hmac">
                                                        <i class="fa fa-refresh"></i> <?php echo _l('n8n_regenerate'); ?>
                                                    </button>
                                                </span>
                                            </div>
                                            <small class="help-block text-muted"><?php echo _l('n8n_hmac_secret_desc'); ?></small>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- Module Status -->
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <h5 class="panel-title"><?php echo _l('n8n_module_status'); ?></h5>
                                            </div>
                                            <div class="panel-body">
                                                <div class="checkbox checkbox-primary checkbox-lg">
                                                    <input type="checkbox"
                                                           id="enabled"
                                                           name="enabled"
                                                           value="1"
                                                           <?php echo ($enabled === '1') ? 'checked' : ''; ?>>
                                                    <label for="enabled">
                                                        <strong><?php echo _l('n8n_enable_module'); ?></strong>
                                                    </label>
                                                </div>
                                                <hr>
                                                <div class="checkbox checkbox-primary">
                                                    <input type="checkbox"
                                                           id="queue_enabled"
                                                           name="queue_enabled"
                                                           value="1"
                                                           <?php echo ($queue_enabled === '1') ? 'checked' : ''; ?>>
                                                    <label for="queue_enabled"><?php echo _l('n8n_enable_queue'); ?></label>
                                                </div>
                                                <div class="form-group mtop10">
                                                    <label class="control-label"><?php echo _l('n8n_max_retries'); ?></label>
                                                    <select name="queue_max_retries" class="form-control">
                                                        <?php for ($i = 1; $i <= 10; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo ($queue_max_retries == $i) ? 'selected' : ''; ?>>
                                                            <?php echo $i; ?>
                                                        </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Inbound Webhook -->
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <h5 class="panel-title"><?php echo _l('n8n_inbound_webhook'); ?></h5>
                                            </div>
                                            <div class="panel-body">
                                                <div class="form-group">
                                                    <label class="control-label"><?php echo _l('n8n_inbound_url'); ?></label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" id="inbound_url" 
                                                               value="<?php echo html_escape($inbound_url); ?>" readonly>
                                                        <span class="input-group-btn">
                                                            <button type="button" class="btn btn-default" id="btn-copy-inbound" title="Copy">
                                                                <i class="fa fa-copy"></i>
                                                            </button>
                                                        </span>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-warning btn-block" id="btn-regenerate-token">
                                                    <i class="fa fa-refresh"></i> <?php echo _l('n8n_regenerate_token'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa fa-save"></i> <?php echo _l('n8n_save_settings'); ?>
                                </button>

                                <?php echo form_close(); ?>
                            </div>

                            <!-- ============================================================= -->
                            <!-- TAB: TRIGGERS & ADVANCED ROUTING -->
                            <!-- ============================================================= -->
                            <div role="tabpanel" class="tab-pane" id="tab-triggers">
                                <?php echo form_open(admin_url('n8n_bridge/settings'), ['id' => 'n8n-triggers-form']); ?>

                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle"></i>
                                    <strong><?php echo _l('n8n_routing_info_title'); ?></strong>
                                    <?php echo _l('n8n_routing_info_desc'); ?>
                                </div>

                                <div class="row">
                                    <!-- Invoices & Leads -->
                                    <div class="col-md-6">
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">
                                                    <i class="fa fa-file-text-o"></i> <?php echo _l('n8n_invoices_leads'); ?>
                                                </h5>
                                            </div>
                                            <div class="panel-body">
                                                <?php 
                                                $invoice_lead_events = ['invoice_created', 'invoice_updated', 'lead_created', 'lead_updated'];
                                                foreach ($invoice_lead_events as $event): 
                                                    $trigger_key = 'n8n_trigger_' . $event;
                                                    $is_enabled = isset($triggers[$event]['enabled']) && $triggers[$event]['enabled'] === '1';
                                                    $override_url = $triggers[$event]['override_url'] ?? '';
                                                ?>
                                                <div class="trigger-item mbot15">
                                                    <div class="checkbox checkbox-primary">
                                                        <input type="checkbox"
                                                               id="<?php echo $trigger_key; ?>"
                                                               name="<?php echo $trigger_key; ?>"
                                                               value="1"
                                                               class="trigger-checkbox"
                                                               <?php echo $is_enabled ? 'checked' : ''; ?>>
                                                        <label for="<?php echo $trigger_key; ?>">
                                                            <strong><?php echo _l('n8n_trigger_' . $event); ?></strong>
                                                        </label>
                                                        <button type="button" class="btn btn-xs btn-default pull-right toggle-override" 
                                                                data-target="override-<?php echo $event; ?>">
                                                            <i class="fa fa-link"></i> <?php echo _l('n8n_override_url'); ?>
                                                        </button>
                                                    </div>
                                                    <div class="override-url-container collapse <?php echo !empty($override_url) ? 'in' : ''; ?>" 
                                                         id="override-<?php echo $event; ?>">
                                                        <div class="form-group mtop10 mbot0">
                                                            <input type="url"
                                                                   name="<?php echo $trigger_key; ?>_url"
                                                                   class="form-control input-sm"
                                                                   value="<?php echo html_escape($override_url); ?>"
                                                                   placeholder="<?php echo _l('n8n_override_url_placeholder'); ?>">
                                                            <small class="text-muted"><?php echo _l('n8n_override_url_hint'); ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Tasks & Tickets -->
                                    <div class="col-md-6">
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">
                                                    <i class="fa fa-tasks"></i> <?php echo _l('n8n_tasks_tickets'); ?>
                                                </h5>
                                            </div>
                                            <div class="panel-body">
                                                <?php 
                                                $task_ticket_events = ['task_created', 'task_updated', 'ticket_created', 'ticket_updated'];
                                                foreach ($task_ticket_events as $event): 
                                                    $trigger_key = 'n8n_trigger_' . $event;
                                                    $is_enabled = isset($triggers[$event]['enabled']) && $triggers[$event]['enabled'] === '1';
                                                    $override_url = $triggers[$event]['override_url'] ?? '';
                                                ?>
                                                <div class="trigger-item mbot15">
                                                    <div class="checkbox checkbox-primary">
                                                        <input type="checkbox"
                                                               id="<?php echo $trigger_key; ?>"
                                                               name="<?php echo $trigger_key; ?>"
                                                               value="1"
                                                               class="trigger-checkbox"
                                                               <?php echo $is_enabled ? 'checked' : ''; ?>>
                                                        <label for="<?php echo $trigger_key; ?>">
                                                            <strong><?php echo _l('n8n_trigger_' . $event); ?></strong>
                                                        </label>
                                                        <button type="button" class="btn btn-xs btn-default pull-right toggle-override" 
                                                                data-target="override-<?php echo $event; ?>">
                                                            <i class="fa fa-link"></i> <?php echo _l('n8n_override_url'); ?>
                                                        </button>
                                                    </div>
                                                    <div class="override-url-container collapse <?php echo !empty($override_url) ? 'in' : ''; ?>" 
                                                         id="override-<?php echo $event; ?>">
                                                        <div class="form-group mtop10 mbot0">
                                                            <input type="url"
                                                                   name="<?php echo $trigger_key; ?>_url"
                                                                   class="form-control input-sm"
                                                                   value="<?php echo html_escape($override_url); ?>"
                                                                   placeholder="<?php echo _l('n8n_override_url_placeholder'); ?>">
                                                            <small class="text-muted"><?php echo _l('n8n_override_url_hint'); ?></small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa fa-save"></i> <?php echo _l('n8n_save_settings'); ?>
                                </button>

                                <?php echo form_close(); ?>
                            </div>

                            <!-- ============================================================= -->
                            <!-- TAB: HEALTH & SUPPORT -->
                            <!-- ============================================================= -->
                            <div role="tabpanel" class="tab-pane" id="tab-health">
                                <div class="row">
                                    <div class="col-md-8">
                                        <!-- System Health Status -->
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">
                                                    <i class="fa fa-heartbeat"></i> <?php echo _l('n8n_system_health'); ?>
                                                    <span class="pull-right">
                                                        <span id="health-overall-badge" class="label label-<?php 
                                                            echo $health_status['overall_status'] === 'healthy' ? 'success' : 
                                                                ($health_status['overall_status'] === 'warning' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo ucfirst($health_status['overall_status']); ?>
                                                        </span>
                                                    </span>
                                                </h5>
                                            </div>
                                            <div class="panel-body">
                                                <table class="table table-striped" id="health-table">
                                                    <thead>
                                                        <tr>
                                                            <th width="30%"><?php echo _l('n8n_health_check'); ?></th>
                                                            <th width="15%"><?php echo _l('n8n_health_status'); ?></th>
                                                            <th width="20%"><?php echo _l('n8n_health_value'); ?></th>
                                                            <th><?php echo _l('n8n_health_message'); ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($health_status['checks'] as $key => $check): ?>
                                                        <tr>
                                                            <td><strong><?php echo html_escape($check['name']); ?></strong></td>
                                                            <td>
                                                                <span class="label label-<?php 
                                                                    echo $check['status'] === 'healthy' ? 'success' : 
                                                                        ($check['status'] === 'warning' ? 'warning' : 'danger'); 
                                                                ?>">
                                                                    <?php echo ucfirst($check['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><code><?php echo html_escape($check['value']); ?></code></td>
                                                            <td class="text-muted"><?php echo html_escape($check['message']); ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                                <button type="button" class="btn btn-default" id="btn-refresh-health">
                                                    <i class="fa fa-refresh"></i> <?php echo _l('n8n_refresh'); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <!-- Auto-Fix Tools -->
                                        <div class="panel panel-warning">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">
                                                    <i class="fa fa-wrench"></i> <?php echo _l('n8n_autofix_tools'); ?>
                                                </h5>
                                            </div>
                                            <div class="panel-body">
                                                <p class="text-muted"><?php echo _l('n8n_autofix_desc'); ?></p>
                                                <button type="button" class="btn btn-warning btn-block" id="btn-reset-stuck">
                                                    <i class="fa fa-refresh"></i> <?php echo _l('n8n_reset_stuck_queue'); ?>
                                                </button>
                                                <small class="help-block"><?php echo _l('n8n_reset_stuck_desc'); ?></small>
                                                <hr>
                                                <button type="button" class="btn btn-danger btn-block" id="btn-clear-dead">
                                                    <i class="fa fa-trash"></i> <?php echo _l('n8n_clear_dead_queue'); ?>
                                                </button>
                                                <small class="help-block"><?php echo _l('n8n_clear_dead_desc'); ?></small>
                                            </div>
                                        </div>

                                        <!-- Quick Stats -->
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">
                                                    <i class="fa fa-clock-o"></i> <?php echo _l('n8n_queue_status'); ?>
                                                </h5>
                                            </div>
                                            <div class="panel-body">
                                                <ul class="list-unstyled">
                                                    <li>
                                                        <span class="text-muted"><?php echo _l('n8n_pending'); ?>:</span>
                                                        <strong class="pull-right"><?php echo $queue_stats['pending']; ?></strong>
                                                    </li>
                                                    <li>
                                                        <span class="text-muted"><?php echo _l('n8n_completed'); ?>:</span>
                                                        <strong class="pull-right text-success"><?php echo $queue_stats['completed']; ?></strong>
                                                    </li>
                                                    <li>
                                                        <span class="text-muted"><?php echo _l('n8n_dead'); ?>:</span>
                                                        <strong class="pull-right text-danger"><?php echo $queue_stats['dead']; ?></strong>
                                                    </li>
                                                    <li class="mtop10 ptop10" style="border-top: 1px solid #eee;">
                                                        <span class="text-muted"><?php echo _l('n8n_last_cron'); ?>:</span>
                                                        <strong class="pull-right"><?php echo html_escape($last_cron_run); ?></strong>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>

                                        <!-- Documentation Links -->
                                        <div class="panel panel-info">
                                            <div class="panel-heading">
                                                <h5 class="panel-title">
                                                    <i class="fa fa-book"></i> <?php echo _l('n8n_documentation'); ?>
                                                </h5>
                                            </div>
                                            <div class="panel-body">
                                                <a href="<?php echo module_dir_url('n8n_bridge', 'README.md'); ?>" 
                                                   target="_blank" class="btn btn-info btn-block">
                                                    <i class="fa fa-file-text-o"></i> <?php echo _l('n8n_view_readme'); ?>
                                                </a>
                                                <a href="<?php echo module_dir_url('n8n_bridge', 'n8n_blueprint.json'); ?>" 
                                                   target="_blank" class="btn btn-default btn-block" download>
                                                    <i class="fa fa-download"></i> <?php echo _l('n8n_download_blueprint'); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

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

    // CSRF Configuration
    var csrfTokenName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

    // Dashboard Stats Data
    var dashboardStats = <?php echo json_encode($dashboard_stats); ?>;

    // =========================================================================
    // CHART.JS - Dashboard Charts
    // =========================================================================
    
    // Chart colors
    var chartColors = {
        success: '#84c529',
        failed: '#fc2d42',
        pending: '#f0ad4e',
        primary: '#03a9f4',
        info: '#00bcd4'
    };

    // 1. Success vs Failure Doughnut Chart
    var ctxDoughnut = document.getElementById('chart-success-failure');
    if (ctxDoughnut) {
        new Chart(ctxDoughnut.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['<?php echo _l('n8n_status_success'); ?>', '<?php echo _l('n8n_status_failed'); ?>', '<?php echo _l('n8n_status_pending'); ?>'],
                datasets: [{
                    data: [
                        dashboardStats.success_failure.success,
                        dashboardStats.success_failure.failed,
                        dashboardStats.success_failure.pending
                    ],
                    backgroundColor: [chartColors.success, chartColors.failed, chartColors.pending],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                },
                cutoutPercentage: 60
            }
        });
    }

    // 2. Traffic Timeline Line Chart
    var ctxTimeline = document.getElementById('chart-timeline');
    if (ctxTimeline) {
        var timelineLabels = dashboardStats.timeline.map(function(d) { return d.label; });
        var timelineSuccess = dashboardStats.timeline.map(function(d) { return d.success; });
        var timelineFailed = dashboardStats.timeline.map(function(d) { return d.failed; });

        new Chart(ctxTimeline.getContext('2d'), {
            type: 'line',
            data: {
                labels: timelineLabels,
                datasets: [{
                    label: '<?php echo _l('n8n_status_success'); ?>',
                    data: timelineSuccess,
                    borderColor: chartColors.success,
                    backgroundColor: 'rgba(132, 197, 41, 0.1)',
                    fill: true,
                    tension: 0.3
                }, {
                    label: '<?php echo _l('n8n_status_failed'); ?>',
                    data: timelineFailed,
                    borderColor: chartColors.failed,
                    backgroundColor: 'rgba(252, 45, 66, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
    }

    // 3. Top Triggers Bar Chart
    var ctxTriggers = document.getElementById('chart-triggers');
    if (ctxTriggers) {
        var triggerLabels = dashboardStats.top_triggers.map(function(t) { return t.event; });
        var triggerCounts = dashboardStats.top_triggers.map(function(t) { return t.count; });
        var triggerSuccess = dashboardStats.top_triggers.map(function(t) { return t.success; });

        new Chart(ctxTriggers.getContext('2d'), {
            type: 'bar',
            data: {
                labels: triggerLabels,
                datasets: [{
                    label: '<?php echo _l('n8n_total'); ?>',
                    data: triggerCounts,
                    backgroundColor: chartColors.primary,
                    borderWidth: 0
                }, {
                    label: '<?php echo _l('n8n_status_success'); ?>',
                    data: triggerSuccess,
                    backgroundColor: chartColors.success,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom'
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }]
                }
            }
        });
    }

    // =========================================================================
    // Settings Tab Functionality
    // =========================================================================

    // Toggle secret visibility
    $('#btn-toggle-secret').on('click', function() {
        var $input = $('#secret');
        var $icon = $(this).find('i');
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Test Connection
    $('#btn-test-connection').on('click', function() {
        var $btn = $(this);
        var url = $.trim($('#webhook_url').val());
        var secret = $.trim($('#secret').val());

        if (!url) {
            alert_float('danger', '<?php echo _l('n8n_url_required'); ?>');
            return;
        }

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l('n8n_testing'); ?>');

        var postData = { url: url, secret: secret };
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/test_connection',
            type: 'POST',
            data: postData,
            dataType: 'json',
            timeout: 15000,
            success: function(response) {
                $btn.prop('disabled', false).html(originalHtml);
                if (response.csrf_hash) csrfHash = response.csrf_hash;
                alert_float(response.success ? 'success' : 'danger', response.message);
            },
            error: function() {
                $btn.prop('disabled', false).html(originalHtml);
                alert_float('danger', '<?php echo _l('n8n_connection_error'); ?>');
            }
        });
    });

    // Regenerate HMAC Secret
    $('#btn-regenerate-hmac').on('click', function() {
        if (!confirm('<?php echo _l('n8n_confirm_regenerate_hmac'); ?>')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/regenerate_hmac_secret',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $('#hmac_secret').val(response.secret);
                    alert_float('success', response.message);
                } else {
                    alert_float('danger', response.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        });
    });

    // Regenerate Inbound Token
    $('#btn-regenerate-token').on('click', function() {
        if (!confirm('<?php echo _l('n8n_confirm_regenerate_token'); ?>')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/regenerate_inbound_token',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false);
                if (response.success) {
                    $('#inbound_url').val(response.url);
                    alert_float('success', response.message);
                } else {
                    alert_float('danger', response.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        });
    });

    // Copy Inbound URL
    $('#btn-copy-inbound').on('click', function() {
        var $input = $('#inbound_url');
        $input.select();
        document.execCommand('copy');
        alert_float('success', '<?php echo _l('n8n_copied'); ?>');
    });

    // =========================================================================
    // Triggers Tab - Override URL Toggle
    // =========================================================================
    
    $('.toggle-override').on('click', function() {
        var targetId = $(this).data('target');
        $('#' + targetId).collapse('toggle');
    });

    // =========================================================================
    // Health Tab Functionality
    // =========================================================================

    // Refresh Health Check
    $('#btn-refresh-health').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> <?php echo _l('n8n_loading'); ?>');

        $.ajax({
            url: admin_url + 'n8n_bridge/health_check_ajax',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                }
            },
            complete: function() {
                $btn.prop('disabled', false).html('<i class="fa fa-refresh"></i> <?php echo _l('n8n_refresh'); ?>');
            }
        });
    });

    // Reset Stuck Queue
    $('#btn-reset-stuck').on('click', function() {
        if (!confirm('<?php echo _l('n8n_confirm_reset_stuck'); ?>')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/reset_stuck_queue',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false);
                alert_float(response.success ? 'success' : 'danger', response.message);
                if (response.success) {
                    setTimeout(function() { location.reload(); }, 1500);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                alert_float('danger', '<?php echo _l('n8n_error'); ?>');
            }
        });
    });

    // Clear Dead Queue
    $('#btn-clear-dead').on('click', function() {
        if (!confirm('<?php echo _l('n8n_confirm_clear_dead'); ?>')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);

        var postData = {};
        postData[csrfTokenName] = csrfHash;

        $.ajax({
            url: admin_url + 'n8n_bridge/clear_dead_queue',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(response) {
                $btn.prop('disabled', false);
                alert_float(response.success ? 'success' : 'danger', response.message);
                if (response.success) {
                    setTimeout(function() { location.reload(); }, 1500);
                }
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
