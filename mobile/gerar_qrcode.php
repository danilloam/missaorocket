<?php
/**
 * gerar_checkin.php
 * Missão Rocket — Painel de Check-in / Projeção
 *
 * REGRAS:
 * - Pode existir mais de um evento no mesmo dia.
 * - Cada evento possui um QR Code próprio.
 * - O QR Code contém somente o ID da prova.
 * - Público pode ser GLOBAL ou DIRECIONADO.
 * - Um evento direcionado pode ter uma ou várias pessoas.
 * - Destinatários são armazenados em prova_destinatarios.
 *
 * DEPENDÊNCIAS:
 * - header.php
 * - conexão PDO em $pdo
 * - tabela provas
 * - tabela usuarios
 * - tabela prova_destinatarios
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Recife');

require_once 'header.php';
exigirAdministrador($pdo);

/*
|--------------------------------------------------------------------------
| ACESSO
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['perfil']) ||
    $_SESSION['perfil'] !== 'admin'
) {
    echo '
        <div class="alert alert-danger py-2 small text-center m-3">
            Acesso restrito para administradores.
        </div>
    ';
    exit;
}


/*
|--------------------------------------------------------------------------
| VARIÁVEIS
|--------------------------------------------------------------------------
*/

$hoje = date('Y-m-d');
$feedback = '';

$usuarios = [];
$provasHoje = [];


/*
|--------------------------------------------------------------------------
| AUXILIARES
|--------------------------------------------------------------------------
*/

/**
 * Escape HTML.
 */
