<?php 
require_once 'header.php'; 

if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
    echo "<div class='alert alert-danger'>Acesso restrito para administradores.</div>";
    exit;
}

$feedback = '';

// Ações do POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['cadastrar_grupo'])) {
        $stmt = $pdo->prepare("INSERT INTO grupos (nome) VALUES (?)");
        $stmt->execute([$_POST['nome_grupo']]);
        $feedback = "<div class='alert alert-success'>Grupo criado com sucesso!</div>";
    }
    
 if (isset($_POST['cadastrar_usuario'])) {
        $nome = trim($_POST['nome']);
        $email = strtolower(trim($_POST['email'])); // Garante gravação em minúsculo
        $senha_pura = trim($_POST['senha']);
        $senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);
        $perfil = $_POST['perfil'];
        $grupo = empty($_POST['grupo_id']) ? null : $_POST['grupo_id'];
        
        // 1. Insere o participante no banco de dados
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, grupo_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $senha_hash, $perfil, $grupo]);
        
        // 2. DISPARAR E-MAIL DE BOAS-VINDAS
        $para = $email;
        $assunto = "=?UTF-8?B?".base64_encode("Foguete Lançado! Seu acesso à Missão Rocket")."?=";
        
        // Corpo do e-mail formatado em HTML limpo (ideal para leitura em smartphones)
        $mensagemHTML = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Acesso à Arena</title>
        </head>
        <body style='font-family: sans-serif; background-color: #f7f9fc; color: #1e293b; padding: 20px; margin: 0;'>
            <div style='max-width: 420px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <span style='background: linear-gradient(135deg, #febb12 0%, #a066ff 100%); color: white; padding: 10px 20px; font-weight: 800; border-radius: 12px; display: inline-block; font-size: 1.2rem;'>
                        🚀 MISSÃO ROCKET
                    </span>
                </div>
                <h2 style='color: #0f172a; margin-bottom: 5px; font-weight: 800;'>Olá, Comandante!</h2>
                <p style='color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-top: 0;'>Seu cadastro foi realizado com sucesso pelo administrador. Prepare-se para entrar na Arena de Performance!</p>
                
                <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 0.85rem; color: #64748b;'><strong>Seus Dados de Acesso:</strong></p>
                    <p style='margin: 0 0 6px 0; font-size: 0.9rem;'><strong>E-mail:</strong> <span style='color: #8b5cf6;'>{$email}</span></p>
                    <p style='margin: 0; font-size: 0.9rem;'><strong>Senha Provisória:</strong> <span style='font-family: monospace; font-weight: bold; background: #fff; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 4px;'>{$senha_pura}</span></p>
                </div>
                
                <p style='color: #ef4444; font-size: 0.8rem; font-weight: bold; text-align: center;'>⚠️ Nota: Por segurança, altere sua senha no primeiro login.</p>
                
                <div style='text-align: center; margin-top: 25px;'>
                    <a href='https://missaorocket.com.br/mobile/' style='background-color: #8b5cf6; color: white; text-decoration: none; padding: 12px 30px; font-weight: bold; border-radius: 12px; inline-block; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);'>
                        Entrar na Arena
                    </a>
                </div>
            </div>
        </body>
        </html>
        ";

        // Cabeçalhos essenciais para o e-mail ser entregue em formato HTML e evitar caixas de Spam
        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: Missão Rocket <no-reply@missaorocket.com.br>" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Envia o e-mail silenciando possíveis avisos do servidor com o operador @
        @mail($para, $assunto, $mensagemHTML, $headers);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Usuário criado e e-mail de instruções enviado!</div>";
    }
    
    if (isset($_POST['cadastrar_prova'])) {
        $grupo_id = ($_POST['tipo'] == 'global') ? null : $_POST['grupo_id'];
        $stmt = $pdo->prepare("INSERT INTO provas (titulo, descricao, pontos, tipo, grupo_id, data_inicio, data_fim) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['titulo'], $_POST['descricao'], $_POST['pontos'], $_POST['tipo'], $grupo_id, $_POST['data_inicio'], $_POST['data_fim']]);
        $feedback = "<div class='alert alert-success'>Prova publicada com sucesso!</div>";
    }

    if (isset($_POST['acao_gestao'])) {
        $hist_id = $_POST['hist_id'];
        $status = $_POST['status']; // 'aprovado' ou 'rejeitado'
        
        $stmt = $pdo->prepare("UPDATE historico_pontos SET status = ? WHERE id = ?");
        $stmt->execute([$status, $hist_id]);
        
        // Se aprovado, atualiza o nível do usuário e distribui medalhas por performance
        if ($status == 'aprovado') {
            $stmtH = $pdo->prepare("SELECT usuario_id FROM historico_pontos WHERE id = ?");
            $stmtH->execute([$hist_id]);
            $u_id = $stmtH->fetch()['usuario_id'];
            atualizarNivelUsuario($pdo, $u_id);
        }
        $feedback = "<div class='alert alert-info'>Status da entrega atualizado!</div>";
    }
}

