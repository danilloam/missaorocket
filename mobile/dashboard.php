<?php 
require_once 'header.php'; 
// Garante constância na comparação de datas e extratos diários

// Definição de datas padrão para busca de histórico (Mês Atual)
$de = date('Y-m-01') . ' 00:00:00';
$ate = date('Y-m-t') . ' 23:59:59';

$user_id = $_SESSION['user_id'] ?? 0;

// 1. Resgatar os pontos, dados atualizados, FOTO (BLOB), GRUPO_ID E OFENSIVA do usuário logado
$stmtUser = $pdo->prepare("
    SELECT u.nome, u.nivel, u.grupo_id, g.nome as grupo_nome,
           COALESCE((SELECT SUM(pontos) FROM historico_pontos WHERE usuario_id = u.id AND status = 'aprovado'), 0) as meus_pontos,
           COALESCE((SELECT COUNT(id) FROM medalhas WHERE usuario_id = u.id), 0) as minhas_medalhas,
           COALESCE(o.ofensiva_atual, 0) as minha_ofensiva
    FROM usuarios u
    LEFT JOIN grupos g ON u.grupo_id = g.id
    LEFT JOIN usuarios_ofensivas o ON u.id = o.usuario_id
    WHERE u.id = ?
");
$stmtUser->execute([$user_id]);
$meuPerfil = $stmtUser->fetch();

if ($meuPerfil) {
    // ⚡ VIA SESSION: Sincroniza e atualiza os dados da sessão com o banco de dados
    $_SESSION['nome']     = $meuPerfil['nome'];
    $_SESSION['nivel']    = $meuPerfil['nivel'];
    $_SESSION['grupo_id'] = $meuPerfil['grupo_id'];
    
   
} else {
    // Fallback caso o usuário não seja encontrado por algum motivo
    $meuPerfil = [
        'nome' => $_SESSION['nome'] ?? 'Comandante',
        'nivel' => $_SESSION['nivel'] ?? 'Bronze',
        'foto' => null,
        'grupo_id' => $_SESSION['grupo_id'] ?? null,
        'grupo_nome' => 'Sem Grupo',
        'meus_pontos' => 0,
        'minhas_medalhas' => 0,
        'minha_ofensiva' => 0
    ];
}

// Cálculo dinâmico para a barra de progresso (Exemplo: Meta de 1000 pontos por Nível)
$meta_pontos_nivel = 1000;
$porcentagem_xp = ($meuPerfil['meus_pontos'] / $meta_pontos_nivel) * 100;
if ($porcentagem_xp > 100) $porcentagem_xp = 100; // Trava em 100% caso ultrapasse

// Captura o grupo_id real retornado pelo perfil para usar no ranking
$meu_grupo_id = $meuPerfil['grupo_id'];

// 2. Calcular a posição atual do usuário no ranking EXCLUSIVO DO GRUPO
$minhaPosicao = "-"; // Fallback caso o usuário não tenha clã associado

if (!empty($meu_grupo_id)) {
    $stmtAllRank = $pdo->prepare("
        SELECT u.id, COALESCE(SUM(h.pontos), 0) as total_skill  
        FROM usuarios u
        LEFT JOIN historico_pontos h ON u.id = h.usuario_id AND h.status = 'aprovado' AND h.grupo_id = ?
        WHERE u.grupo_id = ?
        GROUP BY u.id 
        ORDER BY total_skill DESC
    ");
    $stmtAllRank->execute([$meu_grupo_id, $meu_grupo_id]);
    $allRankings = $stmtAllRank->fetchAll();

    foreach ($allRankings as $index => $rankItem) {
        if ($rankItem['id'] == $user_id) {
            $minhaPosicao = $index + 1;
            break;
        }
    }
}

// 3. Resgatar as últimas pontuações reais que EU consegui (Provas Aprovadas)
$stmtMinhasPontuacoes = $pdo->prepare("
    SELECT h.pontos as pontos_ganhos, h.criado_em, p.titulo  
    FROM historico_pontos h
    JOIN provas p ON h.prova_id = p.id
    WHERE h.usuario_id = ? AND h.status = 'aprovado'
    ORDER BY h.id DESC  
    LIMIT 3
");
$stmtMinhasPontuacoes->execute([$user_id]);
$ultimasPontuacoes = $stmtMinhasPontuacoes->fetchAll();

// Lógica de fallback para a inicial se não houver foto cadastrada
$primeira_letra = strtoupper(mb_substr($meuPerfil['nome'] ?? 'U', 0, 1));
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

    /* Cabeçalho de Boas-vindas */
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
    .user-avatar-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background-color: #e0e7ff;
        border: 2px solid #a855f7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6366f1;
        font-weight: 700;
        font-size: 1.1rem;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(139, 92, 246, 0.15);
    }
    .user-avatar-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Card de Nível e XP */
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
    .xp-bar-container {
        height: 8px;
        background-color: #e2e8f0;
        border-radius: 10px;
        width: 100%;
        overflow: hidden;
        margin-bottom: 0.75rem;
    }
    .xp-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #a855f7, #6366f1);
        border-radius: 10px;
        transition: width 0.5s ease-in-out;
    }
    .level-footer-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.78rem;
        font-weight: 700;
    }

    /* Grelha de Métricas */
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

    /* Links de Ação - Modificado para ocupar 100% da largura */
    .action-links-row {
        display: block;
        width: 100%;
        margin-bottom: 1.75rem;
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

    /* Seção de Lista de Últimas Provas Concluídas */
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
    .section-title-container a {
        font-size: 0.82rem;
        font-weight: 700;
        color: #8b5cf6;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Cartão Minimalista de Prova na Home */
    .home-task-item {
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
    .task-left-block {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .task-icon-circle {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background-color: #dcfce7;
        color: #10b981;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .task-info-meta h6 {
        font-size: 0.92rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0 0 0.15rem 0;
    }
    .task-info-meta span {
        font-size: 0.78rem;
        color: #94a3b8;
        font-weight: 600;
    }
    .task-score-right {
        font-size: 0.95rem;
        font-weight: 800;
        color: #10b981;
        white-space: nowrap;
    }
    .checkin-action-container {
        margin-top: 1.75rem;
    }
    .btn-main-checkin {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        background: linear-gradient(135deg, #8b5cf6, #6366f1);
        color: #ffffff;
        text-decoration: none;
        font-weight: 800;
        font-size: 1rem;
        padding: 0.9rem;
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(139, 92, 246, 0.25);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .btn-main-checkin:active {
        transform: scale(0.98);
        box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);
        color: #ffffff;
    }
</style>

<div class="main-wrapper">

    <div class="welcome-section">
        <div class="text-box">
            <p>Olá, </p>
            <h2><?= htmlspecialchars(explode(' ', $meuPerfil['nome'])[0]) ?></h2>
        </div>
        <a href="editar_perfil.php" class="text-decoration-none">
        <div class="user-avatar-circle"> <img src="foto_perfil.php" alt="Foto de Perfil" onerror="this.style.display='none'; this.parentElement.innerHTML='<?= htmlspecialchars($primeira_letra, ENT_QUOTES, 'UTF-8') ?>';" > </div>
        </a>
    </div>

    <div class="level-progression-card">
        <div class="level-header">
            <div class="level-icon-wrapper">
                <i class="fa-solid fa-medal"></i>
            </div>
            <div class="level-title-text">
                Nível <?= htmlspecialchars($meuPerfil['nivel']) ?>
            </div>
        </div>
        <div class="xp-bar-container">
            <div class="xp-bar-fill" style="width: <?= $porcentagem_xp ?>%;"></div>
        </div>
        <div class="level-footer-meta">
            <span class="text-muted">Grupo: <?= htmlspecialchars($meuPerfil['grupo_nome']) ?></span>
            <span class="text-success">Ranking Clã</span>
        </div>
        
    </div>
 <div class="action-links-row">
        <a href="ranking_geral.php" class="action-pill-btn">
            <i class="fa-solid fa-chart-simple text-muted"></i> Ranking dos GCs
        </a>
    </div>
    <div class="metrics-grid">

        <div class="metric-square-card">
            <div class="metric-label" style="color: #f59e0b;">
                <i class="fa-solid fa-award"></i> Pontos
            </div>
            <div class="metric-value"><?= $meuPerfil['meus_pontos'] ?></div>
        </div>
        
        <a href="ranking.php" class="text-decoration-none d-block">
            <div class="metric-square-card">
                <div class="metric-label" style="color: #3b82f6;">
                    <i class="fa-solid fa-chart-simple"></i> Ranking
                </div>
                <div class="metric-value">#<?= $minhaPosicao ?></div>
            </div>
        </a>
        
        <a href="medalhas.php" class="text-decoration-none d-block">
            <div class="metric-square-card h-100">
                <div class="metric-label" style="color: #ec4899;">
                    <i class="fa-solid fa-circle-nodes"></i> Medalhas
                </div>
                <div class="metric-value"><?= $meuPerfil['minhas_medalhas'] ?></div>
            </div>
        </a>

        <div class="metric-square-card">
            <div class="metric-label" style="color: #10b981;">
                <i class="fa-solid fa-fire"></i> Streak
            </div>
            <div class="metric-value"><?= (int)$meuPerfil['minha_ofensiva'] ?> dias</div>
        </div>
    </div>

    <div class="action-links-row">
        <a href="provas.php" class="action-pill-btn">
            <i class="fa-solid fa-clipboard-list text-muted"></i> Fazer prova
        </a>
    </div>

    <div class="section-title-container">
        <h5>Minhas Conquistas</h5>
        <a href="provas.php">
            <i class="fa-solid fa-chart-simple"></i> Ver Histórico
        </a>
    </div>

    <div class="provas-home-list">
        <?php if(empty($ultimasPontuacoes)): ?>
            <div class="text-center py-4 text-muted small">
                <i class="fa-solid fa-circle-exclamation d-block fs-3 mb-2"></i>
                Você ainda não tem pontuações aprovadas neste mês.
            </div>
        <?php else: ?>
            <?php foreach($ultimasPontuacoes as $pt): 
                $dataConquista = date('d/m/Y H:i', strtotime($pt['criado_em']));
            ?>
                <div class="home-task-item">
                    <div class="task-left-block">
                        <div class="task-icon-circle">
                            <i class="fa-solid fa-check"></i>
                        </div>
                        <div class="task-info-meta">
                            <h6><?= htmlspecialchars($pt['titulo']) ?></h6>
                            <span><i class="fa-regular fa-clock"></i> Concluído em: <?= $dataConquista ?></span>
                        </div>
                    </div>
                    <div class="task-score-right">
                        +<?= $pt['pontos_ganhos'] ?> PTS
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="checkin-action-container">
        <a href="checkin.php" class="btn-main-checkin">
            <i class="fa-solid fa-qrcode fs-5"></i> Fazer Check-in
        </a>
    </div>
</div>
<?php
// Trecho PHP para verificar a flag e depois desativá-la (para não incomodar a cada F5)
$exibirValidacaoLogin = false;
if (isset($_SESSION['acabou_de_logar']) && $_SESSION['acabou_de_logar'] === true) {
    $exibirValidacaoLogin = true;
    unset($_SESSION['acabou_de_logar']); // Consome a flag para os próximos carregamentos
}
?>
<div id="pwa-notification-banner" class="pwa-overlay d-none">
    <div class="pwa-banner">
        <div class="pwa-banner-content">
            <div class="pwa-icon-area">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="pwa-text-area">
                <h6 class="mb-2" id="pwa-banner-title">Notificações Inativas</h6>
                <p class="mb-0" id="pwa-banner-desc">Detectamos que suas notificações estão desativadas. Ative para receber avisos imediatos sobre suas missões e notas!</p>
            </div>
            <button id="btn-pwa-action" class="btn">Ativar Notificações</button>
        </div>
    </div>
</div>

<script>
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
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

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    navigator.serviceWorker.register('sw.js').then(async (reg) => {
        
        // ====================================================================
        // GESTÃO DE ATUALIZAÇÃO DE CACHE AGRESSIVA DO SW
        // ====================================================================
        if (reg.waiting) {
            reg.waiting.postMessage({ type: 'SKIP_WAITING' });
        }

        reg.addEventListener('updatefound', () => {
            const newWorker = reg.installing;
            if (newWorker) {
                newWorker.addEventListener('statechange', () => {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        console.log('Nova versão encontrada! Atualizando cache...');
                        newWorker.postMessage({ type: 'SKIP_WAITING' });
                    }
                });
            }
        });

        // Função para gerar e salvar uma nova assinatura Push no banco de dados
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
                    endpoint: subscription.endpoint,
                    p256dh: key ? btoa(String.fromCharCode.apply(null, new Uint8Array(key))) : null,
                    auth: auth ? btoa(String.fromCharCode.apply(null, new Uint8Array(auth))) : null
                };

                const response = await fetch('salvar_assinatura.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(dadosSub)
                });

                if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);
                console.log('Aparelho registrado e salvo no banco de dados.');

            } catch (err) {
                console.error('Erro ao registrar o aparelho no Push:', err);
            }
        }

        // ====================================================================
        // VALIDAÇÃO CRUZADA: CONSULTA SE O APARELHO CONSTA NO BANCO DE DADOS
        // ====================================================================
        let subscricaoLocal = await reg.pushManager.getSubscription();

        if (Notification.permission === 'granted' && subscricaoLocal) {
            try {
                // Envia o endpoint atual para o arquivo PHP verificar se esse registro ainda existe
                const checarBanco = await fetch('salvar_assinatura.php?action=verificar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ endpoint: subscricaoLocal.endpoint })
                });

                const resultado = await checarBanco.json();

                // Se o ID do aparelho sumiu ou foi apagado da tabela
                if (resultado.status === 'nao_encontrado' || resultado.encontrado === false) {
                    console.warn('Aparelho ativo localmente, mas não encontrado no Banco. Limpando cache de push antigo...');
                    
                    // Força o cancelamento da subscrição velha no aparelho para quebrar o bloqueio visual
                    await subscricaoLocal.unsubscribe();
                    subscricaoLocal = null; // Zera a variável para acionar a abertura do modal
                }
            } catch (e) {
                console.error('Erro ao validar ID no banco:', e);
            }
        }

        // ====================================================================
        // FLUXO DE COMPORTAMENTO DO MODAL BASEADO NO BANCO E APARELHO
        // ====================================================================
        
        // CASO A: Aparelho com permissão local E validado no Banco de Dados
        if (Notification.permission === 'granted' && subscricaoLocal) {
            console.log('Aparelho totalmente validado no sistema e banco.');
            if (banner) banner.classList.add('d-none');
            return;
        }

        // CASO B: Aparelho tem permissão dada pelo usuário, mas limpamos o cache de push acima porque sumiu do banco
        if (Notification.permission === 'granted' && !subscricaoLocal) {
            console.log('Recriando token de push para vincular novamente o ID deste aparelho...');
            await registrarAparelhoNoPush();
            return;
        }

        // CASO C: O Aparelho nunca ativou (Status inicial 'default')
        if (Notification.permission === 'default') {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

            if (isIOS && !isStandalone) {
                bannerTitle.innerText = "Instale o App para Notificações";
                bannerDesc.innerHTML = "Para ativar as notificações no iPhone, toque no ícone de <strong>Compartilhar</strong> <i class='fa-solid fa-square-share-nodes'></i> no Safari e selecione <strong>'Adicionar à Tela de Início'</strong>.";
                btnAction.innerText = "Entendi";
                banner.classList.remove('d-none');
                btnAction.addEventListener('click', () => banner.classList.add('d-none'));
            } else {
                bannerTitle.innerText = "Ação Necessária: Ativar Notificações";
                bannerDesc.innerText = "Detectamos que este dispositivo não está mapeado para alertas automáticos da Arena. Ative agora para não perder nenhuma missão!";
                banner.classList.remove('d-none');

                btnAction.addEventListener('click', () => {
                    Notification.requestPermission().then((permission) => {
                        if (permission === 'granted') {
                            registrarAparelhoNoPush();
                            banner.classList.add('d-none');
                        } else if (permission === 'denied') {
                            alert('As notificações foram negadas. Mude a permissão manualmente nas configurações do seu celular.');
                            banner.classList.add('d-none');
                        }
                    });
                });
            }
        }

        // CASO D: O Aparelho bloqueou explicitamente nas diretivas do navegador ('denied')
        if (Notification.permission === 'denied') {
            bannerTitle.innerText = "Notificações Bloqueadas neste Aparelho";
            bannerDesc.innerText = "Este celular barrou os alertas da Arena Rocket. Toque nas configurações do seu navegador (ou no ícone de cadeado na URL) e marque 'Permitir'.";
            btnAction.innerText = "Como Liberar?";
            banner.classList.remove('d-none');

            btnAction.addEventListener('click', () => {
                alert('Passo a passo:\n1. Clique no ícone de configurações/cadeado ao lado da URL.\n2. Localize a opção "Notificações".\n3. Modifique de "Bloquear" para "Permitir" e recarregue a página.');
            });
        }
    });
    
    // RECARREGA A PÁGINA AUTOMATICAMENTE SE O SERVICE WORKER ADQUIRIR CONTROLE
    let abrindoPelaPrimeiraVez = true;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!abrindoPelaPrimeiraVez) return;
        window.location.reload();
        abrindoPelaPrimeiraVez = false;
    });
});
</script>
</body>
</html>