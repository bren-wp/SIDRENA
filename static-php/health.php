<?php
declare(strict_types=1);

require __DIR__ . '/src/SidrenaStatic.php';
$sidrena = SidrenaStatic::fromConfigFile(__DIR__ . '/config.php');
$status = $sidrena->generateIfNeeded();
$snapshot = $sidrena->readSnapshot();
$runtime = $sidrena->runtimeInfo();

$ok = !empty($status['ok']) && !empty($snapshot) && !empty($runtime['storage_writable']);
http_response_code($ok ? 200 : 503);
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => $ok,
    'edition' => 'static-php',
    'version' => SidrenaStatic::VERSION,
    'generated_at' => $status['generated_at'] ?? null,
    'counts' => [
        'products' => isset($snapshot['products']) && is_array($snapshot['products']) ? count($snapshot['products']) : 0,
        'services' => isset($snapshot['services']) && is_array($snapshot['services']) ? count($snapshot['services']) : 0,
    ],
    'storage_writable' => (bool) $runtime['storage_writable'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
