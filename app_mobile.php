<?php
/**
 * Arena de Performance - Versão Mobile PWA (Mock Mode)
 * Estrutura visual, componentes e navegação simulada.
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Arena de Performance</title>
    
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Arena Rocket">
    <meta name="theme-color" content="#0b0e14">
    <link rel="manifest" href="manifest.json">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-main: #0b0e14;
            --bg-card: #161b26;
            --bg-nav: #1f2638;
            --neon-purple: #bb86fc;
            --neon-cyan: #03dac6;
            --gold: #ffd700;
            --silver: #c0c0c0;
            --bronze: #cd7f32;
        }

        body {
            background-color: var(--bg-main);
            color: #f1f3f5;
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            user-select: none;
            overflow-x: hidden;
            padding-bottom: 85px; /* Altura do Bottom Navigation */
        }

        /* Esconder barras de rolagem mas mantendo o scroll ativo */
        ::-webkit-scrollbar { display: none; }

        /* Estilização Geral de Componentes */
        .gamer-card {
            background: var(--bg-card);
            border: 1px solid #242b3d;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s;
        }
        .text-neon-purple { color: var(--neon-purple); text-shadow: 0 0 8px rgba(187, 134, 252, 0.4); }
        .text-neon-cyan { color: var(--neon-cyan); text-shadow: 0 0 8px rgba(3, 218, 198, 0.4); }

        .badge-nivel {
            padding: 6px 12px;
            font-weight: bold;
            border-radius: 20px;
            text-transform: uppercase;
            font-size: 10px;
        }
        .bg-level-diamante { background: linear-gradient(45deg, #00b4db, #0083b0); color: #fff; }
        .bg-level-ouro { background: linear-gradient(45deg, #ffe259, #ffa751); color: #fff; }
        .bg-level-prata { background: linear-gradient(45deg, #e6e9f0, #eef1f5); color: #333; }
        .bg-level-bronze { background: linear-gradient(45deg, #8a2387, #e94057); color: #fff; }

        /* Controladores de Telas (Views) */
        .app-view {
            display: none;
            animation: fadeIn 0.3s ease-in-out forwards;
        }
        .app-view.active { display: block; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* 1. Splash Screen */
        #view-splash {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: var(--bg-main);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        .rocket-glow {
            font-size: 5rem;
            color: var(--neon-purple);
            animation: pulse 1.5s infinite alternate;
        }
        @keyframes pulse {
            from { text-shadow: 0 0 10px rgba(187,134,252,0.6); transform: scale(0.95); }
            to { text-shadow: 0 0 30px rgba(187,134,252,1); transform: scale(1.05); }
        }

        /* 2. Tela de Login */
        .login-input {
            background-color: #0f131c !important;
            border: 1px solid #242b3d !important;
            color: #white !important;
            border-radius: 10px;
            padding: 12px;
        }
        .login-input:focus {
            border-color: var(--neon-purple) !important;
            box-shadow: 0 0 8px rgba(187,134,252,0.4) !important;
        }

        /* Bottom Navigation Bar Fixa */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            height: 70px;
            background: var(--bg-nav);
            border-top: 1px solid #242b3d;
            display: flex;
            justify-content: space-around;
            align-items: center;
            z-index: 1000;
            padding-bottom: env(safe-area-inset-bottom);
        }
        .nav-item-btn {
            background: none;
            border: none;
            color: #9aa5b5;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.2s;
            width: 20%;
        }
        .nav-item-btn i { font-size: 20px; margin-bottom: 3px; }
        .nav-item-btn.active { color: var(--neon-purple); }

        /* Pódio Visual Slim */
        .podium-mini {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 8px;
            margin-bottom: 20px;
        }
        .podium-item {
            background: #1c2230;
            border-radius: 12px;
            padding: 10px;
            text-align: center;
            flex: 1;
        }
        .podium-item.p-1 { height: 130px; border: 2px solid var(--gold); }
        .podium-item.p-2 { height: 110px; border: 2px solid var(--silver); order: -1;}
        .podium-item.p-3 { height: 95px; border: 2px solid var(--bronze); }

        /* Grid de Medalhas */
        .medal-grid-slot {
            background: #1f2638;
            border-radius: 12px;
            height: 95px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .medal-grid-slot.locked { opacity: 0.4; }
        .medal-grid-slot.locked i { color: #6c757d !important; }
        .lock-badge { position: absolute; top: 5px; right: 8px; font-size: 10px; color: #ff4a5a; }

        /* Subir Evidência Dropzone simulado */
        .upload-box {
            border: 2px dashed #3b465e;
            background: #121620;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div id="view-splash">
        <i class="fa-solid fa-shuttle-space rocket-glow"></i>
        <h2 class="mt-4 font-weight-bold text-white tracking-wide">ARENA ROCKET</h2>
        <p class="text-muted small">Carregando engine de performance...</p>
    </div>

    <div id="view-login" class="app-view container pt-5">
        <div class="text-center my-5">
            <i class="fa-solid fa-trophy text-warning fa-3x mb-2"></i>
            <h3 class="text-white font-weight-bold">Arena de Performance</h3>
            <p class="text-muted small">Gamificação & Desafios Corporativos</p>
        </div>
        <div class="card gamer-card p-4">
            <form id="form-login" onsubmit="event.preventDefault(); navegarPara('dashboard');">
                <div class="mb-3">
                    <label class="form-label text-muted small">E-mail Corporativo</label>
                    <input type="email" class="form-control login-input text-white" value="player@empresa.com.br" required>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Senha de Acesso</label>
                    <input type="password" class="form-control login-input text-white" value="******" required>
                </div>
                <button type="submit" class="btn w-100 py-2.5 fw-bold text-white mb-3" style="background: linear-gradient(90deg, #6200ee, #bb86fc); border: none; border-radius: 10px;">
                    ENTRAR NA ARENA
                </button>
                <div class="text-center">
                    <a href="#" class="text-neon-cyan small text-decoration-none">Esqueci minha senha</a>
                </div>
            </form>
        </div>
    </div>

    <div id="app-content" style="display: none;">

        <div id="view-dashboard" class="app-view container pt-3">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h5