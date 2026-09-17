<?php

header('Content-Type: application/json; charset=utf-8');

require_once '../config.php';
require_once 'auth.php';

try {

    $usuario = validarToken($pdo);

    /*
    |--------------------------------------------------------------------------
    | PERFIL
    |--------------------------------------------------------------------------
    */

    $perfil = [
        'id'       => (int)$usuario['id'],
        'nome'     => $usuario['nome'],
        'email'    => $usuario['email'],
        'nivel'    => $usuario['nivel'],
        'grupo_id' => (int)$usuario['grupo_id']
    ];

    /*
    |--------------------------------------------------------------------------
    | RANKING INDIVIDUAL
    |--------------------------------------------------------------------------
    */

    $stmtRanking = $pdo->prepare("
        SELECT
            u.id,
            u.nome,
            g.nome AS grupo,
            u.nivel,
            COALESCE(SUM(h.pontos),0) AS total_skill
        FROM usuarios u
        LEFT JOIN grupos g
            ON g.id = u.grupo_id
        LEFT JOIN historico_pontos h
            ON h.usuario_id = u.id
            AND h.status = 'aprovado'
        GROUP BY u.id
        ORDER BY total_skill DESC
        LIMIT 20
    ");

    $stmtRanking->execute();

    $ranking = $stmtRanking->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | PROVAS DISPONÍVEIS
    |--------------------------------------------------------------------------
    */

    $stmtProvas = $pdo->prepare("
        SELECT
            id,
            titulo,
            descricao,
            pontos,
            tipo,
            data_inicio,
            data_fim
        FROM provas
        WHERE
        (
            tipo = 'global'
            OR grupo_id = ?
        )
        AND CURDATE() BETWEEN data_inicio AND data_fim
        ORDER BY data_fim ASC
    ");

    $stmtProvas->execute([
        $usuario['grupo_id']
    ]);

    $provas = $stmtProvas->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | MEDALHAS
    |--------------------------------------------------------------------------
    */

    $stmtMedalhas = $pdo->prepare("
        SELECT
            id,
            titulo,
            icone
        FROM medalhas
        WHERE usuario_id = ?
        ORDER BY id DESC
    ");

    $stmtMedalhas->execute([
        $usuario['id']
    ]);

    $medalhas = $stmtMedalhas->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | POSIÇÃO DO USUÁRIO
    |--------------------------------------------------------------------------
    */

    $posicao = null;

    foreach ($ranking as $index => $item) {

        if ((int)$item['id'] === (int)$usuario['id']) {

            $posicao = $index + 1;
            break;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TOTAL DE PONTOS
    |--------------------------------------------------------------------------
    */

    $stmtPontos = $pdo->prepare("
        SELECT COALESCE(SUM(pontos),0) total
        FROM historico_pontos
        WHERE usuario_id = ?
        AND status = 'aprovado'
    ");

    $stmtPontos->execute([
        $usuario['id']
    ]);

    $totalPontos = (int)$stmtPontos->fetchColumn();

    /*
    |--------------------------------------------------------------------------
    | RESPOSTA
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'status' => 'sucesso',

        'perfil' => $perfil,

        'estatisticas' => [
            'pontos' => $totalPontos,
            'posicao' => $posicao,
            'total_medalhas' => count($medalhas)
        ],

        'ranking' => $ranking,

        'provas' => $provas,

        'medalhas' => $medalhas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'status' => 'erro',
        'mensagem' => $e->getMessage()
    ]);
}