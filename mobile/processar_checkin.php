<?php
/**
 * processar_checkin.php
 * Missão Rocket — Processamento de Check-in via QR Code
 *
 * REGRAS:
 * - Evento global: qualquer usuário autenticado pode realizar.
 * - Evento direcionado: somente usuários cadastrados em
 *   prova_destinatarios podem realizar.
 * - Administrador pode possuir grupo_id = NULL e ainda assim
 *   pode realizar check-in.
 * - O servidor é responsável por toda validação.
 * - Nunca confiar somente no JavaScript.
 * - Retorno sempre em JSON.
 */

declare(strict_types=1);

date_default_timezone_set('America/Recife');

/*
|--------------------------------------------------------------------------
| GARANTE RESPOSTA JSON
|--------------------------------------------------------------------------
*/

if (ob_get_level() === 0) {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

/*
|--------------------------------------------------------------------------
| FUNÇÃO DE RESPOSTA
|--------------------------------------------------------------------------
|
| Mantemos success/message porque o scanner atual utiliza esses campos.
| Também enviamos status/mensagem para compatibilidade com versões
| anteriores do sistema.
|
*/

function responder(bool $sucesso, string $mensagem, array $extra = []): never
{
    if (ob_get_length() !== false) {
        ob_clean();
    }

    $resposta = array_merge(
        [
            'success'  => $sucesso,
            'message'  => $mensagem,
            'status'   => $sucesso ? 'sucesso' : 'erro',
            'mensagem' => $mensagem
        ],
        $extra
    );

    echo json_encode(
        $resposta,
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| TRATAMENTO DE ERROS
|--------------------------------------------------------------------------
*/

set_error_handler(
    function (
        int $severity,
        string $message,
        string $file,
        int $line
    ): bool {

        /*
         * Transforma warnings/notices em exceção para impedir que
         * HTML ou mensagens PHP contaminem a resposta JSON.
         */
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException(
            $message,
            0,
            $severity,
            $file,
            $line
        );
    }
);

/*
|--------------------------------------------------------------------------
| TRATAMENTO DE EXCEÇÕES NÃO CAPTURADAS
|--------------------------------------------------------------------------
*/

set_exception_handler(
    function (Throwable $e): void {

        error_log(
            'processar_checkin.php - EXCEÇÃO: ' .
            $e->getMessage() .
            ' em ' .
            $e->getFile() .
            ':' .
            $e->getLine()
        );

        responder(
            false,
            'Ocorreu um erro interno ao processar o check-in.'
        );
    }
);

/*
|--------------------------------------------------------------------------
| SESSÃO
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO / BANCO
|--------------------------------------------------------------------------
*/

try {

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once '../config.php';
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        responder(
            false,
            'Não foi possível conectar ao banco de dados.'
        );
    }

} catch (Throwable $e) {

    error_log(
        'processar_checkin.php - ERRO CONFIG: ' .
        $e->getMessage()
    );

    responder(
        false,
        'Erro ao conectar ao banco de dados.'
    );
}

/*
|--------------------------------------------------------------------------
| MÉTODO HTTP
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    responder(
        false,
        'Método de requisição inválido.'
    );
}

/*
|--------------------------------------------------------------------------
| USUÁRIO AUTENTICADO
|--------------------------------------------------------------------------
*/

$user_id = isset($_SESSION['user_id'])
    ? (int)$_SESSION['user_id']
    : 0;

if ($user_id <= 0) {

    responder(
        false,
        'Sua sessão expirou. Faça login novamente.'
    );
}

/*
|--------------------------------------------------------------------------
| ID DO EVENTO / PROVA
|--------------------------------------------------------------------------
*/

$prova_id = filter_input(
    INPUT_POST,
    'prova_id',
    FILTER_VALIDATE_INT
);

$prova_id = $prova_id !== false && $prova_id !== null
    ? (int)$prova_id
    : 0;

if ($prova_id <= 0) {

    responder(
        false,
        'QR Code inválido ou código de check-in não informado.'
    );
}

/*
|--------------------------------------------------------------------------
| BUSCA O EVENTO
|--------------------------------------------------------------------------
|
| NÃO usamos grupo_id da sessão para decidir autorização.
|
| A autorização pertence ao próprio evento:
|
| global      -> todos
| direcionado -> somente destinatários
|
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.titulo,
            p.descricao,
            p.pontos,
            p.tipo,
            p.data_inicio,
            p.data_fim
        FROM provas p
        WHERE p.id = ?
        LIMIT 1
    ");

    $stmt->execute([$prova_id]);

    $prova = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'processar_checkin.php - ERRO AO BUSCAR PROVA: ' .
        $e->getMessage()
    );

    responder(
        false,
        'Não foi possível validar o evento de check-in.'
    );
}

/*
|--------------------------------------------------------------------------
| EVENTO EXISTE?
|--------------------------------------------------------------------------
*/

if (!$prova) {

    responder(
        false,
        'Este QR Code é inválido ou o evento de check-in não existe.'
    );
}

/*
|--------------------------------------------------------------------------
| VALIDA TIPO DO EVENTO
|--------------------------------------------------------------------------
*/

$tipo = strtolower(
    trim((string)($prova['tipo'] ?? ''))
);

if (!in_array($tipo, ['global', 'direcionado'], true)) {

    error_log(
        'processar_checkin.php - TIPO DE EVENTO INVÁLIDO. ' .
        'Prova: ' . $prova_id .
        ' Tipo: ' . $tipo
    );

    responder(
        false,
        'Este evento de check-in possui uma configuração inválida.'
    );
}

/*
|--------------------------------------------------------------------------
| VALIDA DATA DO EVENTO
|--------------------------------------------------------------------------
|
| O QR só funciona durante o dia configurado para o evento.
|
*/

$hoje = date('Y-m-d');

$dataInicio = !empty($prova['data_inicio'])
    ? date('Y-m-d', strtotime((string)$prova['data_inicio']))
    : null;

$dataFim = !empty($prova['data_fim'])
    ? date('Y-m-d', strtotime((string)$prova['data_fim']))
    : null;

if (!$dataInicio || !$dataFim) {

    responder(
        false,
        'Este evento de check-in não possui período válido.'
    );
}

if ($hoje < $dataInicio || $hoje > $dataFim) {

    responder(
        false,
        'Este check-in não está disponível hoje. O período do evento já encerrou ou ainda não começou.'
    );
}

/*
|--------------------------------------------------------------------------
| VALIDAÇÃO DE AUTORIZAÇÃO
|--------------------------------------------------------------------------
|
| ESTA É A PARTE PRINCIPAL DA REGRA.
|
| GLOBAL:
|   qualquer usuário autenticado pode fazer.
|
| DIRECIONADO:
|   precisa existir obrigatoriamente uma relação em:
|
|       prova_destinatarios
|
|   relacionando:
|
|       prova_id
|       usuario_id
|
*/

if ($tipo === 'direcionado') {

    try {

        $stmtAutorizacao = $pdo->prepare("
            SELECT 1
            FROM prova_destinatarios pd
            WHERE pd.prova_id = ?
              AND pd.usuario_id = ?
            LIMIT 1
        ");

        $stmtAutorizacao->execute([
            $prova_id,
            $user_id
        ]);

        $autorizado = $stmtAutorizacao->fetchColumn();

    } catch (Throwable $e) {

        error_log(
            'processar_checkin.php - ERRO AO VALIDAR DESTINATÁRIO: ' .
            $e->getMessage()
        );

        responder(
            false,
            'Não foi possível verificar sua autorização para este check-in.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | USUÁRIO NÃO ESTÁ LIBERADO
    |--------------------------------------------------------------------------
    */

    if (!$autorizado) {

        responder(
            false,
            'Você não está liberado para realizar o check-in deste evento.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| VERIFICA SE JÁ REALIZOU O CHECK-IN
|--------------------------------------------------------------------------
*/

try {

    $stmtDuplicidade = $pdo->prepare("
        SELECT id
        FROM historico_pontos
        WHERE usuario_id = ?
          AND prova_id = ?
          AND status = 'aprovado'
        LIMIT 1
    ");

    $stmtDuplicidade->execute([
        $user_id,
        $prova_id
    ]);

    $jaRealizou = $stmtDuplicidade->fetchColumn();

} catch (Throwable $e) {

    error_log(
        'processar_checkin.php - ERRO DUPLICIDADE: ' .
        $e->getMessage()
    );

    responder(
        false,
        'Não foi possível verificar se o check-in já foi realizado.'
    );
}

if ($jaRealizou) {

    responder(
        false,
        'Você já realizou o check-in deste evento.'
    );
}

/*
|--------------------------------------------------------------------------
| BUSCA GRUPO ATUAL DO USUÁRIO
|--------------------------------------------------------------------------
|
| IMPORTANTE:
|
| grupo_id pode ser NULL.
|
| Isso é permitido para administradores e outros usuários sem grupo.
| NÃO utilizamos grupo_id para bloquear o check-in.
|
*/

try {

    $stmtUsuario = $pdo->prepare("
        SELECT
            id,
            grupo_id
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmtUsuario->execute([$user_id]);

    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'processar_checkin.php - ERRO USUÁRIO: ' .
        $e->getMessage()
    );

    responder(
        false,
        'Não foi possível validar seu usuário.'
    );
}

if (!$usuario) {

    responder(
        false,
        'Usuário não encontrado.'
    );
}

/*
|--------------------------------------------------------------------------
| GRUPO
|--------------------------------------------------------------------------
|
| Pode ser NULL.
|
*/

$grupo_id = null;

if (
    isset($usuario['grupo_id']) &&
    $usuario['grupo_id'] !== '' &&
    $usuario['grupo_id'] !== null
) {
    $grupo_id = (int)$usuario['grupo_id'];

    if ($grupo_id <= 0) {
        $grupo_id = null;
    }
}

/*
|--------------------------------------------------------------------------
| PONTOS
|--------------------------------------------------------------------------
*/

$pontos = (int)($prova['pontos'] ?? 0);

if ($pontos < 0) {
    $pontos = 0;
}

/*
|--------------------------------------------------------------------------
| FUNÇÃO — ATUALIZA OFENSIVA
|--------------------------------------------------------------------------
*/

function processarOfensivaUsuario(PDO $pdo, int $usuarioId): void
{
    $hoje = date('Y-m-d');

    $ontem = date(
        'Y-m-d',
        strtotime('-1 day')
    );

    $stmt = $pdo->prepare("
        SELECT
            ofensiva_atual,
            ofensiva_maxima,
            ultima_ofensiva_em
        FROM usuarios_ofensivas
        WHERE usuario_id = ?
        LIMIT 1
    ");

    $stmt->execute([$usuarioId]);

    $ofensiva = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | PRIMEIRO CHECK-IN DA OFENSIVA
    |--------------------------------------------------------------------------
    */

    if (!$ofensiva) {

        $stmt = $pdo->prepare("
            INSERT INTO usuarios_ofensivas
            (
                usuario_id,
                ofensiva_atual,
                ofensiva_maxima,
                ultima_ofensiva_em
            )
            VALUES (?, 1, 1, ?)
        ");

        $stmt->execute([
            $usuarioId,
            $hoje
        ]);

        checarPremioOfensiva(
            $pdo,
            $usuarioId,
            1
        );

        return;
    }

    $ultimaData = $ofensiva['ultima_ofensiva_em'];

    $ofensivaAtual = (int)$ofensiva['ofensiva_atual'];

    $ofensivaMaxima = (int)$ofensiva['ofensiva_maxima'];

    /*
    |--------------------------------------------------------------------------
    | JÁ PROCESSADO HOJE
    |--------------------------------------------------------------------------
    */

    if ($ultimaData === $hoje) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | DIA SEGUINTE = MANTÉM SEQUÊNCIA
    |--------------------------------------------------------------------------
    */

    if ($ultimaData === $ontem) {
        $ofensivaAtual++;
    } else {
        $ofensivaAtual = 1;
    }

    if ($ofensivaAtual > $ofensivaMaxima) {
        $ofensivaMaxima = $ofensivaAtual;
    }

    $stmt = $pdo->prepare("
        UPDATE usuarios_ofensivas
        SET
            ofensiva_atual = ?,
            ofensiva_maxima = ?,
            ultima_ofensiva_em = ?
        WHERE usuario_id = ?
    ");

    $stmt->execute([
        $ofensivaAtual,
        $ofensivaMaxima,
        $hoje,
        $usuarioId
    ]);

    checarPremioOfensiva(
        $pdo,
        $usuarioId,
        $ofensivaAtual
    );
}

/*
|--------------------------------------------------------------------------
| FUNÇÃO — PREMIA OFENSIVA
|--------------------------------------------------------------------------
*/

function checarPremioOfensiva(
    PDO $pdo,
    int $usuarioId,
    int $diasAtuais
): void {

    $stmt = $pdo->prepare("
        SELECT
            dias_requeridos,
            pontos_bonus,
            titulo_medalha,
            icone_medalha
        FROM config_ofensivas
        WHERE dias_requeridos = ?
        LIMIT 1
    ");

    $stmt->execute([
        $diasAtuais
    ]);

    $regra = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$regra) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCA GRUPO DO USUÁRIO
    |--------------------------------------------------------------------------
    */

    $stmtUser = $pdo->prepare("
        SELECT grupo_id
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmtUser->execute([
        $usuarioId
    ]);

    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $grupoId = null;

    if (
        isset($user['grupo_id']) &&
        $user['grupo_id'] !== '' &&
        $user['grupo_id'] !== null
    ) {
        $grupoId = (int)$user['grupo_id'];

        if ($grupoId <= 0) {
            $grupoId = null;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BÔNUS DE PONTOS
    |--------------------------------------------------------------------------
    */

    $bonus = (int)($regra['pontos_bonus'] ?? 0);

    if ($bonus > 0) {

        $stmtPontos = $pdo->prepare("
            INSERT INTO historico_pontos
            (
                usuario_id,
                grupo_id,
                prova_id,
                pontos,
                evidencia,
                status,
                criado_em
            )
            VALUES
            (
                ?,
                ?,
                999,
                ?,
                'Bônus de Ofensiva Dinâmico',
                'aprovado',
                NOW()
            )
        ");

        $stmtPontos->execute([
            $usuarioId,
            $grupoId,
            $bonus
        ]);

        if (function_exists('atualizarNivelUsuario')) {

            atualizarNivelUsuario(
                $pdo,
                $usuarioId
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MEDALHA
    |--------------------------------------------------------------------------
    */

    $tituloMedalha = trim(
        (string)($regra['titulo_medalha'] ?? '')
    );

    if ($tituloMedalha !== '') {

        $iconeMedalha = (string)(
            $regra['icone_medalha'] ?? ''
        );

        $stmtMedalha = $pdo->prepare("
            INSERT INTO medalhas
            (
                usuario_id,
                titulo,
                icone
            )
            VALUES (?, ?, ?)
        ");

        $stmtMedalha->execute([
            $usuarioId,
            $tituloMedalha,
            $iconeMedalha
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| GRAVA CHECK-IN
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | NOVA VERIFICAÇÃO DE DUPLICIDADE DENTRO DA TRANSAÇÃO
    |--------------------------------------------------------------------------
    |
    | Evita duplicidade em chamadas simultâneas.
    |
    */

    $stmtDuplicidadeFinal = $pdo->prepare("
        SELECT id
        FROM historico_pontos
        WHERE usuario_id = ?
          AND prova_id = ?
          AND status = 'aprovado'
        LIMIT 1
        FOR UPDATE
    ");

    $stmtDuplicidadeFinal->execute([
        $user_id,
        $prova_id
    ]);

    if ($stmtDuplicidadeFinal->fetchColumn()) {

        $pdo->rollBack();

        responder(
            false,
            'Você já realizou o check-in deste evento.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | INSERE CHECK-IN
    |--------------------------------------------------------------------------
    */

    $stmtInsert = $pdo->prepare("
        INSERT INTO historico_pontos
        (
            usuario_id,
            grupo_id,
            prova_id,
            pontos,
            evidencia,
            status,
            criado_em
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'Check-in via QR Code Presencial',
            'aprovado',
            NOW()
        )
    ");

    $stmtInsert->execute([
        $user_id,
        $grupo_id,
        $prova_id,
        $pontos
    ]);

    /*
    |--------------------------------------------------------------------------
    | ATUALIZA NÍVEL
    |--------------------------------------------------------------------------
    */

    if (function_exists('atualizarNivelUsuario')) {

        atualizarNivelUsuario(
            $pdo,
            $user_id
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESSA OFENSIVA
    |--------------------------------------------------------------------------
    */

    processarOfensivaUsuario(
        $pdo,
        $user_id
    );

    /*
    |--------------------------------------------------------------------------
    | CONFIRMA TRANSAÇÃO
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'processar_checkin.php - ERRO AO GRAVAR CHECK-IN: ' .
        $e->getMessage() .
        ' | usuário=' . $user_id .
        ' | prova=' . $prova_id
    );

    responder(
        false,
        'Não foi possível registrar o check-in. Tente novamente.'
    );
}

/*
|--------------------------------------------------------------------------
| SUCESSO
|--------------------------------------------------------------------------
*/

responder(
    true,
    'Check-in confirmado! +' .
    $pontos .
    ' pontos adicionados à sua Arena.',
    [
        'prova_id' => $prova_id,
        'pontos'   => $pontos,
        'tipo'     => $tipo
    ]
);

?>