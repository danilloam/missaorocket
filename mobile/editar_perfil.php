<?php
require_once 'header.php';

$msg = '';
$user_id = $_SESSION['user_id'] ?? 0;

// 1. Busca os dados atuais do usuário logado (incluindo o campo BLOB da foto)
$stmt = $pdo->prepare("
    SELECT u.nome, u.email, u.foto, g.nome as grupo_nome 
    FROM usuarios u 
    LEFT JOIN grupos g ON u.grupo_id = g.id 
    WHERE u.id = ? LIMIT 1
");
$stmt->execute([$user_id]);
$usuario = $stmt->fetch();

if (!$usuario) {
    echo "<div class='alert alert-danger m-3'>Usuário não encontrado.</div>";
    exit;
}

// 2. Processa o Formulário de Atualização (Nome + Foto em formato BLOB)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novo_nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
    $foto_binaria = $usuario['foto']; // Mantém o BLOB atual caso não envie um novo arquivo

    // Validação e Processamento do Upload para salvar no BLOB
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        $fileName = $_FILES['foto_perfil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $extensoes_permitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($fileExtension, $extensoes_permitidas)) {
            // ⚡ Captura o arquivo temporário e transforma em uma string de bytes binários
            $foto_binaria = file_get_contents($fileTmpPath);
        } else {
            $msg = "<div class='alert alert-danger py-2 small text-center rounded-3'>Formato inválido! Envie JPG, JPEG, PNG ou WEBP.</div>";
        }
    }

    if (empty($msg)) {
        if (!empty($novo_nome)) {
            // ⚡ Atualiza o banco de dados enviando o binário diretamente no parâmetro
            $stmtUpdate = $pdo->prepare("UPDATE usuarios SET nome = ?, foto = ? WHERE id = ?");
            $stmtUpdate->execute([$novo_nome, $foto_binaria, $user_id]);
            
            // ⚡ Atualiza os dados locais da variável do usuário para renderizar na página atual
            $usuario['nome'] = $novo_nome;
            $usuario['foto'] = $foto_binaria;

            // ⚡ Atualiza as variáveis de sessão para sincronizar com o Header e a Dashboard na hora
            $_SESSION['nome'] = $novo_nome;
            if (!empty($foto_binaria)) {
                $_SESSION['foto'] = 'data:image/jpeg;base64,' . base64_encode($foto_binaria);
            } else {
                $_SESSION['foto'] = null;
            }
            
            $msg = "<div class='alert alert-success py-2 small text-center rounded-3'>Perfil e foto atualizados com sucesso!</div>";
        } else {
            $msg = "<div class='alert alert-warning py-2 small text-center rounded-3'>O campo nome não pode ficar vazio.</div>";
        }
    }
}

// Lógica de fallback para renderizar a foto atual vinda do banco ou a primeira letra
$primeira_letra = strtoupper(mb_substr($usuario['nome'] ?? 'U', 0, 1));
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
    
    /* Container do Avatar Redondo Interativo */
    .avatar-upload-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        margin: 1.5rem 0;
    }
    .avatar-preview-circle {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background-color: #8b5cf6;
        color: #ffffff;
        font-size: 2.2rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.2);
        border: 3px solid #ffffff;
        overflow: hidden;
        position: relative;
    }
    .avatar-preview-circle img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .avatar-edit-badge {
        background-color: #0f172a;
        color: #ffffff;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        position: absolute;
        bottom: 0;
        right: 0;
        border: 2px solid #ffffff;
        cursor: pointer;
    }

    /* Inputs arredondados nativos de PWA */
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
    }
    .custom-input-group:focus-within {
        border-color: #8b5cf6;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15);
    }
    .custom-input-group.disabled-field {
        background-color: #f1f5f9;
    }
    .custom-input-group i {
        color: #64748b;
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
    .custom-input-group input:disabled {
        color: #94a3b8;
    }

    .shortcut-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background-color: #f3e8ff;
        color: #8b5cf6;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.82rem;
        font-weight: 700;
        text-decoration: none;
    }
    .admin-only-hint {
        font-size: 0.75rem;
        color: #94a3b8;
        display: block;
        margin-top: 0.35rem;
    }

    /* Rodapé colado na base inferior */
    .action-button-container {
        width: 100%;
        max-width: 480px;
        margin: auto auto 0 auto;
        padding: 1rem;
    }
    .btn-save-profile {
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
    }
</style>

<div class="main-wrapper">
    
    <div class="screen-header">
        <h2>Editar Perfil</h2>
    </div>

    <div class="mt-2">
        <?= $msg ?>
    </div>

    <form method="POST" action="editar_perfil.php" enctype="multipart/form-data" id="profileForm">
        
        <div class="avatar-upload-container">
            <div style="position: relative;">
                <div class="avatar-preview-circle" id="avatarPreview">
                    <?php if (!empty($usuario['foto'])): ?>
                        <img src="data:image/jpeg;base64,<?= base64_encode($usuario['foto']) ?>" alt="Foto de perfil">
                    <?php else: ?>
                        <?= $primeira_letra ?>
                    <?php endif; ?>
                </div>
                <label for="foto_perfil" class="avatar-edit-badge">
                    <i class="fa-solid fa-camera"></i>
                </label>
            </div>
            <input type="file" name="foto_perfil" id="foto_perfil" accept="image/*" style="display: none;">
            <span class="text-muted small mt-2" style="font-size: 0.75rem;">Toque no ícone para alterar a imagem</span>
        </div>

        <div class="mb-3">
            <label class="form-label">Nome Completo</label>
            <div class="custom-input-group">
                <i class="fa-solid fa-user text-muted"></i>
                <input type="text" name="nome" value="<?= htmlspecialchars($usuario['nome']) ?>" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">E-mail (Identificador)</label>
            <div class="custom-input-group disabled-field">
                <i class="fa-solid fa-envelope text-muted"></i>
                <input type="email" value="<?= htmlspecialchars($usuario['email']) ?>" disabled>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label">Seu Clã / Equipe</label>
            <div class="custom-input-group disabled-field">
                <i class="fa-solid fa-people-group text-muted"></i>
                <input type="text" value="<?= htmlspecialchars($usuario['grupo_nome'] ?? 'Nenhum Clã Vinculado') ?>" disabled>
            </div>
            <span class="admin-only-hint">
                <i class="fa-solid fa-circle-info"></i> Alteração de clã permitida apenas para administradores.
            </span>
        </div>

        <div class="mb-3">
            <label class="form-label d-block">Segurança da Conta</label>
            <a href="alterar_senha.php" class="shortcut-pill">
                <i class="fa-solid fa-key"></i> Alterar Senha de Acesso
            </a>
        </div>

    </form>
</div>

<div class="action-button-container">
    <button type="submit" form="profileForm" class="btn-save-profile">
        Salvar Alterações <i class="fa-solid fa-circle-check"></i>
    </button>
</div>

<script>
    document.getElementById('foto_perfil').addEventListener('change', function(e) {
        const file