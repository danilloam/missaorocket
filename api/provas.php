<?php

header('Content-Type: application/json');

require_once '../config.php';
require_once 'auth.php';

$usuario = validarToken($pdo);

$stmt = $pdo->prepare("
SELECT *
FROM provas
WHERE
(
 tipo='global'
 OR grupo_id=?
)
AND CURDATE()
BETWEEN data_inicio
AND data_fim
");

$stmt->execute([
    $usuario['grupo_id']
]);

echo json_encode([
    'status'=>'sucesso',
    'provas'=>$stmt->fetchAll(PDO::FETCH_ASSOC)
]);