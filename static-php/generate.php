<?php
declare(strict_types=1);

require __DIR__ . '/src/SidrenaStatic.php';
$sidrena = SidrenaStatic::fromConfigFile(__DIR__ . '/config.php');

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        echo "Web generiranje prihvaća samo POST zahtjev.\n";
        exit;
    }

    $config = $sidrena->config();
    $expected = getenv('SIDRENA_GENERATE_TOKEN');
    if (!is_string($expected) || $expected === '') {
        $expected = (string) ($config['web_generate_token'] ?? '');
    }

    $provided = isset($_SERVER['HTTP_X_SIDRENA_TOKEN']) ? trim((string) $_SERVER['HTTP_X_SIDRENA_TOKEN']) : '';
    if ($provided === '' && isset($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/^Bearer\s+(.+)$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $match)) {
        $provided = trim((string) $match[1]);
    }
    if ($provided === '' && isset($_POST['token'])) {
        $provided = (string) $_POST['token'];
    }

    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
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
