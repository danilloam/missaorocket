<?php 
    require_once 'header.php'; 
    $user_id = (int)($_SESSION['user_id'] ?? 0); 
    if ($user_id <= 0) {
    header('Location: index.php');
    exit; } 
    /* |-------------------------------------------------------------------------- | MEDALHAS DO USUÁRIO |-------------------------------------------------------------------------- */
    $stmt = $pdo->prepare(" SELECT id, usuario_id, titulo, icone, conquistada_em FROM medalhas WHERE usuario_id = ? ORDER BY conquistada_em DESC, id DESC "); 
    $stmt->execute([$user_id]); 
    $minhasMedalhas = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    /* |-------------------------------------------------------------------------- | TOTAL DE MEDALHAS CONQUISTADAS |-------------------------------------------------------------------------- */ 
    $totalConquistadas = count($minhasMedalhas);
    /* |-------------------------------------------------------------------------- | TODAS AS MEDALHAS DE OFENSIVA CONFIGURADAS |-------------------------------------------------------------------------- */ 
    $stmtConfig = $pdo->query(" SELECT id, dias_requeridos, pontos_bonus, titulo_medalha, icone_medalha FROM config_ofensivas WHERE dias_requeridos > 0 ORDER BY dias_requeridos ASC ");
    $configOfensivas = $stmtConfig->fetchAll(PDO::FETCH_ASSOC);
    /* |-------------------------------------------------------------------------- | MEDALHAS JÁ CONQUISTADAS |-------------------------------------------------------------------------- */
    $medalhasConquistadas = []; 
    foreach ($minhasMedalhas as $medalha) { 
    $titulo = trim((string)($medalha['titulo'] ?? ''));
    if ($titulo !== '') { 
    $medalhasConquistadas[$titulo] = true; 
    } 
    }
