<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getClientIp(): string {
    $candidates = [];

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        foreach (explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']) as $value) {
            $candidates[] = trim($value);
        }
    }

    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $candidates[] = trim((string)$_SERVER['HTTP_X_REAL_IP']);
    }

    if (!empty($_SERVER['REMOTE_ADDR'])) {
        $candidates[] = trim((string)$_SERVER['REMOTE_ADDR']);
    }

    foreach ($candidates as $candidate) {
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }

    return 'indisponivel';
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
            registrado_em TEXT NOT NULL
        )'
    );

    $columns = $pdo->query("PRAGMA table_info(presencas)")->fetchAll();
    $columnNames = array_column($columns, 'name');
    if (!in_array('email', $columnNames, true)) {
        $pdo->exec('ALTER TABLE presencas ADD COLUMN email TEXT');
    }
    if (!in_array('ip', $columnNames, true)) {
        $pdo->exec('ALTER TABLE presencas ADD COLUMN ip TEXT');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO presencas (evento, nome, telefone, email, bairro, ip, registrado_em)
         VALUES (:evento, :nome, :telefone, :email, :bairro, :ip, :registrado_em)'
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
