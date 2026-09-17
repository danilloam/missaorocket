<?php
require_once 'config.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;

if(!$user_id){
    echo json_encode(["status" => "erro", "mensagem" => "Usuario nao autenticado"]);
    exit;
}

$stmt = $pdo->prepare("SELECT biometria_facial FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$res = $stmt->fetch();

echo json_encode([
    "status" => "sucesso",
    "vetor" => $res['biometria_facial'] ?? null
]);
exit;