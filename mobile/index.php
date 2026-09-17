<?php
require_once '../config.php';
if (autenticarPorDispositivo($pdo)) { header('Location: dashboard.php'); exit; }
$erro = '';
$nome_tripulante = 'Comandante';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['identificar_tripulante'])
) {
    header('Content-Type: application/json; charset=UTF-8');

    $endpoint = trim($_POST['endpoint'] ?? '');

    if ($endpoint === '') {
        echo json_encode([
            'sucesso' => false,
            'nome'    => 'Comandante'
        ]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nome
            FROM usuarios_notificacoes un
            INNER JOIN usuarios u 
                ON u.id = un.usuario_id
            WHERE un.endpoint = ?
            LIMIT 1
        ");

        $stmt->execute([$endpoint]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && !empty(trim($usuario['nome']))) {
            echo json_encode([
                'sucesso' => true,
                'nome'    => trim($usuario['nome'])
            ]);
        } else {
            echo json_encode([
                'sucesso' => false,
                'nome'    => 'Comandante'
            ]);
        }

    } catch (Throwable $e) {
        error_log(
            'Erro ao identificar tripulante pelo Push: ' .
            $e->getMessage()
        );

        echo json_encode([
            'sucesso' => false,
            'nome'    => 'Comandante'
        ]);
    }

    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? strtolower(trim($_POST['email'])) : '';
    $senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

    if (!empty($email) && !empty($senha)) {
        $stmt = $pdo->prepare("SELECT id, grupo_id, nome, email, senha, perfil, nivel, foto FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if (password_verify($senha, $user['senha'])) {
               autenticarUsuarioSessao($user);
                criarTokenDispositivo( $pdo, (int)$user['id'] );
                // FLAG INDISPENSÁVEL: Indica para a Dashboard que o fluxo veio de um login recente
                $_SESSION['acabou_de_logar'] = true;

                if ($senha === '123456') {
                    $_SESSION['forçar_alteracao'] = true;
                    header('Location: alterar_senha.php');
                    exit;
                }
                
                if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
                    header('Location: dashboard.php');
                } else {
                    header('Location: gerenciar.php');
                }
                exit;
            } else {
                $erro = "Senha incorreta para este usuário.";
            }
        } else {
            $erro = "E-mail não encontrado no sistema.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#f7f9fc">
    <link rel="manifest" href="manifest.json">
    <title>Login - Miss&atilde;o Rocket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f7f9fc !important;
            color: #1e293b;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            margin: 0;
            overflow-x: hidden;
        }

        /* Container principal simulando app móvel */
        .app-container {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            padding: 2rem 1.5rem 0 1.5rem;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        /* Badge superior esquerdo "Login" */
        .login-badge {
            background-color: #00875a;
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 6px;
            width: fit-content;
            margin-bottom: 2rem;
        }

        /* Logotipo com o gradiente */
        .rocket-logo-box {
            width: 110px;
            height: 110px;
            background: linear-gradient(135deg, #febb12 0%, #a066ff 100%);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 12px 24px rgba(160, 102, 255, 0.25);
        }
        .rocket-logo-box i {
            font-size: 3rem;
            color: white;
            transform: rotate(-45deg);
        }

        /* Títulos principais */
        .main-title {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            text-align: center;
            letter-spacing: -0.5px;
            margin-bottom: 0.25rem;
        }
        .subtitle {
            font-size: 0.9rem;
            font-weight: 700;
            color: #febb12;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2.5rem;
        }

        /* Sessão de boas-vindas */
        .welcome-text {
            color: #94a3b8;
            font-size: 1rem;
            margin-bottom: 0.1rem;
            font-weight: 600;
        }
        .commander-text {
            font-size: 1.6rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 2rem;
        }

        /* Customização fina das labels e inputs */
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 0.5rem;
        }
        .custom-input-group {
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 0.4rem 1rem;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.01);
        }
        .custom-input-group:focus-within {
            border-color: #a066ff;
            box-shadow: 0 0 0 3px rgba(160, 102, 255, 0.15);
        }
        .custom-input-group i {
            color: #1e293b;
            font-size: 1.1rem;
            margin-right: 0.75rem;
        }
        .custom-input-group input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 0.95rem;
            color: #334155;
            background: transparent;
        }
        .custom-input-group input::placeholder {
            color: #cbd5e1;
        }

        /* Links adicionais */
        .forgot-password-link {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b5cf6;
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            margin: 1.5rem 0;
            transition: color 0.2s;
        }
        .forgot-password-link i {
            margin-right: 0.5rem;
            color: #0f172a;
            font-size: 1rem;
        }
        .forgot-password-link:hover {
            color: #6d28d9;
        }

        /* Mensagem de Erro customizada */
        .error-message {
            color: #ef4444;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1.5rem;
            min-height: 20px;
        }

        /* Botão de Ação Inferior */
        .action-button-container {
            width: 100%;
            max-width: 420px;
            margin: auto auto 0 auto;
            padding: 0 1rem 1rem 1rem;
        }
        .btn-enter-arena {
            background-color: #8b5cf6;
            color: white;
            font-weight: 700;
            font-size: 1.05rem;
            padding: 1rem;
            border-radius: 18px;
            border: none;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 8px 20px rgba(139, 92, 246, 0.3);
            transition: all 0.2s ease;
        }
        .btn-enter-arena:active {
            transform: scale(0.98);
            background-color: #7c3aed;
        }
    </style>
