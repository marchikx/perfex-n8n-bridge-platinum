<?php
/**
 * N8N Bridge Module Installation - Platinum Release v2.0
 *
 * Complete installation with ALL features:
 * - v1.0: Basic logs table and options
 * - v1.1: Queue table (tbln8n_queue) + Inbound token
 * - v2.0: Event routing options + Dashboard support
 *
 * @package     N8N_Bridge
 * @category    Installation
 * @author      Perfex CRM Module
 * @version     2.0.0
 * @since       1.0.0
 */

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Install N8N Bridge module
 *
 * Creates ALL necessary database tables and initializes ALL settings.
 *
 * @return bool True on success, false on failure
 */
function n8n_bridge_install(): bool
{
    $CI =& get_instance();
    $CI->load->database();

    $success = true;
    $db_prefix = db_prefix();
    $logs_table = $db_prefix . 'n8n_logs';
    $queue_table = $db_prefix . 'n8n_queue';

    // =========================================================================
    // STEP 1: Create Logs Table (v1.0)
    // =========================================================================
    try {
        if (!$CI->db->table_exists($logs_table)) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$logs_table}` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_type` VARCHAR(100) NOT NULL COMMENT 'Event type identifier',
                `payload` LONGTEXT NULL COMMENT 'JSON payload sent to webhook',
                `response_code` INT(11) NULL DEFAULT NULL COMMENT 'HTTP response code',
                `response_message` TEXT NULL COMMENT 'Response body or error message',
                `status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending' COMMENT 'Delivery status',
                `retry_count` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
                `created_at` DATETIME NOT NULL COMMENT 'Log entry creation time',
                PRIMARY KEY (`id`),
                INDEX `idx_n8n_logs_event_type` (`event_type`),
                INDEX `idx_n8n_logs_status` (`status`),
                INDEX `idx_n8n_logs_created_at` (`created_at`),
                INDEX `idx_n8n_logs_status_created` (`status`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='N8N Bridge webhook delivery logs';";

            $CI->db->query($sql);
            log_message('info', 'N8N Bridge: Logs table created successfully');
        }
    } catch (Throwable $e) {
        log_message('error', 'N8N Bridge: Failed to create logs table - ' . $e->getMessage());
        $success = false;
    }

    // =========================================================================
    // STEP 2: Create Queue Table (v1.1 Enterprise - Resilient Delivery)
    // =========================================================================
    try {
        if (!$CI->db->table_exists($queue_table)) {
            $sql = "CREATE TABLE IF NOT EXISTS `{$queue_table}` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `event_type` VARCHAR(100) NOT NULL COMMENT 'Event type identifier',
                `payload` LONGTEXT NOT NULL COMMENT 'JSON payload to send',
                `webhook_url` VARCHAR(2048) NOT NULL COMMENT 'Target webhook URL',
                `status` ENUM('pending','processing','completed','failed','dead') NOT NULL DEFAULT 'pending' COMMENT 'Queue item status',
                `attempts` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of delivery attempts',
                `max_attempts` INT(11) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Maximum retry attempts',
                `last_attempt_at` DATETIME NULL DEFAULT NULL COMMENT 'Last delivery attempt timestamp',
                `next_attempt_at` DATETIME NOT NULL COMMENT 'Next scheduled retry timestamp',
                `last_error` TEXT NULL COMMENT 'Last error message',
                `last_response_code` INT(11) NULL DEFAULT NULL COMMENT 'Last HTTP response code',
                `created_at` DATETIME NOT NULL COMMENT 'Queue entry creation time',
                `completed_at` DATETIME NULL DEFAULT NULL COMMENT 'Successful delivery timestamp',
                `priority` TINYINT(3) UNSIGNED NOT NULL DEFAULT 5 COMMENT 'Priority 1-10 (1=highest)',
                `checksum` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA256 payload checksum for deduplication',
                PRIMARY KEY (`id`),
                INDEX `idx_n8n_queue_status` (`status`),
                INDEX `idx_n8n_queue_next_attempt` (`next_attempt_at`),
                INDEX `idx_n8n_queue_status_next` (`status`, `next_attempt_at`),
                INDEX `idx_n8n_queue_event_type` (`event_type`),
                INDEX `idx_n8n_queue_created` (`created_at`),
                INDEX `idx_n8n_queue_checksum` (`checksum`),
                INDEX `idx_n8n_queue_priority_next` (`priority`, `next_attempt_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            COMMENT='N8N Bridge resilient delivery queue with exponential backoff';";

            $CI->db->query($sql);
            log_message('info', 'N8N Bridge: Queue table created successfully');
        }
    } catch (Throwable $e) {
        log_message('error', 'N8N Bridge: Failed to create queue table - ' . $e->getMessage());
        $success = false;
    }

    // =========================================================================
    // STEP 3: Initialize ALL Options
    // =========================================================================
    try {
        if (function_exists('add_option') && function_exists('get_option')) {
            
            // -----------------------------------------------------------------
            // Core Settings (v1.0)
            // -----------------------------------------------------------------
            $core_options = [
                'n8n_bridge_webhook_url'   => '',
                'n8n_bridge_secret'        => '',
                'n8n_bridge_enabled'       => '0',
            ];

            foreach ($core_options as $key => $value) {
                if (get_option($key) === false) {
                    add_option($key, $value);
                }
            }

            // -----------------------------------------------------------------
            // Enterprise Security (v1.1) - HMAC + Queue + Inbound
            // -----------------------------------------------------------------
            
            // Generate HMAC secret if not exists
            if (get_option('n8n_bridge_hmac_secret') === false) {
                $hmac_secret = bin2hex(random_bytes(32)); // 64 hex characters
                add_option('n8n_bridge_hmac_secret', $hmac_secret);
                log_message('info', 'N8N Bridge: HMAC secret generated');
            }

            // Queue system options
            if (get_option('n8n_bridge_queue_enabled') === false) {
                add_option('n8n_bridge_queue_enabled', '1'); // Enabled by default
            }
            if (get_option('n8n_bridge_queue_max_retries') === false) {
                add_option('n8n_bridge_queue_max_retries', '5');
            }
            if (get_option('n8n_bridge_last_cron_run') === false) {
                add_option('n8n_bridge_last_cron_run', '');
            }

            // Generate secure inbound token (32 hex characters)
            if (get_option('n8n_inbound_token') === false) {
                $inbound_token = bin2hex(random_bytes(16));
                add_option('n8n_inbound_token', $inbound_token);
                log_message('info', 'N8N Bridge: Inbound authentication token generated');
            }

            // -----------------------------------------------------------------
            // Event Triggers + Routing URLs (v1.0 + v2.0)
            // -----------------------------------------------------------------
            $event_types = [
                'invoice_created',
                'invoice_updated',
                'lead_created',
                'lead_updated',
                'task_created',
                'task_updated',
                'ticket_created',
                'ticket_updated',
            ];

            foreach ($event_types as $event) {
                // Trigger enabled/disabled (v1.0)
                $trigger_key = 'n8n_trigger_' . $event;
                if (get_option($trigger_key) === false) {
                    add_option($trigger_key, '0'); // Disabled by default
                }

                // Event-specific override URL (v2.0 Platinum - Advanced Routing)
                $url_key = $trigger_key . '_url';
                if (get_option($url_key) === false) {
                    add_option($url_key, '');
                }
            }

            log_message('info', 'N8N Bridge: All options initialized successfully');
        }
    } catch (Throwable $e) {
        log_message('error', 'N8N Bridge: Failed to initialize options - ' . $e->getMessage());
    }

    // =========================================================================
    // STEP 4: Log Installation
    // =========================================================================
    try {
        if (function_exists('log_activity')) {
            log_activity('N8N Bridge module installed [Version: 2.0.0 Platinum Release]');
        }
    } catch (Throwable $e) {
        // Silent failure for activity logging
    }

    return $success;
}

