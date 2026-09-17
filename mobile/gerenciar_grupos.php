<?php


require_once '../config.php';
exigirAdministrador($pdo);

if (isset($_GET['logo']) && ctype_digit((string) $_GET['logo'])) {
    $grupoId = (int) $_GET['logo'];

    try {
        $stmt = $pdo->prepare("
            SELECT logo, logo_tipo
            FROM grupos
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$grupoId]);
        $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$grupo || empty($grupo['logo'])) {
            http_response_code(404);
            exit;
        }

        $tipo = !empty($grupo['logo_tipo'])
            ? $grupo['logo_tipo']
            : 'image/png';

        /*
         * Segurança adicional: somente tipos de imagem permitidos.
         */
        $tiposPermitidos = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif'
        ];

        if (!in_array($tipo, $tiposPermitidos, true)) {
            $tipo = 'image/png';
        }

        header('Content-Type: ' . $tipo);
        header('Content-Length: ' . strlen($grupo['logo']));
        header('Cache-Control: public, max-age=86400');

        echo $grupo['logo'];
        exit;
    } catch (Throwable $e) {
        http_response_code(404);
        exit;
    }
}

require_once 'header.php';

date_default_timezone_set('America/Recife');

/*
 * Controle de acesso.
 * Mantido compatível com o padrão utilizado no painel administrativo.
 */
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'admin') {
    echo "<div class='alert alert-danger py-2 small text-center'>Acesso restrito para administradores.</div>";
    exit;
}

$feedback = '';
$feedbackTipo = 'success';

/*
 * Configuração do upload.
 * 5 MB é suficiente para logotipos e evita BLOBs desnecessariamente grandes.
 */
$maxLogoSize = 5 * 1024 * 1024;

$tiposPermitidos = [
    'image/jpeg' => 'JPG/JPEG',
    'image/png'  => 'PNG',
    'image/webp' => 'WebP',
    'image/gif'  => 'GIF'
];

