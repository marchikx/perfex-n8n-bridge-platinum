<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Seed extends AdminController
{
    public function index()
    {
        // 1. Перевірка прав (тільки адмін може це робити)
        if (!is_admin()) {
            access_denied('N8N Bridge Seeder');
        }

        $this->load->database();

        // 2. Очищаємо старі логи (щоб таблиця була красивою)
        $this->db->query("TRUNCATE TABLE " . db_prefix() . "n8n_logs");

        echo '<div style="font-family:sans-serif; padding:40px; text-align:center;">';
        echo '<h1 style="color:#28b8da;">Generating Platinum Data...</h1>';

        // 3. Налаштування для генерації
        $events = ['invoice_created', 'lead_created', 'task_updated', 'ticket_created', 'invoice_updated', 'lead_status_changed'];
        
        // Імітуємо ваш Docker n8n
        $webhook_url = 'http://localhost:5678/webhook-test/a4c92f-perfex-crm-bridge';
        
        $count = 0;

        // 4. Генеруємо 25 записів
        for ($i = 0; $i < 25; $i++) {
            
            // Випадкова подія
            $event = $events[array_rand($events)];
            
            // 90% успішних, 10% помилок (для реалістичності)
            $is_success = (rand(1, 100) > 10); 
            $http_code = $is_success ? 200 : (rand(0,1) ? 404 : 500);
            
            // Випадковий час за останні 3 дні
            $date = date('Y-m-d H:i:s', strtotime('-' . rand(0, 72) . ' hours -' . rand(0, 59) . ' minutes'));

            // Дані, що записуються в базу
            $data = [
                'webhook_url'   => $webhook_url,
                'event_name'    => $event,
                'payload'       => json_encode([
                    'event' => $event,
                    'source' => 'perfex_crm',
                    'entity_id' => rand(100, 999),
                    'timestamp' => time()
                ]),
                'response_code' => $http_code,
                'response_body' => $is_success 
                    ? json_encode(['status' => 'success', 'message' => 'Workflow started', 'executionId' => rand(1000,9999)]) 
                    : json_encode(['status' => 'error', 'message' => 'Workflow not found']),
                'recorded_at'   => $date,
                'duration'      => rand(150, 800) / 1000 // імітація часу виконання (0.15s - 0.8s)
            ];

            // Вставка в таблицю tbln8n_logs
            $this->db->insert(db_prefix() . 'n8n_logs', $data);
            $count++;
        }

        echo "<h2 style='color:#84c529;'>✅ Успішно! Створено $count логів.</h2>";
        echo "<p>Логи записані ніби вони пішли на <strong>$webhook_url</strong></p>";
        echo "<br><a href='" . admin_url('n8n_bridge/settings?group=logs') . "' style='padding:15px 30px; background:#28b8da; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold;'>👉 ВІДКРИТИ ЛОГИ (ЗРОБИТИ СКРІНШОТ)</a>";
        echo '</div>';
    }
}

// Тимчасовий хук для генерації даних при відкритті налаштувань
hooks()->add_action('admin_init', 'n8n_bridge_auto_seed');

function n8n_bridge_auto_seed() {
    // Перевіряємо, чи ми на сторінці налаштувань нашого модуля
    if (strpos($_SERVER['REQUEST_URI'], 'n8n_bridge/settings') !== false && isset($_GET['seed_now'])) {
        $CI =& get_instance();
        $CI->load->database();
        
        // Очистити таблицю
        $CI->db->query("TRUNCATE TABLE " . db_prefix() . "n8n_logs");
        
        // Генерація 20 записів
        $events = ['invoice_created', 'lead_created', 'task_updated', 'ticket_created'];
        for ($i = 0; $i < 20; $i++) {
             $is_success = (rand(1, 100) > 15);
             $data = [
                'webhook_url'   => 'http://n8n-local:5678/webhook/test',
                'event_name'    => $events[array_rand($events)],
                'payload'       => json_encode(['id' => rand(10,999), 'source' => 'auto-seed']),
                'response_code' => $is_success ? 200 : 500,
                'response_body' => $is_success ? '{"status":"ok"}' : '{"error":"timeout"}',
                'recorded_at'   => date('Y-m-d H:i:s', strtotime('-'.rand(1,50).' hours')),
                'duration'      => rand(100, 500) / 1000
            ];
            $CI->db->insert(db_prefix() . 'n8n_logs', $data);
        }
        
        // Повідомлення і редірект, щоб не зациклилось
        set_alert('success', 'Fake Logs Generated Successfully!');
        redirect(admin_url('n8n_bridge/settings?group=logs'));
    }
}