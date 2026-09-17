<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Missão: ROCKET</title>
    <link rel="manifest" href="/manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php"><i class="fa-solid fa-trophy text-warning"></i> Missão: Rocket</a>
        <div class="collapse navbar-collapse">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="provas.php">Provas</a></li>
                <?php if(isset($_SESSION['perfil']) && $_SESSION['perfil'] == 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="gerenciar.php">Painel Admin</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white me-3">
                Olá, <?= $_SESSION['nome'] ?? 'Visitante' ?> (<?= $_SESSION['nivel'] ?? 'Bronze' ?>)
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>
<div class="container mb-5">