/*
 * Cadastro de novo clã.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    try {
        if ($acao === 'cadastrar_grupo') {
            $nome = trim($_POST['nome'] ?? '');

            if ($nome === '') {
                throw new RuntimeException('Informe o nome do clã.');
            }

            if (mb_strlen($nome) > 150) {
                throw new RuntimeException('O nome do clã deve ter no máximo 150 caracteres.');
            }

            $stmt = $pdo->prepare("SELECT id FROM grupos WHERE nome = ? LIMIT 1");
            $stmt->execute([$nome]);

            if ($stmt->fetch()) {
                throw new RuntimeException('Já existe um clã com este nome.');
            }

            $stmt = $pdo->prepare("INSERT INTO grupos (nome) VALUES (?)");
            $stmt->execute([$nome]);

            $feedback = 'Clã cadastrado com sucesso.';
            $feedbackTipo = 'success';
        }

        /*
         * Atualização do nome do clã.
         */
        elseif ($acao === 'editar_grupo') {
            $grupoId = filter_input(INPUT_POST, 'grupo_id', FILTER_VALIDATE_INT);
            $nome = trim($_POST['nome'] ?? '');

            if (!$grupoId) {
                throw new RuntimeException('Clã inválido.');
            }

            if ($nome === '') {
                throw new RuntimeException('Informe o nome do clã.');
            }

            if (mb_strlen($nome) > 150) {
                throw new RuntimeException('O nome do clã deve ter no máximo 150 caracteres.');
            }

            $stmt = $pdo->prepare("
                SELECT id
                FROM grupos
                WHERE nome = ?
                  AND id <> ?
                LIMIT 1
            ");
            $stmt->execute([$nome, $grupoId]);

            if ($stmt->fetch()) {
                throw new RuntimeException('Já existe outro clã com este nome.');
            }

            $stmt = $pdo->prepare("UPDATE grupos SET nome = ? WHERE id = ?");
            $stmt->execute([$nome, $grupoId]);

            $feedback = 'Clã atualizado com sucesso.';
            $feedbackTipo = 'success';
        }

        /*
         * Adicionar ou alterar o logotipo.
         */
        elseif ($acao === 'salvar_logo') {
            $grupoId = filter_input(INPUT_POST, 'grupo_id', FILTER_VALIDATE_INT);

            if (!$grupoId) {
                throw new RuntimeException('Clã inválido.');
            }

            $stmt = $pdo->prepare("SELECT id, nome FROM grupos WHERE id = ? LIMIT 1");
            $stmt->execute([$grupoId]);
            $grupo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$grupo) {
                throw new RuntimeException('Clã não encontrado.');
            }

            if (
                !isset($_FILES['logo']) ||
                !is_array($_FILES['logo']) ||
                $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE
            ) {
                throw new RuntimeException('Selecione uma imagem para o logotipo.');
            }

            $arquivo = $_FILES['logo'];

            if ($arquivo['error'] !== UPLOAD_ERR_OK) {
                $mensagensUpload = [
                    UPLOAD_ERR_INI_SIZE   => 'A imagem excede o limite configurado no servidor.',
                    UPLOAD_ERR_FORM_SIZE  => 'A imagem excede o limite permitido pelo formulário.',
                    UPLOAD_ERR_PARTIAL    => 'O upload da imagem foi interrompido.',
                    UPLOAD_ERR_NO_TMP_DIR => 'A pasta temporária do servidor não está disponível.',
                    UPLOAD_ERR_CANT_WRITE => 'Não foi possível gravar o arquivo temporário.',
                    UPLOAD_ERR_EXTENSION  => 'O upload foi bloqueado por uma extensão do servidor.'
                ];

                $mensagem = $mensagensUpload[$arquivo['error']] ?? 'Não foi possível enviar a imagem.';
                throw new RuntimeException($mensagem);
            }

            if (!isset($arquivo['tmp_name']) || !is_uploaded_file($arquivo['tmp_name'])) {
                throw new RuntimeException('Arquivo de upload inválido.');
            }

            $tamanho = (int) ($arquivo['size'] ?? 0);

            if ($tamanho <= 0) {
                throw new RuntimeException('A imagem está vazia.');
            }

            if ($tamanho > $maxLogoSize) {
                throw new RuntimeException('O logotipo deve ter no máximo 5 MB.');
            }

            /*
             * getimagesize valida que o conteúdo é realmente uma imagem.
             * O MIME informado pelo navegador não é considerado suficiente.
             */
            $infoImagem = @getimagesize($arquivo['tmp_name']);

            if ($infoImagem === false || empty($infoImagem['mime'])) {
                throw new RuntimeException('O arquivo selecionado não é uma imagem válida.');
            }

            $mime = $infoImagem['mime'];

            if (!isset($tiposPermitidos[$mime])) {
                throw new RuntimeException('Formato não permitido. Use JPG, PNG, WebP ou GIF.');
            }

            $conteudo = file_get_contents($arquivo['tmp_name']);

            if ($conteudo === false || $conteudo === '') {
                throw new RuntimeException('Não foi possível ler a imagem enviada.');
            }

            $stmt = $pdo->prepare("
                UPDATE grupos
                SET logo = ?, logo_tipo = ?
                WHERE id = ?
            ");

            $stmt->bindValue(1, $conteudo, PDO::PARAM_LOB);
            $stmt->bindValue(2, $mime, PDO::PARAM_STR);
            $stmt->bindValue(3, $grupoId, PDO::PARAM_INT);
            $stmt->execute();

            $feedback = 'Logotipo do clã "' . htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8') . '" atualizado com sucesso.';
            $feedbackTipo = 'success';
        }
    } catch (Throwable $e) {
        $feedback = $e->getMessage();
        $feedbackTipo = 'danger';
    }
}

/*
 * Lista dos clãs.
 * COALESCE/CASE permite saber apenas se existe logo sem carregar o BLOB
 * para a página principal.
 */
$grupos = $pdo->query("
    SELECT
        id,
        nome,
        CASE
            WHEN logo IS NOT NULL AND OCTET_LENGTH(logo) > 0 THEN 1
            ELSE 0
        END AS possui_logo
    FROM grupos
    ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (isset($_POST['cadastrar_grupo'])) {

        $stmt = $pdo->prepare("INSERT INTO grupos (nome) VALUES (?)");
        $stmt->execute([$_POST['nome_grupo']]);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Clã/Grupo registado!</div>";
    }

    

}


?>

