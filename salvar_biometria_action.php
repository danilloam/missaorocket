<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['vetor_facial']) || !isset($_POST['usuario_alvo_id'])) {
    echo json_encode(["status" => "erro", "mensagem" => "Requisição inválida."]);
    exit;
}

// Valida se quem está enviando a requisição é de fato um admin
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
    echo json_encode(["status" => "erro", "mensagem" => "Ação não permitida para este perfil."]);
    exit;
}

$usuario_alvo_id = $_POST['usuario_alvo_id'];
$vetor_facial = $_POST['vetor_facial'];

// Atualiza a biometria do usuário selecionado pelo admin
$stmt = $pdo->prepare("UPDATE usuarios SET biometria_facial = ? WHERE id = ?");
$sucesso = $stmt->execute([$vetor_facial, $usuario_alvo_id]);

if ($sucesso) {
    echo json_encode(["status" => "sucesso", "mensagem" => "Biometria do usuário cadastrada com sucesso!"]);
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Erro ao salvar no banco de dados."]);
}
exit;