<?php 
require_once 'config.php'; // Carrega a conexão e sessão[cite: 3]

// Busca o período atual ou do mês para mostrar na tela
$de = $_GET['de'] ?? date('Y-m-01');
$ate = $_GET['ate'] ?? date('Y-m-t');

// Ranking de Grupos/Clãs consolidado
$queryGrp = "SELECT g.nome, COALESCE(SUM(h.pontos), 0) as total_skill 
             FROM grupos g
             LEFT JOIN historico_pontos h ON g.id = h.grupo_id AND h.status = 'aprovado'
             WHERE h.criado_em BETWEEN ? AND ? OR h.id IS NULL
             GROUP by g.id ORDER BY total_skill DESC";
$stmtGrp = $pdo->prepare($queryGrp);
$stmtGrp->execute([$de . ' 00:00:00', $ate . ' 23:59:59']);
$rankingGrupos = $stmtGrp->fetchAll();

// Separando o Top 3 para o Pódio de Clãs
$topGrupos = array_slice($rankingGrupos, 0, 3);
$restanteGrupos = array_slice($rankingGrupos, 3);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Placar de Líderes - Transmissão Oficial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@500;700;900&family=Poppins:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        /* Estética Cyberpunk/Gamer feita sob medida para Telão */
        body {
            background: radial-gradient(circle at center, #111625 0%, #070a12 100%);
            color: #ffffff;
            font-family: 'Poppins', sans-serif;
            overflow-x: hidden;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 20px;
        }

        .title-arena {
            font-family: 'Orbitron', sans-serif;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 4px;
            color: #00f0ff;
            text-shadow: 0 0 20px rgba(0, 240, 255, 0.6);
            font-size: 2.8rem;
        }

        .live-indicator {
            background: rgba(255, 0, 85, 0.2);
            border: 2px solid #ff0055;
            color: #ff0055;
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 30px;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 1px;
            box-shadow: 0 0 15px rgba(255, 0, 85, 0.4);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }

        /* Estrutura do Grande Pódio dos Clãs */
        .podium-arena {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 25px;
            margin: 40px 0;
        }

        .clan-podium-card {
            background: linear-gradient(180deg, rgba(30, 38, 56, 0.8) 0%, rgba(16, 21, 33, 0.9) 100%);
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            width: 280px;
            border: 3px solid transparent;
            position: relative;
            transition: all 0.5s ease;
        }

        /* Efeitos específicos das colocações do pódio */
        .rank-1 {
            order: 2;
            height: 340px;
            border-color: #ffd700;
            box-shadow: 0 0 35px rgba(255, 215, 0, 0.3);
            background: linear-gradient(180deg, rgba(45, 40, 25, 0.9) 0%, rgba(16, 21, 33, 0.9) 100%);
        }
        .rank-1 .score-pts { color: #ffd700; text-shadow: 0 0 15px rgba(255, 215, 0, 0.5); }

        .rank-2 {
            order: 1;
            height: 280px;
            border-color: #00f0ff;
            box-shadow: 0 0 25px rgba(0, 240, 255, 0.2);
        }
        .rank-2 .score-pts { color: #00f0ff; text-shadow: 0 0 15px rgba(0, 240, 255, 0.5); }

        .rank-3 {
            order: 3;
            height: 240px;
            border-color: #ff0055;
            box-shadow: 0 0 25px rgba(255, 0, 85, 0.2);
        }
        .rank-3 .score-pts { color: #ff0055; text-shadow: 0 0 15px rgba(255, 0, 85, 0.5); }

        .clan-name {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: 1px;
            margin-top: 10px;
        }

        .score-pts {
            font-family: 'Orbitron', sans-serif;
            font-size: 2.5rem;
            font-weight: 900;
            margin-top: 15px;
        }

        .crown-icon {
            font-size: 3.5rem;
            margin-bottom: 10px;
        }

        /* Lista Inferior (Posições de 4 para baixo) */
        .leaderboard-list {
            max-width: 900px;
            margin: 0 auto w-100;
            background: rgba(22, 27, 38, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .leaderboard-row {
            background: rgba(31, 38, 56, 0.5);
            margin: 8px 0;
            padding: 15px 25px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid #6200ee;
            transition: transform 0.3s;
        }

        .row-rank {
            font-family: 'Orbitron', sans-serif;
            font-weight: 700;
            font-size: 1.3rem;
            color: #9aa5b5;
            width: 50px;
        }

        .row-name {
            font-size: 1.3rem;
            font-weight: 600;
            flex-grow: 1;
        }

        .row-score {
            font-family: 'Orbitron', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: #03dac6;
        }

        /* Barra de rodapé do evento */
        .marquee-footer {
            background: #101521;
            border-top: 2px solid #242b3d;
            padding: 15px;
            text-align: center;
            font-size: 1.1rem;
            color: #9aa5b5;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
    </style>
</head>
<body>

    <!-- Topo do Telão -->
    <div class="d-flex justify-content-between align-items-center px-4 pt-2">
        <div>
            <h1 class="title-arena m-0"><i class="fa-solid fa-shield-halved text-warning"></i> BATALHA DE GCS</h1>
            <p class="text-muted m-0 p-0 fs-5">Placar Geral dos Grupos por Evento</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end text-muted small me-2 font-weight-bold">
                <i class="fa-solid fa-calendar-days"></i> Temporada Atual<br>
                <?= date('d/m', strtotime($de)) ?> até <?= date('d/m', strtotime($ate)) ?>
            </div>
            <div class="live-indicator">
                <i class="fa-solid fa-circle text-danger me-1"></i> Ao Vivo
            </div>
        </div>
    </div>

    <!-- PÓDIO GRANDE (TOP 3) -->
    <div class="podium-arena">
        <?php 
        // Renderiza o Top 1, 2 e 3 caso existam grupos cadastrados
        foreach($topGrupos as $index => $grupo):
            $pos = $index + 1;
            $badge = ($pos == 1) ? '👑' : (($pos == 2) ? '🥈' : '🥉');
        ?>
            <div class="clan-podium-card rank-<?= $pos ?>">
                <div class="crown-icon"><?= $badge ?></div>
                <div class="badge bg-dark px-3 py-1 text-uppercase tracking-wider rounded-pill" style="font-size: 0.8rem; letter-spacing: 1px;">
                    <?= $pos ?>º Colocado
                </div>
                <div class="clan-name text-truncate text-white"><?= htmlspecialchars($grupo['nome']) ?></div>
                <div class="score-pts"><?= $grupo['total_skill'] ?> <span style="font-size: 14px; color:#9aa5b5;">PTS</span></div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($topGrupos)): ?>
            <div class="text-center py-5 text-muted fs-4">Nenhuma pontuação registrada para este evento ainda.</div>
        <?php endif; ?>
    </div>

    <!-- RESTANTE DA LISTA (Abaixo do 3º Lugar) -->
    <?php if(!empty($restanteGrupos)): ?>
    <div class="leaderboard-list container">
        <?php 
        $posG = 4; 
        foreach($restanteGrupos as $grupo): 
        ?>
            <div class="leaderboard-row">
                <div class="row-rank">#<?= $posG++ ?></div>
                <div class="row-name text-white"><?= htmlspecialchars($grupo['nome']) ?></div>
                <div class="row-score"><?= $grupo['total_skill'] ?> PTS</div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Rodapé Invisível/Informativo do Telão -->
    <div class="marquee-footer mt-4 rounded">
        <i class="fa-solid fa-bolt text-warning animate__animated animate__flash animate__infinite"></i> 
        Envie suas evidências pelo painel do usuário para subir no ranking! Próxima atualização em instantes.
    </div>

    <!-- SCRIPT DE AUTO-REFRESH (Atualiza a tela sozinho a cada 15 segundos) -->
    <script>
        setTimeout(function(){
            window.location.reload();
        }, 15000); // 15000 milissegundos = 15 segundos
    </script>
</body>
</html>