<style>
    .grupos-page {
        padding: 1rem;
    }

    .grupos-header {
        background: linear-gradient(135deg, #172033 0%, #253653 100%);
        color: #fff;
        border-radius: 18px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        box-shadow: 0 8px 25px rgba(0, 0, 0, .12);
    }

    .grupos-header h1 {
        font-size: 1.35rem;
        margin: 0;
        font-weight: 700;
    }

    .grupos-header p {
        margin: .35rem 0 0;
        opacity: .78;
        font-size: .9rem;
    }

    .grupo-card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .08);
        height: 100%;
        overflow: hidden;
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .grupo-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, .12);
    }

    .grupo-logo-area {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 170px;
        padding: 1.25rem;
        background: #f5f7fb;
    }

    .grupo-logo {
        width: 130px;
        height: 130px;
        object-fit: contain;
        border-radius: 20px;
        background: #fff;
        border: 1px solid #e5e7eb;
        padding: 8px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, .08);
    }

    .grupo-logo-sem-imagem {
        width: 130px;
        height: 130px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #e9edf5;
        color: #7b8495;
        font-size: 2.5rem;
        border: 2px dashed #cbd2df;
    }

    .grupo-card-body {
        padding: 1rem;
    }

    .grupo-nome {
        font-size: 1.05rem;
        font-weight: 700;
        margin: 0;
        color: #202938;
    }

    .grupo-status-logo {
        font-size: .76rem;
        color: #697386;
        margin-top: .25rem;
    }

    .logo-upload-area {
        border: 2px dashed #cbd5e1;
        border-radius: 14px;
        padding: 1rem;
        background: #f8fafc;
    }

    .logo-preview {
        width: 90px;
        height: 90px;
        object-fit: contain;
        border-radius: 14px;
        border: 1px solid #dee2e6;
        background: #fff;
        padding: 5px;
    }

    .modal-content {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
    }

    .btn {
        border-radius: 10px;
    }

    @media (max-width: 575.98px) {
        .grupos-page {
            padding: .65rem;
        }

        .grupo-logo-area {
            min-height: 145px;
        }

        .grupo-logo,
        .grupo-logo-sem-imagem {
            width: 105px;
            height: 105px;
        }
    }
</style>

