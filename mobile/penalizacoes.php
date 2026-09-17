<?php
require_once '../config.php';
exigirAdministrador($pdo);
require_once 'header.php';

date_default_timezone_set('America/Recife');

/*
|--------------------------------------------------------------------------
| AUTENTICAÇÃO
|--------------------------------------------------------------------------
*/
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminId = (int)($_SESSION['user_id'] ?? 0);

if ($adminId <= 0) {
    header('Location: index.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| SOMENTE ADMINISTRADORES
|--------------------------------------------------------------------------
*/
$perfilAdmin = strtolower(trim((string)($_SESSION['perfil'] ?? '')));

$perfisPermitidos = [
    'admin',
    'administrador',
    'superadmin',
    'super_admin'
];

if (!in_array($perfilAdmin, $perfisPermitidos, true)) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acesso negado</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 max-w-md w-full text-center">

            <div class="w-16 h-16 mx-auto rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl mb-4">
                <i class="fa-solid fa-lock"></i>
            </div>

            <h1 class="text-xl font-extrabold text-slate-900 mb-2">
                Acesso restrito
            </h1>

            <p class="text-sm text-slate-500 mb-6">
                Esta área está disponível somente para administradores da Arena.
            </p>

            <a href="dashboard.php"
               class="inline-flex items-center justify-center gap-2 w-full bg-violet-600 hover:bg-violet-700 text-white font-bold py-3 px-4 rounded-xl transition">
                <i class="fa-solid fa-arrow-left"></i>
                Voltar ao Dashboard
            </a>

        </div>

    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/
if (empty($_SESSION['csrf_penalizacoes'])) {
    $_SESSION['csrf_penalizacoes'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_penalizacoes'];

/*
|--------------------------------------------------------------------------
| MENSAGENS
|--------------------------------------------------------------------------
*/
$mensagem = '';
$tipoMensagem = '';

/*
|--------------------------------------------------------------------------
| PROCESSAMENTO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf, $token)) {

        $mensagem = 'A sessão de segurança expirou. Atualize a página e tente novamente.';
        $tipoMensagem = 'erro';

    } else {

        $grupoId = (int)($_POST['grupo_id'] ?? 0);
        $tipo = trim((string)($_POST['tipo'] ?? ''));
        $pontos = (int)($_POST['pontos'] ?? 0);
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $observacao = trim((string)($_POST['observacao'] ?? ''));

        /*
        |--------------------------------------------------------------------------
        | VALIDAÇÕES
        |--------------------------------------------------------------------------
        */

        if ($grupoId <= 0) {

            $mensagem = 'Selecione um grupo.';
            $tipoMensagem = 'erro';

        } elseif (!in_array($tipo, ['adicao', 'penalizacao'], true)) {

            $mensagem = 'Tipo de operação inválido.';
            $tipoMensagem = 'erro';

        } elseif ($pontos <= 0) {

            $mensagem = 'Informe uma quantidade de pontos maior que zero.';
            $tipoMensagem = 'erro';

        } elseif ($pontos > 100000) {

            $mensagem = 'A quantidade máxima permitida é de 100.000 pontos.';
            $tipoMensagem = 'erro';

        } elseif ($motivo === '') {

            $mensagem = 'Informe o motivo da operação.';
            $tipoMensagem = 'erro';

        } elseif (mb_strlen($motivo) > 255) {

            $mensagem = 'O motivo pode possuir no máximo 255 caracteres.';
            $tipoMensagem = 'erro';

        } elseif (mb_strlen($observacao) > 5000) {

            $mensagem = 'A observação pode possuir no máximo 5.000 caracteres.';
            $tipoMensagem = 'erro';

        } else {

            /*
            |--------------------------------------------------------------------------
            | CONFERE SE O GRUPO EXISTE
            |--------------------------------------------------------------------------
            */
            $stmtGrupo = $pdo->prepare("
                SELECT id, nome
                FROM grupos
                WHERE id = ?
                LIMIT 1
            ");

            $stmtGrupo->execute([$grupoId]);
            $grupoSelecionado = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

            if (!$grupoSelecionado) {

                $mensagem = 'O grupo selecionado não foi encontrado.';
                $tipoMensagem = 'erro';

            } else {

                try {

                    $pdo->beginTransaction();

                    /*
                    |--------------------------------------------------------------------------
                    | REGISTRA A OPERAÇÃO
                    |--------------------------------------------------------------------------
                    */
                    $stmtInsert = $pdo->prepare("
                        INSERT INTO penalizacoes_grupos
                        (
                            grupo_id,
                            usuario_admin_id,
                            tipo,
                            pontos,
                            motivo,
                            observacao,
                            criado_em
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            NOW()
                        )
                    ");

                    $stmtInsert->execute([
                        $grupoId,
                        $adminId,
                        $tipo,
                        $pontos,
                        $motivo,
                        $observacao !== '' ? $observacao : null
                    ]);

                    $pdo->commit();

                    $mensagem = $tipo === 'penalizacao'
                        ? 'Penalização registrada com sucesso.'
                        : 'Pontos adicionados ao grupo com sucesso.';

                    $tipoMensagem = 'sucesso';

                    /*
                    |--------------------------------------------------------------------------
                    | LIMPA POST APÓS SUCESSO
                    |--------------------------------------------------------------------------
                    */
                    $_POST = [];

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        'Erro em penalizacoes.php: ' . $e->getMessage()
                    );

                    $mensagem = 'Não foi possível registrar a operação. Tente novamente.';
                    $tipoMensagem = 'erro';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| GRUPOS
|--------------------------------------------------------------------------
*/
$stmtGrupos = $pdo->query("
    SELECT id, nome
    FROM grupos
    ORDER BY nome ASC
");

$grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| HISTÓRICO
|--------------------------------------------------------------------------
*/
$stmtHistorico = $pdo->query("
    SELECT
        p.id,
        p.grupo_id,
        p.tipo,
        p.pontos,
        p.motivo,
        p.observacao,
        p.criado_em,
        g.nome AS grupo_nome,
        u.nome AS admin_nome
    FROM penalizacoes_grupos p
    INNER JOIN grupos g
        ON g.id = p.grupo_id
    LEFT JOIN usuarios u
        ON u.id = p.usuario_admin_id
    ORDER BY p.id DESC
    LIMIT 100
");

$historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| RESUMO GERAL
|--------------------------------------------------------------------------
*/
$stmtResumo = $pdo->query("
    SELECT
        COUNT(*) AS total_operacoes,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'adicao'
                    THEN pontos
                    ELSE 0
                END
            ),
            0
        ) AS total_adicoes,

        COALESCE(
            SUM(
                CASE
                    WHEN tipo = 'penalizacao'
                    THEN pontos
                    ELSE 0
                END
            ),
            0
        ) AS total_penalizacoes

    FROM penalizacoes_grupos
");

$resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [
    'total_operacoes' => 0,
    'total_adicoes' => 0,
    'total_penalizacoes' => 0
];

/*
|--------------------------------------------------------------------------
| SALDO POR GRUPO
|--------------------------------------------------------------------------
*/
$stmtSaldos = $pdo->query("
    SELECT
        g.id,
        g.nome,
        g.logo,
        COALESCE(
            SUM(
                CASE
                    WHEN p.tipo = 'adicao' THEN p.pontos
                    WHEN p.tipo = 'penalizacao' THEN -p.pontos
                    ELSE 0
                END
            ),
            0
        ) AS saldo
    FROM grupos g
    LEFT JOIN penalizacoes_grupos p
        ON p.grupo_id = g.id
    GROUP BY
        g.id,
        g.nome,
        g.logo
    ORDER BY
        saldo DESC,
        g.nome ASC
");

$saldosGrupos = $stmtSaldos->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| TOTAL LÍQUIDO
|--------------------------------------------------------------------------
*/
$totalLiquido =
    (int)$resumo['total_adicoes']
    -
    (int)$resumo['total_penalizacoes'];

/*
|--------------------------------------------------------------------------
| DADOS DO FORMULÁRIO
|--------------------------------------------------------------------------
*/
$formGrupo = (int)($_POST['grupo_id'] ?? 0);
$formTipo = $_POST['tipo'] ?? 'penalizacao';
$formPontos = $_POST['pontos'] ?? '';
$formMotivo = $_POST['motivo'] ?? '';
$formObservacao = $_POST['observacao'] ?? '';

?>

<style>

    body {
        background-color: #f8fafc !important;
        color: #1e293b;
    }

    .main-wrapper {
        max-width: 480px;
        margin: 0 auto;
        padding: 1rem 1rem 5rem 1rem;
    }

    /*
    |--------------------------------------------------------------------------
    | CABEÇALHO
    |--------------------------------------------------------------------------
    */

    .welcome-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .welcome-section .text-box p {
        font-size: 0.9rem;
        color: #64748b;
        margin: 0;
    }

    .welcome-section .text-box h2 {
        font-size: 1.6rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .admin-icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background-color: #fef2f2;
        border: 2px solid #fecaca;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ef4444;
        font-size: 1.1rem;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.10);
    }

    /*
    |--------------------------------------------------------------------------
    | CARD PRINCIPAL
    |--------------------------------------------------------------------------
    */

    .level-progression-card {
        background-color: #ffffff;
        border-radius: 20px;
        padding: 1.25rem;
        box-shadow: 0 10px 25px rgba(168, 85, 247, 0.05);
        border: 1px solid #f1f5f9;
        margin-bottom: 1.25rem;
    }

    .level-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 0.75rem;
    }

    .level-icon-wrapper {
        width: 36px;
        height: 36px;
        background-color: #a855f7;
        color: white;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .level-title-text {
        font-size: 0.9rem;
        font-weight: 800;
        color: #7c3aed;
    }

    .card-description {
        color: #64748b;
        font-size: 0.8rem;
        line-height: 1.5;
        margin: 0;
    }

    /*
    |--------------------------------------------------------------------------
    | MÉTRICAS
    |--------------------------------------------------------------------------
    */

    .metrics-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 1.25rem;
    }

    .metric-square-card {
        background-color: #ffffff;
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 12px rgba(0,0,0,0.01);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .metric-square-card:active {
        transform: scale(0.98);
    }

    .metric-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 0.5rem;
    }

    .metric-value {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
    }

    /*
    |--------------------------------------------------------------------------
    | BOTÃO
    |--------------------------------------------------------------------------
    */

    .action-links-row {
        display: block;
        width: 100%;
        margin-bottom: 1.25rem;
    }

    .action-pill-btn {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: #334155;
        font-weight: 700;
        font-size: 0.95rem;
        text-decoration: none;
        box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        transition: transform 0.2s, background-color 0.2s;
    }

    .action-pill-btn:active {
        transform: scale(0.99);
        background-color: #f1f5f9;
    }

    /*
    |--------------------------------------------------------------------------
    | FORMULÁRIO
    |--------------------------------------------------------------------------
    */

    .form-card {
        background-color: #ffffff;
        border-radius: 20px;
        padding: 1.25rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 10px 25px rgba(168, 85, 247, 0.05);
        margin-bottom: 1.25rem;
    }

    .form-label-custom {
        display: block;
        font-size: 0.78rem;
        font-weight: 800;
        color: #475569;
        margin-bottom: 0.4rem;
    }

    .form-control-custom {
        width: 100%;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 0.75rem 0.85rem;
        background-color: #ffffff;
        color: #0f172a;
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-control-custom:focus {
        border-color: #a855f7;
        box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.10);
    }

    .form-group-custom {
        margin-bottom: 1rem;
    }

    .operation-box {
        border-radius: 14px;
        padding: 0.85rem;
        margin-bottom: 1rem;
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.45;
    }

    .operation-penalizacao {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    .operation-adicao {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .btn-submit-custom {
        width: 100%;
        border: 0;
        border-radius: 14px;
        padding: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        font-weight: 800;
        font-size: 0.95rem;
        color: #ffffff;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-submit-custom.penalizacao {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.20);
    }

    .btn-submit-custom.adicao {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 8px 20px rgba(16, 185, 129, 0.20);
    }

    .btn-submit-custom:active {
        transform: scale(0.98);
    }

    /*
    |--------------------------------------------------------------------------
    | ALERTAS
    |--------------------------------------------------------------------------
    */

    .alert-custom {
        border-radius: 14px;
        padding: 0.9rem 1rem;
        margin-bottom: 1.25rem;
        font-size: 0.82rem;
        font-weight: 700;
        display: flex;
        align-items: flex-start;
        gap: 9px;
    }

    .alert-success-custom {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #065f46;
    }

    .alert-error-custom {
        background: #fef2f2;
        border: 1px solid #fecaca;
        color: #991b1b;
    }

    /*
    |--------------------------------------------------------------------------
    | TÍTULOS DE SEÇÃO
    |--------------------------------------------------------------------------
    */

    .section-title-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .section-title-container h5 {
        font-size: 1rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }

    .section-title-container span {
        font-size: 0.75rem;
        font-weight: 700;
        color: #94a3b8;
    }

    /*
    |--------------------------------------------------------------------------
    | GRUPOS
    |--------------------------------------------------------------------------
    */

    .group-balance-card {
        background-color: #ffffff;
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.01);
    }

    .group-left-block {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .group-icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background-color: #ede9fe;
        color: #8b5cf6;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
        flex-shrink: 0;
    }

    .group-info-meta {
        min-width: 0;
    }

    .group-info-meta h6 {
        font-size: 0.9rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 0.15rem 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .group-info-meta span {
        font-size: 0.73rem;
        color: #94a3b8;
        font-weight: 600;
    }

    .group-score-right {
        font-size: 0.95rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .score-positive {
        color: #10b981;
    }

    .score-negative {
        color: #ef4444;
    }

    .score-neutral {
        color: #64748b;
    }

    /*
    |--------------------------------------------------------------------------
    | HISTÓRICO
    |--------------------------------------------------------------------------
    */

    .history-item {
        background-color: #ffffff;
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.01);
        gap: 12px;
    }

    .history-left {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
    }

    .history-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .history-icon.penalizacao {
        background-color: #fee2e2;
        color: #ef4444;
    }

    .history-icon.adicao {
        background-color: #dcfce7;
        color: #10b981;
    }

    .history-info {
        min-width: 0;
    }

    .history-info h6 {
        font-size: 0.86rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 0.15rem 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .history-info span {
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 600;
        display: block;
    }

    .history-points {
        font-size: 0.9rem;
        font-weight: 800;
        white-space: nowrap;
    }

    .empty-state {
        background-color: #ffffff;
        border-radius: 16px;
        padding: 1.5rem 1rem;
        border: 1px solid #f1f5f9;
        text-align: center;
        color: #94a3b8;
        font-size: 0.8rem;
    }

    /*
    |--------------------------------------------------------------------------
    | RESPONSIVO
    |--------------------------------------------------------------------------
    */

    @media (max-width: 360px) {

        .main-wrapper {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        .metric-square-card {
            padding: 0.85rem;
        }

        .history-item,
        .group-balance-card {
            padding: 0.85rem;
        }

    }
    .group-card {
    background: #fff;
    border: 1px solid #f1f5f9;
    border-radius: 16px;
    padding: .9rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    box-shadow: 0 3px 12px rgba(15, 23, 42, .04);
}

.group-info {
    display: flex;
    align-items: center;
    gap: .75rem;
    min-width: 0;
}

.group-avatar {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 13px;
    background: #f3e8ff;
    color: #7c3aed;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-size: 1.05rem;
}

.group-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.group-name {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.group-name strong {
    color: #1e293b;
    font-size: .92rem;
    font-weight: 800;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.group-name span {
    color: #94a3b8;
    font-size: .72rem;
    margin-top: 2px;
}

.group-balance {
    font-size: 1rem;
    font-weight: 900;
    white-space: nowrap;
}

.group-balance small {
    font-size: .65rem;
    font-weight: 700;
}

.group-balance.positive {
    color: #16a34a;
}

.group-balance.negative {
    color: #dc2626;
}

</style>

<div class="main-wrapper">

    <!-- CABEÇALHO -->
    
    <style>
    body { background-color: #f8fafc !important; color: #1e293b; }
    .main-wrapper { max-width: 500px; margin: 0 auto; padding: 1.5rem 1rem 5rem 1rem; }
    .task-admin-card { background: #ffffff; border-radius: 16px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.01); display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .search-input-group { position: relative; }
    .search-input-group i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-input-group input { width: 100%; padding: 0.5rem 1rem 0.5rem 2.2rem; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem; outline: none; }
</style>

<div class="main-wrapper">
       <div class="d-flex align-items-center mb-3">
        <a href="gerenciar.php" class="btn btn-sm btn-outline-secondary rounded-3 me-3"><i class="fa-solid fa-arrow-left"></i></a>
      Retornar ao Gerenciador
    </div>

    <div class="welcome-section">

        <div class="text-box">

            <p>Administração</p>

            <h2>Penalizações</h2>

        </div>

        <div class="admin-icon-circle">
            <i class="fa-solid fa-gavel"></i>
        </div>

    </div>


    <!-- EXPLICAÇÃO -->

    <div class="level-progression-card">

        <div class="level-header">

            <div class="level-icon-wrapper">
                <i class="fa-solid fa-scale-balanced"></i>
            </div>

            <div class="level-title-text">
                Ajuste de Pontuação
            </div>

        </div>

        <p class="card-description">
            Registre adições ou penalizações de pontos nos grupos.
            Todas as operações ficam armazenadas no histórico administrativo.
        </p>

    </div>


    <!-- MENSAGEM -->

    <?php if ($mensagem !== ''): ?>

        <div class="alert-custom <?= $tipoMensagem === 'sucesso'
            ? 'alert-success-custom'
            : 'alert-error-custom'
        ?>">

            <i class="fa-solid <?= $tipoMensagem === 'sucesso'
                ? 'fa-circle-check'
                : 'fa-circle-exclamation'
            ?>"></i>

            <span>
                <?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- MÉTRICAS -->

    <div class="metrics-grid">

        <div class="metric-square-card">

            <div class="metric-label" style="color: #6366f1;">
                <i class="fa-solid fa-list-check"></i>
                Operações
            </div>

            <div class="metric-value">
                <?= number_format((int)$resumo['total_operacoes'], 0, ',', '.') ?>
            </div>

        </div>


        <div class="metric-square-card">

            <div class="metric-label" style="color: #10b981;">
                <i class="fa-solid fa-plus"></i>
                Adicionados
            </div>

            <div class="metric-value">
                <?= number_format((int)$resumo['total_adicoes'], 0, ',', '.') ?>
            </div>

        </div>


        <div class="metric-square-card">

            <div class="metric-label" style="color: #ef4444;">
                <i class="fa-solid fa-minus"></i>
                Penalizados
            </div>

            <div class="metric-value">
                <?= number_format((int)$resumo['total_penalizacoes'], 0, ',', '.') ?>
            </div>

        </div>


        <div class="metric-square-card">

            <div class="metric-label" style="color: #8b5cf6;">
                <i class="fa-solid fa-chart-line"></i>
                Saldo Líquido
            </div>

            <div class="metric-value"
                 style="color: <?= $totalLiquido >= 0 ? '#10b981' : '#ef4444' ?>;">

                <?= $totalLiquido > 0 ? '+' : '' ?>
                <?= number_format($totalLiquido, 0, ',', '.') ?>

            </div>

        </div>

    </div>


    <!-- FORMULÁRIO -->

    <div class="section-title-container">

        <h5>Nova Operação</h5>

        <span>
            Ajuste administrativo
        </span>

    </div>


    <div class="form-card">

        <form method="POST"
              action=""
              id="formPenalizacao"
              autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
            >


            <!-- GRUPO -->

            <div class="form-group-custom">

                <label
                    for="grupo_id"
                    class="form-label-custom"
                >
                    Grupo
                </label>

                <select
                    name="grupo_id"
                    id="grupo_id"
                    class="form-control-custom"
                    required
                >

                    <option value="">
                        Selecione o grupo
                    </option>

                    <?php foreach ($grupos as $grupo): ?>

                        <option
                            value="<?= (int)$grupo['id'] ?>"
                            <?= $formGrupo === (int)$grupo['id'] ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $grupo['nome'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TIPO -->

            <div class="form-group-custom">

                <label
                    for="tipo"
                    class="form-label-custom"
                >
                    Tipo de operação
                </label>

                <select
                    name="tipo"
                    id="tipo"
                    class="form-control-custom"
                    required
                >

                    <option
                        value="penalizacao"
                        <?= $formTipo === 'penalizacao' ? 'selected' : '' ?>
                    >
                        Penalização / retirar pontos
                    </option>

                    <option
                        value="adicao"
                        <?= $formTipo === 'adicao' ? 'selected' : '' ?>
                    >
                        Adição / conceder pontos
                    </option>

                </select>

            </div>


            <!-- AVISO DINÂMICO -->

            <div
                id="operationBox"
                class="operation-box operation-penalizacao"
            >
                <i class="fa-solid fa-triangle-exclamation"></i>

                <span id="operationText">
                    Os pontos informados serão retirados do saldo administrativo do grupo.
                </span>

            </div>


            <!-- PONTOS -->

            <div class="form-group-custom">

                <label
                    for="pontos"
                    class="form-label-custom"
                >
                    Pontos
                </label>

                <input
                    type="number"
                    name="pontos"
                    id="pontos"
                    class="form-control-custom"
                    min="1"
                    max="100000"
                    step="1"
                    value="<?= htmlspecialchars((string)$formPontos, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Ex.: 20"
                    required
                >

            </div>


            <!-- MOTIVO -->

            <div class="form-group-custom">

                <label
                    for="motivo"
                    class="form-label-custom"
                >
                    Motivo
                </label>

                <input
                    type="text"
                    name="motivo"
                    id="motivo"
                    class="form-control-custom"
                    maxlength="255"
                    value="<?= htmlspecialchars((string)$formMotivo, ENT_QUOTES, 'UTF-8') ?>"
                    placeholder="Ex.: Infração às regras da Arena"
                    required
                >

            </div>


            <!-- OBSERVAÇÃO -->

            <div class="form-group-custom">

                <label
                    for="observacao"
                    class="form-label-custom"
                >
                    Observação
                    <span style="font-weight:600;color:#94a3b8;">
                        (opcional)
                    </span>
                </label>

                <textarea
                    name="observacao"
                    id="observacao"
                    class="form-control-custom"
                    rows="4"
                    maxlength="5000"
                    placeholder="Detalhes adicionais sobre a operação..."
                ><?= htmlspecialchars((string)$formObservacao, ENT_QUOTES, 'UTF-8') ?></textarea>

            </div>


            <!-- BOTÃO -->

            <button
                type="submit"
                id="btnSubmit"
                class="btn-submit-custom penalizacao"
            >

                <i class="fa-solid fa-gavel"></i>

                <span id="btnSubmitText">
                    Registrar Penalização
                </span>

            </button>

        </form>

    </div>



    <!-- SALDO DOS GRUPOS -->

    <div class="section-title-container">

        <h5>Saldo dos Grupos</h5>

        <span>
            Acumulado
        </span>

    </div>


    <div>

        <?php if (empty($saldosGrupos)): ?>

            <div class="empty-state">

                <i class="fa-solid fa-users-slash fs-4 d-block mb-2"></i>

                Nenhum grupo encontrado.

            </div>

        <?php else: ?>

            <?php foreach ($saldosGrupos as $grupo): ?>

                <?php

                $saldo = (int)$grupo['saldo'];

                if ($saldo > 0) {
                    $classeSaldo = 'score-positive';
                    $prefixoSaldo = '+';
                } elseif ($saldo < 0) {
                    $classeSaldo = 'score-negative';
                    $prefixoSaldo = '';
                } else {
                    $classeSaldo = 'score-neutral';
                    $prefixoSaldo = '';
                }

                ?>

                <div class="group-card">

    <div class="group-info">

        <div class="group-avatar">
            <?php if (!empty($grupo['logo'])): ?>
                <img
                    src="data:image/jpeg;base64,<?= base64_encode($grupo['logo']) ?>"
                    alt="<?= htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8') ?>"
                >
            <?php else: ?>
                <i class="fa-solid fa-users"></i>
            <?php endif; ?>
        </div>

        <div class="group-name">
            <strong>
                <?= htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8') ?>
            </strong>

            <span>
                Saldo administrativo
            </span>
        </div>

    </div>

    <div class="group-balance <?= $saldo >= 0 ? 'positive' : 'negative' ?>">
        <?= $saldo > 0 ? '+' : '' ?><?= number_format($saldo, 0, ',', '.') ?>
        <small>pts</small>
    </div>

</div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <!-- HISTÓRICO -->

    <div class="section-title-container" style="margin-top:1.75rem;">

        <h5>Histórico Recente</h5>

        <span>
            Últimas 100
        </span>

    </div>


    <div>

        <?php if (empty($historico)): ?>

            <div class="empty-state">

                <i class="fa-solid fa-clock-rotate-left fs-4 d-block mb-2"></i>

                Nenhuma operação registrada ainda.

            </div>

        <?php else: ?>

            <?php foreach ($historico as $item): ?>

                <?php

                $isPenalizacao = $item['tipo'] === 'penalizacao';

                $dataOperacao = date(
                    'd/m/Y H:i',
                    strtotime($item['criado_em'])
                );

                ?>

                <div class="history-item">

                    <div class="history-left">

                        <div class="history-icon <?= $isPenalizacao
                            ? 'penalizacao'
                            : 'adicao'
                        ?>">

                            <i class="fa-solid <?= $isPenalizacao
                                ? 'fa-minus'
                                : 'fa-plus'
                            ?>"></i>

                        </div>


                        <div class="history-info">

                            <h6>

                                <?= htmlspecialchars(
                                    $item['grupo_nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </h6>

                            <span>

                                <?= htmlspecialchars(
                                    $item['motivo'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                            <span>

                                <i class="fa-regular fa-clock"></i>

                                <?= $dataOperacao ?>

                                ·

                                <?= htmlspecialchars(
                                    $item['admin_nome'] ?? 'Administrador',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </div>

                    </div>


                    <div class="history-points <?= $isPenalizacao
                        ? 'score-negative'
                        : 'score-positive'
                    ?>">

                        <?= $isPenalizacao ? '-' : '+' ?>
                        <?= number_format(
                            (int)$item['pontos'],
                            0,
                            ',',
                            '.'
                        ) ?>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <!-- RODAPÉ INFORMATIVO -->

    <div
        style="
            margin-top:1.5rem;
            padding:0.9rem;
            text-align:center;
            color:#94a3b8;
            font-size:0.72rem;
            font-weight:600;
            line-height:1.5;
        "
    >

        <i class="fa-solid fa-shield-halved"></i>

        Todas as alterações de pontuação são registradas
        para fins de auditoria administrativa.

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const tipo = document.getElementById('tipo');
    const operationBox = document.getElementById('operationBox');
    const operationText = document.getElementById('operationText');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnSubmitText = document.getElementById('btnSubmitText');
    const form = document.getElementById('formPenalizacao');


    /*
    |--------------------------------------------------------------------------
    | ALTERA VISUAL CONFORME TIPO
    |--------------------------------------------------------------------------
    */

    function atualizarOperacao() {

        if (tipo.value === 'adicao') {

            operationBox.classList.remove('operation-penalizacao');
            operationBox.classList.add('operation-adicao');

            operationBox.innerHTML =
                '<i class="fa-solid fa-circle-plus"></i> ' +
                '<span>Os pontos informados serão adicionados ao saldo administrativo do grupo.</span>';

            btnSubmit.classList.remove('penalizacao');
            btnSubmit.classList.add('adicao');

            btnSubmitText.textContent = 'Adicionar Pontos';

        } else {

            operationBox.classList.remove('operation-adicao');
            operationBox.classList.add('operation-penalizacao');

            operationBox.innerHTML =
                '<i class="fa-solid fa-triangle-exclamation"></i> ' +
                '<span>Os pontos informados serão retirados do saldo administrativo do grupo.</span>';

            btnSubmit.classList.remove('adicao');
            btnSubmit.classList.add('penalizacao');

            btnSubmitText.textContent = 'Registrar Penalização';
        }
    }


    tipo.addEventListener('change', atualizarOperacao);

    atualizarOperacao();


    /*
    |--------------------------------------------------------------------------
    | CONFIRMAÇÃO
    |--------------------------------------------------------------------------
    */

    form.addEventListener('submit', function (event) {

        const grupoSelect = document.getElementById('grupo_id');
        const pontosInput = document.getElementById('pontos');

        const grupo =
            grupoSelect.options[grupoSelect.selectedIndex]?.text || '';

        const pontos =
            parseInt(pontosInput.value || '0', 10);

        if (!grupoSelect.value) {

            event.preventDefault();

            alert('Selecione um grupo.');

            grupoSelect.focus();

            return;
        }

        if (!pontos || pontos <= 0) {

            event.preventDefault();

            alert('Informe uma quantidade válida de pontos.');

            pontosInput.focus();

            return;
        }


        if (tipo.value === 'penalizacao') {

            const confirmar = confirm(
                'CONFIRMAR PENALIZAÇÃO\n\n' +
                'Grupo: ' + grupo + '\n' +
                'Pontos: -' + pontos + '\n\n' +
                'Esta operação será registrada no histórico administrativo.\n\n' +
                'Deseja continuar?'
            );

            if (!confirmar) {

                event.preventDefault();

                return;
            }

        } else {

            const confirmar = confirm(
                'CONFIRMAR ADIÇÃO DE PONTOS\n\n' +
                'Grupo: ' + grupo + '\n' +
                'Pontos: +' + pontos + '\n\n' +
                'Esta operação será registrada no histórico administrativo.\n\n' +
                'Deseja continuar?'
            );

            if (!confirmar) {

                event.preventDefault();

                return;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | EVITA DUPLO ENVIO
        |--------------------------------------------------------------------------
        */

        btnSubmit.disabled = true;

        btnSubmit.style.opacity = '0.7';

        btnSubmitText.textContent = 'Registrando...';

    });

});

</script>

</body>
</html>