/**
 * Uninstall N8N Bridge module
 *
 * Removes database tables. Settings are preserved for data retention.
 *
 * @return bool True on success
 */
function n8n_bridge_uninstall(): bool
{
    $CI =& get_instance();
    $CI->load->database();

    $db_prefix = db_prefix();

    // Drop logs table
    try {
        $CI->db->query("DROP TABLE IF EXISTS `{$db_prefix}n8n_logs`");
        log_message('info', 'N8N Bridge: Logs table dropped during uninstall');
    } catch (Throwable $e) {
        log_message('error', 'N8N Bridge: Failed to drop logs table - ' . $e->getMessage());
    }

    // Drop queue table
    try {
        $CI->db->query("DROP TABLE IF EXISTS `{$db_prefix}n8n_queue`");
        log_message('info', 'N8N Bridge: Queue table dropped during uninstall');
    } catch (Throwable $e) {
        log_message('error', 'N8N Bridge: Failed to drop queue table - ' . $e->getMessage());
    }

    // Log activity
    try {
        if (function_exists('log_activity')) {
            log_activity('N8N Bridge module uninstalled');
        }
    } catch (Throwable $e) {
        // Silent
    }

    return true;
}

/**
 * Upgrade N8N Bridge module
 *
 * Handles database migrations for version upgrades.
 *
 * @param string $from_version Previous version
 * @param string $to_version   New version
 * @return bool True on success
 */
