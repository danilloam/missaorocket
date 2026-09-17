<?php 
require_once 'header.php'; 

// Filtros de Período do Ranking
$de = $_GET['de'] ?? date('Y-m-01');
$ate = $_GET['ate'] ?? date('Y-m-t');

// 1. Ranking Individual
$queryInd = "SELECT u.nome, g.nome as grupo, COALESCE(SUM(h.pontos), 0) as total_skill, u.nivel 
             FROM usuarios u
             LEFT JOIN grupos g ON u.grupo_id = g.id
             LEFT JOIN historico_pontos h ON u.id = h.usuario_id AND h.status = 'aprovado'
             WHERE h.criado_em BETWEEN ? AND ? OR h.id IS NULL
             GROUP BY u.id ORDER BY total_skill DESC";
$stmtInd = $pdo->prepare($queryInd);
$stmtInd->execute([$de . ' 00:00:00', $ate . ' 23:59:59']);
$rankingIndividual = $stmtInd->fetchAll();

// 2. Ranking de Grupos
$queryGrp = "SELECT g.nome, COALESCE(SUM(h.pontos), 0) as total_skill 
             FROM grupos g
             LEFT JOIN historico_pontos h ON g.id = h.grupo_id AND h.status = 'aprovado'
             WHERE h.criado_em BETWEEN ? AND ? OR h.id IS NULL
             GROUP BY g.id ORDER BY total_skill DESC";
$stmtGrp = $pdo->prepare($queryGrp);
$stmtGrp->execute([$de . ' 00:00:00', $ate . ' 23:59:59']);
$rankingGrupos = $stmtGrp->fetchAll();

// 3. Minhas Medalhas
$stmtMedalhas = $pdo->prepare("SELECT * FROM medalhas WHERE usuario_id = ?");
$stmtMedalhas->execute([$_SESSION['user_id'] ?? 0]);
$minhasMedalhas = $stmtMedalhas->fetchAll();

// Separando o Top 3 para o Pódio Visual
$top3 = array_slice($rankingIndividual, 0, 3);
$restanteRanking = array_slice($rankingIndividual, 3);
?>

