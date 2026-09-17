<?php
require_once 'header.php';
// Garante o uso correto do fuso horário brasileiro
date_default_timezone_set('America/Recife');

$user_id = $_SESSION['user_id'] ?? 0;
$meu_grupo_id = $_SESSION['grupo_id'] ?? null;

// Query Principal: Soma a pontuação de todos os usuários de cada clã
// Ignora registros onde o grupo_id seja nulo (usuários sem clã)
$sqlRankingGrupos = "
    SELECT 
        g.id AS grupo_id,
        g.nome AS grupo_nome,
        COALESCE(SUM(h.pontos), 0) AS total_pontos,
        COUNT(DISTINCT u.id) AS total_membros
    FROM grupos g
    LEFT JOIN usuarios u ON u.grupo_id = g.id
    LEFT JOIN historico_pontos h ON u.id = h.usuario_id AND h.status = 'aprovado' AND h.grupo_id = g.id
    GROUP BY g.id
    ORDER BY total_pontos DESC, grupo_nome ASC
";

$stmt = $pdo->query($sqlRankingGrupos);
$rankingGrupos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separa o Top 3 para o pódio visual de Clãs
$podium = array_slice($rankingGrupos, 0, 3);
$restanteLista = array_slice($rankingGrupos, 3);
?>