function n8n_bridge_upgrade(string $from_version, string $to_version): bool
{
    $CI =& get_instance();
    $CI->load->database();

    $db_prefix = db_prefix();

    try {
        // ---------------------------------------------------------------------
        // Upgrade to v1.1.0 (Enterprise - Queue + Inbound)
        // ---------------------------------------------------------------------
        if (version_compare($from_version, '1.1.0', '<')) {
            
            // Create queue table if missing
            $queue_table = $db_prefix . 'n8n_queue';
            if (!$CI->db->table_exists($queue_table)) {
                $sql = "CREATE TABLE IF NOT EXISTS `{$queue_table}` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `event_type` VARCHAR(100) NOT NULL,
                    `payload` LONGTEXT NOT NULL,
                    `webhook_url` VARCHAR(2048) NOT NULL,
                    `status` ENUM('pending','processing','completed','failed','dead') NOT NULL DEFAULT 'pending',
                    `attempts` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                    `max_attempts` INT(11) UNSIGNED NOT NULL DEFAULT 5,
                    `last_attempt_at` DATETIME NULL DEFAULT NULL,
                    `next_attempt_at` DATETIME NOT NULL,
                    `last_error` TEXT NULL,
                    `last_response_code` INT(11) NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL,
                    `completed_at` DATETIME NULL DEFAULT NULL,
                    `priority` TINYINT(3) UNSIGNED NOT NULL DEFAULT 5,
                    `checksum` VARCHAR(64) NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    INDEX `idx_n8n_queue_status` (`status`),
                    INDEX `idx_n8n_queue_next_attempt` (`next_attempt_at`),
                    INDEX `idx_n8n_queue_status_next` (`status`, `next_attempt_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                $CI->db->query($sql);
            }

            // Add new options
            if (function_exists('add_option') && function_exists('get_option')) {
                if (get_option('n8n_bridge_hmac_secret') === false) {
                    add_option('n8n_bridge_hmac_secret', bin2hex(random_bytes(32)));
                }
                if (get_option('n8n_bridge_queue_enabled') === false) {
                    add_option('n8n_bridge_queue_enabled', '1');
                }
                if (get_option('n8n_bridge_queue_max_retries') === false) {
                    add_option('n8n_bridge_queue_max_retries', '5');
                }
                if (get_option('n8n_bridge_last_cron_run') === false) {
                    add_option('n8n_bridge_last_cron_run', '');
                }
                if (get_option('n8n_inbound_token') === false) {
                    add_option('n8n_inbound_token', bin2hex(random_bytes(16)));
                }
            }

            log_message('info', 'N8N Bridge: Upgraded to v1.1.0 - Enterprise features enabled');
        }

        // ---------------------------------------------------------------------
        // Upgrade to v2.0.0 (Platinum - Dashboard + Routing)
        // ---------------------------------------------------------------------
        if (version_compare($from_version, '2.0.0', '<') && version_compare($to_version, '2.0.0', '>=')) {
            
            // Add event-specific routing URLs
            if (function_exists('add_option') && function_exists('get_option')) {
                $event_types = [
                    'invoice_created', 'invoice_updated',
                    'lead_created', 'lead_updated',
                    'task_created', 'task_updated',
                    'ticket_created', 'ticket_updated',
                ];

                foreach ($event_types as $event) {
                    $url_key = 'n8n_trigger_' . $event . '_url';
                    if (get_option($url_key) === false) {
                        add_option($url_key, '');
                    }
                }

                // Ensure all enterprise options exist
                if (get_option('n8n_bridge_hmac_secret') === false) {
                    add_option('n8n_bridge_hmac_secret', bin2hex(random_bytes(32)));
                }
                if (get_option('n8n_inbound_token') === false) {
                    add_option('n8n_inbound_token', bin2hex(random_bytes(16)));
                }
            }

            log_message('info', 'N8N Bridge: Upgraded to v2.0.0 - Platinum features enabled');
        }

        log_message('info', "N8N Bridge: Upgrade completed from {$from_version} to {$to_version}");
        return true;

    } catch (Throwable $e) {
        log_message('error', 'N8N Bridge: Upgrade failed - ' . $e->getMessage());
        return false;
    }
}

/**
 * Activate N8N Bridge module
 *
 * @return void
 */
function n8n_bridge_activate(): void
{
    n8n_bridge_install();

    if (function_exists('log_activity')) {
        log_activity('N8N Bridge module activated [Version: 2.0.0]');
    }
}

/**
 * Deactivate N8N Bridge module
 *
 * @return void
 */
function n8n_bridge_deactivate(): void
{
    if (function_exists('log_activity')) {
        log_activity('N8N Bridge module deactivated');
    }
}
