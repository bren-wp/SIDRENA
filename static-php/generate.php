<?php
declare(strict_types=1);

require __DIR__ . '/src/SidrenaStatic.php';
$sidrena = SidrenaStatic::fromConfigFile(__DIR__ . '/config.php');

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $config = $sidrena->config();
    $expected = getenv('SIDRENA_GENERATE_TOKEN');
    if (!is_string($expected) || $expected === '') {
        $expected = (string) ($config['web_generate_token'] ?? '');
    }
    $provided = isset($_GET['token']) ? (string) $_GET['token'] : '';
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Web generiranje je isključeno ili token nije valjan.\n";
        exit;
    }
}

try {
    $status = $sidrena->generate();
    $out = json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    if (!$isCli) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo $out;
} catch (Throwable $e) {
    if (!$isCli) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(1);
}