if (!function_exists('e')) {
    function e($valor): string
    {
        return htmlspecialchars(
            (string)$valor,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}


/**
 * Extrai o nome amigável do evento.
 *
 * Aceita:
 *
 * Check-in - Celebration - 14/09
 * Check-in no Culto - 14/09
 */
function nomeEventoDoTitulo(string $titulo): string
{
    $titulo = trim($titulo);

    /*
    | Remove prefixo.
    */
    $titulo = preg_replace(
        '/^Check-in(?:\s+no\s+Culto)?\s*-\s*/iu',
        '',
        $titulo
    );

    /*
    | Remove data final.
    */
    $titulo = preg_replace(
        '/\s*-\s*\d{2}\/\d{2}$/u',
        '',
        $titulo
    );

    return trim($titulo);
}


/**
 * Verifica data YYYY-MM-DD.
 */
function dataValida(string $data): bool
{
    $dt = DateTime::createFromFormat(
        '!Y-m-d',
        $data
    );

    return (
        $dt !== false &&
        $dt->format('Y-m-d') === $data
    );
}


/**
 * Retorna feedback HTML.
 */
function feedbackHtml(
    string $tipo,
    string $mensagem
): string {
    return sprintf(
        '<div class="alert alert-%s py-2 small text-center mb-3">%s</div>',
        e($tipo),
        e($mensagem)
    );
}


/*
|--------------------------------------------------------------------------
| CARREGAR USUÁRIOS
|--------------------------------------------------------------------------
|
| A lista é carregada uma única vez.
|
*/

try {

    $stmtUsuarios = $pdo->query("
        SELECT
            id,
            nome
        FROM usuarios
        WHERE id > 0
        ORDER BY nome ASC
    ");

    $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    error_log(
        'gerar_checkin.php - erro ao carregar usuários: ' .
        $e->getMessage()
    );

    $usuarios = [];

    $feedback = feedbackHtml(
        'warning',
        'Não foi possível carregar a lista de participantes.'
    );
}


/*
|--------------------------------------------------------------------------
| MAPA DE USUÁRIOS
|--------------------------------------------------------------------------
|
| Facilita validar destinatários enviados pelo formulário.
|
*/

$usuariosPermitidos = [];

foreach ($usuarios as $usuario) {

    $id = (int)($usuario['id'] ?? 0);

    if ($id <= 0) {
        continue;
    }

    $usuariosPermitidos[$id] = [
        'id' => $id,
        'nome' => (string)($usuario['nome'] ?? '')
    ];
}


/*
|--------------------------------------------------------------------------
| PROCESSAR CADASTRO / EDIÇÃO
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salvar_checkin'])
) {

    /*
    |----------------------------------------------------------------------
    | DADOS RECEBIDOS
    |----------------------------------------------------------------------
    */

    $provaId = filter_var(
        $_POST['prova_id'] ?? 0,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'default' => 0,
                'min_range' => 0
            ]
        ]
    );

    $dataEvento = trim(
        (string)($_POST['data_evento'] ?? '')
    );

    $nomeEvento = trim(
        (string)($_POST['nome_evento'] ?? '')
    );

    $pontos = filter_var(
        $_POST['pontos'] ?? 40,
        FILTER_VALIDATE_INT,
        [
            'options' => [
                'default' => 40
            ]
        ]
    );

    $descricao = trim(
        (string)($_POST['descricao'] ?? '')
    );

    $tipo = trim(
        (string)($_POST['tipo'] ?? 'global')
    );

    $destinatariosRecebidos =
        $_POST['destinatarios'] ?? [];


    /*
    |----------------------------------------------------------------------
    | NORMALIZA DESTINATÁRIOS
    |----------------------------------------------------------------------
    */

    if (!is_array($destinatariosRecebidos)) {
        $destinatariosRecebidos = [];
    }

    $destinatarios = [];

    foreach ($destinatariosRecebidos as $usuarioId) {

        $usuarioId = filter_var(
            $usuarioId,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'default' => 0,
                    'min_range' => 1
                ]
            ]
        );

        if ($usuarioId > 0) {
            $destinatarios[] = $usuarioId;
        }
    }

    /*
    | Remove duplicados.
    */
    $destinatarios = array_values(
        array_unique($destinatarios)
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÕES
    |--------------------------------------------------------------------------
    */

    if (!dataValida($dataEvento)) {

        $feedback = feedbackHtml(
            'danger',
            'Informe uma data válida.'
        );

    } elseif (
        $nomeEvento === '' ||
        mb_strlen($nomeEvento, 'UTF-8') < 2
    ) {

        $feedback = feedbackHtml(
            'danger',
            'Informe um nome válido para o evento.'
        );

    } elseif (
        mb_strlen($nomeEvento, 'UTF-8') > 120
    ) {

        $feedback = feedbackHtml(
            'danger',
            'O nome do evento deve ter no máximo 120 caracteres.'
        );

    } elseif (
        !in_array(
            $tipo,
            ['global', 'direcionado'],
            true
        )
    ) {

        $feedback = feedbackHtml(
            'danger',
            'Tipo de público inválido.'
        );

    } elseif (
        $pontos < 1 ||
        $pontos > 100000
    ) {

        $feedback = feedbackHtml(
            'danger',
            'A pontuação deve estar entre 1 e 100.000 pontos.'
        );

    } elseif (
        mb_strlen($descricao, 'UTF-8') > 1000
    ) {

        $feedback = feedbackHtml(
            'danger',
            'A descrição deve ter no máximo 1.000 caracteres.'
        );

    } elseif (
        $tipo === 'direcionado' &&
        empty($destinatarios)
    ) {

        $feedback = feedbackHtml(
            'warning',
            'Selecione pelo menos uma pessoa para um check-in direcionado.'
        );

    } else {

        /*
        |--------------------------------------------------------------------------
        | VALIDAR DESTINATÁRIOS
        |--------------------------------------------------------------------------
        */

        $destinatariosInvalidos = [];

        foreach ($destinatarios as $usuarioId) {

            if (!isset($usuariosPermitidos[$usuarioId])) {
                $destinatariosInvalidos[] = $usuarioId;
            }
        }

        if (!empty($destinatariosInvalidos)) {

            $feedback = feedbackHtml(
                'danger',
                'Um ou mais participantes selecionados não existem ou não estão disponíveis.'
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | PROCESSAMENTO
            |--------------------------------------------------------------------------
            */

            try {

                $dataFormatada = DateTime::createFromFormat(
                    '!Y-m-d',
                    $dataEvento
                )->format('d/m');

                $titulo = sprintf(
                    'Check-in - %s - %s',
                    $nomeEvento,
                    $dataFormatada
                );

                if ($descricao === '') {
                    $descricao =
                        'Confirme sua presença no evento realizado hoje.';
                }


                /*
                |------------------------------------------------------------------
                | TRANSAÇÃO
                |------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | EDIÇÃO
                |--------------------------------------------------------------------------
                */

                if ($provaId > 0) {

                    /*
                    |------------------------------------------------------------------
                    | Localiza a prova.
                    |------------------------------------------------------------------
                    |
                    | Não basta confiar no ID enviado pelo navegador.
                    |
                    */

                    $stmtExiste = $pdo->prepare("
                        SELECT
                            id,
                            titulo,
                            tipo,
                            data_inicio,
                            data_fim
                        FROM provas
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmtExiste->execute([
                        $provaId
                    ]);

                    $provaExistente =
                        $stmtExiste->fetch(PDO::FETCH_ASSOC);


                    if (!$provaExistente) {

                        throw new RuntimeException(
                            'Evento de check-in não encontrado.'
                        );
                    }


                    /*
                    |------------------------------------------------------------------
                    | Garante que é um check-in.
                    |------------------------------------------------------------------
                    */

                    $tituloExistente =
                        (string)$provaExistente['titulo'];

                    if (
                        !preg_match(
                            '/^Check-in(?:\s+no\s+Culto)?\s*-/iu',
                            $tituloExistente
                        )
                    ) {

                        throw new RuntimeException(
                            'A prova informada não é um evento de check-in.'
                        );
                    }


                    /*
                    |------------------------------------------------------------------
                    | Atualiza evento.
                    |------------------------------------------------------------------
                    */

                    $stmtUpdate = $pdo->prepare("
                        UPDATE provas
                        SET
                            titulo = ?,
                            descricao = ?,
                            pontos = ?,
                            tipo = ?,
                            data_inicio = ?,
                            data_fim = ?
                        WHERE id = ?
                        LIMIT 1
                    ");

                    $stmtUpdate->execute([
                        $titulo,
                        $descricao,
                        $pontos,
                        $tipo,
                        $dataEvento,
                        $dataEvento,
                        $provaId
                    ]);


                    /*
                    |------------------------------------------------------------------
                    | Remove destinatários antigos.
                    |------------------------------------------------------------------
                    */

                    $stmtDeleteDest = $pdo->prepare("
                        DELETE FROM prova_destinatarios
                        WHERE prova_id = ?
                    ");

                    $stmtDeleteDest->execute([
                        $provaId
                    ]);


                /*
                |--------------------------------------------------------------------------
                | NOVO EVENTO
                |--------------------------------------------------------------------------
                */

                } else {

                    $stmtInsert = $pdo->prepare("
                        INSERT INTO provas
                        (
                            titulo,
                            descricao,
                            pontos,
                            tipo,
                            grupo_id,
                            data_inicio,
                            data_fim
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            NULL,
                            ?,
                            ?
                        )
                    ");

                    $stmtInsert->execute([
                        $titulo,
                        $descricao,
                        $pontos,
                        $tipo,
                        $dataEvento,
                        $dataEvento
                    ]);

                    $provaId =
                        (int)$pdo->lastInsertId();


                    if ($provaId <= 0) {

                        throw new RuntimeException(
                            'Não foi possível obter o ID do novo evento.'
                        );
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | DESTINATÁRIOS
                |--------------------------------------------------------------------------
                */

                if ($tipo === 'direcionado') {

                    $stmtDestinatario = $pdo->prepare("
                        INSERT INTO prova_destinatarios
                        (
                            prova_id,
                            usuario_id
                        )
                        VALUES
                        (
                            ?,
                            ?
                        )
                    ");

                    foreach ($destinatarios as $usuarioId) {

                        $stmtDestinatario->execute([
                            $provaId,
                            $usuarioId
                        ]);
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | FINALIZA TRANSAÇÃO
                |--------------------------------------------------------------------------
                */

                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | FEEDBACK
                |--------------------------------------------------------------------------
                */

                $acao = !empty($_POST['prova_id'])
                    ? 'atualizado'
                    : 'cadastrado';

                $feedback = feedbackHtml(
                    'success',
                    "Evento #{$provaId} {$acao} com sucesso."
                );


            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    'gerar_checkin.php - erro ao salvar evento: ' .
                    $e->getMessage()
                );

                $feedback = feedbackHtml(
                    'danger',
                    'Não foi possível salvar o evento. Verifique os dados e tente novamente.'
                );
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| CARREGAR EVENTOS DO DIA
|--------------------------------------------------------------------------
*/

try {

    $stmtProvas = $pdo->prepare("
        SELECT
            p.id,
            p.titulo,
            p.descricao,
            p.pontos,
            p.tipo,
            p.data_inicio
        FROM provas p
        WHERE p.data_inicio = ?
          AND p.titulo LIKE 'Check-in%'
        ORDER BY p.id ASC
    ");

    $stmtProvas->execute([
        $hoje
    ]);

    $provasHoje =
        $stmtProvas->fetchAll(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | CARREGAR DESTINATÁRIOS
    |--------------------------------------------------------------------------
    |
    | Uma consulta única para evitar N+1.
    |
    */

    if (!empty($provasHoje)) {

        $idsProvas = [];

        foreach ($provasHoje as $prova) {

            $id = (int)$prova['id'];

            if ($id > 0) {
                $idsProvas[] = $id;
            }
        }

        $idsProvas = array_values(
            array_unique($idsProvas)
        );


        if (!empty($idsProvas)) {

            $placeholders =
                implode(
                    ',',
                    array_fill(
                        0,
                        count($idsProvas),
                        '?'
                    )
                );

            $stmtDest = $pdo->prepare("
                SELECT
                    pd.prova_id,
                    pd.usuario_id AS id,
                    u.nome
                FROM prova_destinatarios pd
                INNER JOIN usuarios u
                    ON u.id = pd.usuario_id
                WHERE pd.prova_id IN ($placeholders)
                ORDER BY u.nome ASC
            ");

            $stmtDest->execute($idsProvas);

            $destinatariosPorProva = [];

            while (
                $dest =
                $stmtDest->fetch(PDO::FETCH_ASSOC)
            ) {

                $provaIdDest =
                    (int)$dest['prova_id'];

                if (
                    !isset(
                        $destinatariosPorProva[
                            $provaIdDest
                        ]
                    )
                ) {
                    $destinatariosPorProva[
                        $provaIdDest
                    ] = [];
                }

                $destinatariosPorProva[
                    $provaIdDest
                ][] = [
                    'id' =>
                        (int)$dest['id'],

                    'nome' =>
                        (string)$dest['nome']
                ];
            }


            /*
            |--------------------------------------------------------------
            | Anexa destinatários às provas.
            |--------------------------------------------------------------
            */

            foreach ($provasHoje as &$prova) {

                $id =
                    (int)$prova['id'];

                $prova['destinatarios'] =
                    $destinatariosPorProva[$id]
                    ?? [];
            }

            unset($prova);
        }

    } else {

        $provasHoje = [];
    }


} catch (Throwable $e) {

    error_log(
        'gerar_checkin.php - erro ao carregar eventos: ' .
        $e->getMessage()
    );

    $provasHoje = [];

    $feedback = feedbackHtml(
        'danger',
        'Erro ao carregar os eventos de check-in.'
    );
}


/*
|--------------------------------------------------------------------------
| DADOS PARA JAVASCRIPT
|--------------------------------------------------------------------------
*/

$eventosJson = [];

foreach ($provasHoje as $prova) {

    $eventosJson[] = [
        'id' =>
            (int)$prova['id']
    ];
}


$usuariosJson = [];

foreach ($usuarios as $usuario) {

    $usuariosJson[] = [
        'id' =>
            (int)$usuario['id'],

        'nome' =>
            (string)$usuario['nome']
    ];
}

?>

<style>

body {
    background-color: #f8fafc !important;
    color: #1e293b;
}

.main-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    padding: 1.5rem 1rem 3rem;
}

.evento-card {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 22px;
    padding: 1.35rem;
    box-shadow: 0 10px 30px rgba(15, 23, 42, .035);
    height: 100%;
}

.qrcode-box {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 12px;
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    min-height: 244px;
    min-width: 244px;
}

.qrcode-box img,
.qrcode-box canvas {
    display: block;
    margin: 0 auto;
}

.badge-global {
    background: #dcfce7;
    color: #166534;
}

.badge-direcionado {
    background: #dbeafe;
    color: #1d4ed8;
}

.destinatarios-box {
    max-height: 150px;
    overflow-y: auto;
}

.pessoa-item {
    cursor: pointer;
}

.pessoa-item:hover {
    background: #f8fafc;
}

.min-width-0 {
    min-width: 0;
}

@media (max-width: 576px) {

    .main-wrapper {
        padding: 1rem .75rem 2rem;
    }

    .qrcode-box {
        min-width: 224px;
        min-height: 224px;
    }
}

@media print {

    body * {
        visibility: hidden !important;
    }

    .evento-card.printando,
    .evento-card.printando * {
        visibility: visible !important;
    }

    .evento-card.printando {

        position: absolute;
        left: 0;
        top: 0;
        width: 100%;

        border: none;
        box-shadow: none;
        border-radius: 0;
    }

    .no-print {
        display: none !important;
    }
}

</style>


<div class="main-wrapper">

    <!-- CABEÇALHO -->

    <div class="d-flex justify-content-between align-items-center gap-3 mb-4 no-print">

        <div class="d-flex align-items-center min-width-0">

            <a
                href="gerenciar.php"
                class="btn btn-sm btn-outline-secondary rounded-3 me-3 flex-shrink-0"
                title="Voltar"
            >
                <i class="fa-solid fa-arrow-left"></i>
            </a>

            <div>

                <h4 class="fw-bold mb-0">
                    Painel de Check-in
                </h4>

                <small class="text-muted">
                    Eventos de <?= e(date('d/m/Y')) ?>
                </small>

            </div>

        </div>


        <button
            type="button"
            class="btn btn-primary btn-sm rounded-3 fw-bold flex-shrink-0"
            onclick="novoEvento()"
            data-bs-toggle="modal"
            data-bs-target="#modalCadastroCheckin"
        >
            <i class="fa-solid fa-plus me-1"></i>
            Novo evento
        </button>

    </div>


    <?= $feedback ?>


    <!-- SEM EVENTOS -->

    <?php if (empty($provasHoje)): ?>

        <div class="evento-card text-center py-5 no-print">

            <i class="fa-solid fa-calendar-xmark fa-3x text-warning mb-3"></i>

            <h5 class="fw-bold">
                Nenhum evento cadastrado hoje
            </h5>

            <p class="text-muted small mb-4">
                Cadastre um ou vários eventos para esta data.
            </p>

            <button
                type="button"
                class="btn btn-primary btn-sm fw-bold rounded-3"
                onclick="novoEvento()"
                data-bs-toggle="modal"
                data-bs-target="#modalCadastroCheckin"
            >
                <i class="fa-solid fa-plus me-1"></i>
                Cadastrar evento
            </button>

        </div>


    <!-- COM EVENTOS -->

    <?php else: ?>

        <div class="d-flex justify-content-between align-items-center mb-3 no-print">

            <div>

                <span class="fw-bold">
                    <?= count($provasHoje) ?>
                </span>

                evento<?= count($provasHoje) !== 1 ? 's' : '' ?>
                hoje

            </div>

            <small class="text-muted">
                Cada evento possui um QR Code próprio.
            </small>

        </div>


        <div class="row g-4">

            <?php foreach ($provasHoje as $prova): ?>

                <?php

                $provaId =
                    (int)$prova['id'];

                $destinatarios =
                    $prova['destinatarios'] ?? [];

                $destIds = [];

                foreach ($destinatarios as $dest) {

                    $destIds[] =
                        (int)$dest['id'];
                }

                $nomeEvento =
                    nomeEventoDoTitulo(
                        (string)$prova['titulo']
                    );

                $quantidadeDestinatarios =
                    count($destinatarios);

                ?>

                <div class="col-12 col-md-6">

                    <div
                        class="evento-card"
                        id="evento-<?= $provaId ?>"
                    >

                        <!-- CABEÇALHO DO EVENTO -->

                        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">

                            <div class="min-width-0">

                                <h5 class="fw-bold mb-1">
                                    <?= e($nomeEvento) ?>
                                </h5>


                                <?php if ($prova['tipo'] === 'global'): ?>

                                    <span class="badge badge-global">

                                        <i class="fa-solid fa-earth-americas me-1"></i>

                                        Global

                                    </span>

                                <?php else: ?>

                                    <span class="badge badge-direcionado">

                                        <i class="fa-solid fa-users me-1"></i>

                                        <?= $quantidadeDestinatarios ?>

                                        destinatário<?= $quantidadeDestinatarios !== 1 ? 's' : '' ?>

                                    </span>

                                <?php endif; ?>

                            </div>


                            <span class="badge bg-dark flex-shrink-0">
                                #<?= $provaId ?>
                            </span>

                        </div>


                        <!-- QR CODE -->

                        <div class="text-center my-4">

                            <div
                                class="qrcode-box"
                                id="qrcode-<?= $provaId ?>"
                            ></div>

                            <div class="small text-muted mt-2">

                                Código do evento:

                                <strong>
                                    <?= $provaId ?>
                                </strong>

                            </div>

                        </div>


                        <!-- INFORMAÇÕES -->

                        <div class="small">

                            <div class="mb-3">

                                <strong>
                                    Descrição:
                                </strong>

                                <div class="text-muted mt-1">
                                    <?= e($prova['descricao']) ?>
                                </div>

                            </div>


                            <div class="mb-2">

                                <strong>
                                    Recompensa:
                                </strong>

                                <span class="text-success fw-bold">
                                    +<?= (int)$prova['pontos'] ?> PTS
                                </span>

                            </div>


                            <?php if ($prova['tipo'] === 'direcionado'): ?>

                                <div class="mt-3">

                                    <strong>
                                        Participantes autorizados:
                                    </strong>


                                    <?php if (!empty($destinatarios)): ?>

                                        <div class="destinatarios-box mt-2">

                                            <?php foreach ($destinatarios as $dest): ?>

                                                <span class="badge bg-light text-dark border mb-1">

                                                    <i class="fa-solid fa-user me-1"></i>

                                                    <?= e($dest['nome']) ?>

                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php else: ?>

                                        <div class="text-danger mt-1">
                                            Nenhum destinatário configurado.
                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <!-- AÇÕES -->

                        <div class="d-flex gap-2 mt-4 no-print">

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary w-100 rounded-3 fw-bold"
                                onclick="copiarCodigo(<?= $provaId ?>)"
                            >
                                <i class="fa-solid fa-copy me-1"></i>
                                Copiar código
                            </button>


                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary w-100 rounded-3 fw-bold"
                                data-id="<?= $provaId ?>"
                                data-data="<?= e($prova['data_inicio']) ?>"
                                data-pontos="<?= (int)$prova['pontos'] ?>"
                                data-tipo="<?= e($prova['tipo']) ?>"
                                data-titulo="<?= e($prova['titulo']) ?>"
                                data-descricao="<?= e($prova['descricao']) ?>"
                                data-destinatarios="<?= e(
                                    json_encode(
                                        $destIds,
                                        JSON_UNESCAPED_UNICODE |
                                        JSON_UNESCAPED_SLASHES
                                    )
                                ) ?>"
                                onclick="editarEvento(this)"
                                data-bs-toggle="modal"
                                data-bs-target="#modalCadastroCheckin"
                            >
                                <i class="fa-solid fa-pen me-1"></i>
                                Editar
                            </button>


                            <button
                                type="button"
                                class="btn btn-sm btn-dark rounded-3"
                                onclick="imprimirEvento(<?= $provaId ?>)"
                                title="Imprimir"
                            >
                                <i class="fa-solid fa-print"></i>
                            </button>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>


<!-- ====================================================================== -->
<!-- MODAL CADASTRO / EDIÇÃO -->
<!-- ====================================================================== -->

<div
    class="modal fade"
    id="modalCadastroCheckin"
    tabindex="-1"
    aria-labelledby="modalCadastroCheckinLabel"
    aria-hidden="true"
>

    <div
        class="modal-dialog modal-dialog-centered"
        style="max-width:500px;"
    >

        <div
            class="modal-content border-0"
            style="
                border-radius:20px;
                box-shadow:0 15px 50px rgba(0,0,0,.15);
            "
        >

            <div class="modal-header border-0 pb-0">

                <h6
                    class="modal-title fw-bold"
                    id="modalTitulo"
                >
                    🚀 Novo Check-in
                </h6>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>

            </div>


            <form
                method="POST"
                id="formCheckin"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="salvar_checkin"
                    value="1"
                >

                <input
                    type="hidden"
                    name="prova_id"
                    id="prova_id"
                    value=""
                >


                <div class="modal-body">

                    <!-- NOME -->

                    <div class="mb-3">

                        <label
                            for="nome_evento"
                            class="form-label small fw-bold"
                        >
                            Nome do evento
                        </label>

                        <input
                            type="text"
                            name="nome_evento"
                            id="nome_evento"
                            class="form-control rounded-3"
                            placeholder="Ex.: Celebration, Culto Jovem..."
                            maxlength="120"
                            required
                        >

                    </div>


                    <!-- DATA / PONTOS -->

                    <div class="row g-3">

                        <div class="col-6">

                            <label
                                for="data_evento"
                                class="form-label small fw-bold"
                            >
                                Data
                            </label>

                            <input
                                type="date"
                                name="data_evento"
                                id="data_evento"
                                class="form-control rounded-3"
                                value="<?= e($hoje) ?>"
                                required
                            >

                        </div>


                        <div class="col-6">

                            <label
                                for="pontos"
                                class="form-label small fw-bold"
                            >
                                Pontos
                            </label>

                            <input
                                type="number"
                                name="pontos"
                                id="pontos"
                                class="form-control rounded-3"
                                value="40"
                                min="1"
                                max="100000"
                                required
                            >

                        </div>

                    </div>


                    <!-- DESCRIÇÃO -->

                    <div class="mt-3">

                        <label
                            for="descricao"
                            class="form-label small fw-bold"
                        >
                            Descrição
                        </label>

                        <textarea
                            name="descricao"
                            id="descricao"
                            class="form-control rounded-3"
                            rows="3"
                            maxlength="1000"
                            placeholder="Descrição apresentada ao participante..."
                        >Confirme sua presença no evento realizado hoje.</textarea>

                    </div>


                    <!-- TIPO -->

                    <div class="mt-3">

                        <label
                            for="tipo"
                            class="form-label small fw-bold"
                        >
                            Público do check-in
                        </label>

                        <select
                            name="tipo"
                            id="tipo"
                            class="form-select rounded-3"
                            onchange="alterarTipo()"
                        >

                            <option value="global">
                                🌎 Global — todos podem participar
                            </option>

                            <option value="direcionado">
                                👥 Pessoas selecionadas
                            </option>

                        </select>

                    </div>


                    <!-- DESTINATÁRIOS -->

                    <div
                        class="mt-3 d-none"
                        id="boxDestinatarios"
                    >

                        <label class="form-label small fw-bold">
                            Participantes autorizados
                        </label>


                        <div class="input-group mb-2">

                            <span class="input-group-text">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </span>

                            <input
                                type="text"
                                id="buscarPessoa"
                                class="form-control"
                                placeholder="Buscar participante..."
                                autocomplete="off"
                                oninput="filtrarPessoas()"
                            >

                        </div>


                        <div
                            class="border rounded-3 p-2"
                            style="
                                max-height:220px;
                                overflow-y:auto;
                            "
                            id="listaPessoas"
                        >

                            <?php foreach ($usuarios as $usuario): ?>

                                <label
                                    class="d-flex align-items-center gap-2 p-2 rounded pessoa-item"
                                    data-nome="<?= e(
                                        mb_strtolower(
                                            $usuario['nome'],
                                            'UTF-8'
                                        )
                                    ) ?>"
                                >

                                    <input
                                        type="checkbox"
                                        class="form-check-input destinatario-check"
                                        name="destinatarios[]"
                                        value="<?= (int)$usuario['id'] ?>"
                                    >

                                    <span class="small">
                                        <?= e($usuario['nome']) ?>
                                    </span>

                                </label>

                            <?php endforeach; ?>

                            <?php if (empty($usuarios)): ?>

                                <div class="text-center text-muted small py-3">
                                    Nenhum participante disponível.
                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="d-flex justify-content-between mt-2">

                            <small class="text-muted">
                                Uma ou várias pessoas podem ser selecionadas.
                            </small>

                            <small
                                class="fw-bold text-primary"
                                id="contadorSelecionados"
                            >
                                0 selecionado(s)
                            </small>

                        </div>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="modal-footer border-0 pt-0">

                    <button
                        type="button"
                        class="btn btn-secondary btn-sm rounded-3 px-3"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary btn-sm rounded-3 fw-bold px-4"
                        id="btnSalvarCheckin"
                    >
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Salvar evento
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- QR CODE -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>


<script>
'use strict';


/*
|--------------------------------------------------------------------------
| DADOS DO PHP
|--------------------------------------------------------------------------
*/

const eventos = <?= json_encode(
    $eventosJson,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;

const usuarios = <?= json_encode(
    $usuariosJson,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES
) ?>;


/*
|--------------------------------------------------------------------------
| GERAR QR CODES
|--------------------------------------------------------------------------
|
| IMPORTANTE:
| O QR contém somente o ID numérico da prova.
|
| Exemplo:
|
| 152
|
| O checkin.php recebe:
|
| prova_id = 152
|
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        eventos.forEach(
            function (evento) {

                const container =
                    document.getElementById(
                        'qrcode-' + evento.id
                    );

                if (!container) {
                    return;
                }

                new QRCode(
                    container,
                    {
                        text: String(evento.id),

                        width: 220,
                        height: 220,

                        colorDark: '#0f172a',
                        colorLight: '#ffffff',

                        correctLevel:
                            QRCode.CorrectLevel.H
                    }
                );
            }
        );


        /*
        |--------------------------------------------------------------
        | Checkboxes
        |--------------------------------------------------------------
        */

        document
            .querySelectorAll(
                '.destinatario-check'
            )
            .forEach(
                function (check) {

                    check.addEventListener(
                        'change',
                        atualizarContador
                    );

                }
            );


        atualizarContador();
    }
);


/*
|--------------------------------------------------------------------------
| NOVO EVENTO
|--------------------------------------------------------------------------
*/

function novoEvento() {

    const form =
        document.getElementById(
            'formCheckin'
        );

    if (form) {
        form.reset();
    }


    document.getElementById(
        'modalTitulo'
    ).textContent =
        '🚀 Novo Check-in';


    document.getElementById(
        'prova_id'
    ).value = '';


    document.getElementById(
        'data_evento'
    ).value =
        '<?= e($hoje) ?>';


    document.getElementById(
        'pontos'
    ).value = '40';


    document.getElementById(
        'descricao'
    ).value =
        'Confirme sua presença no evento realizado hoje.';


    document.getElementById(
        'tipo'
    ).value =
        'global';


    document
        .querySelectorAll(
            '.destinatario-check'
        )
        .forEach(
            function (check) {
                check.checked = false;
            }
        );


    document.getElementById(
        'buscarPessoa'
    ).value = '';


    document
        .querySelectorAll(
            '.pessoa-item'
        )
        .forEach(
            function (item) {
                item.style.display = '';
            }
        );


    alterarTipo();
    atualizarContador();
}


/*
|--------------------------------------------------------------------------
| EDITAR EVENTO
|--------------------------------------------------------------------------
*/

function editarEvento(botao) {

    let destinatarios = [];


    try {

        destinatarios =
            JSON.parse(
                botao.dataset.destinatarios ||
                '[]'
            );

    } catch (error) {

        console.error(
            'Erro ao ler destinatários:',
            error
        );

        destinatarios = [];
    }


    destinatarios =
        destinatarios.map(Number);


    document.getElementById(
        'modalTitulo'
    ).textContent =
        '📝 Editar Check-in #' +
        botao.dataset.id;


    document.getElementById(
        'prova_id'
    ).value =
        botao.dataset.id;


    document.getElementById(
        'data_evento'
    ).value =
        botao.dataset.data;


    document.getElementById(
        'pontos'
    ).value =
        botao.dataset.pontos;


    document.getElementById(
        'descricao'
    ).value =
        botao.dataset.descricao;


    document.getElementById(
        'tipo'
    ).value =
        botao.dataset.tipo;


    document.getElementById(
        'nome_evento'
    ).value =
        extrairNomeEvento(
            botao.dataset.titulo
        );


    document
        .querySelectorAll(
            '.destinatario-check'
        )
        .forEach(
            function (check) {

                check.checked =
                    destinatarios.includes(
                        Number(check.value)
                    );

            }
        );


    document.getElementById(
        'buscarPessoa'
    ).value = '';


    document
        .querySelectorAll(
            '.pessoa-item'
        )
        .forEach(
            function (item) {
                item.style.display = '';
            }
        );


    alterarTipo();
    atualizarContador();
}


/*
|--------------------------------------------------------------------------
| EXTRAIR NOME
|--------------------------------------------------------------------------
*/

function extrairNomeEvento(titulo) {

    return String(titulo || '')
        .replace(
            /^Check-in(?:\s+no\s+Culto)?\s*-\s*/i,
            ''
        )
        .replace(
            /\s*-\s*\d{2}\/\d{2}$/i,
            ''
        )
        .trim();
}


/*
|--------------------------------------------------------------------------
| ALTERAR TIPO
|--------------------------------------------------------------------------
*/

function alterarTipo() {

    const tipo =
        document.getElementById(
            'tipo'
        ).value;

    const box =
        document.getElementById(
            'boxDestinatarios'
        );


    if (tipo === 'direcionado') {

        box.classList.remove(
            'd-none'
        );

    } else {

        box.classList.add(
            'd-none'
        );

    }


    atualizarContador();
}


/*
|--------------------------------------------------------------------------
| FILTRAR PESSOAS
|--------------------------------------------------------------------------
*/

function filtrarPessoas() {

    const campo =
        document.getElementById(
            'buscarPessoa'
        );

    const busca =
        campo.value
            .toLocaleLowerCase(
                'pt-BR'
            )
            .trim();


    document
        .querySelectorAll(
            '.pessoa-item'
        )
        .forEach(
            function (item) {

                const nome =
                    item.dataset.nome ||
                    '';

                item.style.display =
                    nome.includes(busca)
                        ? ''
                        : 'none';

            }
        );
}


/*
|--------------------------------------------------------------------------
| CONTADOR
|--------------------------------------------------------------------------
*/

function atualizarContador() {

    const selecionados =
        document.querySelectorAll(
            '.destinatario-check:checked'
        ).length;


    const contador =
        document.getElementById(
            'contadorSelecionados'
        );


    if (contador) {

        contador.textContent =
            selecionados +
            ' selecionado(s)';
    }
}


/*
|--------------------------------------------------------------------------
| COPIAR CÓDIGO
|--------------------------------------------------------------------------
*/

async function copiarCodigo(id) {

    const codigo =
        String(id);


    try {

        if (
            navigator.clipboard &&
            window.isSecureContext
        ) {

            await navigator.clipboard.writeText(
                codigo
            );

        } else {

            const campo =
                document.createElement(
                    'textarea'
                );

            campo.value = codigo;

            campo.style.position =
                'fixed';

            campo.style.opacity =
                '0';


            document.body.appendChild(
                campo
            );


            campo.focus();
            campo.select();


            document.execCommand(
                'copy'
            );


            campo.remove();
        }


        alert(
            '🎯 Código do check-in copiado: ' +
            codigo
        );


    } catch (error) {

        window.prompt(
            'Copie o código do check-in:',
            codigo
        );
    }
}


/*
|--------------------------------------------------------------------------
| IMPRESSÃO INDIVIDUAL
|--------------------------------------------------------------------------
*/

function imprimirEvento(id) {

    document
        .querySelectorAll(
            '.evento-card'
        )
        .forEach(
            function (evento) {

                evento.classList.remove(
                    'printando'
                );

            }
        );


    const escolhido =
        document.getElementById(
            'evento-' + id
        );


    if (!escolhido) {
        return;
    }


    escolhido.classList.add(
        'printando'
    );


    window.print();


    setTimeout(
        function () {

            escolhido.classList.remove(
                'printando'
            );

        },
        500
    );
}


/*
|--------------------------------------------------------------------------
| VALIDAÇÃO CLIENT-SIDE DO FORMULÁRIO
|--------------------------------------------------------------------------
|
| A validação abaixo é apenas UX.
| A validação real permanece no PHP.
|
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.getElementById(
                'formCheckin'
            );


        if (!form) {
            return;
        }


        form.addEventListener(
            'submit',
            function (event) {

                const tipo =
                    document.getElementById(
                        'tipo'
                    ).value;


                if (
                    tipo === 'direcionado'
                ) {

                    const quantidade =
                        document.querySelectorAll(
                            '.destinatario-check:checked'
                        ).length;


                    if (quantidade === 0) {

                        event.preventDefault();

                        alert(
                            'Selecione pelo menos uma pessoa para este check-in direcionado.'
                        );

                        return;
                    }
                }


                /*
                |----------------------------------------------------------
                | Evita clique duplo no botão.
                |----------------------------------------------------------
                */

                const botao =
                    document.getElementById(
                        'btnSalvarCheckin'
                    );


                if (botao) {

                    botao.disabled =
                        true;

                    botao.innerHTML =
                        '<i class="fa-solid fa-spinner fa-spin me-1"></i> Salvando...';
                }
            }
        );
    }
);

</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</body>
</html>