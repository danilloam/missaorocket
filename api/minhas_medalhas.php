<?php

header('Content-Type: application/json');

require_once '../config.php';
require_once 'auth.php';

$usuario = validarToken($pdo);

$stmt = $pdo->prepare("
SELECT *
FROM medalhas
WHERE usuario_id = ?
");

$stmt->execute([
    $usuario['id']
]);

echo json_encode([
    'status'=>'sucesso',
    'medalhas'=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);