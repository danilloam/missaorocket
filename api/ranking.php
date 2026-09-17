<?php

header('Content-Type: application/json');

require_once '../config.php';
require_once 'auth.php';

validarToken($pdo);

$queryInd = "
SELECT
u.nome,
g.nome AS grupo,
COALESCE(SUM(h.pontos),0) total_skill,
u.nivel
FROM usuarios u
LEFT JOIN grupos g
ON g.id = u.grupo_id

LEFT JOIN historico_pontos h
ON h.usuario_id = u.id
AND h.status='aprovado'

GROUP BY u.id
ORDER BY total_skill DESC
";

$rankingIndividual =
$pdo->query($queryInd)->fetchAll(PDO::FETCH_ASSOC);

$queryGrp = "
SELECT
g.nome,
COALESCE(SUM(h.pontos),0) total_skill
FROM grupos g
LEFT JOIN historico_pontos h
ON h.grupo_id=g.id
AND h.status='aprovado'
GROUP BY g.id
ORDER BY total_skill DESC
";

$rankingGrupos =
$pdo->query($queryGrp)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'status'=>'sucesso',
    'ranking_integrantes'=>$rankingIndividual,
    'ranking_grupos'=>$rankingGrupos
]);