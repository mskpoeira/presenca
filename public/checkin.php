<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function isTrustedProxy(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) return false;
    if ($ip === '127.0.0.1' || $ip === '::1') return true;
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

function getClientIp(): string {
    $remote = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($remote !== '' && isTrustedProxy($remote)) {
        $forwarded = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))[0] ?? '');
        if ($forwarded !== '' && filter_var($forwarded, FILTER_VALIDATE_IP)) return $forwarded;
        $real = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if ($real !== '' && filter_var($real, FILTER_VALIDATE_IP)) return $real;
    }
    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : 'indisponivel';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Método não permitido.']);
}

$raw = file_get_contents('php://input');
$input = json_decode($raw ?: '', true);

if (!is_array($input)) {
    respond(400, ['ok' => false, 'error' => 'Dados inválidos.']);
}

$nome = trim((string)($input['nome'] ?? ''));
$telefone = trim((string)($input['telefone'] ?? ''));
$email = strtolower(trim((string)($input['email'] ?? '')));
$bairro = trim((string)($input['bairro'] ?? ''));
$latitude = isset($input['latitude']) && is_numeric($input['latitude']) ? (float)$input['latitude'] : null;
$longitude = isset($input['longitude']) && is_numeric($input['longitude']) ? (float)$input['longitude'] : null;
$accuracy = isset($input['accuracy']) && is_numeric($input['accuracy']) ? max(0, (int)$input['accuracy']) : null;
$ip = getClientIp();

if ($nome === '' || strlen($nome) < 2 || strlen($nome) > 120) {
    respond(422, ['ok' => false, 'error' => 'Informe um nome válido.']);
}

$telefoneDigitos = preg_replace('/\D+/', '', $telefone) ?? '';
if (strlen($telefoneDigitos) < 10 || strlen($telefoneDigitos) > 11) {
    respond(422, ['ok' => false, 'error' => 'Informe um telefone válido com DDD.']);
}

if ($email === '' || strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, ['ok' => false, 'error' => 'Informe um e-mail válido.']);
}

if ($bairro === '' || strlen($bairro) < 2 || strlen($bairro) > 100) {
    respond(422, ['ok' => false, 'error' => 'Informe um bairro válido.']);
}
if ($latitude !== null && ($latitude < -90 || $latitude > 90)) $latitude = null;
if ($longitude !== null && ($longitude < -180 || $longitude > 180)) $longitude = null;

$storage = getenv('PRESENCA_STORAGE') ?: '/var/www/storage';

if (!is_dir($storage) && !mkdir($storage, 0770, true) && !is_dir($storage)) {
    respond(500, ['ok' => false, 'error' => 'Falha ao preparar armazenamento.']);
}

$dbPath = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'presenca.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);

    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS presencas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            evento TEXT NOT NULL,
            nome TEXT NOT NULL,
            telefone TEXT NOT NULL,
            email TEXT,
            bairro TEXT NOT NULL,
            ip TEXT,
            latitude REAL,
            longitude REAL,
            accuracy INTEGER,
            registrado_em TEXT NOT NULL
        )'
    );

    $columns = $pdo->query("PRAGMA table_info(presencas)")->fetchAll();
    $columnNames = array_column($columns, 'name');
    if (!in_array('email', $columnNames, true)) {
        $pdo->exec('ALTER TABLE presencas ADD COLUMN email TEXT');
    }
    if (!in_array('ip', $columnNames, true)) $pdo->exec('ALTER TABLE presencas ADD COLUMN ip TEXT');
    if (!in_array('latitude', $columnNames, true)) $pdo->exec('ALTER TABLE presencas ADD COLUMN latitude REAL');
    if (!in_array('longitude', $columnNames, true)) $pdo->exec('ALTER TABLE presencas ADD COLUMN longitude REAL');
    if (!in_array('accuracy', $columnNames, true)) $pdo->exec('ALTER TABLE presencas ADD COLUMN accuracy INTEGER');

    $stmt = $pdo->prepare(
        'INSERT INTO presencas (evento, nome, telefone, email, bairro, ip, latitude, longitude, accuracy, registrado_em)
         VALUES (:evento, :nome, :telefone, :email, :bairro, :ip, :latitude, :longitude, :accuracy, :registrado_em)'
    );

    $timezone = new DateTimeZone('America/Sao_Paulo');
    $agora = new DateTimeImmutable('now', $timezone);

    $stmt->execute([
        ':evento' => '2026-09-28',
        ':nome' => $nome,
        ':telefone' => $telefoneDigitos,
        ':email' => $email,
        ':bairro' => $bairro,
        ':ip' => $ip,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':accuracy' => $accuracy,
        ':registrado_em' => $agora->format('Y-m-d H:i:s'),
    ]);

    @chmod($dbPath, 0660);

    respond(201, [
        'ok' => true,
        'id' => (int)$pdo->lastInsertId(),
    ]);
} catch (Throwable $e) {
    error_log('Falha no banco de presença: ' . $e->getMessage());
    respond(500, ['ok' => false, 'error' => 'Não foi possível registrar a presença.']);
}
