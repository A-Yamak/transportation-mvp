<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Local webhook receiver for testing ERP callbacks.
 *
 * Usage: php artisan webhook:receive
 *
 * This starts a simple PHP built-in server on port 9999
 * that logs all incoming requests - simulating what Melo ERP would receive.
 */
class WebhookReceiver extends Command
{
    protected $signature = 'webhook:receive {--port=9999 : Port to listen on}';

    protected $description = 'Start a local webhook receiver to test ERP callbacks';

    public function handle(): int
    {
        $port = $this->option('port');
        $receiverPath = storage_path('app/webhook-receiver.php');

        // Create the webhook receiver script
        $script = <<<'PHP'
<?php
// Simple webhook receiver - logs all incoming requests
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];
$headers = getallheaders();
$body = file_get_contents('php://input');

$timestamp = date('Y-m-d H:i:s');
$logEntry = "\n" . str_repeat('=', 60) . "\n";
$logEntry .= "[$timestamp] $method $uri\n";
$logEntry .= str_repeat('-', 60) . "\n";
$logEntry .= "HEADERS:\n";
foreach ($headers as $key => $value) {
    $logEntry .= "  $key: $value\n";
}
$logEntry .= str_repeat('-', 60) . "\n";
$logEntry .= "BODY:\n";
if ($body) {
    $decoded = json_decode($body, true);
    if ($decoded) {
        $logEntry .= json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } else {
        $logEntry .= $body . "\n";
    }
} else {
    $logEntry .= "(empty)\n";
}
$logEntry .= str_repeat('=', 60) . "\n";

// Output to console
echo $logEntry;

// Also save to file
file_put_contents(__DIR__ . '/webhook-log.txt', $logEntry, FILE_APPEND);

// Respond with success (what Melo ERP would return)
echo json_encode([
    'success' => true,
    'message' => 'Callback received',
    'timestamp' => $timestamp,
]);
PHP;

        file_put_contents($receiverPath, $script);

        $this->info('');
        $this->info('========================================');
        $this->info('  WEBHOOK RECEIVER STARTED');
        $this->info('========================================');
        $this->info('');
        $this->info("Listening on: http://localhost:{$port}");
        $this->info('');
        $this->info('This simulates Melo ERP receiving callbacks.');
        $this->info('All incoming requests will be logged below.');
        $this->info('');
        $this->info('To test, update business callback_url to:');
        $this->info("  http://host.docker.internal:{$port}/api/delivery-callback");
        $this->info('');
        $this->info('Press Ctrl+C to stop.');
        $this->info('');
        $this->info(str_repeat('-', 60));

        // Start PHP built-in server
        passthru("php -S localhost:{$port} {$receiverPath}");

        return Command::SUCCESS;
    }
}
