<?php require_once '../config.php'; 
date_default_timezone_set('America/Recife');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Missão: ROCKET</title>
    <link rel="manifest" href="manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { font-family: 'Poppins', sans-serif; background-color: #f4f6f9; }
        .navbar { background-color: #161b26 !important; }
        .table-responsive { border: none; }
        @media (max-width: 768px) {
            .card { margin-bottom: 15px; }
            h1, h2, h3 { font-size: 1.4rem; }
        }

        /* --- ESTILOS DO MODAL DE NOTIFICAÇÃO (PWA) --- */
        .pwa-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(6px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .pwa-banner { 
            width: 88%; 
            max-width: 360px; 
            background-color: #ffffff;
            border: 1px solid #e2e8f0; 
            border-radius: 24px;
            padding: 2rem 1.5rem; 
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15); 
            animation: popupSurgir 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .pwa-banner-content { 
            display: flex; 
            flex-direction: column;
            align-items: center; 
            text-align: center;
            gap: 1rem; 
        }
        .pwa-icon-area {
            width: 70px;
            height: 70px;
            background: rgba(139, 92, 246, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.5rem;
        }
        .pwa-icon-area i {
            font-size: 1.8rem;
            color: #8b5cf6;
        }
        .pwa-text-area { 
            color: #1e293b; 
        }
        #pwa-banner-title { 
            font-size: 1.35rem; 
            font-weight: 800; 
            color: #0f172a;
            letter-spacing: -0.5px; 
        }
        #pwa-banner-desc { 
            font-size: 0.9rem !important; 
            color: #64748b;
            line-height: 1.5; 
            font-weight: 500;
        }
        #btn-pwa-action { 
            background-color: #8b5cf6;
            color: white;
            font-weight: 700;
            font-size: 1rem;
            padding: 0.9rem;
            border-radius: 16px;
            border: none;
            width: 100%; 
            box-shadow: 0 6px 16px rgba(139, 92, 246, 0.25);
            transition: all 0.2s ease;
        }
        #btn-pwa-action:active {
            transform: scale(0.98);
            background-color: #7c3aed;
        }
        @keyframes popupSurgir {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>


<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3 sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fa-solid fa-rocket text-warning"></i> Miss&atilde;o: ROCKET
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarRocket">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarRocket">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link py-2" href="dashboard.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link py-2" href="provas.php"><i class="fa-solid fa-bullseye me-2"></i>Provas</a></li>
                <?php if(isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin'): ?>
                    <li class="nav-item"><a class="nav-link py-2 text-warning" href="gerenciar.php"><i class="fa-solid fa-sliders me-2"></i>Painel Admin</a></li>
                <?php endif; ?>
                <li class="nav-item d-lg-none"><hr class="dropdown-divider bg-secondary"></li>
                <li class="nav-item d-lg-none"><a class="nav-link text-danger py-2" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sair</a></li>
            </ul>
            <div class="d-none d-lg-flex align-items-center">
                <span class="navbar-text text-white me-3 small">
                    Olá, <strong><?= htmlspecialchars($_SESSION['nome'] ?? 'Visitante') ?></strong> (<?= $_SESSION['nivel'] ?? 'Bronze' ?>)
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </div>
</nav>

<div class="container d-lg-none mb-3">
    <div class="p-2 text-white rounded d-flex justify-content-between align-items-center small shadow-sm" style="background-color: #161b26 !important;">
        <span>🚀 <strong><?= htmlspecialchars($_SESSION['nome'] ?? 'Visitante') ?></strong></span>
        <span class="badge bg-secondary"><?= $_SESSION['nivel'] ?? 'Bronze' ?></span>
    </div>
</div>

<div class="container mb-5">