</head>
<body>

    <div class="app-container">
        <div class="login-badge">Login</div>

        <div class="rocket-logo-box">
            <i class="fa-solid fa-rocket"></i>
        </div>

        <h1 class="main-title">Miss&atilde;o: Rocket</h1>
        <div class="subtitle">Arena de Performance</div>

        <div class="welcome-text">Bem-vindo de volta,</div>
        <div class="commander-text"  id="nomeTripulante">Comandante</div>

        <form method="POST" action="index.php" id="loginForm">
            <div class="mb-3">
                <label class="form-label">E-mail</label>
                <div class="custom-input-group">
                    <i class="fa-regular fa-envelope"></i>
                    <input type="email" name="email" placeholder="Seu e-mail cadastrado" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Senha</label>
                <div class="custom-input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="senha" placeholder="Sua senha secreta" required>
                </div>
            </div>
        </form>

        <a href="#" class="forgot-password-link">
            <i class="fa-solid fa-bars-staggered"></i> Esqueci minha senha
        </a>

        <div class="error-message">
            <?php if (!empty($erro)): ?>
                <?= htmlspecialchars($erro) ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="action-button-container">
        <button type="submit" form="loginForm" class="btn-enter-arena">
            Entrar na Arena <i class="fa-solid fa-rocket"></i>
        </button>
    </div>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
            .then(reg => {
                if (reg.waiting) {
                    reg.waiting.postMessage({ type: 'SKIP_WAITING' });
                }
                reg.addEventListener('updatefound', () => {
                    const newWorker = reg.installing;
                    if (newWorker) {
                        newWorker.addEventListener('statechange', () => {
                            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                newWorker.postMessage({ type: 'SKIP_WAITING' });
                            }
                        });
                    }
                });
            });
        });

        let abrindoPelaPrimeiraVez = true;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!abrindoPelaPrimeiraVez) return;
            window.location.reload();
            abrindoPelaPrimeiraVez = false;
        });
    }
</script>
<script>
document.addEventListener('DOMContentLoaded', async function () {

    const nomeElemento = document.getElementById('nomeTripulante');

    if (!nomeElemento) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Tenta identificar o tripulante pelo Web Push já cadastrado
    |--------------------------------------------------------------------------
    */

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    try {

        const registro = await navigator.serviceWorker.ready;

        const subscription = await registro.pushManager.getSubscription();

        /*
        | Não existe assinatura neste dispositivo.
        | Mantém "Comandante".
        */
        if (!subscription) {
            return;
        }

        const endpoint = subscription.endpoint;

        if (!endpoint) {
            return;
        }

        const dados = new URLSearchParams();

        dados.append('identificar_tripulante', '1');
        dados.append('endpoint', endpoint);

        const resposta = await fetch('index.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: dados.toString()
        });

        if (!resposta.ok) {
            return;
        }

        const resultado = await resposta.json();

        if (
            resultado &&
            resultado.sucesso === true &&
            resultado.nome &&
            resultado.nome.trim() !== ''
        ) {
            nomeElemento.textContent = resultado.nome.trim();
        }

    } catch (erro) {

        /*
        | Falha silenciosa.
        | O usuário continua vendo "Comandante".
        */
        console.warn(
            'Não foi possível identificar o tripulante:',
            erro
        );
    }
});
</script>
</body>

</html>