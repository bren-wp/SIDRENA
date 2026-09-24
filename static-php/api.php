<?php
declare(strict_types=1);

require __DIR__ . '/src/SidrenaStatic.php';
$sidrena = SidrenaStatic::fromConfigFile(__DIR__ . '/config.php');
$sidrena->generateIfNeeded();
$snapshot = $sidrena->readSnapshot();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60, stale-while-revalidate=300');

if (!$snapshot) {
    http_response_code(503);
    echo json_encode([
        'ok' => false,
        'edition' => 'static-php',
        'version' => SidrenaStatic::VERSION,
        'message' => 'Sidrena snapshot još nije dostupan.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'ok' => true,
    'edition' => 'static-php',
    'version' => SidrenaStatic::VERSION,
    'generated_at' => $snapshot['generated_at'] ?? null,
    'location' => $snapshot['location'] ?? [],
    'products' => $snapshot['products'] ?? [],
    'services' => $snapshot['services'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
