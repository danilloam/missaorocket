<?php
require_once 'header.php';
// Garante o uso correto do fuso horário brasileiro
date_default_timezone_set('America/Recife');

$user_id = $_SESSION['user_id'] ?? 0;
$grupo_id = $_SESSION['grupo_id'] ?? null;

// Se o usuário não tiver clã associado
if ($grupo_id === null) {
    echo "<div class='main-wrapper text-center py-5'>
            <i class='fa-solid fa-circle-exclamation fa-3x text-warning mb-3'></i>
            <h5 class='fw-bold'>Você não pertence a nenhum GC</h5>
            <p class='text-muted small'>Solicite a um administrador para vincular você a um grupo.</p>
            <a href='dashboard.php' class='btn btn-sm btn-secondary rounded-3 mt-2'>Voltar ao Painel</a>
          </div>";
    exit;
}

// 1. Puxar o nome do grupo atual
// 1. Puxar os dados do GC atual, incluindo a logo armazenada em BLOB
$stmtGrupo = $pdo->prepare("
    SELECT nome, logo, logo_tipo
    FROM grupos
    WHERE id = ?
    LIMIT 1
");
$stmtGrupo->execute([$grupo_id]);

$grupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

$grupoNome = $grupo['nome'] ?? 'Meu GC';
$grupoLogo = $grupo['logo'] ?? null;
$grupoLogoTipo = $grupo['logo_tipo'] ?? 'image/png';

// 2. Query Principal: Ranking exclusivo dos participantes do clã ativo
$sqlRanking = "
    SELECT 
        u.id, 
        u.nome, 
        u.foto, 
        u.nivel,
        COALESCE(SUM(h.pontos), 0) AS total_pontos,
        COALESCE(o.ofensiva_atual, 0) AS streak
    FROM usuarios u
    LEFT JOIN historico_pontos h ON u.id = h.usuario_id AND h.status = 'aprovado' AND h.grupo_id = ?
    LEFT JOIN usuarios_ofensivas o ON u.id = o.usuario_id
    WHERE u.grupo_id = ?
    GROUP BY u.id
    ORDER BY total_pontos DESC, streak DESC, u.nome ASC
";

$stmtRank = $pdo->prepare($sqlRanking);
$stmtRank->execute([$grupo_id, $grupo_id]);
$ranking = $stmtRank->fetchAll(PDO::FETCH_ASSOC);

// Identifica o Top 3 para o pódio visual
$podium = array_slice($ranking, 0, 3);
$restanteLista = array_slice($ranking, 3);
?>

<style>
    body { background-color: #f8fafc !important; color: #1e293b; }
    .main-wrapper { max-width: 480px; margin: 0 auto; padding: 1rem 1rem 8rem 1rem; }
    .screen-header h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; }
    .screen-header p { font-size: 0.85rem; color: #64748b; margin: 0; }
    
    /* Design do Pódio (Top 3) */
    .podium-container { display: flex; justify-content: center; align-items: flex-end; gap: 10px; margin: 2rem 0 1.5rem 0; padding-top: 1rem; }
    .podium-col { display: flex; flex-direction: column; align-items: center; position: relative; }
    .podium-1 { order: 2; width: 34%; }
    .podium-2 { order: 1; width: 30%; }
    .podium-3 { order: 3; width: 30%; }
    
    .podium-avatar { width: 64px; height: 64px; border-radius: 50%; border: 3px solid #fff; object-fit: cover; box-shadow: 0 8px 16px rgba(0,0,0,0.1); background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #475569; font-size: 1.25rem; }
    .podium-1 .podium-avatar { width: 76px; height: 76px; border-color: #fbbf24; box-shadow: 0 10px 20px rgba(251, 191, 36, 0.2); }
    .podium-2 .podium-avatar { border-color: #94a3b8; }
    .podium-3 .podium-avatar { border-color: #b45309; }
    
    .podium-badge { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 800; color: #fff; position: absolute; bottom: 42px; z-index: 2; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
    .podium-1 .podium-badge { background-color: #fbbf24; bottom: 48px; width: 26px; height: 26px; font-size: 0.85rem; }
    .podium-2 .podium-badge { background-color: #94a3b8; }
    .podium-3 .podium-badge { background-color: #b45309; }
    
    .podium-name { font-size: 0.82rem; font-weight: 800; color: #0f172a; text-align: center; margin-top: 8px; width: 100%; white-space: nowrap; overflow: hidden; text-truncate: true; }
    .podium-score { font-size: 0.8rem; font-weight: 700; color: #8b5cf6; background-color: #fff; padding: 2px 8px; border-radius: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); margin-top: 2px; }
    
    /* Elementos da Tabela/Lista */
    .rank-list-card { background-color: #ffffff; border-radius: 18px; border: 1px solid #f1f5f9; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.01); padding: 0.5rem; }
    .rank-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0.5rem; border-radius: 12px; transition: background-color 0.2s; }
    .rank-item.is-me { background-color: #f3e8ff !important; border: 1px dashed #c084fc; }
    .rank-item-left { display: flex; align-items: center; gap: 12px; max-width: 70%; }
    .rank-number { font-size: 0.88rem; font-weight: 800; color: #64748b; width: 24px; text-align: center; font-monospace; }
    .rank-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #475569; font-size: 0.95rem; }
    .rank-info h6 { font-size: 0.9rem; font-weight: 800; color: #0f172a; margin: 0; }
    .rank-info span { font-size: 0.75rem; color: #94a3b8; font-weight: 600; }
    .rank-score-right { text-align: right; }
    .rank-score-pts { font-size: 0.92rem; font-weight: 800; color: #0f172a; }
    .rank-score-streak { font-size: 0.72rem; color: #10b981; font-weight: 700; display: flex; align-items: center; justify-content: flex-end; gap: 2px; }

    /* Barra Fixa Inferior do Próprio Usuário */
    .sticky-my-rank { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%); width: 100%; max-width: 480px; background-color: #7c3aed; color: #ffffff; padding: 0.9rem 1.25rem; box-shadow: 0 -8px 24px rgba(124, 58, 237, 0.25); border-radius: 20px 20px 0 0; display: flex; align-items: center; justify-content: space-between; z-index: 100; }
.grupo-logo-header {
    width: 58px;
    height: 58px;
    min-width: 58px;
    border-radius: 16px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.grupo-logo-header img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 5px;
}

.grupo-logo-empty {
    color: #94a3b8;
    font-size: 1.4rem;
}
</style>

<div class="main-wrapper">
    
    <!-- Cabeçalho -->
<div class="d-flex justify-content-between align-items-center screen-header">
    <div class="d-flex align-items-center gap-3">

        <?php if (!empty($grupoLogo)): ?>
            <div class="grupo-logo-header">
                <img
                    src="data:<?= htmlspecialchars($grupoLogoTipo) ?>;base64,<?= base64_encode($grupoLogo) ?>"
                    alt="Logo do <?= htmlspecialchars($grupoNome) ?>"
                >
            </div>
        <?php else: ?>
            <div class="grupo-logo-header grupo-logo-empty">
                <i class="fa-solid fa-users"></i>
            </div>
        <?php endif; ?>

        <div>
            <p>Classificação Geral</p>
            <h2>Ranking: <?= htmlspecialchars($grupoNome) ?></h2>
        </div>

    </div>

    <a href="dashboard.php"
       class="btn btn-sm btn-light border rounded-3 font-monospace fw-bold px-2.5 py-1.5"
       style="font-size: 0.82rem;">
        <i class="fa-solid fa-arrow-left"></i> Painel
    </a>
</div>

    <!-- PÓDIO (TOP 3) -->
    <?php if (!empty($podium)): ?>
    <div class="podium-container">
        <!-- 1º Lugar -->
        <?php if (isset($podium[0])): 
            $p1_letra = strtoupper(mb_substr($podium[0]['nome'] ?? 'U', 0, 1)); ?>
            <div class="podium-col podium-1">
                <div class="podium-avatar">
                    <?php if (!empty($podium[0]['foto']) && file_exists($podium[0]['foto'])): ?>
                        <img src="<?= htmlspecialchars($podium[0]['foto']) ?>" class="w-100 h-100 rounded-circle" style="object-fit:cover;">
                    <?php else: ?><?= $p1_letra ?><?php endif; ?>
                </div>
                <div class="podium-badge">1</div>
                <div class="podium-name"><?= htmlspecialchars(explode(' ', $podium[0]['nome'])[0]) ?></div>
                <div class="podium-score">🏆 <?= $podium[0]['total_pontos'] ?></div>
            </div>
        <?php endif; ?>

        <!-- 2º Lugar -->
        <?php if (isset($podium[1])): 
            $p2_letra = strtoupper(mb_substr($podium[1]['nome'] ?? 'U', 0, 1)); ?>
            <div class="podium-col podium-2">
                <div class="podium-avatar">
                    <?php if (!empty($podium[1]['foto']) && file_exists($podium[1]['foto'])): ?>
                        <img src="<?= htmlspecialchars($podium[1]['foto']) ?>" class="w-100 h-100 rounded-circle" style="object-fit:cover;">
                    <?php else: ?><?= $p2_letra ?><?php endif; ?>
                </div>
                <div class="podium-badge">2</div>
                <div class="podium-name"><?= htmlspecialchars(explode(' ', $podium[1]['nome'])[0]) ?></div>
                <div class="podium-score"><?= $podium[1]['total_pontos'] ?></div>
            </div>
        <?php endif; ?>

        <!-- 3º Lugar -->
        <?php if (isset($podium[2])): 
            $p3_letra = strtoupper(mb_substr($podium[2]['nome'] ?? 'U', 0, 1)); ?>
            <div class="podium-col podium-3">
                <div class="podium-avatar">
                    <?php if (!empty($podium[2]['foto']) && file_exists($podium[2]['foto'])): ?>
                        <img src="<?= htmlspecialchars($podium[2]['foto']) ?>" class="w-100 h-100 rounded-circle" style="object-fit:cover;">
                    <?php else: ?><?= $p3_letra ?><?php endif; ?>
                </div>
                <div class="podium-badge">3</div>
                <div class="podium-name"><?= htmlspecialchars(explode(' ', $podium[2]['nome'])[0]) ?></div>
                <div class="podium-score"><?= $podium[2]['total_pontos'] ?></div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- LISTA DO RESTANTE DOS PARTICIPANTES -->
    <div class="rank-list-card">
        <?php 
        $posicao = 1;
        $minhaPosicaoFinal = "-";
        $meusPontosFinal = 0;

        foreach ($ranking as $rk):
            // Armazena dados do usuário logado para o painel fixo
            if ($rk['id'] == $user_id) {
                $minhaPosicaoFinal = $posicao;
                $meusPontosFinal = $rk['total_pontos'];
            }

            // A lista visual abaixo do pódio só renderiza da 4ª posição em diante
            if ($posicao > 3):
                $letra_lista = strtoupper(mb_substr($rk['nome'] ?? 'U', 0, 1));
                $isMe = ($rk['id'] == $user_id) ? 'is-me' : '';
        ?>
                <div class="rank-item <?= $isMe ?>">
                    <div class="rank-item-left">
                        <div class="rank-number"><?= $posicao ?></div>
                        <div class="rank-avatar">
                            <?php if (!empty($rk['foto']) && file_exists($rk['foto'])): ?>
                                <img src="<?= htmlspecialchars($rk['foto']) ?>" class="w-100 h-100 rounded-circle" style="object-fit:cover;">
                            <?php else: ?><?= $letra_lista ?><?php endif; ?>
                        </div>
                        <div class="rank-info text-truncate">
                            <h6><?= htmlspecialchars($rk['nome']) ?></h6>
                            <span>Nível <?= htmlspecialchars($rk['nivel']) ?></span>
                        </div>
                    </div>
                    <div class="rank-score-right">
                        <div class="rank-score-pts"><?= $rk['total_pontos'] ?> <span class="text-xxs text-muted fw-normal">pts</span></div>
                        <?php if($rk['streak'] > 0): ?>
                            <div class="rank-score-streak"><i class="fa-solid fa-fire"></i> <?= $rk['streak'] ?>d</div>
                        <?php endif; ?>
                    </div>
                </div>
        <?php 
            endif;
            $posicao++;
        endforeach; 
        ?>
    </div>
</div>

<!-- BARRA FIXA INFERIOR COM A MINHA POSIÇÃO ATUAL -->
<div class="sticky-my-rank">
    <div class="d-flex align-items-center gap-3">
        <div class="fw-extrabold font-monospace text-center bg-white text-purple rounded-3 px-2 py-0.5 small" style="min-width:34px; color:#7c3aed;">
            #<?= $minhaPosicaoFinal ?>
        </div>
        <div>
            <div class="small opacity-75 fw-bold" style="font-size:0.75rem;">Sua Classificação</div>
            <div class="fw-extrabold small text-truncate max-w-[180px]"><?= htmlspecialchars($_SESSION['nome'] ?? 'Você') ?></div>
        </div>
    </div>
    <div class="text-end">
        <div class="fw-extrabold fs-6"><?= $meusPontosFinal ?> <span class="small opacity-75 fw-normal" style="font-size:0.75rem;">PTS</span></div>
    </div>
</div>

</body>
</html>