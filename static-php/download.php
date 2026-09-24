<?php
declare(strict_types=1);

require __DIR__ . '/src/SidrenaStatic.php';
$sidrena = SidrenaStatic::fromConfigFile(__DIR__ . '/config.php');

$key = isset($_GET['file']) ? preg_replace('/[^a-z0-9_]/', '', strtolower((string) $_GET['file'])) : '';
$archive = isset($_GET['archive']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $_GET['archive']) : '';
$file = $sidrena->resolveDownload($key, $archive);

if (!$file) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Datoteka nije pronađena.\n";
    exit;
}

header('Content-Type: ' . $file['mime']);
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $file['name']) . '"');
header('Content-Length: ' . (string) filesize($file['path']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
readfile($file['path']);
