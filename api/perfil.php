<?php

header('Content-Type: application/json');

require_once '../config.php';
require_once 'auth.php';

$usuario = validarToken($pdo);

echo json_encode([
    'status' => 'sucesso',
    'usuario' => [
        'id' => $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'nivel' => $usuario['nivel'],
        'grupo_id' => $usuario['grupo_id']
    ]
]);