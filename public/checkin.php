<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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
$bairro = trim((string)($input['bairro'] ?? ''));

if ($nome === '' || strlen($nome) < 2 || strlen($nome) > 120) {
    respond(422, ['ok' => false, 'error' => 'Informe um nome válido.']);
}

$telefoneDigitos = preg_replace('/\D+/', '', $telefone) ?? '';
if (strlen($telefoneDigitos) < 10 || strlen($telefoneDigitos) > 11) {
    respond(422, ['ok' => false, 'error' => 'Informe um telefone válido com DDD.']);
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
            bairro TEXT NOT NULL,
            registrado_em TEXT NOT NULL
        )'
    );

    $stmt = $pdo->prepare(
        'INSERT INTO presencas (evento, nome, telefone, bairro, registrado_em)
         VALUES (:evento, :nome, :telefone, :bairro, :registrado_em)'
    );

    $timezone = new DateTimeZone('America/Sao_Paulo');
    $agora = new DateTimeImmutable('now', $timezone);

    $stmt->execute([
        ':evento' => '2026-09-28',
        ':nome' => $nome,
        ':telefone' => $telefoneDigitos,
        ':bairro' => $bairro,
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
