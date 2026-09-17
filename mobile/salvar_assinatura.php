<?php
require_once '../config.php';

// Define o retorno estrito como JSON para o JavaScript ler corretamente
header('Content-Type: application/json');

// Captura o usuário logado dinamicamente pela sessão.
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode([
        'status' => 'erro',
        'message' => 'Usuário não autenticado.'
    ]);
    exit;
}

// ====================================================================
// CASO 1: VERIFICAÇÃO SE O APARELHO CONSTA NO BANCO (Evita cache preso)
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'verificar') {
    $input = json_decode(file_get_contents('php://input'), true);
    $endpoint = $input['endpoint'] ?? '';

    if (!empty($endpoint)) {
        // Busca se o endpoint deste telemóvel/celular está registado para este usuário específico
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios_notificacoes WHERE endpoint = ? AND usuario_id = ? LIMIT 1");
        $stmtCheck->execute([$endpoint, $user_id]);
        $aparelhoExiste = $stmtCheck->fetch();

        if ($aparelhoExiste) {
            echo json_encode([
                'status' => 'encontrado', 
                'encontrado' => true,
                'message' => 'Aparelho validado com sucesso.'
            ]);
        } else {
            // Gatilho crítico: avisa o front-end para limpar o cache do navegador local
            echo json_encode([
                'status' => 'nao_encontrado', 
                'encontrado' => false,
                'message' => 'ID da instalação não encontrado no banco de dados.'
            ]);
        }
        exit;
    }
}

// ====================================================================
// CASO 2: FLUXO PADRÃO - INSERÇÃO / GRAVAÇÃO DA NOVA ASSINATURA PUSH
// ====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $endpoint = $input['endpoint'] ?? '';
    $p256dh   = $input['p256dh'] ?? '';
    $auth     = $input['auth'] ?? '';

    if (!empty($endpoint) && !empty($p256dh) && !empty($auth)) {
        // Limpa registos obsoletos gerados por esse mesmo dispositivo para não duplicar envios
        $stmtDelete = $pdo->prepare("DELETE FROM usuarios_notificacoes WHERE endpoint = ?");
        $stmtDelete->execute([$endpoint]);

        // Grava as novas chaves de criptografia válidas para push enviadas pelo telemóvel
        $stmtInsert = $pdo->prepare("INSERT INTO usuarios_notificacoes (usuario_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$user_id, $endpoint, $p256dh, $auth]);

        echo json_encode([
            'status' => 'sucesso', 
            'message' => 'Aparelho mapeado com sucesso no banco!'
        ]);
        exit;
    }
}

// Se nenhuma das condições acima for atendida
echo json_encode([
    'status' => 'erro', 
    'message' => 'Dados de push incompletos ou requisição inválida.'
]);