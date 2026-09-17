<?php

// Define o cabeçalho para retornar uma resposta JSON em UTF-8
header('Content-Type: application/json; charset=utf-8');

// Inclui as configurações de banco de dados e arquivos de autenticação
require_once '../config.php';
require_once 'auth.php';

try {
    // 1. Valida o Token do usuário logado
    $usuario = validarToken($pdo);

    // 2. Captura e sanitiza o ID da prova vindo da URL (ex: api/prova.php?id=5)
    $prova_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($prova_id <= 0) {
        http_response_code(400); // Bad Request
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'O ID da prova fornecido é inválido ou não foi informado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCA DETALHADA DA PROVA
    |--------------------------------------------------------------------------
    | Restrições da Query:
    | - O ID deve corresponder à prova requisitada.
    | - A prova deve ser do tipo 'global' OU pertencer ao grupo_id do usuário.
    | - A data atual (CURDATE()) deve estar entre a data_inicio e data_fim.
    */
    $stmt = $pdo->prepare("
        SELECT 
            id,
            titulo,
            descricao,
            pontos,
            tipo AS categoria,
            data_inicio,
            data_fim
        FROM provas
        WHERE id = ?
        AND (
            tipo = 'global'
            OR grupo_id = ?
        )
        AND CURDATE() BETWEEN data_inicio AND data_fim
    ");

    $stmt->execute([
        $prova_id,
        $usuario['grupo_id']
    ]);

    $prova = $stmt->fetch(PDO::FETCH_ASSOC);

    // 3. Verifica se a prova foi encontrada e está ativa
    if (!$prova) {
        http_response_code(444); // Not Found / Unavailable
        echo json_encode([
            'status' => 'erro',
            'mensagem' => 'Esta prova não está disponível, já expirou ou você não tem permissão para acessá-la.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 4. Formatações adicionais para facilitar o consumo no Front-end (opcional)
    // Transforma a data_fim em um label amigável (Ex: "Até 15/06")
    $prova['prazo_formatado'] = 'Até ' . date('d/m', strtotime($prova['data_fim']));
    $prova['pontos'] = (int)$prova['pontos'];

    /*
    |--------------------------------------------------------------------------
    | RETORNO DA RESPOSTA COM SUCESSO
    |--------------------------------------------------------------------------
    */
    http_response_code(200); // OK
    echo json_encode([
        'status' => 'sucesso',
        'dados' => $prova
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Tratamento de erros estruturais ou falhas de conexão
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'status' => 'erro',
        'mensagem' => 'Erro interno no servidor: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}