<div class="grupos-page">
           
    <!-- BOTÃO VOLTAR -->

    <div class="action-links-row">

        <a
            href="gerenciar.php"
            class="action-pill-btn"
        >

            <i class="fa-solid fa-arrow-left text-muted"></i>

            Voltar ao Gerenciador

        </a>

    </div>

      <?= $feedback ?>
      
    <div class="grupos-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
        
        <div>
            <h1><i class="bi bi-people-fill me-2"></i>Gerenciamento de Clãs</h1>
            <p>Cadastre os clãs e gerencie seus respectivos logotipos.</p>
        </div>

        <button
            type="button"
            class="btn btn-light fw-semibold"
            data-bs-toggle="modal"
            data-bs-target="#modalNovoGrupo"
        >
            <i class="bi bi-plus-circle me-1"></i>
            Novo clã
        </button>
    </div>

    <?php if ($feedback !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($feedbackTipo, ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($grupos)): ?>
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body text-center py-5">
                <i class="bi bi-people fs-1 text-secondary"></i>
                <h5 class="mt-3">Nenhum clã cadastrado</h5>
                <p class="text-secondary mb-3">Cadastre o primeiro clã para começar.</p>

                <button
                    type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#modalNovoGrupo"
                >
                    <i class="bi bi-plus-circle me-1"></i>
                    Cadastrar clã
                </button>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($grupos as $grupo): ?>
                <?php
                    $grupoId = (int) $grupo['id'];
                    $grupoNome = $grupo['nome'];
                    $possuiLogo = (int) $grupo['possui_logo'] === 1;
                ?>

                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="card grupo-card">
                        <div class="grupo-logo-area">
                            <?php if ($possuiLogo): ?>
                                <img
                                    src="gerenciar_grupos.php?logo=<?= $grupoId ?>"
                                    alt="Logotipo de <?= htmlspecialchars($grupoNome, ENT_QUOTES, 'UTF-8') ?>"
                                    class="grupo-logo"
                                    loading="lazy"
                                >
                            <?php else: ?>
                                <div class="grupo-logo-sem-imagem" title="Clã sem logotipo">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="grupo-card-body">
                            <h2 class="grupo-nome">
                                <?= htmlspecialchars($grupoNome, ENT_QUOTES, 'UTF-8') ?>
                            </h2>

                            <div class="grupo-status-logo mb-3">
                                <?php if ($possuiLogo): ?>
                                    <i class="bi bi-check-circle-fill text-success me-1"></i>
                                    Logotipo cadastrado
                                <?php else: ?>
                                    <i class="bi bi-exclamation-circle me-1"></i>
                                    Sem logotipo
                                <?php endif; ?>
                            </div>

                            <div class="d-grid gap-2">
                                <button
                                    type="button"
                                    class="btn btn-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalLogoGrupo<?= $grupoId ?>"
                                >
                                    <i class="bi bi-camera me-1"></i>
                                    <?= $possuiLogo ? 'Alterar logo' : 'Adicionar logo' ?>
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-outline-secondary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalEditarGrupo<?= $grupoId ?>"
                                >
                                    <i class="bi bi-pencil me-1"></i>
                                    Editar clã
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal de edição do nome -->
                <div class="modal fade" id="modalEditarGrupo<?= $grupoId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-pencil me-2"></i>
                                    Editar clã
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="acao" value="editar_grupo">
                                    <input type="hidden" name="grupo_id" value="<?= $grupoId ?>">

                                    <label class="form-label fw-semibold">Nome do clã</label>
                                    <input
                                        type="text"
                                        name="nome"
                                        class="form-control"
                                        maxlength="150"
                                        required
                                        value="<?= htmlspecialchars($grupoNome, ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i>
                                        Salvar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Modal de logo -->
                <div class="modal fade" id="modalLogoGrupo<?= $grupoId ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-image me-2"></i>
                                    <?= $possuiLogo ? 'Alterar logo' : 'Adicionar logo' ?>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <form method="post" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <input type="hidden" name="acao" value="salvar_logo">
                                    <input type="hidden" name="grupo_id" value="<?= $grupoId ?>">

                                    <div class="text-center mb-3">
                                        <?php if ($possuiLogo): ?>
                                            <img
                                                src="gerenciar_grupos.php?logo=<?= $grupoId ?>"
                                                alt="Logo atual"
                                                class="logo-preview"
                                                id="previewLogo<?= $grupoId ?>"
                                            >
                                        <?php else: ?>
                                            <div
                                                class="grupo-logo-sem-imagem mx-auto"
                                                id="previewSemLogo<?= $grupoId ?>"
                                            >
                                                <i class="bi bi-image"></i>
                                            </div>

                                            <img
                                                src=""
                                                alt="Pré-visualização"
                                                class="logo-preview d-none mx-auto"
                                                id="previewLogo<?= $grupoId ?>"
                                            >
                                        <?php endif; ?>
                                    </div>

                                    <div class="logo-upload-area">
                                        <label class="form-label fw-semibold">
                                            Logotipo do clã
                                        </label>

                                        <input
                                            type="file"
                                            name="logo"
                                            class="form-control"
                                            accept="image/jpeg,image/png,image/webp,image/gif"
                                            required
                                            data-preview-target="previewLogo<?= $grupoId ?>"
                                            data-empty-target="<?= $possuiLogo ? '' : 'previewSemLogo' . $grupoId ?>"
                                        >

                                        <div class="form-text">
                                            JPG, PNG, WebP ou GIF. Tamanho máximo: 5 MB.
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                        Cancelar
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-cloud-arrow-up me-1"></i>
                                        Salvar logo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal novo clã -->
<div class="modal fade" id="modalNovoGrupo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-people-fill me-2"></i>
                    Novo clã
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="acao" value="cadastrar_grupo">

                    <label class="form-label fw-semibold">Nome do clã</label>
                    <input
                        type="text"
                        name="nome"
                        class="form-control"
                        maxlength="150"
                        required
                        autofocus
                        placeholder="Digite o nome do clã"
                    >

                    <div class="form-text">
                        Depois de cadastrar, você poderá adicionar o logotipo do clã.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancelar
                    </button>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>
                        Cadastrar clã
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="file"][data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            const file = this.files && this.files[0];
            const previewId = this.dataset.previewTarget;
            const emptyId = this.dataset.emptyTarget;

            const preview = document.getElementById(previewId);
            const empty = emptyId ? document.getElementById(emptyId) : null;

            if (!file || !preview) {
                return;
            }

            if (!file.type.startsWith('image/')) {
                this.value = '';
                return;
            }

            const reader = new FileReader();

            reader.onload = function (event) {
                preview.src = event.target.result;
                preview.classList.remove('d-none');

                if (empty) {
                    empty.classList.add('d-none');
                }
            };

            reader.readAsDataURL(file);
        });
    });
});
</script>