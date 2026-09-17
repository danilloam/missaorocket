<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../config.php'; // Conexão PDO ativa

$feedback = '';
$status = '';
$prova = null;

// 1. Captura o ID do evento vindo da URL (ex: checkin_login.php?id=15)
$prova_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($prova_id === 0) {
    $feedback = "Código de check-in inválido ou ausente.";
    $status = 'erro';
} else {
    // Busca as informações do evento para exibir na tela
    $stmt = $pdo->prepare("SELECT id, titulo, pontos FROM provas WHERE id = ?");
    $stmt->execute([$prova_id]);
    $prova = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prova) {
        $feedback = "Este evento ou check-in não existe.";
        $status = 'erro';
    }
}

// 2. Processa o Formulário de Login + Check-in automático
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $status !== 'erro') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $senha = $_POST['senha'];

    // Busca o usuário no banco de dados pelo e-mail
    $stmtUser = $pdo->prepare("SELECT id, nome, senha, perfil FROM usuarios WHERE email = ? LIMIT 1");
    $stmtUser->execute([$email]);
    $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // Verifica se o usuário existe e se a senha está correta
    if ($usuario && ($senha === $usuario['senha'] || password_verify($senha, $usuario['senha']))) {
        
        // Loga o usuário na sessão
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['perfil'] = $usuario['perfil'];

        $usuario_id = $usuario['id'];

        // 3. Evita que o usuário pontue duas vezes no mesmo evento
        $stmtCheck = $pdo->prepare("SELECT id FROM historico_pontos WHERE usuario_id = ? AND prova_id = ?");
        $stmtCheck->execute([$usuario_id, $prova_id]);

        if ($stmtCheck->fetch()) {
            $feedback = "Olá, {$usuario['nome']}! Você já realizou o check-in e garantiu seus pontos para este evento.";
            $status = 'ja_feito';
        } else {
            // 4. Salva a presença e computa a pontuação com as colunas corretas
            $stmtInsert = $pdo->prepare("
                INSERT INTO historico_pontos (usuario_id, prova_id, pontos, evidencia, status, criado_em) 
                VALUES (?, ?, ?, 'Check-in via link Online\Presencial','aprovado', NOW())
            ");
            
            if ($stmtInsert->execute([$usuario_id, $prova_id, $prova['pontos']])) {
                $feedback = "Parabéns, {$usuario['nome']}! Seu check-in foi realizado com sucesso.";
                $status = 'sucesso';
            } else {
                $feedback = "Erro interno ao processar sua pontuação. Tente novamente.";
                $status = 'erro';
            }
        }
    } else {
        $feedback = "E-mail ou senha incorretos. Tente novamente.";
        $status = 'login_invalido';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in no Evento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    
    <style>
        body { 
            background-color: #f8fafc !important; 
            color: #1e293b; 
            font-family: system-ui, -apple-system, sans-serif; 
            min-height: 100vh;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0;
        }
        .main-wrapper { 
            width: 100%;
            max-width: 480px; 
            padding: 1.5rem; 
            box-sizing: border-box;
        }
        .qrcode-container { 
            background: #ffffff; 
            border-radius: 28px; 
            border: 1px solid #e2e8f0; 
            padding: 3rem 2.5rem; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.03); 
        }
        
        /* Ajuste estrutural do Form para evitar desalinhamento lateral */
        .form-container-block {
            display: block;
            width: 100%;
            margin-top: 1.5rem;
        }
        
        .form-group-custom {
            display: block !important;
            width: 100% !important;
            text-align: left !important;
            margin-bottom: 1.5rem;
        }
        
        .form-group-custom label {
            display: block !important;
            width: 100% !important;
            text-align: left !important;
            margin-bottom: 0.5rem;
            color: #475569;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .form-control { 
            display: block !important;
            width: 100% !important;
            border-radius: 14px !important; 
            padding: 0.75rem 1.2rem !important; 
            border: 1px solid #cbd5e1 !important; 
            font-size: 0.95rem !important; 
            background-color: #fff !important;
            box-sizing: border-box;
        }
        
        .form-control:focus { 
            box-shadow: 0 0 0 4px rgba(15, 23, 42, 0.08) !important; 
            border-color: #0f172a !important; 
            outline: 0;
        }
        
        .btn-submit { 
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background-color: #0f172a !important; 
            color: white !important; 
            border: none !important; 
            font-weight: 700 !important; 
            border-radius: 14px !important; 
            padding: 0.85rem !important; 
            width: 100% !important; 
            font-size: 0.95rem !important; 
            transition: background 0.2s; 
            margin-top: 1.5rem;
        }
        
        .btn-submit:hover { 
            background-color: #1e293b !important; 
        }
        
        .rocket-icon {
            color: #0f172a;
            margin-bottom: 1.5rem;
            display: inline-block;
        }
        
        .title-identifique {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
        }
    </style>
</head>
<body>

<div class="main-wrapper">
    <div class="qrcode-container text-center">
        
        <?php if ($status === 'sucesso'): ?>
            <div class="text-success mb-3"><i class="fa-solid fa-circle-check fa-4x"></i></div>
            <h5 class="fw-bold text-dark mb-1">Presença Confirmada!</h5>
            <p class="text-muted small mb-4"><?= $feedback ?></p>
            
            <div class="p-3 bg-light rounded-3 border text-start small mb-4">
                <div class="mb-1"><strong>Missão Ativa:</strong> <span class="text-primary"><?= htmlspecialchars($prova['titulo']) ?></span></div>
                <div><strong>Pontos Ganhos:</strong> <span class="text-success fw-bold">+<?= $prova['pontos'] ?> PTS</span></div>
            </div>
            <script>confetti();</script>
            <a href="index.php" class="btn btn-sm btn-outline-secondary w-100 rounded-3 py-2 fw-bold">Ir para o Painel</a>

        <?php elseif ($status === 'ja_feito'): ?>
            <div class="text-warning mb-3"><i class="fa-solid fa-circle-exclamation fa-4x"></i></div>
            <h5 class="fw-bold text-dark mb-2">Check-in já realizado!</h5>
            <p class="text-muted small mb-4"><?= $feedback ?></p>
            
            <div class="p-3 bg-light rounded-3 border text-start small mb-4">
                <div class="text-truncate"><strong>Missão:</strong> <span class="text-dark"><?= htmlspecialchars($prova['titulo']) ?></span></div>
            </div>
            <a href="index.php" class="btn btn-sm btn-outline-secondary w-100 rounded-3 py-2 fw-bold">Ir para o Painel</a>

        <?php elseif ($status === 'erro' && !$prova): ?>
            <div class="text-danger mb-3"><i class="fa-solid fa-calendar-xmark fa-4x"></i></div>
            <h5 class="fw-bold text-dark mb-2">Ops! Link Inválido</h5>
            <p class="text-muted small mb-4"><?= $feedback ?></p>
            <a href="index.php" class="btn btn-sm btn-secondary w-100 rounded-3 py-2 fw-bold">Voltar ao Início</a>

        <?php else: ?>
            <div class="rocket-icon"><i class="fa-solid fa-rocket fa-3x"></i></div>
            <h5 class="title-identifique mb-3">Identifique-se no Rocket</h5>
            <p class="text-muted small mb-4 px-1">Confirme sua presença no <strong><?= htmlspecialchars($prova['titulo']) ?></strong> para pontuar.</p>

            <?php if ($status === 'login_invalido' || ($status === 'erro' && $prova)): ?>
                <div class="alert alert-danger py-2 small text-center mb-3"><?= $feedback ?></div>
            <?php endif; ?>

            <form method="POST" class="form-container-block">
                <div class="form-group-custom">
                    <label class="small fw-bold">E-mail</label>
                    <input type="email" name="email" class="form-control" placeholder="Seu e-mail cadastrado" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                
                <div class="form-group-custom">
                    <label class="small fw-bold">Senha do Sistema</label>
                    <input type="password" name="senha" class="form-control" placeholder="Sua senha de acesso" required>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-right-to-bracket me-2"></i> Entrar e Pontuar
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>