// Consultas estruturais
$grupos = $pdo->query("SELECT * FROM grupos")->fetchAll();
$provas = $pdo->query("SELECT p.*, g.nome as grupo_nome FROM provas p LEFT JOIN grupos g ON p.grupo_id = g.id")->fetchAll();
$analises = $pdo->query("SELECT h.*, u.nome as user_nome, p.titulo as prova_titulo FROM historico_pontos h JOIN usuarios u ON h.usuario_id = u.id JOIN provas p ON h.prova_id = p.id WHERE h.status = 'pendente'")->fetchAll();
?>

<h2>🛠 Painel Administrativo da Gestão</h2>
<?= $feedback ?>

<div class="row mt-4">
    <div class="col-md-4 mb-4">
        <div class="card p-3 shadow-sm h-100">
            <h5><i class="fa-solid fa-users"></i> Gerenciar Grupos</h5>
            <form method="POST" class="mb-3">
                <div class="mb-2">
                    <input type="text" name="nome_grupo" class="form-control form-control-sm" placeholder="Nome do novo Grupo" required>
                </div>
                <button type="submit" name="cadastrar_grupo" class="btn btn-primary btn-sm w-100">Criar Grupo</button>
            </form>
            <hr>
            <ul class="list-group overflow-auto" style="max-height: 150px;">
                <?php foreach($grupos as $g): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 small">
                        <?= htmlspecialchars($g['nome']) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card p-3 shadow-sm h-100">
            <h5><i class="fa-solid fa-user-plus"></i> Novo Integrante</h5>
            <form method="POST">
                <input type="text" name="nome" class="form-control form-control-sm mb-2" placeholder="Nome completo" required>
                <input type="email" name="email" class="form-control form-control-sm mb-2" placeholder="E-mail corporativo" required>
                <input type="password" name="senha" class="form-control form-control-sm mb-2" placeholder="Senha inicial" required>
                <select name="perfil" class="form-select form-select-sm mb-2">
                    <option value="participante">Perfil: Participante</option>
                    <option value="admin">Perfil: Gestão/Admin</option>
                </select>
                <select name="grupo_id" class="form-select form-select-sm mb-2">
                    <option value="">Vincular a nenhum Grupo</option>
                    <?php foreach($grupos as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= $g['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="cadastrar_usuario" class="btn btn-success btn-sm w-100">Cadastrar</button>
            </form>
        </div>
    </div>

    <div class="col-md-4 mb-4">
        <div class="card p-3 shadow-sm h-100">
            <h5><i class="fa-solid fa-bullseye"></i> Criar Prova</h5>
            <form method="POST">
                <input type="text" name="titulo" class="form-control form-control-sm mb-2" placeholder="Título da Prova" required>
                <textarea name="descricao" class="form-control form-control-sm mb-2" placeholder="Instruções ou Descrição"></textarea>
                <input type="number" name="pontos" class="form-control form-control-sm mb-2" placeholder="Skill de Pontuação (ex: 30)" required>
                <select name="tipo" class="form-select form-select-sm mb-2" id="tipo_prova" onchange="if(this.value=='exclusiva'){$('#grp_box').show()}else{$('#grp_box').hide()}">
                    <option value="global">Todos os Grupos (Global)</option>
                    <option value="exclusiva">Grupo Específico</option>
                </select>
                <div id="grp_box" style="display:none;">
                    <select name="grupo_id" class="form-select form-select-sm mb-2">
                        <?php foreach($grupos as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= $g['nome'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-1 mb-2">
                    <div class="col"><input type="date" name="data_inicio" class="form-control form-control-sm" required></div>
                    <div class="col"><input type="date" name="data_fim" class="form-control form-control-sm" required></div>
                </div>
                <button type="submit" name="cadastrar_prova" class="btn btn-warning btn-sm w-100">Lançar Prova</button>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-2 mb-5">
    <div class="card-header bg-dark text-white">📑 Análise e Aprovação de Evidências</div>
    <div class="card-body p-0">
        <table class="table table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>Participante</th>
                    <th>Prova</th>
                    <th>Evidência</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($analises)): ?>
                    <tr><td colspan="4" class="text-center text-muted p-3">Nenhuma evidência pendente de aprovação.</td></tr>
                <?php endif; ?>
                <?php foreach($analises as $an): 
                    // Identifica a extensão para saber se renderiza como imagem interna no modal
                    $ext = strtolower(pathinfo($an['evidencia'], PATHINFO_EXTENSION));
                    $isImagem = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
                ?>
                <tr>
                    <td><?= htmlspecialchars($an['user_nome']) ?></td>
                    <td><?= htmlspecialchars($an['prova_titulo']) ?> (<?= $an['pontos'] ?> pts)</td>
                    <td>
                        <button type="button" class="btn btn-link btn-sm fw-bold text-decoration-none" data-bs-toggle="modal" data-bs-target="#modalEvidencia<?= $an['id'] ?>">
                            <i class="fa-solid fa-eye"></i> Visualizar Evidência
                        </button>
                    </td>
                    <td>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="hist_id" value="<?= $an['id'] ?>">
                            <input type="hidden" name="status" value="aprovado">
                            <button type="submit" name="acao_gestao" value="1" class="btn btn-success btn-sm me-1">Aprovar</button>
                        </form>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="hist_id" value="<?= $an['id'] ?>">
                            <input type="hidden" name="status" value="rejeitado">
                            <button type="submit" name="acao_gestao" value="1" class="btn btn-danger btn-sm">Rejeitar</button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="modalEvidencia<?= $an['id'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content" style="border-radius: 16px;">
                            <div class="modal-header border-0 pb-0">
                                <h6 class="modal-title fw-bold text-dark">
                                    <i class="fa-solid fa-file-shield text-primary me-2"></i>Evidência de <?= htmlspecialchars($an['user_nome']) ?>
                                </h6>
                                <button type="button" class="btn-close" data-bs-toggle="modal" data-bs-target="#modalEvidencia<?= $an['id'] ?>" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center py-4">
                                <p class="text-muted small mb-3">Prova subordinada: <strong><?= htmlspecialchars($an['prova_titulo']) ?></strong></p>
                                
                                <?php if ($isImagem): ?>
                                    <div class="p-2 border rounded-3 bg-light inline-block overflow-hidden" style="max-height: 450px;">
                                        <img src="<?= htmlspecialchars($an['evidencia']) ?>" class="img-fluid rounded-2" style="max-height: 430px; object-fit: contain;" alt="Evidência Visual">
                                    </div>
                                <?php else: ?>
                                    <div class="p-4 border border-dashed rounded-3 bg-light my-3 mx-auto" style="max-width: 400px;">
                                        <i class="fa-solid fa-file-pdf fa-3x text-danger mb-3"></i>
                                        <h6>Documento Anexo (.<?= $ext ?>)</h6>
                                        <p class="text-muted small">Este arquivo não pode ser renderizado diretamente no navegador.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer border-0 pt-0 d-flex justify-content-between">
                                <a href="<?= htmlspecialchars($an['evidencia']) ?>" download class="btn btn-sm btn-outline-dark">
                                    <i class="fa-solid fa-download me-1"></i> Baixar Arquivo Original
                                </a>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Fechar Visualização</button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>