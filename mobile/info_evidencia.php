<?php

require_once '../config.php';

header('Content-Type: application/json; charset=UTF-8');

if (
    !isset($_SESSION['perfil']) ||
    $_SESSION['perfil'] !== 'admin'
) {
    http_response_code(403);

    echo json_encode([
        'erro' => 'Acesso negado'
    ]);

    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);

    echo json_encode([
        'erro' => 'ID inválido'
    ]);

    exit;
}

$stmt = $pdo->prepare("
    SELECT evidencia
    FROM historico_pontos
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$evidencia = $stmt->fetchColumn();

if ($evidencia === false || empty($evidencia)) {
    http_response_code(404);

    echo json_encode([
        'erro' => 'Evidência não encontrada'
    ]);

    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($evidencia);

echo json_encode([
    'sucesso' => true,
    'mime'    => $mime,
    'imagem'  => str_starts_with($mime, 'image/'),
    'pdf'     => $mime === 'application/pdf'
]);

exit;