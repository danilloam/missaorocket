<?php

require_once '../config.php';
exigirAdministrador($pdo);
require_once 'disparar_notificacao.php';
require_once 'header.php';

// Garante que o PHP use o horário correto do Brasil para calcular o Hoje e Ontem
date_default_timezone_set('America/Recife');

if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
    echo "<div class='alert alert-danger py-2 small text-center'>Acesso restrito para administradores.</div>";
    exit;
}

$feedback = '';

// ==========================================
// FUNÇÕES AUXILIARES PARA O BÔNUS DE OFENSIVA
// ==========================================



function processarOfensivaUsuario(PDO $pdo, int $usuarioId): void
{
    if ($usuarioId <= 0) {
        return;
    }

    $hoje  = date('Y-m-d');
    $ontem = date('Y-m-d', strtotime('-1 day'));

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
    | PRIMEIRA OFENSIVA
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

        // Primeiro dia conta normalmente.
        checarEPremiarOfensiva(
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
    | JÁ PROCESSOU HOJE
    |--------------------------------------------------------------------------
    */

    if ($ultimaData === $hoje) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | CONTINUIDADE
    |--------------------------------------------------------------------------
    */

    if ($ultimaData === $ontem) {

        // Continua a sequência.
        $ofensivaAtual++;

    } else {

        // Perdeu a sequência.
        $ofensivaAtual = 1;
    }

    /*
    |--------------------------------------------------------------------------
    | RECORDE
    |--------------------------------------------------------------------------
    */

    if ($ofensivaAtual > $ofensivaMaxima) {
        $ofensivaMaxima = $ofensivaAtual;
    }

    /*
    |--------------------------------------------------------------------------
    | ATUALIZA OFENSIVA
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | VERIFICA CONQUISTAS
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    | A conquista NÃO altera ofensiva_atual.
    |
    | Se chegou a 7:
    |
    | 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8...
    |
    */

    checarEPremiarOfensiva(
        $pdo,
        $usuarioId,
        $ofensivaAtual
    );
}


function checarEPremiarOfensiva(
    PDO $pdo,
    int $usuarioId,
    int $diasAtuais
): void {

    if ($usuarioId <= 0 || $diasAtuais <= 0) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | PROCURA UMA CONQUISTA PARA ESTE MARCO
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            id,
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

    if (!$user) {
        return;
    }

    $grupoId = !empty($user['grupo_id'])
        ? (int)$user['grupo_id']
        : null;

    /*
    |--------------------------------------------------------------------------
    | VERIFICA SE ESTA MEDALHA JÁ FOI CONCEDIDA
    |--------------------------------------------------------------------------
    |
    | Isso evita duplicar a mesma medalha caso a função seja chamada
    | novamente.
    |
    */

    if (!empty($regra['titulo_medalha'])) {

        $stmtExiste = $pdo->prepare("
            SELECT id
            FROM medalhas
            WHERE usuario_id = ?
              AND titulo = ?
            LIMIT 1
        ");

        $stmtExiste->execute([
            $usuarioId,
            $regra['titulo_medalha']
        ]);

        $medalhaExiste = $stmtExiste->fetch(PDO::FETCH_ASSOC);

    } else {

        $medalhaExiste = false;
    }

    /*
    |--------------------------------------------------------------------------
    | TRANSAÇÃO DA PREMIAÇÃO
    |--------------------------------------------------------------------------
    */

    try {

        $pdo->beginTransaction();

        /*
        |--------------------------------------------------------------------------
        | PONTOS BÔNUS
        |--------------------------------------------------------------------------
        */

        if ((int)$regra['pontos_bonus'] > 0) {

            $stmtPontos = $pdo->prepare("
                INSERT INTO historico_pontos
                (
                    usuario_id,
                    grupo_id,
                    prova_id,
                    pontos,
                    evidencia,
                    status
                )
                VALUES
                (?, ?, 2, ?, ?, 'aprovado')
            ");

            $stmtPontos->execute([
                $usuarioId,
                $grupoId,
                (int)$regra['pontos_bonus'],
                'Bônus de Ofensiva: ' . $regra['titulo_medalha']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | MEDALHA
        |--------------------------------------------------------------------------
        */

        if (
            !empty($regra['titulo_medalha']) &&
            !$medalhaExiste
        ) {

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
                $regra['titulo_medalha'],
                $regra['icone_medalha']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | FINALIZA
        |--------------------------------------------------------------------------
        */

        $pdo->commit();

        /*
        |--------------------------------------------------------------------------
        | ATUALIZA NÍVEL
        |--------------------------------------------------------------------------
        |
        | Fazemos DEPOIS do commit.
        |
        */

        if ((int)$regra['pontos_bonus'] > 0) {
            atualizarNivelUsuario(
                $pdo,
                $usuarioId
            );
        }

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'ERRO AO PROCESSAR OFENSIVA DO USUÁRIO ' .
            $usuarioId .
            ': ' .
            $e->getMessage()
        );
    }
}

// ==========================================
// PROCESSAR AÇÕES POST
// ==========================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['cadastrar_grupo'])) {

        $stmt = $pdo->prepare("INSERT INTO grupos (nome) VALUES (?)");
        $stmt->execute([$_POST['nome_grupo']]);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Clã/Grupo registado!</div>";
    }

    if (isset($_POST['cadastrar_usuario'])) {

        $nome = trim($_POST['nome']);
        $email = strtolower(trim($_POST['email']));
        $senha_pura = trim($_POST['senha']);
        $senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);
        $perfil = $_POST['perfil'];
        $grupo = empty($_POST['grupo_id']) ? null : $_POST['grupo_id'];

        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, grupo_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $senha_hash, $perfil, $grupo]);

        $para = $email;
        $assunto = "=?UTF-8?B?" . base64_encode("Foguete Lançado! Seu acesso à Missão Rocket") . "?=";

        $mensagemHTML = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Acesso à Arena</title>
        </head>
        <body style='font-family: sans-serif; background-color: #f7f9fc; color: #1e293b; padding: 20px; margin: 0;'>
            <div style='max-width: 420px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <span style='background: linear-gradient(135deg, #febb12 0%, #a066ff 100%); color: white; padding: 10px 20px; font-weight: 800; border-radius: 12px; display: inline-block; font-size: 1.2rem;'>
                        🚀 MISSÃO ROCKET
                    </span>
                </div>

                <h2 style='color: #0f172a; margin-bottom: 5px; font-weight: 800;'>Olá, Comandante!</h2>

                <p style='color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-top: 0;'>
                    Seu cadastro foi realizado com sucesso pelo administrador. Prepare-se para entrar na Arena de Performance!
                </p>

                <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 0.85rem; color: #64748b;'>
                        <strong>Seus Dados de Acesso:</strong>
                    </p>

                    <p style='margin: 0 0 6px 0; font-size: 0.9rem;'>
                        <strong>E-mail:</strong> <span style='color: #8b5cf6;'>{$email}</span>
                    </p>

                    <p style='margin: 0; font-size: 0.9rem;'>
                        <strong>Senha Provisória:</strong>
                        <span style='font-family: monospace; font-weight: bold; background: #fff; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 4px;'>{$senha_pura}</span>
                    </p>
                </div>

                <p style='color: #ef4444; font-size: 0.8rem; font-weight: bold; text-align: center;'>
                    ⚠️ Nota: Por segurança, altere sua senha no primeiro login.
                </p>

                <div style='text-align: center; margin-top: 25px;'>
                    <a href='https://missaorocket.com.br/mobile/' style='background-color: #8b5cf6; color: white; text-decoration: none; padding: 12px 30px; font-weight: bold; border-radius: 12px; display: inline-block; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);'>
                        Entrar na Arena
                    </a>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: Missão Rocket <no-reply@missaorocket.com.br>" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        @mail($para, $assunto, $mensagemHTML, $headers);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Usuário criado e e-mail de instruções enviado!</div>";
    }

    if (isset($_POST['cadastrar_prova'])) {

        $grupo_id = ($_POST['tipo'] == 'global') ? null : $_POST['grupo_id'];

        $stmt = $pdo->prepare("INSERT INTO provas (titulo, descricao, pontos, tipo, grupo_id, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['titulo_prova'],
            $_POST['descricao'],
            $_POST['pontos'],
            $_POST['tipo'],
            $grupo_id,
            $_POST['data_inicio'],
            $_POST['data_fim']
        ]);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Missão adicionada à Arena!</div>";
    }

    if (isset($_POST['acao_gestao'])) {

        $hist_id = (int)$_POST['hist_id'];
        $status_definido = $_POST['status_definido'] ?? '';
        $motivo_rejeicao = isset($_POST['motivo_rejeicao']) ? trim($_POST['motivo_rejeicao']) : null;

        // Query original de negócio: traz o nome do usuário e o título da prova via JOIN.
        $stH = $pdo->prepare("
            SELECT h.usuario_id, h.pontos, u.nome as usuario_nome, p.titulo as prova_titulo
            FROM historico_pontos h
            JOIN usuarios u ON h.usuario_id = u.id
            JOIN provas p ON h.prova_id = p.id
            WHERE h.id = ?
        ");
        $stH->execute([$hist_id]);
        $hist = $stH->fetch();

        if ($hist) {

            if ($status_definido === 'aprovado') {

                $stmt = $pdo->prepare("UPDATE historico_pontos SET status = 'aprovado', criado_em = NOW(), motivo_rejeicao = NULL WHERE id = ?");
                $stmt->execute([$hist_id]);

                processarOfensivaUsuario($pdo, $hist['usuario_id']);

                $feedback = "<div class='alert alert-success py-2 small text-center rounded-3'>Evidência de <strong>" . htmlspecialchars($hist['usuario_nome'] ?? '') . "</strong> aprovada com sucesso!</div>";

            } else {

                $stmt = $pdo->prepare("UPDATE historico_pontos SET status = 'rejeitado', criado_em = NOW(), motivo_rejeicao = ? WHERE id = ?");
                $stmt->execute([$motivo_rejeicao, $hist_id]);

                $feedback = "<div class='alert alert-warning py-2 small text-center rounded-3'>Evidência de <strong>" . htmlspecialchars($hist['usuario_nome'] ?? '') . "</strong> rejeitada.</div>";
            }

            // Configuração dos parâmetros dinâmicos e idênticos aos aplicados em provas.php.
            $statusPush = ($status_definido === 'aprovado') ? 'aprovada' : 'reprovada';

            if ($status_definido === 'aprovado') {

                $dadosPush = [
                    'title' => '✅ Missão Aprovada!',
                    'body'  => "Parabéns! Sua evidência para a missão \"" . ($hist['prova_titulo'] ?? '') . "\" foi aprovada e seus pontos computados.",
                    'url'   => 'provas.php'
                ];

            } else {

                $motivoTexto = !empty($motivo_rejeicao) ? " Motivo: " . $motivo_rejeicao : "";

                $dadosPush = [
                    'title' => '❌ Evidência Recusada',
                    'body'  => "Sua evidência para a missão \"" . ($hist['prova_titulo'] ?? '') . "\" precisa de ajustes." . $motivoTexto,
                    'url'   => 'provas.php'
                ];
            }

            // Executa o disparo dinâmico usando a estrutura unificada.
            dispararNotificacaoEvidencia($pdo, $hist['usuario_id'], $statusPush, $dadosPush);

            $feedback .= "<div class='alert alert-info py-2 small text-center'>Avaliação submetida e notificação enviada!</div>";
        }
    }
}

// ==========================================
// QUERIES DE LEITURA
// ==========================================

$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nome ASC")->fetchAll();

$analises = $pdo->query("
    SELECT
        h.id,
        h.usuario_id,
        h.prova_id,
        h.pontos,
        h.status,
        h.criado_em,
        u.nome AS user_nome,
        p.titulo AS prova_titulo
    FROM historico_pontos h
    INNER JOIN usuarios u ON u.id = h.usuario_id
    INNER JOIN provas p ON p.id = h.prova_id
    WHERE h.status = 'pendente'
    ORDER BY h.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Query de Cruzamento do Status de Notificações Ativas/Inativas
$statusNotificacoes = $pdo->query("
    SELECT
        u.id,
        u.nome,
        u.email,
        g.nome as clan_nome,
        IF(un.endpoint IS NOT NULL, 1, 0) as ativado
    FROM usuarios u
    LEFT JOIN usuarios_notificacoes un ON u.id = un.usuario_id
    LEFT JOIN grupos g ON u.grupo_id = g.id
    ORDER BY u.nome ASC
")->fetchAll();

?>

<style>
    .admin-page {
        max-width: 1100px;
        margin: 0 auto;
    }

    .admin-hero {
        background: linear-gradient(135deg, #fff7d6 0%, #ffffff 48%, #f3e8ff 100%);
        border: 1px solid #e5e7eb;
        border-radius: 18px;
        padding: 16px;
    }

    .admin-title {
        letter-spacing: -0.02em;
    }

    .admin-stat {
        border-radius: 14px;
        border: 1px solid #e5e7eb;
        background: #fff;
        padding: 12px 14px;
    }

    .mission-card {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: #fff;
        transition: transform .15s ease, box-shadow .15s ease;
    }

    .mission-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 .35rem 1rem rgba(15, 23, 42, .08);
    }

    .mission-name {
        min-width: 0;
    }

    .mission-name .title,
    .mission-name .user {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .admin-tabs {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 5px;
    }

    .admin-tabs .nav-link {
        border-radius: 10px;
        font-weight: 700;
        color: #64748b;
    }

    .admin-tabs .nav-link.active {
        color: #111827;
        background: #f8fafc;
        box-shadow: 0 1px 4px rgba(15, 23, 42, .08);
    }

    .action-button {
        min-width: 42px;
    }

    .notification-status {
        white-space: nowrap;
    }

    .form-section {
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        background: #fff;
    }

    @media (max-width: 575.98px) {
        .admin-hero {
            padding: 13px;
            border-radius: 14px;
        }

        .admin-tabs .nav-link {
            font-size: .76rem;
            padding: .55rem .25rem;
        }

        .mission-card {
            border-radius: 13px;
        }
    }
</style>

<div class="container-fluid py-2 py-md-3">
    <div class="admin-page">

        <!-- Banner PWA -->
        <div id="pwa-notification-banner" class="alert alert-warning border border-warning-subtle rounded-3 p-3 mb-3 d-none shadow-sm">
            <div class="d-flex align-items-start gap-2">
                <i class="fa-solid fa-bell-ring fa-xl text-warning mt-1"></i>
                <div class="w-100">
                    <h6 class="fw-bold text-dark mb-1" id="pwa-banner-title">Alertas do Painel Admin</h6>
                    <p class="text-secondary small mb-2" id="pwa-banner-desc">
                        Ative as notificações para receber atualizações e avisos de novas evidências na Arena em tempo real.
                    </p>
                    <button type="button" id="btn-pwa-action" class="btn btn-sm btn-dark fw-bold px-3 py-1 rounded-2">
                        Ativar Alertas
                    </button>
                </div>
            </div>
        </div>

        <!-- Cabeçalho -->
        <div class="admin-hero mb-3 shadow-sm">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-3 bg-dark text-warning d-flex align-items-center justify-content-center"
                             style="width:42px;height:42px;flex:0 0 42px;">
                            <i class="fa-solid fa-sliders"></i>
                        </div>

                        <div class="min-w-0">
                            <h4 class="fw-bold my-0 text-dark admin-title">
                                Painel de Operações Admin
                            </h4>
                            <div class="small text-secondary">
                                Controle de evidências, missões, participantes, clãs e notificações.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-auto">
                    <a href="gerar_qrcode.php"
                       class="btn btn-dark w-100 py-2 rounded-3 fw-bold font-monospace shadow-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>
                        Gerar Checkin
                    </a>
                </div>
                <div class="col-12 col-lg-auto">
                    <a href="gerenciar_provas.php"
                       class="btn btn-dark w-100 py-2 rounded-3 fw-bold font-monospace shadow-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>
                       Gerenciar Missões
                    </a>
                </div>
                <div class="col-12 col-lg-auto">
                    <a href="gerenciar_participantes.php"
                       class="btn btn-dark w-100 py-2 rounded-3 fw-bold font-monospace shadow-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>
                        Gerenciar Participantes
                    </a>
                </div>
                <div class="col-12 col-lg-auto">
                    <a href="gerenciar_grupos.php"
                       class="btn btn-dark w-100 py-2 rounded-3 fw-bold font-monospace shadow-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>
                        Gerenciar GCs
                    </a>
                </div>
                <div class="col-12 col-lg-auto">
                    <a href="penalizacoes.php"
                       class="btn btn-dark w-100 py-2 rounded-3 fw-bold font-monospace shadow-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>
                        Penalizações
                    </a>
                </div>
                <div class="col-12 col-lg-auto">
                    <a href="webpush.php"
                       class="btn btn-dark w-100 py-2 rounded-3 fw-bold font-monospace shadow-sm">
                        <i class="fa-solid fa-qrcode me-1"></i>
                        Web Push
                    </a>
                </div>
            </div>
        </div>

        <?= $feedback ?>

        <!-- Indicador visual -->
        <div class="row g-2 mb-3">
            <div class="col-6 col-md-3">
                <div class="admin-stat shadow-sm h-100">
                    <div class="small text-muted">Pendentes</div>
                    <div class="fs-4 fw-bold text-warning"><?= count($analises) ?></div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="admin-stat shadow-sm h-100">
                    <div class="small text-muted">Clãs</div>
                    <div class="fs-4 fw-bold text-dark"><?= count($grupos) ?></div>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <div class="admin-stat shadow-sm h-100 d-flex align-items-center justify-content-between">
                    <div>
                        <div class="small text-muted">Área administrativa</div>
                        <div class="fw-bold text-dark">Missão Rocket</div>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                        <i class="fa-solid fa-shield-halved me-1"></i> Admin
                    </span>
                </div>
            </div>
        </div>


        <div class="tab-content" id="adminTabsContent">

            <!-- =====================================================
                 ABA: ANÁLISE
            ====================================================== -->
            <div class="tab-pane fade show active" id="analise" role="tabpanel">

                <div class="card p-3 border-0 shadow-sm rounded-4">

                    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h6 class="fw-bold mb-1 text-secondary">
                                <i class="fa-solid fa-hourglass-start me-1"></i>
                                Evidências Aguardando Verificação
                            </h6>
                            <div class="small text-muted">
                                Revise a evidência antes de aprovar ou recusar.
                            </div>
                        </div>

                        <?php if (!empty($analises)): ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-3 py-2">
                                <?= count($analises) ?> pendente(s)
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($analises)): ?>

                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fa-solid fa-circle-check fa-3x text-success"></i>
                            </div>
                            <div class="fw-bold text-dark">Tudo em dia!</div>
                            <p class="text-muted text-center py-2 my-0 small">
                                Nenhuma evidência pendente neste momento.
                            </p>
                        </div>

                    <?php else: ?>

                        <div class="row g-2">

                            <?php foreach ($analises as $an): ?>

                                <div class="col-12">

                                    <div class="mission-card p-3">

                                        <div class="d-flex align-items-start gap-3">

                                            <div class="rounded-3 bg-light border d-flex align-items-center justify-content-center text-secondary"
                                                 style="width:42px;height:42px;flex:0 0 42px;">
                                                <i class="fa-solid fa-file-circle-check"></i>
                                            </div>

                                            <div class="mission-name flex-grow-1">

                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <strong class="text-dark user">
                                                        <?= htmlspecialchars($an['user_nome']) ?>
                                                    </strong>

                                                    <span class="badge bg-dark rounded-pill flex-shrink-0">
                                                        +<?= $an['pontos'] ?> pts
                                                    </span>
                                                </div>

                                                <div class="title text-muted small" title="<?= htmlspecialchars($an['prova_titulo']) ?>">
                                                    <i class="fa-solid fa-trophy me-1 text-warning"></i>
                                                    <?= htmlspecialchars($an['prova_titulo']) ?>
                                                </div>

                                            </div>

                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill d-none d-md-inline-block">
                                                Pendente
                                            </span>

                                        </div>

                                        <!-- Motivo -->
                                        <div id="boxMotivo<?= $an['id'] ?>" class="mt-3 d-none">
                                            <label class="form-label text-danger fw-bold text-xxs tracking-wider uppercase mb-1">
                                                Motivo da Recusa:
                                            </label>

                                            <textarea id="inputMotivo<?= $an['id'] ?>"
                                                      class="form-control form-control-sm border-danger"
                                                      rows="2"
                                                      placeholder="Ex: Arquivo ilegível ou incorreto..."></textarea>
                                        </div>

                                        <!-- Ações -->
                                        <div class="d-flex gap-1 justify-content-end align-items-center mt-3 pt-2 border-top">

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary px-3 py-2 me-auto fw-bold rounded-3"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#modalEvidencia<?= $an['id'] ?>">
                                                <i class="fa-solid fa-eye me-1"></i>
                                                Ver evidência
                                            </button>

                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="hist_id" value="<?= $an['id'] ?>">
                                                <input type="hidden" name="status_definido" value="aprovado">

                                                <button type="submit"
                                                        name="acao_gestao"
                                                        class="btn btn-sm btn-success px-3 py-2 rounded-3"
                                                        title="Aprovar evidência">
                                                    <i class="fa-solid fa-check me-1"></i>
                                                    <span class="d-none d-sm-inline">Aprovar</span>
                                                </button>
                                            </form>

                                            <button type="button"
                                                    id="btnRejeitarView<?= $an['id'] ?>"
                                                    onclick="exibirCampoRejeicao(<?= $an['id'] ?>)"
                                                    class="btn btn-sm btn-danger px-3 py-2 rounded-3"
                                                    title="Rejeitar evidência">
                                                <i class="fa-solid fa-xmark me-1"></i>
                                                <span class="d-none d-sm-inline">Recusar</span>
                                            </button>

                                        </div>

                                    </div>

                                </div>

                                <!-- Modal -->
                                <div class="modal fade modal-evidencia"
                                     id="modalEvidencia<?= (int)$an['id'] ?>"
                                     tabindex="-1"
                                     aria-hidden="true"
                                     data-evidencia-id="<?= (int)$an['id'] ?>">

                                    <div class="modal-dialog modal-dialog-centered modal-lg">

                                        <div class="modal-content border-0 shadow"
                                             style="border-radius:20px;overflow:hidden;">

                                            <div class="modal-header border-0 pb-0">

                                                <div class="min-w-0">
                                                    <h6 class="modal-title fw-bold text-dark mb-1">
                                                        🚀 Evidência de
                                                        <?= htmlspecialchars($an['user_nome']) ?>
                                                    </h6>

                                                    <small class="text-muted d-block text-truncate">
                                                        <?= htmlspecialchars($an['prova_titulo']) ?>
                                                    </small>
                                                </div>

                                                <button type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                        aria-label="Fechar"></button>

                                            </div>

                                            <div class="modal-body">

                                                <div id="loadingEvidencia<?= (int)$an['id'] ?>"
                                                     class="text-center py-5">

                                                    <div class="spinner-border text-warning" role="status"></div>

                                                    <div class="small text-muted mt-2">
                                                        Carregando evidência...
                                                    </div>

                                                </div>

                                                <div id="conteudoEvidencia<?= (int)$an['id'] ?>"
                                                     class="d-none"></div>

                                            </div>

                                            <div class="modal-footer border-0">

                                                <a href="visualizar_evidencia.php?id=<?= (int)$an['id'] ?>&download=1"
                                                   class="btn btn-sm btn-outline-dark rounded-3">
                                                    <i class="fa-solid fa-download me-1"></i>
                                                    Baixar Original
                                                </a>

                                                <button type="button"
                                                        class="btn btn-sm btn-secondary rounded-3"
                                                        data-bs-dismiss="modal">
                                                    Fechar
                                                </button>

                                            </div>

                                        </div>

                                    </div>
                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

            <!-- =====================================================
                 ABA: REGISTOS
            ====================================================== -->
 

            <!-- =====================================================
                 ABA: NOTIFICAÇÕES
            ====================================================== -->
            <div class="tab-pane fade" id="notificacoes-painel" role="tabpanel">

                <div class="card p-3 border-0 shadow-sm rounded-4">

                    <div class="d-flex align-items-center gap-2 mb-3">

                        <div class="rounded-3 bg-warning-subtle text-warning d-flex align-items-center justify-content-center"
                             style="width:38px;height:38px;">
                            <i class="fa-solid fa-bell"></i>
                        </div>

                        <div>
                            <h6 class="fw-bold mb-0 text-dark">
                                Status de Ativação do Web Push
                            </h6>
                            <div class="small text-muted">
                                Consulte quais participantes estão com alertas ativos.
                            </div>
                        </div>

                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle small mb-0">

                            <thead class="table-light font-monospace">
                                <tr>
                                    <th>Participante</th>
                                    <th>Clã</th>
                                    <th class="text-center">Push</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php if (empty($statusNotificacoes)): ?>

                                    <tr>
                                        <td colspan="3"
                                            class="text-muted text-center py-4">
                                            Nenhum participante registrado.
                                        </td>
                                    </tr>

                                <?php else: ?>

                                    <?php foreach ($statusNotificacoes as $usr): ?>

                                        <tr>

                                            <td>
                                                <div class="fw-bold text-dark">
                                                    <?= htmlspecialchars($usr['nome']) ?>
                                                </div>

                                                <span class="text-muted"
                                                      style="font-size: 0.75rem;">
                                                    <?= htmlspecialchars($usr['email']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <span class="badge bg-light text-secondary border">
                                                    <?= $usr['clan_nome']
                                                        ? htmlspecialchars($usr['clan_nome'])
                                                        : 'Sem Clã' ?>
                                                </span>
                                            </td>

                                            <td class="text-center notification-status">

                                                <?php if ($usr['ativado'] == 1): ?>

                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill fw-bold">
                                                        <i class="fa-solid fa-circle-check me-1"></i>
                                                        Ativo
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill fw-bold">
                                                        <i class="fa-solid fa-circle-xmark me-1"></i>
                                                        Inativo
                                                    </span>

                                                <?php endif; ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                <?php endif; ?>

                            </tbody>

                        </table>
                    </div>

                </div>

            </div>

        </div>

    </div>
</div>

<script>
function baixarBlobVirtual(base64Str, mimeType, nomeArquivo) {

    const byteCharacters = atob(base64Str);
    const byteNumbers = new Array(byteCharacters.length);

    for (let i = 0; i < byteCharacters.length; i++) {
        byteNumbers[i] = byteCharacters.charCodeAt(i);
    }

    const byteArray = new Uint8Array(byteNumbers);
    const blob = new Blob([byteArray], { type: mimeType });

    const link = document.createElement('a');

    link.href = window.URL.createObjectURL(blob);
    link.download = nomeArquivo;

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    window.URL.revokeObjectURL(link.href);
}

function exibirCampoRejeicao(id) {

    const box = document.getElementById('boxMotivo' + id);
    const input = document.getElementById('inputMotivo' + id);
    const btn = document.getElementById('btnRejeitarView' + id);

    if (box.classList.contains('d-none')) {

        box.classList.remove('d-none');
        input.focus();

        btn.innerHTML = '<i class="fa-solid fa-xmark me-1"></i> Rejeitar';
        btn.classList.remove('btn-danger');
        btn.classList.add('btn-danger');

    } else {

        const motivoTexto = input.value.trim();

        if (motivoTexto === '') {
            alert('É obrigatório descrever o motivo da recusa.');
            input.focus();
            return;
        }

        if (confirm('Deseja realmente recusar essa evidência?')) {

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const campos = {
                hist_id: id,
                status_definido: 'rejeitado',
                motivo_rejeicao: motivoTexto,
                acao_gestao: '1'
            };

            Object.keys(campos).forEach(function (nome) {
                const inputHidden = document.createElement('input');
                inputHidden.type = 'hidden';
                inputHidden.name = nome;
                inputHidden.value = campos[nome];
                form.appendChild(inputHidden);
            });

            document.body.appendChild(form);
            form.submit();
        }
    }
}

// ====================================================================
// SCRIPT DE VALIDAÇÃO E ATIVAÇÃO DO WEB PUSH
// ====================================================================

function urlBase64ToUint8Array(base64String) {

    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

document.addEventListener("DOMContentLoaded", function() {

    const banner = document.getElementById('pwa-notification-banner');
    const btnAction = document.getElementById('btn-pwa-action');
    const bannerTitle = document.getElementById('pwa-banner-title');
    const bannerDesc = document.getElementById('pwa-banner-desc');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    navigator.serviceWorker.register('sw.js').then(async (reg) => {

        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }

        async function registrarAparelhoNoPush() {

            try {

                const subscription = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(
                        'BBbFobVqYPKTUYZXKXtbyqIuzHtCOGUBL1wKIXVWffY0hsBQWfrOZc0vo0M4rz6gxGuw_8_89XvxFvMSpOShQlA'
                    )
                });

                const key = subscription.getKey('p256dh');
                const auth = subscription.getKey('auth');

                const dadosSub = {
                    action: 'salvar',
                    endpoint: subscription.endpoint,
                    p256dh: key
                        ? btoa(String.fromCharCode.apply(null, new Uint8Array(key)))
                        : null,
                    auth: auth
                        ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth)))
                        : null
                };

                const response = await fetch('salvar_assinatura.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dadosSub)
                });

                if (!response.ok) {
                    throw new Error(`Erro HTTP: ${response.status}`);
                }

                console.log('Inscrição do Admin salva com sucesso!');

            } catch (err) {

                console.error('Erro ao inscrever Admin no push:', err);

            }
        }

        // Recupera o token local atual gerado pelo navegador
        let subscricaoLocal = await reg.pushManager.getSubscription();

        if (Notification.permission === 'granted' && subscricaoLocal) {

            try {

                // Parâmetro 'action' enviado dentro do JSON do POST para validação limpa no backend
                const checarBanco = await fetch('salvar_assinatura.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'verificar',
                        endpoint: subscricaoLocal.endpoint
                    })
                });

                const resultado = await checarBanco.json();

                // Se o banco não possuir este token, removemos o token local.
                if (resultado.status === 'nao_encontrado' || resultado.encontrado === false) {

                    console.warn('Token fantasma detectado no Admin. Limpando...');

                    await subscricaoLocal.unsubscribe().catch(() => {});
                    subscricaoLocal = null;
                }

            } catch (e) {

                console.error('Falha ao validar token com o servidor. Forçando re-checagem.');

                await subscricaoLocal.unsubscribe().catch(() => {});
                subscricaoLocal = null;
            }
        }

        // === Decisão de Exibição Baseada no Banco de Dados Atualizado ===
        if (Notification.permission === 'granted' && subscricaoLocal) {

            if (banner) {
                banner.classList.add('d-none');
            }

            return;
        }

        if (Notification.permission === 'granted' && !subscricaoLocal) {

            // Permissão dada mas token limpo/inválido no banco: gera um novo e vincula.
            await registrarAparelhoNoPush();

            return;
        }

        if (Notification.permission === 'default' || !subscricaoLocal) {

            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone =
                window.matchMedia('(display-mode: standalone)').matches ||
                window.navigator.standalone;

            if (isIOS && !isStandalone) {

                bannerTitle.innerText = "Instale o App Admin";
                bannerDesc.innerHTML =
                    "Para receber alertas de novas evidências no iPhone, adicione este painel à sua <strong>Tela de Início</strong> via botão de Compartilhar.";

                btnAction.innerText = "Entendi";

                banner.classList.remove('d-none');

                btnAction.addEventListener('click', () => {
                    banner.classList.add('d-none');
                });

            } else {

                banner.classList.remove('d-none');

                btnAction.addEventListener('click', () => {

                    Notification.requestPermission().then((permission) => {

                        if (permission === 'granted') {
                            registrarAparelhoNoPush();
                            banner.classList.add('d-none');
                        } else {
                            banner.classList.add('d-none');
                        }

                    });

                });

            }
        }

    });

    let abrindoPelaPrimeiraVez = true;

    navigator.serviceWorker.addEventListener('controllerchange', () => {

        if (!abrindoPelaPrimeiraVez) {
            return;
        }

        window.location.reload();
        abrindoPelaPrimeiraVez = false;
    });

});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.querySelectorAll('.modal-evidencia').forEach(modal => {

    modal.addEventListener('show.bs.modal', async function () {

        const id = this.dataset.evidenciaId;

        const loading = document.getElementById(
            'loadingEvidencia' + id
        );

        const conteudo = document.getElementById(
            'conteudoEvidencia' + id
        );

        // Já carregou antes
        if (this.dataset.carregado === '1') {
            return;
        }

        loading.classList.remove('d-none');
        conteudo.classList.add('d-none');

        try {

            const response = await fetch(
                'info_evidencia.php?id=' +
                encodeURIComponent(id),
                {
                    credentials: 'same-origin'
                }
            );

            if (!response.ok) {
                throw new Error(
                    'Erro HTTP ' + response.status
                );
            }

            const dados = await response.json();

            if (dados.imagem) {

                conteudo.innerHTML = `
                    <div class="text-center">
                        <img
                            src="visualizar_evidencia.php?id=${id}"
                            class="img-fluid rounded-3 border"
                            style="
                                max-height:70vh;
                                object-fit:contain;
                            "
                            alt="Evidência"
                        >
                    </div>
                `;

            } else if (dados.pdf) {

                conteudo.innerHTML = `
                    <div
                        class="ratio"
                        style="--bs-aspect-ratio: 120%;"
                    >
                        <iframe
                            src="visualizar_evidencia.php?id=${id}"
                            class="border rounded-3"
                            title="Evidência PDF"
                        ></iframe>
                    </div>
                `;

            } else {

                conteudo.innerHTML = `
                    <div class="text-center py-5">

                        <i
                            class="fa-solid fa-file
                            fa-3x text-secondary mb-3"
                        ></i>

                        <div class="fw-bold">
                            Arquivo anexado
                        </div>

                        <div class="small text-muted mt-2">
                            Utilize o botão "Baixar Original"
                            para visualizar este arquivo.
                        </div>

                    </div>
                `;
            }

            this.dataset.carregado = '1';

            loading.classList.add('d-none');
            conteudo.classList.remove('d-none');

        } catch (erro) {

            console.error(erro);

            loading.classList.add('d-none');

            conteudo.innerHTML = `
                <div class="alert alert-danger small">
                    Não foi possível carregar a evidência.
                </div>
            `;

            conteudo.classList.remove('d-none');
        }

    });

});
</script>

</body>
</html>