<style>
    body { background-color: #f8fafc !important; color: #1e293b; }
    .main-wrapper { max-width: 480px; margin: 0 auto; padding: 1rem 1rem 5rem 1rem; }
    .screen-header h2 { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0; }
    .screen-header p { font-size: 0.85rem; color: #64748b; margin: 0; }
    
    /* Pódio de Clãs */
    .podium-container { display: flex; justify-content: center; align-items: flex-end; gap: 12px; margin: 2rem 0 1.5rem 0; }
    .podium-col { display: flex; flex-direction: column; align-items: center; position: relative; }
    .podium-1 { order: 2; width: 34%; }
    .podium-2 { order: 1; width: 30%; }
    .podium-3 { order: 3; width: 30%; }
    
    /* Escudo/Ícone do Clã no Pódio */
    .clan-shield { width: 60px; height: 60px; border-radius: 16px; border: 3px solid #fff; box-shadow: 0 8px 16px rgba(0,0,0,0.1); background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 100%); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #fff; }
    .podium-1 .clan-shield { width: 72px; height: 72px; border-color: #fbbf24; background: linear-gradient(135deg, #fef08a 0%, #eab308 100%); color: #fff; box-shadow: 0 10px 20px rgba(251, 191, 36, 0.25); }
    .podium-2 .clan-shield { border-color: #94a3b8; background: linear-gradient(135deg, #f1f5f9 0%, #475569 100%); }
    .podium-3 .clan-shield { border-color: #b45309; background: linear-gradient(135deg, #ffedd5 0%, #c2410c 100%); }
    
    .podium-badge { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 800; color: #fff; position: absolute; bottom: 42px; z-index: 2; box-shadow: 0 2px 4px rgba(0,0,0,0.15); }
    .podium-1 .podium-badge { background-color: #fbbf24; bottom: 48px; width: 26px; height: 26px; font-size: 0.85rem; }
    .podium-2 .podium-badge { background-color: #94a3b8; }
    .podium-3 .podium-badge { background-color: #b45309; }
    
    .podium-name { font-size: 0.82rem; font-weight: 800; color: #0f172a; text-align: center; margin-top: 8px; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .podium-score { font-size: 0.8rem; font-weight: 700; color: #8b5cf6; background-color: #fff; padding: 2px 8px; border-radius: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.03); margin-top: 2px; }
    
    /* Lista de Clãs */
    .clan-list-card { background-color: #ffffff; border-radius: 18px; border: 1px solid #f1f5f9; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.01); padding: 0.5rem; }
    .clan-item { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0.5rem; border-radius: 12px; }
    .clan-item.is-my-clan { background-color: #f3e8ff !important; border: 1px dashed #c084fc; }
    .clan-item-left { display: flex; align-items: center; gap: 12px; max-width: 70%; }
    .clan-number { font-size: 0.88rem; font-weight: 800; color: #64748b; width: 24px; text-align: center; }
    .clan-mini-shield { width: 38px; height: 38px; border-radius: 10px; background-color: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 700; color: #475569; font-size: 1rem; }
    .clan-item.is-my-clan .clan-mini-shield { background-color: #c084fc; color: #fff; }
    .clan-info h6 { font-size: 0.9rem; font-weight: 800; color: #0f172a; margin: 0; }
    .clan-info span { font-size: 0.75rem; color: #94a3b8; font-weight: 600; }
    .clan-score-pts { font-size: 0.95rem; font-weight: 800; color: #8b5cf6; text-align: right; }
</style>

<div class="main-wrapper">
    
    <div class="d-flex justify-content-between align-items-center screen-header mb-2">
        <div>
            <p>Batalha de Clãs</p>
            <h2>Ranking dos Grupos</h2>
        </div>
        <a href="dashboard.php" class="btn btn-sm btn-light border rounded-3 font-monospace fw-bold px-2.5 py-1.5" style="font-size: 0.82rem;">
            <i class="fa-solid fa-arrow-left"></i> Painel
        </a>
    </div>

    <?php if (!empty($podium)): ?>
    <div class="podium-container">
        <?php if (isset($podium[0])): ?>
            <div class="podium-col podium-1">
                <div class="clan-shield">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="podium-badge">1</div>
                <div class="podium-name"><?= htmlspecialchars($podium[0]['grupo_nome']) ?></div>
                <div class="podium-score">🔥 <?= $podium[0]['total_pontos'] ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($podium[1])): ?>
            <div class="podium-col podium-2">
                <div class="clan-shield">
                    <i class="fa-solid fa-shield"></i>
                </div>
                <div class="podium-badge">2</div>
                <div class="podium-name"><?= htmlspecialchars($podium[1]['grupo_nome']) ?></div>
                <div class="podium-score"><?= $podium[1]['total_pontos'] ?></div>
            </div>
        <?php endif; ?>

        <?php if (isset($podium[2])): ?>
            <div class="podium-col podium-3">
                <div class="clan-shield">
                    <i class="fa-solid fa-shield"></i>
                </div>
                <div class="podium-badge">3</div>
                <div class="podium-name"><?= htmlspecialchars($podium[2]['grupo_nome']) ?></div>
                <div class="podium-score"><?= $podium[2]['total_pontos'] ?></div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="clan-list-card">
        <?php 
        $posicao = 1;
        foreach ($rankingGrupos as $grupo):
            $isMyClan = ($grupo['grupo_id'] == $meu_grupo_id) ? 'is-my-clan' : '';
            $primeiraLetra = strtoupper(mb_substr($grupo['grupo_nome'] ?? 'C', 0, 1));
            
            // Renderiza todas as linhas na tabela para clareza visual
        ?>
            <div class="clan-item <?= $isMyClan ?> mb-1">
                <div class="clan-item-left">
                    <div class="clan-number font-monospace"><?= $posicao ?></div>
                    <div class="clan-mini-shield">
                        <?= $primeiraLetra ?>
                    </div>
                    <div class="clan-info text-truncate">
                        <h6>
                            <?= htmlspecialchars($grupo['grupo_nome']) ?>
                            <?php if($grupo['grupo_id'] == $meu_grupo_id): ?>
                                <span class="badge bg-purple ms-1 text-white text-xxs" style="background-color: #8b5cf6;">Seu GC</span>
                            <?php endif; ?>
                        </h6>
                        <span><i class="fa-solid fa-users text-muted me-1"></i> <?= $grupo['total_membros'] ?> membros</span>
                    </div>
                </div>
                <div class="clan-score-pts">
                    <?= $grupo['total_pontos'] ?> <span class="text-xxs text-muted fw-normal">pts</span>
                </div>
            </div>
        <?php 
            $posicao++;
        endforeach; 
        
        if (empty($rankingGrupos)) {
            echo "<p class='text-muted text-center py-4 my-0 small'>Nenhum clã computado até o momento.</p>";
        }
        ?>
    </div>
</div>

</body>
</html>