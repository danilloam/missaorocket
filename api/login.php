<?php

header('Content-Type: application/json');

require_once '../config.php';
require_once 'auth.php';

$dados = json_decode(file_get_contents("php://input"), true);

$email = trim($dados['email'] ?? '');
$senha = trim($dados['senha'] ?? '');

if (!$email || !$senha) {

    http_response_code(400);

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Dados inválidos'
    ]);

    exit;
}

$stmt = $pdo->prepare("
SELECT *
FROM usuarios
WHERE email = ?
LIMIT 1
");

$stmt->execute([$email]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($senha, $user['senha'])) {

    http_response_code(401);

    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Login inválido'
    ]);

    exit;
}

$token = gerarToken();

$update = $pdo->prepare("
UPDATE usuarios
SET api_token = ?
WHERE id = ?
");

$update->execute([
    $token,
    $user['id']
]);

echo json_encode([
    'status' => 'sucesso',
    'token' => $token,
    'usuario' => [
        'id' => $user['id'],
        'nome' => $user['nome'],
        'grupo_id' => $user['grupo_id'],
        'nivel' => $user['nivel']
    ]
]);