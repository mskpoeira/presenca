<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$storage = getenv('PRESENCA_STORAGE') ?: '/var/www/storage';
if (!is_dir($storage) && !mkdir($storage, 0770, true) && !is_dir($storage)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao preparar armazenamento.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$file = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'contador.txt';
$fp = fopen($file, 'c+');

if ($fp === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Falha ao registrar presença.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!flock($fp, LOCK_EX)) {
    fclose($fp);
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Sistema ocupado. Tente novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

rewind($fp);
$current = trim(stream_get_contents($fp) ?: '');
$count = ctype_digit($current) ? (int)$current : 0;
$count++;

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, (string)$count);
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
