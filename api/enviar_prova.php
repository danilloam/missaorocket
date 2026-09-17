<?php

header('Content-Type: application/json');

require_once '../config.php';
require_once 'auth.php';

$usuario = validarToken($pdo);

$prova_id = intval($_POST['prova_id'] ?? 0);

if (!$prova_id) {

    echo json_encode([
        'status'=>'erro',
        'mensagem'=>'Prova inválida'
    ]);

    exit;
}

$permitidos = [
    'jpg',
    'jpeg',
    'png',
    'pdf'
];

$ext = strtolower(
    pathinfo(
        $_FILES['arquivo']['name'],
        PATHINFO_EXTENSION
    )
);

if (!in_array($ext,$permitidos)) {

    echo json_encode([
        'status'=>'erro',
        'mensagem'=>'Arquivo inválido'
    ]);

    exit;
}

$nome = uniqid() . '.' . $ext;

$destino =
'../uploads/' . $nome;

move_uploaded_file(
    $_FILES['arquivo']['tmp_name'],
    $destino
);

$stmt = $pdo->prepare("
SELECT pontos
FROM provas
WHERE id=?
");

$stmt->execute([$prova_id]);

$prova = $stmt->fetch();

$insert = $pdo->prepare("
INSERT INTO historico_pontos
(
 usuario_id,
 grupo_id,
 prova_id,
 pontos,
 evidencia,
 status
)
VALUES
(
 ?, ?, ?, ?, ?, 'pendente'
)
");

$insert->execute([
    $usuario['id'],
    $usuario['grupo_id'],
    $prova_id,
    $prova['pontos'],
    $destino
]);

echo json_encode([
    'status'=>'sucesso',
    'mensagem'=>'Prova enviada'
]);