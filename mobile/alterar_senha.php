<?php
require_once 'header.php';

$msg = '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = isset($_POST['senha_atual']) ? trim($_POST['senha_atual']) : '';
    $nova_senha  = isset($_POST['nova_senha']) ? trim($_POST['nova_senha']) : '';
    $confirma_senha = isset($_POST['confirma_senha']) ? trim($_POST['confirma_senha']) : '';

    if (!empty($senha_atual) && !empty($nova_senha) && !empty($confirma_senha)) {
        if ($nova_senha !== $confirma_senha) {
            $msg = "<div class='alert alert-danger py-2 small text-center rounded-3'>A nova senha e a confirmação não coincidem.</div>";
        } elseif (strlen($nova_senha) < 6) {
            $msg = "<div class='alert alert-danger py-2 small text-center rounded-3'>A nova senha deve ter pelo menos 6 caracteres.</div>";
        } else {
            // Busca a senha atual hashada no banco para validação
            $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user && password_verify($senha_atual, $user['senha'])) {
                // Gera o novo hash seguro
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                // Atualiza no banco de dados
                $stmtUpdate = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmtUpdate->execute([$nova_senha_hash, $user_id]);

                $msg = "<div class='alert alert-success py-2 small text-center rounded-3'>Senha alterada com sucesso!</div>";
            } else {
                $msg = "<div class='alert alert-danger py-2 small text-center rounded-3'>A senha atual digitada está incorreta.</div>";
            }
        }
    } else {
        $msg = "<div class='alert alert-warning py-2 small text-center rounded-3'>Por favor, preencha todos os campos.</div>";
    }
}
?>

<style>
    body {
        background-color: #f8fafc !important;
        color: #1e293b;
        height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .main-wrapper {
        max-width: 480px;
        margin: 0 auto;
        padding: 1rem 1rem 0 1rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        width: 100%;
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
    
    /* Customização dos Inputs nativos de App */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 0.4rem;
    }
    .custom-input-group {
        background-color: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 0.65rem 1rem;
        display: flex;
        align-items: center;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.01);
    }
    .custom-input-group:focus-within {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    }
    .custom-input-group i {
        color: #1e293b;
        font-size: 1.1rem;
        margin-right: 0.75rem;
        width: 20px;
        text-align: center;
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

    /* Rodapé do botão colado na base */
    .action-button-container {
        width: 100%;
        max-width: 480px;
        margin: auto auto 0 auto;
        padding: 1rem;
    }
    .btn-save-password {
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
        box-shadow: 0 8px 20px rgba(139, 92, 246, 0.2);
        transition: all 0.2s ease;
    }
    .btn-save-password:active {
        transform: scale(0.98);
        background-color: #7c3aed;
    }
</style>

<div class="main-wrapper">
    
    <div class="screen-header mb-4">
        <h2>Segurança</h2>
        <p>Atualize suas credenciais de acesso à arena</p>
    </div>

    <div class="mb-3">
        <?= $msg ?>
    </div>

    <form method="POST" action="alterar_senha.php" id="passwordForm">
        
        <div class="mb-3">
            <label class="form-label">Senha Atual</label>
            <div class="custom-input-group">
                <i class="fa-solid fa-lock-open text-muted"></i>
                <input type="password" name="senha_atual" placeholder="Digite sua senha atual" required>
            </div>
        </div>

        <hr class="text-muted opacity-25 my-4">

        <div class="mb-3">
            <label class="form-label">Nova Senha</label>
            <div class="custom-input-group">
                <i class="fa-solid fa-key text-muted"></i>
                <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" required>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Confirmar Nova Senha</label>
            <div class="custom-input-group">
                <i class="fa-solid fa-shield-halved text-muted"></i>
                <input type="password" name="confirma_senha" placeholder="Repita a nova senha" required>
            </div>
        </div>

    </form>
</div>

<div class="action-button-container">
    <button type="submit" form="passwordForm" class="btn-save-password">
        Salvar Nova Senha <i class="fa-solid fa-circle-check"></i>
    </button>
</div>

</body>
</html>