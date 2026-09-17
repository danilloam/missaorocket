<?php
require_once 'header.php';

$msg = '';

$user_id = $_SESSION['user_id'];
$grupo_id = $_SESSION['grupo_id'] ?? 0;

// PROCESSAR UPLOAD
if (isset($_POST['enviar_prova'])) {

    $prova_id = (int)$_POST['prova_id'];

    // Verifica situação atual da prova para este usuário
    $stVerifica = $pdo->prepare("
        SELECT status
        FROM historico_pontos
        WHERE usuario_id = ?
          AND prova_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stVerifica->execute([
        $user_id,
        $prova_id
    ]);

    $ultimaTentativa = $stVerifica->fetch(PDO::FETCH_ASSOC);

    if ($ultimaTentativa) {

        if ($ultimaTentativa['status'] == 'pendente') {

            $msg = "
            <div class='alert alert-warning'>
                Esta prova já foi enviada e está aguardando análise.
            </div>";

        } elseif ($ultimaTentativa['status'] == 'aprovado') {

            $msg = "
            <div class='alert alert-success'>
                Esta prova já foi aprovada e concluída.
            </div>";

        } else {

            processarUpload();

        }

    } else {

        processarUpload();
    }
}

function processarUpload()
{
    global $pdo, $user_id, $grupo_id, $msg, $prova_id;

    if (
        !isset($_FILES['evidencia']) ||
        $_FILES['evidencia']['error'] !== UPLOAD_ERR_OK
    ) {

        $msg = "
        <div class='alert alert-danger'>
            Nenhum arquivo enviado.
        </div>";

        return;
    }

    $diretorio = "../uploads/";

    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0777, true);
    }

    $extensao = strtolower(
        pathinfo(
            $_FILES['evidencia']['name'],
            PATHINFO_EXTENSION
        )
    );

    $permitidas = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array($extensao, $permitidas)) {

        $msg = "
        <div class='alert alert-danger'>
            Apenas PDF, JPG, JPEG e PNG são permitidos.
        </div>";

        return;
    }

    $nome_arquivo =
        time() . '_' .
        uniqid('', true) . '.' .
        $extensao;

    $caminho_final = $diretorio . $nome_arquivo;

    if (!move_uploaded_file(
        $_FILES['evidencia']['tmp_name'],
        $caminho_final
    )) {

        $msg = "
        <div class='alert alert-danger'>
            Erro ao fazer upload do arquivo.
        </div>";

        return;
    }

    $stP = $pdo->prepare("
        SELECT pontos
        FROM provas
        WHERE id = ?
    ");

    $stP->execute([$prova_id]);

    $prova = $stP->fetch(PDO::FETCH_ASSOC);

    if (!$prova) {

        $msg = "
        <div class='alert alert-danger'>
            Prova não encontrada.
        </div>";

        return;
    }

    $pts = $prova['pontos'];

    $ins = $pdo->prepare("
        INSERT INTO historico_pontos
        (
            usuario_id,
            grupo_id,
            prova_id,
            pontos,
            evidencia,
            status
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            'pendente'
        )
    ");

    $ins->execute([
        $user_id,
        $grupo_id,
        $prova_id,
        $pts,
        $caminho_final
    ]);

    $msg = "
    <div class='alert alert-success'>
        Evidência enviada com sucesso!
        Aguardando aprovação da gestão.
    </div>";
}

// LISTAR PROVAS
$stmt = $pdo->prepare("
    SELECT *
FROM provas
WHERE
    (tipo = 'global' OR grupo_id = ?)
    AND DATE(data_inicio) <= CURDATE()
    AND DATE(data_fim) >= CURDATE()
    AND (titulo NOT LIKE '%Check-in %')
    AND id NOT IN (1, 2)
");

$stmt->execute([$grupo_id]);

$provas = $stmt->fetchAll();
?>

<h2>🎯 Provas Disponíveis</h2>

<?= $msg ?>

<div class="row mt-4">

<?php if (empty($provas)): ?>

    <p class="text-muted">
        Nenhuma prova disponível para você no momento.
    </p>

<?php endif; ?>

<?php foreach ($provas as $p): ?>

<?php

$stStatus = $pdo->prepare("
    SELECT status
    FROM historico_pontos
    WHERE usuario_id = ?
      AND prova_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stStatus->execute([
    $user_id,
    $p['id']
]);

$statusAtual = $stStatus->fetch(PDO::FETCH_ASSOC);

?>

<div class="col-md-4 mb-3">

    <div class="card h-100 shadow-sm">

        <div class="card-body">

            <h5 class="card-title">
                <?= htmlspecialchars($p['titulo']) ?>
            </h5>

            <span class="badge bg-info mb-2">
                <?= $p['tipo'] == 'global'
                    ? 'Global (Todos os Grupos)'
                    : 'Exclusiva do Grupo' ?>
            </span>

            <p class="card-text text-muted small">
                <?= htmlspecialchars($p['descricao']) ?>
            </p>

            <p class="mb-1">
                🏅 <strong>Valor:</strong>
                <?= $p['pontos'] ?> Skills
            </p>

            <p class="small text-danger">
                ⏱ <strong>Prazo final:</strong>
                <?= date('d/m/Y', strtotime($p['data_fim'])) ?>
            </p>

        </div>

        <div class="card-footer bg-white border-top-0">

            <?php if ($statusAtual): ?>

                <?php if ($statusAtual['status'] == 'pendente'): ?>

                    <div class="alert alert-warning mb-0">
                        ⏳ Evidência enviada e aguardando análise.
                    </div>

                <?php elseif ($statusAtual['status'] == 'aprovado'): ?>

                    <div class="alert alert-success mb-0">
                        ✅ Prova concluída com sucesso.
                    </div>

                <?php elseif ($statusAtual['status'] == 'rejeitado'): ?>

                    <div class="alert alert-danger">
                        ❌ Evidência recusada.
                        Envie uma nova tentativa.
                    </div>

                    <form method="POST" enctype="multipart/form-data">

                        <input
                            type="hidden"
                            name="prova_id"
                            value="<?= $p['id'] ?>">

                        <div class="mb-2">
                            <label class="form-label small">
                                Nova Evidência:
                            </label>

                            <input
                                type="file"
                                name="evidencia"
                                class="form-control form-control-sm"
                                required>
                        </div>

                        <button
                            type="submit"
                            name="enviar_prova"
                            class="btn btn-success btn-sm w-100">

                            Reenviar Evidência

                        </button>

                    </form>

                <?php endif; ?>

            <?php else: ?>

                <form method="POST" enctype="multipart/form-data">

                    <input
                        type="hidden"
                        name="prova_id"
                        value="<?= $p['id'] ?>">

                    <div class="mb-2">

                        <label class="form-label small">
                            Subir Evidência:
                        </label>

                        <input
                            type="file"
                            name="evidencia"
                            class="form-control form-control-sm"
                            required>

                    </div>

                    <button
                        type="submit"
                        name="enviar_prova"
                        class="btn btn-success btn-sm w-100">

                        Enviar para Análise

                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php endforeach; ?>

</div>

</body>
</html>