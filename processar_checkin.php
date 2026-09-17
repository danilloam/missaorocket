<?php
require_once 'config.php'; // Carrega a conexão com o banco e a sessão[cite: 22, 24]

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['imagem_rosto']) || !isset($_POST['lat']) || !isset($_POST['lng'])) { //[cite: 22, 24]
    echo json_encode(["status" => "erro", "mensagem" => "Requisição inválida ou dados ausentes."]); //[cite: 22, 24]
    exit;
}

if (!isset($_SESSION['user_id'])) { //[cite: 22, 24]
    echo json_encode(["status" => "erro", "mensagem" => "Usuário não autenticado."]); //[cite: 24]
    exit;
}

$user_id = $_SESSION['user_id']; //[cite: 22, 24]
$grupo_id = $_SESSION['grupo_id'] ?? null; //[cite: 22, 24]
$id_prova_checkin = 99; //[cite: 22, 24]
$pontos_ganhos = 20;    //[cite: 22, 24]

$user_lat = floatval($_POST['lat']); //[cite: 24]
$user_lng = floatval($_POST['lng']); //[cite: 24]

try {
    // BUSCA DINÂMICA: Descobre o local cadastrado para o usuário e traz as coordenadas dele[cite: 5]
    $stmtLocal = $pdo->prepare("SELECT l.* FROM locais l 
                                JOIN usuarios u ON u.local_id = l.id 
                                WHERE u.id = ?");
    $stmtLocal->execute([$user_id]);
    $local = $stmtLocal->fetch();

    if (!$local) {
        echo json_encode(["status" => "erro", "mensagem" => "Seu perfil não está vinculado a nenhuma unidade/local da Arena Rocket."]);
        exit;
    }

    $alvo_latitude = floatval($local['latitude']);
    $alvo_longitude = floatval($local['longitude']);
    $raio_maximo = intval($local['raio_metros']);
    $nome_local = $local['nome'];

    // Fórmula de Haversine[cite: 24]
    function calcularDistanciaHaversine($latFrom, $lonFrom, $latTo, $lonTo) { //[cite: 24]
        $earthRadius = 6371000; //[cite: 24]
        $latDelta = deg2rad($latTo - $latFrom); //[cite: 24]
        $lonDelta = deg2rad($lonTo - $lonFrom); //[cite: 24]
        $a = sin($latDelta / 2) * sin($latDelta / 2) + cos(deg2rad($latFrom)) * cos(deg2rad($latTo)) * sin($lonDelta / 2) * sin($lonDelta / 2); //[cite: 24]
        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a))); //[cite: 24]
    }

    $distancia_final = calcularDistanciaHaversine($user_lat, $user_lng, $alvo_latitude, $alvo_longitude);

    if ($distancia_final > $raio_maximo) {
        echo json_encode([
            "status" => "erro", 
            "mensagem" => "Bloqueado! Você está fora do perímetro permitido para a unidade **" . htmlspecialchars($nome_local) . "** (Distância atual: " . round($distancia_final) . " metros)."
        ]);
        exit;
    }

    // Trava de Duplicidade Diária[cite: 24]
    $stmtCheck = $pdo->prepare("SELECT id FROM historico_pontos WHERE usuario_id = ? AND prova_id = ? AND DATE(criado_em) = CURDATE()"); //[cite: 24]
    $stmtCheck->execute([$user_id, $id_prova_checkin]); //[cite: 24]
    if ($stmtCheck->fetch()) { //[cite: 24]
        echo json_encode(["status" => "erro", "mensagem" => "Você já realizou seu check-in hoje!"]); //[cite: 24]
        exit;
    }

    // Processamento da Imagem de Auditoria[cite: 24]
    $imgData = str_replace(' ', '+', str_replace('data:image/jpeg;base64,', '', $_POST['imagem_rosto'])); //[cite: 24]
    $imgDecodificada = base64_decode($imgData); //[cite: 24]

    $diretorio = "uploads/biometria/"; //[cite: 24]
    if (!is_dir($diretorio)) mkdir($diretorio, 0777, true); //[cite: 24]
    
    $caminho_final = $diretorio . $user_id . '_' . time() . '.jpg'; //[cite: 24]
    file_put_contents($caminho_final, $imgDecodificada); //[cite: 24]

    // Insere os pontos no banco[cite: 24]
    $ins = $pdo->prepare("INSERT INTO historico_pontos (usuario_id, grupo_id, prova_id, pontos, evidencia, status) VALUES (?, ?, ?, ?, ?, 'aprovado')"); //[cite: 24]
    $ins->execute([$user_id, $grupo_id, $id_prova_checkin, $pontos_ganhos, $caminho_final]); //[cite: 24]

    atualizarNivelUsuario($pdo, $user_id); //[cite: 24]

    echo json_encode([
        "status" => "sucesso", 
        "mensagem" => "Presença confirmada na unidade **" . htmlspecialchars($nome_local) . "**! Distância do ponto central: " . round($distancia_final) . "m."
    ]);
    exit;
} catch (\Exception $e) {
    echo json_encode(["status" => "erro", "mensagem" => "Erro crítico interno de processamento."]);
    exit;
}