<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['vetor_atual'])) {
    echo json_encode(["status" => "erro", "mensagem" => "Sinal biométrico não recebido."]);
    exit;
}

$vetor_atual = json_decode($_POST['vetor_atual'], true);
$id_prova_checkin = 99; // ID fixo da prova de presença do seu painel
$pontos_ganhos = 20;

try {
    // 1. Busca todos os usuários que possuem Face ID cadastrado no banco
    $stmtUsers = $pdo->query("SELECT id, nome, grupo_id, biometria_facial FROM usuarios WHERE biometria_facial IS NOT NULL");
    $usuarios = $stmtUsers->fetchAll();

    $usuario_identificado = null;
    $menor_distancia = 1.0; // Padrão inicial alto

    // 2. FUNÇÃO MATEMÁTICA: Distância Euclidiana nativa no PHP
    // Compara o rosto do totem com cada rosto do banco de dados
    foreach ($usuarios as $user) {
        $vetor_banco = json_decode($user['biometria_facial'], true);
        
        if (!is_array($vetor_banco) || count($vetor_banco) !== count($vetor_atual)) continue;

        // Soma dos quadrados das diferenças das 128 coordenadas faciais
        $soma = 0;
        for ($i = 0; $i < count($vetor_atual); $i++) {
            $soma += pow($vetor_atual[$i] - $vetor_banco[$i], 2);
        }
        $distancia_calculada = sqrt($soma);

        // Guarda o usuário que teve a maior semelhança (menor distância geométrica)
        if ($distancia_calculada < $menor_distancia) {
            $menor_distancia = $distancia_calculada;
            $usuario_identificado = $user;
        }
    }

    // 3. Validação de Margem de Assertividade (0.50 é o padrão seguro do face-api)
    if (!$usuario_identificado || $menor_distancia > 0.60) {
        echo json_encode(["status" => "erro", "mensagem" => "Rosto desconhecido. Cadastre seu Face ID na recepção!"]);
        exit;
    }

    $user_id = $usuario_identificado['id'];
    $nome_user = $usuario_identificado['nome'];
    $grupo_id = $usuario_identificado['grupo_id'];

    // 4. TRAVA CRÍTICA SOLICITADA: Apenas 1 Check-in por dia civil
    $stmtCheck = $pdo->prepare("SELECT id FROM historico_pontos 
                                WHERE usuario_id = ? AND prova_id = ? AND DATE(criado_em) = CURDATE()");
    $stmtCheck->execute([$user_id, $id_prova_checkin]);
    
    if ($stmtCheck->fetch()) {
        echo json_encode(["status" => "erro", "mensagem" => "Bloqueado! " . explode(" ", $nome_user)[0] . " já pontuou hoje."]);
        exit;
    }

    // 5. Se passou nas travas, computa a recompensa imediatamente
    $ins = $pdo->prepare("INSERT INTO historico_pontos (usuario_id, grupo_id, prova_id, pontos, evidencia, status) 
                          VALUES (?, ?, ?, ?, 'Totem Presencial', 'aprovado')");
    $ins->execute([$user_id, $grupo_id, $id_prova_checkin, $pontos_ganhos]);

    // Atualiza o ranking de nível do integrante
    atualizarNivelUsuario($pdo, $user_id);

    // Retorna os dados para a animação do Totem
    echo json_encode([
        "status" => "sucesso",
        "nome" => htmlspecialchars($nome_user),
        "pontos" => $pontos_ganhos
    ]);
    exit;

} catch (\Exception $e) {
    echo json_encode(["status" => "erro", "mensagem" => "Falha no banco central."]);
    exit;
}