/* |-------------------------------------------------------------------------- | PRÓXIMA MEDALHA DE OFENSIVA |-------------------------------------------------------------------------- |
| Procura a primeira medalha configurada que o usuário ainda não possui. |-------------------------------------------------------------------------- */ 
$proximaMedalha = null; 
foreach ($configOfensivas as $config) {
$titulo = trim((string)($config['titulo_medalha'] ?? ''));
if ($titulo === '') { continue; } 
if (!isset($medalhasConquistadas[$titulo])) {
$proximaMedalha = $config; 
break; 
} 
} 
/* |-------------------------------------------------------------------------- | OFENSIVA ATUAL DO USUÁRIO |-------------------------------------------------------------------------- */
$stmtOfensiva = $pdo->prepare(" SELECT ofensiva_atual, ofensiva_maxima, ultima_ofensiva_em FROM usuarios_ofensivas WHERE usuario_id = ? LIMIT 1 ");
$stmtOfensiva->execute([$user_id]); 
$ofensivaUsuario = $stmtOfensiva->fetch(PDO::FETCH_ASSOC);
$ofensivaAtual = (int)($ofensivaUsuario['ofensiva_atual'] ?? 0);
$ofensivaMaxima = (int)($ofensivaUsuario['ofensiva_maxima'] ?? 0); 
/* |-------------------------------------------------------------------------- | PROGRESSO PARA A PRÓXIMA MEDALHA |-------------------------------------------------------------------------- */ 
$provasRestantes = 0;
$percentualProgresso = 0; 
if ($proximaMedalha) { $diasNecessarios = (int)$proximaMedalha['dias_requeridos']; 
if ($diasNecessarios > 0) { $diasFaltantes = max( 0, $diasNecessarios - $ofensivaAtual ); 
$provasRestantes = $diasFaltantes;
$percentualProgresso = min( 100, (int)round( ($ofensivaAtual / $diasNecessarios) * 100 ) ); } } 
/* |-------------------------------------------------------------------------- | TÍTULO DA PRÓXIMA MEDALHA |-------------------------------------------------------------------------- */
$proximaMedalhaTitulo = $proximaMedalha['titulo_medalha'] ?? 'Todas as medalhas conquistadas'; 
/* |-------------------------------------------------------------------------- | ÍCONE DA PRÓXIMA MEDALHA |-------------------------------------------------------------------------- */
$proximaMedalhaIcone = $proximaMedalha['icone_medalha'] ?? 'fa-solid fa-trophy'; 
/* |-------------------------------------------------------------------------- | META TOTAL |-------------------------------------------------------------------------- | 
| Agora a meta representa a quantidade real de medalhas configuradas. |-------------------------------------------------------------------------- */
$metaMedalhas = count($configOfensivas);
/* |-------------------------------------------------------------------------- | EVITA DIVISÃO POR ZERO |-------------------------------------------------------------------------- */ 
$percentualMedalhas = $metaMedalhas > 0 ? min(100, (int)round(($totalConquistadas / $metaMedalhas) * 100)) : 0;
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
    .screen-header h2 {
        font-size: 1.7rem;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
    }
    .screen-header p {
        font-size: 0.85rem;
        color: #64748b;
        margin: 0;
    }

    /* Pílula de Placar Superior Right (ex: 24/50) */
    .medal-score-pill {
        background-color: #f3e8ff;
        color: #a855f7;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 6px 14px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        gap: 6px;
        border: 1px solid rgba(168, 85, 247, 0.15);
    }

    /* Filtros Horizontais Deslizantes */
    .filter-scroll {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 4px;
        scrollbar-width: none;
    }
    .filter-scroll::-webkit-scrollbar {
        display: none;
    }
    .filter-btn {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 6px 16px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
        color: #475569;
        white-space: nowrap;
        transition: all 0.2s;
    }
    .filter-btn.active {
        background-color: #ffffff;
        border-color: #8b5cf6;
        color: #8b5cf6;
        box-shadow: 0 2px 6px rgba(139, 92, 246, 0.08);
    }

    /* Grade de Medalhas Conquistadas */
    .medal-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 1.5rem;
    }
    .medal-box-card {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        aspect-ratio: 1 / 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.01);
        text-align: center;
        transition: all 0.2s ease;
    }
    .medal-icon-container {
        width: 54px;
        height: 54px;
        border-radius: 50%;
        background-color: #f1f5f9;
        color: #1e293b;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);
        margin-bottom: 0.5rem;
    }
    /* Estilização para quando for uma medalha ativa e customizada */
    .medal-box-card.has-medal .medal-icon-container {
        background-color: #faf5ff;
        color: #a855f7;
        border: 1px solid rgba(168, 85, 247, 0.1);
    }
    .medal-text-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #334155;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Bloco Inferior: Próxima Medalha */
    .next-medal-card {
        background-color: #ffffff;
        border-radius: 20px;
        padding: 1.25rem;
        border: 1px solid #f1f5f9;
        box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        text-align: center;
    }
    .next-medal-card h6 {
        font-size: 0.85rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 1rem;
    }
    .next-medal-row {
        display: flex;
        align-items: center;
        gap: 12px;
        text-align: left;
        margin-bottom: 0.5rem;
    }
    .next-medal-badge-placeholder {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background-color: #f3e8ff;
        color: #a855f7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
    .next-medal-info-block {
        flex-grow: 1;
    }
    .next-medal-name {
        font-size: 0.92rem;
        font-weight: 800;
        color: #0f172a;
        margin-bottom: 0.5rem;
    }
    .progress-micro-bar {
        height: 6px;
        background-color: #e2e8f0;
        border-radius: 10px;
        width: 100%;
        overflow: hidden;
    }
    .progress-micro-fill {
        height: 100%;
        background-color: #8b5cf6;
        width: 75%; /* Simulação de preenchimento progressivo */
        border-radius: 10px;
    }
    .next-medal-hint {
        font-size: 0.72rem;
        color: #94a3b8;
        font-weight: 600;
        text-align: center;
        margin-top: 0.5rem;
    }

    /* Estado Vazio */
    .empty-state-box {
        grid-column: span 3;
        text-align: center;
        padding: 3rem 1rem;
        color: #94a3b8;
    }
</style>

<div class="main-wrapper">

    <div class="d-flex justify-content-between align-items-center screen-header mb-4">
        <div>
            <h2>Medalhas</h2>
            <p>Suas conquistas na Arena</p>
        </div>
        <div class="medal-score-pill">
            <i class="fa-solid fa-medal"></i> <?= $totalConquistadas ?>/<?= $metaMedalhas ?>
        </div>
    </div>

    <div class="filter-scroll mb-4">
        <button class="filter-btn active" data-filter="all"><i class="fa-solid fa-check me-1"></i> Todas</button>
        <button class="filter-btn" data-filter="provas">Provas</button>
        <button class="filter-btn" data-filter="grupo">Grupo</button>
        <button class="filter-btn" data-filter="raros">Raros</button>
    </div>

    <div class="medal-grid" id="medalGridContainer">
        <?php if (empty($minhasMedalhas)): ?>
            <div class="medal-box-card">
                <div class="medal-icon-container">
                    <i class="fa-solid fa-question"></i>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($minhasMedalhas as $medalha): 
                // Define uma categoria simulada com base no nome para compatibilidade com o filtro visual do mockup
                $categoria = "provas";
                if (str_contains(strtolower($medalha['titulo']), 'grupo') || str_contains(strtolower($medalha['titulo']), 'clã')) {
                    $categoria = "grupo";
                } elseif (str_contains(strtolower($medalha['titulo']), 'pioneiro') || str_contains(strtolower($medalha['titulo']), 'lendário')) {
                    $categoria = "raros";
                }
            ?>
                <div class="medal-box-card has-medal" data-type="<?= $categoria ?>">
                    <div class="medal-icon-container">
                        <i class="<?= htmlspecialchars($medalha['icone'] ?: 'fa-solid fa-award') ?>"></i>
                    </div>
                    <div class="medal-text-title" title="<?= htmlspecialchars($medalha['titulo']) ?>">
                        <?= htmlspecialchars($medalha['titulo']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <div class="medal-box-card" data-type="all">
            <div class="medal-icon-container">
                <i class="fa-solid fa-question"></i>
            </div>
        </div>
    </div>

    <div class="next-medal-card">
        <h6>Próxima Medalha</h6>
        
        <div class="next-medal-row">
            <div class="next-medal-badge-placeholder">
                <i class="fa-regular fa-star"></i>
            </div>
            <div class="next-medal-info-block">
                <div class="next-medal-name"><?= $proximaMedalhaTitulo ?></div>
                <div class="progress-micro-bar">
                    <div class="progress-micro-fill"></div>
                </div>
            </div>
        </div>
        
        <div class="next-medal-hint">
            Faltam <?= $provasRestantes ?> dias para desbloquear
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const filterButtons = document.querySelectorAll('.filter-btn');
        const medalCards = document.querySelectorAll('.medal-box-card.has-medal');

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                // Alterna classe ativa dos botões
                filterButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');

                const selectedFilter = button.getAttribute('data-filter');

                medalCards.forEach(card => {
                    const cardType = card.getAttribute('data-type');
                    
                    if (selectedFilter === 'all' || cardType === selectedFilter) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
    });
</script>

</body>
</html>