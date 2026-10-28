<?php
/**
 * Local sender regression tests: php tests/sender-hmac.test.php
 * Loads the real library; replaces Perfex options, logging, DB and HTTP transport.
 * No Perfex installation, n8n instance, database or network is used.
 */
declare(strict_types=1);

define('BASEPATH', __DIR__);
$options = [];
$messages = [];

function get_option(string $name)
{
    return $GLOBALS['options'][$name] ?? false;
}

function log_message(string $level, string $message): void
{
    $GLOBALS['messages'][] = [$level, $message];
}

require __DIR__ . '/../n8n_bridge/libraries/N8n_sender.php';

final class SenderProbe extends N8n_sender
{
    public array $requests = [];
    public array $queued = [];

    public function __construct()
    {
        // Do not bootstrap CodeIgniter or connect to a database.
    }

    protected function execute_curl_request(string $url, string $json, array $headers): array
    {
        $this->requests[] = compact('url', 'json', 'headers');
        return ['success' => true, 'http_code' => 200, 'message' => 'accepted'];
    }

    protected function insert_pending_log(string $event_type, string $payload_json): int
    {
        return 1;
    }

    protected function update_log(int $log_id, int $response_code, string $response_message, string $status): void
    {
    }

    public function add_to_queue(
        string $event_type,
        string $payload_json,
        string $webhook_url,
        string $error_message = '',
        int $response_code = 0
    ): bool {
        $this->queued[] = compact('event_type', 'payload_json', 'webhook_url', 'error_message', 'response_code');
        return true;
    }
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function deliver(SenderProbe $sender, string $path, string $provided = '', bool $queue = true): array
{
    return $path === 'normal'
        ? $sender->send_webhook('https://example.invalid/hook', 'invoice_paid', ['note' => 'Тест / exact bytes'], $provided, $queue)
        : $sender->send_queued_webhook(42, 'https://example.invalid/hook', '{ "note": "Тест / exact bytes" }', 'invoice_paid');
}

$passed = 0;
$failed = 0;
function run_case(string $name, callable $test): void
{
    $GLOBALS['options'] = [];
    $GLOBALS['messages'] = [];
    try {
        $test();
        ++$GLOBALS['passed'];
        echo "ok   {$name}\n";
    } catch (Throwable $e) {
        ++$GLOBALS['failed'];
        fwrite(STDERR, "FAIL {$name}: {$e->getMessage()}\n");
    }
}

foreach (['normal', 'queued'] as $path) {
    foreach (['unset' => [], 'empty' => ['n8n_bridge_hmac_secret' => '', 'n8n_bridge_secret' => '']] as $label => $settings) {
        run_case("{$path}: {$label} secrets block HTTP", function () use ($path, $settings): void {
            $GLOBALS['options'] = $settings + ['n8n_bridge_queue_enabled' => '1'];
            $sender = new SenderProbe();
            $result = deliver($sender, $path);
            check($result['success'] === false, 'must fail');
            check($result['http_code'] === 0, 'must return HTTP code zero');
            check(strpos($result['message'], 'HMAC secret is not configured') !== false, 'must explain configuration failure');
            check($sender->requests === [], 'HTTP transport must not be called');
            if ($path === 'normal') {
                // Keep the existing queue-on-failure contract; retry must also fail closed.
                check($result['queued'] === true && count($sender->queued) === 1, 'queue-enabled failure must retain payload');
                $entry = $sender->queued[0];
                $retry = $sender->send_queued_webhook(43, $entry['webhook_url'], $entry['payload_json'], $entry['event_type']);
                check($retry['success'] === false && $retry['http_code'] === 0, 'queued failure must also be blocked');
                check($sender->requests === [], 'retry of retained payload must not call HTTP');
            }
        });
    }

    $valid = [
        'dedicated beats legacy' => ['hmac-key', 'legacy-key', '', 'hmac-key'],
        'dedicated only' => ['hmac-key', '', '', 'hmac-key'],
        'legacy fallback' => ['', 'legacy-key', '', 'legacy-key'],
        'secret bytes preserved' => ['  hmac key  ', '', '', '  hmac key  '],
    ];
    if ($path === 'normal') {
        $valid['explicit beats both'] = ['hmac-key', 'legacy-key', 'explicit-key', 'explicit-key'];
        $valid['explicit without settings'] = ['', '', 'explicit-key', 'explicit-key'];
    }
    foreach ($valid as $label => [$hmac, $legacy, $provided, $expected]) {
        run_case("{$path}: {$label}", function () use ($path, $hmac, $legacy, $provided, $expected): void {
            $GLOBALS['options'] = ['n8n_bridge_hmac_secret' => $hmac, 'n8n_bridge_secret' => $legacy];
            $sender = new SenderProbe();
            $result = deliver($sender, $path, $provided);
            check($result['success'] === true && $result['http_code'] === 200, 'configured send must succeed');
            check(count($sender->requests) === 1, 'must call HTTP exactly once');
            $request = $sender->requests[0];
            $signature = hash_hmac('sha256', $request['json'], $expected);
            check(in_array('X-Perfex-Signature: ' . $signature, $request['headers'], true), 'signature must use expected secret and exact body');
            check($sender->queued === [], 'successful send must not enqueue');
            if ($path === 'queued') {
                check($request['json'] === '{ "note": "Тест / exact bytes" }', 'queued bytes must be unchanged');
                check(in_array('X-Perfex-Queue-Id: 42', $request['headers'], true), 'queue header must be retained');
                check(in_array('X-Perfex-Retry: true', $request['headers'], true), 'retry header must be retained');
            } else {
                check(json_decode($request['json'], true, 512, JSON_THROW_ON_ERROR)['data']['note'] === 'Тест / exact bytes', 'normal payload must be intact');
            }
        });
    }
}

foreach ([false, true] as $queueFlag) {
    run_case('normal: missing secret, queue flag ' . ($queueFlag ? 'on, setting off' : 'off'), function () use ($queueFlag): void {
        $GLOBALS['options'] = ['n8n_bridge_queue_enabled' => $queueFlag ? '0' : '1'];
        $sender = new SenderProbe();
        $result = deliver($sender, 'normal', '', $queueFlag);
        check($result['success'] === false && $result['http_code'] === 0, 'must fail before HTTP');
        check($result['queued'] === false && $sender->queued === [], 'must respect disabled queue');
        check($sender->requests === [], 'HTTP transport must not be called');
    });
}

echo "{$passed} passed; {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