<!-- Estilos customizados para a estética Gamer/Teen -->
<style>
    body {
        background-color: #0b0e14 !important;
        color: #f1f3f5 !important;
        font-family: 'Poppins', sans-serif;
    }
    .gamer-card {
        background: #161b26;
        border: 1px solid #242b3d;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    }
    .text-neon-purple { color: #bb86fc; text-shadow: 0 0 10px rgba(187, 134, 252, 0.5); }
    .text-neon-cyan { color: #03dac6; text-shadow: 0 0 10px rgba(3, 218, 198, 0.5); }
    
    /* Pódio */
    .podium-container {
        display: flex;
        justify-content: center;
        align-items: flex-end;
        gap: 15px;
        padding: 20px 0;
    }
    .podium-card {
        background: linear-gradient(135deg, #1f2638, #161b26);
        border-radius: 12px;
        padding: 15px;
        text-align: center;
        width: 100%;
        transition: transform 0.3s;
        border: 2px solid transparent;
    }
    .podium-card:hover { transform: translateY(-5px); }
    .podium-1 { order: 2; height: 190px; border-color: #ffd700; box-shadow: 0 0 15px rgba(255, 215, 0, 0.2); }
    .podium-2 { order: 1; height: 160px; border-color: #c0c0c0; }
    .podium-3 { order: 3; height: 140px; border-color: #cd7f32; }
    
    /* Badges de Nível Customizadas */
    .badge-nivel { padding: 6px 12px; font-weight: bold; border-radius: 20px; text-transform: uppercase; font-size: 11px; }
    .bg-level-diamante { background: linear-gradient(45deg, #00b4db, #0083b0); color: #fff; box-shadow: 0 0 10px rgba(0,180,219,0.5); }
    .bg-level-ouro { background: linear-gradient(45deg, #ffe259, #ffa751); color: #fff; }
    .bg-level-prata { background: linear-gradient(45deg, #e6e9f0, #eef1f5); color: #333; }
    .bg-level-bronze { background: linear-gradient(45deg, #8a2387, #e94057); color: #fff; }

    /* Tabelas modernas */
    .table-gamer { color: #e1e7ed; vertical-align: middle; }
    .table-gamer tbody tr { background-color: #161b26; border-bottom: 1px solid #242b3d; transition: background 0.2s; }
    .table-gamer tbody tr:hover { background-color: #1f2638; }
    .table-gamer th { background-color: #1f2638; color: #9aa5b5; border: none; text-transform: uppercase; font-size: 12px; letter-spacing: 1px; }
    .my-row { background: linear-gradient(90deg, rgba(187, 134, 252, 0.15), rgba(3, 218, 198, 0.05)) !important; border: 1px solid #bb86fc !important; }
    
    /* Inventário de Medalhas */
    .medal-slot {
        background: #1f2638;
        border: 2px dashed #3b465e;
        border-radius: 12px;
        width: 95px;
        height: 105px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        transition: all 0.3s;
    }
    .medal-slot.active {
        background: linear-gradient(135deg, #242b3d, #161b26);
        border: 2px solid #ffc107;
        box-shadow: 0 4px 12px rgba(255, 193, 7, 0.15);
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="font-weight-bold text-neon-purple m-0">⚡ Arena de Performance</h2>
    <span class="badge bg-level-<?= strtolower($_SESSION['nivel'] ?? 'bronze') ?> badge-nivel">Seu Nível: <?= $_SESSION['nivel'] ?? 'Bronze' ?></span>
</div>

<!-- Filtro Neon Minimalista -->
<div class="card gamer-card p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="date" name="de" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= $de ?>">
        </div>
        <div class="col-md-3">
            <input type="date" name="ate" class="form-control form-control-sm bg-dark text-white border-secondary" value="<?= $ate ?>">
        </div>
        <div class="col-md-6 text-md-end">
            <button type="submit" class="btn btn-sm btn-primary px-3 bg-gradient border-0" style="background: #6200ee;">Filtrar Arena</button>
            <a href="dashboard.php?de=<?= date('Y-m-01') ?>&ate=<?= date('Y-m-t') ?>" class="btn btn-sm btn-outline-secondary px-3">Mês Atual</a>
        </div>
    </form>
</div>

<!-- PÓDIO VISUAL (TOP 3) -->
<h4 class="text-neon-cyan mb-3"><i class="fa-solid fa-crown text-warning"></i> Lendários da Semana</h4>
<div class="row mb-4">
    <div class="col-md-12">
        <div class="podium-container">
            <?php 
            foreach($top3 as $index => $player): 
                $pos = $index + 1;
                $podiumClass = "podium-" . $pos;
                $crown = ($pos == 1) ? '👑' : (($pos == 2) ? '🥈' : '🥉');
            ?>
                <div class="podium-card <?= $podiumClass ?>">
                    <div class="fs-2 mb-1"><?= $crown ?></div>
                    <div class="font-weight-bold text-truncate text-white" style="max-width: 140px;"><?= htmlspecialchars($player['nome']) ?></div>
                    <small class="text-muted d-block mb-2">@<?= htmlspecialchars($player['grupo'] ?? 'Sem GC') ?></small>
                    <span class="badge bg-level-<?= strtolower($player['nivel']) ?> badge-nivel mb-2"><?= $player['nivel'] ?></span>
                    <h4 class="text-neon-cyan m-0 font-weight-bold"><?= $player['total_skill'] ?> <span style="font-size: 12px;">PTS</span></h4>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Gráfico Estilizado -->
    <div class="col-md-6 mb-4 mb-md-0">
        <div class="card gamer-card p-3 h-100">
            <h5 class="text-white mb-3"><i class="fa-solid fa-chart-bar text-neon-purple"></i> Força dos GCs (Grupos de Crescimentos)</h5>
            <div class="position-relative style-chart" style="height: 220px;">
                <canvas id="chartGrupos"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Inventário de Medalhas / Conquistas -->
    <div class="col-md-6">
        <div class="card gamer-card p-3 h-100">
            <h5 class="text-white mb-2"><i class="fa-solid fa-box-open text-warning"></i> Meu Inventário de Conquistas</h5>
            <p class="text-muted small mb-3">Complete missões para desbloquear insígnias raras.</p>
            <div class="d-flex flex-wrap gap-2 justify-content-start">
                <?php if(empty($minhasMedalhas)): ?>
                    <div class="w-100 text-center py-4 border rounded border-secondary bg-dark text-muted">
                        <i class="fa-solid fa-lock fa-2x mb-2 text-secondary"></i>
                        <p class="m-0 small">Nenhuma insígnia conquistada. Vá em busca das Provas!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($minhasMedalhas as $med): ?>
                        <div class="medal-slot active">
                            <i class="<?= $med['icone'] ?> fa-2x text-warning mb-2 animate__animated animate__bounceIn"></i>
                            <div class="text-white text-center px-1 text-truncate w-100" style="font-size: 10px; font-weight: bold;"><?= $med['titulo'] ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Ranking de Integrantes Restantes -->
    <div class="col-md-6 mb-4">
        <div class="card gamer-card overflow-hidden">
            <div class="card-header border-0 py-3 d-flex justify-content-between align-items-center" style="background: #1f2638;">
                <h6 class="m-0 font-weight-bold text-white"><i class="fa-solid fa-list-ol text-neon-purple"></i> Classificação Geral</h6>
                <span class="badge bg-dark text-muted"><?= count($rankingIndividual) ?> Players</span>
            </div>
            <div class="table-responsive">
                <table class="table table-gamer mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Pos</th>
                            <th>Jogador</th>
                            <th>GC</th>
                            <th>Rank</th>
                            <th class="text-end pe-3">XP / Skill</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loop do Restante do Ranking -->
                        <?php 
                        $pos = 4; 
                        foreach($restanteRanking as $row): 
                            $isMe = ($row['nome'] == ($_SESSION['nome'] ?? ''));
                        ?>
                        <tr class="<?= $isMe ? 'my-row' : '' ?>">
                            <td class="ps-3 text-muted">#<?= $pos++ ?></td>
                            <td>
                                <span class="font-weight-bold text-black"><?= htmlspecialchars($row['nome']) ?></span>
                                <?= $isMe ? '<span class="badge bg-primary ms-1" style="font-size: 9px;">VOCÊ</span>' : '' ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($row['grupo'] ?? 'Sem GC') ?></td>
                            <td><span class="badge bg-level-<?= strtolower($row['nivel']) ?> badge-nivel" style="font-size: 9px; padding: 3px 8px;"><?= $row['nivel'] ?></span></td>
                            <td class="text-end pe-3 text-neon-cyan font-weight-bold"><?= $row['total_skill'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Ranking de Grupos Moderno -->
    <div class="col-md-6">
        <div class="card gamer-card overflow-hidden">
            <div class="card-header border-0 py-3" style="background: #1f2638;">
                <h6 class="m-0 font-weight-bold text-white"><i class="fa-solid fa-shield-halved text-neon-cyan"></i> Batalha de GCs</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-gamer mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">Pos</th>
                            <th>Nome do GC</th>
                            <th class="text-end pe-3">Pontuação Global</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $posG=1; foreach($rankingGrupos as $row): ?>
                        <tr>
                            <td class="ps-3 text-muted">
                                <?= $posG <= 3 ? '🛡️' : '#'.$posG ?>
                            </td>
                            <td><strong class="text-black"><?= htmlspecialchars($row['nome']) ?></strong></td>
                            <td class="text-end pe-3 text-warning font-weight-bold"><?= $row['total_skill'] ?> <span class="text-muted" style="font-size: 10px;">pts</span></td>
                        </tr>
                        <?php $posG++; endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Google Fonts para melhorar a tipografia -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Configuração do Gráfico Neon com Chart.js
    const ctxGrupos = document.getElementById('chartGrupos').getContext('2d');
    new Chart(ctxGrupos, {
        type: 'bar',
        data: {
            labels: [<?php foreach($rankingGrupos as $r) echo "'".htmlspecialchars($r['nome'])."',"; ?>],
            datasets: [{
                label: 'Pontos Totais',
                data: [<?php foreach($rankingGrupos as $r) echo $r['total_skill'].","; ?>],
                backgroundColor: [
                    'rgba(187, 134, 252, 0.6)', // Roxo Neon Transparente
                    'rgba(3, 218, 198, 0.6)',   // Ciano Neon Transparente
                    'rgba(255, 193, 7, 0.6)',   // Amarelo
                    'rgba(233, 64, 87, 0.6)'    // Rosa/Vermelho
                ],
                borderColor: [
                    '#bb86fc',
                    '#03dac6',
                    '#ffc107',
                    '#e94057'
                ],
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { 
                    grid: { display: false },
                    ticks: { color: '#9aa5b5' }
                },
                y: { 
                    grid: { color: '#242b3d' },
                    ticks: { color: '#9aa5b5' }
                }
            }
        }
    });
</script>
<?php
// Faça essa consulta no início ou antes do bloco HTML do gerenciar.php para listar os usuários
$usuarios_registrados = $pdo->query("SELECT u.id, u.nome, u.email, g.nome as grupo_nome, u.biometria_facial 
                                     FROM usuarios u 
                                     LEFT JOIN grupos g ON u.grupo_id = g.id")->fetchAll();
?>

<div class="card shadow-sm mt-4 mb-5">
    <div class="card-header bg-secondary text-white fw-bold">👥 Central de Players & Controle de Biometria</div>
    <div class="card-body p-0">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3">Nome</th>
                    <th>E-mail</th>
                    <th>Grupo/Clã</th>
                    <th>Status Face ID</th>
                    <th class="text-end pe-3">Ação Gestora</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($usuarios_registrados as $user): ?>
                <tr>
                    <td class="ps-3 fw-bold"><?= htmlspecialchars($user['nome']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['grupo_nome'] ?? 'Sem grupo') ?></td>
                    <td>
                        <?php if(!empty($user['biometria_facial'])): ?>
                            <span class="badge bg-success"><i class="fa-solid fa-circle-check"></i> Mapeado</span>
                        <?php else: ?>
                            <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark"></i> Não Cadastrado</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end pe-3">
                        <a href="cadastrar_biometria.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fa-solid fa-face-viewfinder"></i> Mapear Rosto
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>