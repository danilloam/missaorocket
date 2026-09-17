<?php
require_once '../config.php';

function gerarToken()
{
    return bin2hex(random_bytes(32));
}

function validarToken(PDO $pdo)
{
    $headers = getallheaders();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Token não informado'
        ]);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);

    $stmt = $pdo->prepare("
        SELECT *
        FROM usuarios
        WHERE api_token = ?
        LIMIT 1
    ");

    $stmt->execute([$token]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(401);
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Token inválido'
        ]);
        exit;
    }

    return $usuario;
}