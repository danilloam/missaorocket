<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
require_once 'config.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo == 'GET') {
    // Retorna os Rankings consolidados para o App
    $queryInd = "SELECT u.nome, g.nome as grupo, COALESCE(SUM(h.pontos), 0) as total_skill, u.nivel 
                 FROM usuarios u LEFT JOIN grupos g ON u.grupo_id = g.id
                 LEFT JOIN historico_pontos h ON u.id = h.usuario_id AND h.status = 'aprovado'
                 GROUP BY u.id ORDER BY total_skill DESC";
    $rankingIndividual = $pdo->query($queryInd)->fetchAll();

    $queryGrp = "SELECT g.nome, COALESCE(SUM(h.pontos), 0) as total_skill 
                 FROM grupos g LEFT JOIN historico_pontos h ON g.id = h.grupo_id AND h.status = 'aprovado'
                 GROUP BY g.id ORDER BY total_skill DESC";
    $rankingGrupos = $pdo->query($queryGrp)->fetchAll();

    echo json_encode([
        "status" => "sucesso",
        "data" => [
            "ranking_integrantes" => $rankingIndividual,
            "ranking_grupos" => $rankingGrupos
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
} else {
    http_response_code(405);
    echo json_encode(["status" => "erro", "mensagem" => "Método não permitido."]);
}
?>