<?php

require_once '../config.php';
exigirAdministrador($pdo);
require_once 'header.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Recife');

/*
|--------------------------------------------------------------------------
| MENSAGENS DE RETORNO
|--------------------------------------------------------------------------
*/
$sucesso = trim($_GET['sucesso'] ?? '');
$erro    = trim($_GET['erro'] ?? '');
$feedback = '';
/*
|--------------------------------------------------------------------------
| CARREGAR USUÁRIOS
|--------------------------------------------------------------------------
*/
$usuarios = [];
$erroBanco = '';

try {

    $sql = "
        SELECT
            u.id,
            u.grupo_id,
            u.nome AS usuario_nome,
            u.email,
            u.nivel,
            g.nome AS grupo_nome
        FROM usuarios u
        LEFT JOIN grupos g ON u.grupo_id = g.id
        ORDER BY u.nome ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $erroBanco = 'Não foi possível carregar os participantes.';

    error_log(
        '[Missão Rocket] Erro ao carregar participantes: ' .
        $e->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| CARREGAR GRUPOS
|--------------------------------------------------------------------------
*/
$grupos = [];

try {

    $stmtGrupos = $pdo->query("
        SELECT id, nome
        FROM grupos
        ORDER BY nome ASC
    ");

    $grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        '[Missão Rocket] Erro ao carregar grupos: ' .
        $e->getMessage()
    );
}


if ($_SERVER['REQUEST_METHOD'] == 'POST') {

   
    if (isset($_POST['cadastrar_usuario'])) {

        $nome = trim($_POST['nome']);
        $email = strtolower(trim($_POST['email']));
        $senha_pura = trim($_POST['senha']);
        $senha_hash = password_hash($senha_pura, PASSWORD_DEFAULT);
        $perfil = $_POST['perfil'];
        $grupo = empty($_POST['grupo_id']) ? null : $_POST['grupo_id'];

        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, grupo_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $senha_hash, $perfil, $grupo]);

        $para = $email;
        $assunto = "=?UTF-8?B?" . base64_encode("Foguete Lançado! Seu acesso à Missão Rocket") . "?=";

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

                <p style='color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-top: 0;'>
                    Seu cadastro foi realizado com sucesso pelo administrador. Prepare-se para entrar na Arena de Performance!
                </p>

                <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin: 20px 0;'>
                    <p style='margin: 0 0 8px 0; font-size: 0.85rem; color: #64748b;'>
                        <strong>Seus Dados de Acesso:</strong>
                    </p>

                    <p style='margin: 0 0 6px 0; font-size: 0.9rem;'>
                        <strong>E-mail:</strong> <span style='color: #8b5cf6;'>{$email}</span>
                    </p>

                    <p style='margin: 0; font-size: 0.9rem;'>
                        <strong>Senha Provisória:</strong>
                        <span style='font-family: monospace; font-weight: bold; background: #fff; padding: 2px 6px; border: 1px solid #cbd5e1; border-radius: 4px;'>{$senha_pura}</span>
                    </p>
                </div>

                <p style='color: #ef4444; font-size: 0.8rem; font-weight: bold; text-align: center;'>
                    ⚠️ Nota: Por segurança, altere sua senha no primeiro login.
                </p>

                <div style='text-align: center; margin-top: 25px;'>
                    <a href='https://missaorocket.com.br/mobile/' style='background-color: #8b5cf6; color: white; text-decoration: none; padding: 12px 30px; font-weight: bold; border-radius: 12px; display: inline-block; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);'>
                        Entrar na Arena
                    </a>
                </div>
            </div>
        </body>
        </html>
        ";

        $headers  = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        $headers .= "From: Missão Rocket <no-reply@missaorocket.com.br>" . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        @mail($para, $assunto, $mensagemHTML, $headers);

        $feedback = "<div class='alert alert-success py-2 small text-center'>Usuário criado e e-mail de instruções enviado!</div>";
    }

   
}

?>


    <script src="https://cdn.tailwindcss.com"></script>


<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <!-- ==========================================================
         CABEÇALHO
    =========================================================== -->
    <style>
    body { background-color: #f8fafc !important; color: #1e293b; }
    .main-wrapper { max-width: 500px; margin: 0 auto; padding: 1.5rem 1rem 5rem 1rem; }
    .task-admin-card { background: #ffffff; border-radius: 16px; padding: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.01); display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .search-input-group { position: relative; }
    .search-input-group i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
    .search-input-group input { width: 100%; padding: 0.5rem 1rem 0.5rem 2.2rem; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.9rem; outline: none; }
</style>

<div class="main-wrapper">
    <div class="d-flex align-items-center mb-3">
        <a href="gerenciar.php" class="btn btn-sm btn-outline-secondary rounded-3 me-3"><i class="fa-solid fa-arrow-left"></i></a>
      
    </div>
    
    <div class="mb-6 border-b border-gray-200 pb-4">

        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 tracking-tight">
            Gerenciar Participantes
        </h1>

        <p class="text-xs sm:text-sm text-gray-500 mt-1">
            Altere dados, grupos, permissões e resete senhas.
        </p>

    </div>

 <?= $feedback ?>
    <!-- ==========================================================
         MENSAGENS DE SUCESSO
    =========================================================== -->

    <?php if ($sucesso === 'senha_resetada'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm"
            role="alert"
        >

            <div class="flex items-start gap-3">

                <div class="flex-shrink-0">

                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100">

                        <svg
                            class="h-5 w-5 text-green-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>

                    </div>

                </div>

                <div class="flex-1">

                    <p class="text-sm font-bold text-green-800">
                        Senha redefinida com sucesso!
                    </p>

                    <p class="mt-1 text-sm text-green-700">
                        A senha do participante foi alterada para
                        <span class="font-mono font-bold bg-white px-2 py-0.5 rounded border border-green-200">
                            123456
                        </span>
                    </p>

                    <p class="mt-1 text-xs text-green-600">
                        O participante já pode utilizar a nova senha para acessar o sistema.
                    </p>

                </div>

                <button
                    type="button"
                    onclick="fecharAlerta()"
                    class="text-green-500 hover:text-green-700 text-xl leading-none"
                    aria-label="Fechar"
                >
                    &times;
                </button>

            </div>

        </div>

    <?php elseif ($sucesso === 'atualizado'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 shadow-sm"
            role="alert"
        >

            <div class="flex items-start gap-3">

                <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-100 flex-shrink-0">

                    <svg
                        class="h-5 w-5 text-green-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M5 13l4 4L19 7"
                        />
                    </svg>

                </div>

                <div class="flex-1">

                    <p class="text-sm font-bold text-green-800">
                        Participante atualizado com sucesso!
                    </p>

                    <p class="mt-1 text-sm text-green-700">
                        Os dados cadastrais foram salvos corretamente.
                    </p>

                </div>

                <button
                    type="button"
                    onclick="fecharAlerta()"
                    class="text-green-500 hover:text-green-700 text-xl leading-none"
                    aria-label="Fechar"
                >
                    &times;
                </button>

            </div>

        </div>

    <?php endif; ?>


    <!-- ==========================================================
         MENSAGENS DE ERRO
    =========================================================== -->

    <?php if ($erro === 'usuario_invalido'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                Não foi possível processar o participante.
            </p>

            <p class="mt-1 text-sm text-red-700">
                O usuário informado é inválido ou não foi identificado.
            </p>

        </div>

    <?php elseif ($erro === 'usuario_nao_encontrado'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                Participante não encontrado.
            </p>

            <p class="mt-1 text-sm text-red-700">
                O usuário selecionado não existe mais no sistema.
            </p>

        </div>

    <?php elseif ($erro === 'reset_senha'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                Não foi possível redefinir a senha.
            </p>

            <p class="mt-1 text-sm text-red-700">
                Ocorreu um erro durante a atualização da senha.
                Verifique os logs do sistema e tente novamente.
            </p>

        </div>

    <?php elseif ($erro === 'atualizacao'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                Não foi possível atualizar o participante.
            </p>

            <p class="mt-1 text-sm text-red-700">
                Os dados não foram alterados.
            </p>

        </div>

    <?php elseif ($erro === 'nome_obrigatorio'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                Nome obrigatório.
            </p>

            <p class="mt-1 text-sm text-red-700">
                Informe o nome completo do participante.
            </p>

        </div>

    <?php elseif ($erro === 'email_invalido'): ?>

        <div
            id="alertaResultado"
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                E-mail inválido.
            </p>

            <p class="mt-1 text-sm text-red-700">
                Informe um endereço de e-mail válido.
            </p>

        </div>

    <?php endif; ?>


    <!-- ==========================================================
         ERRO DE BANCO
    =========================================================== -->

    <?php if ($erroBanco !== ''): ?>

        <div
            class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm"
            role="alert"
        >

            <p class="text-sm font-bold text-red-800">
                <?= htmlspecialchars($erroBanco, ENT_QUOTES, 'UTF-8') ?>
            </p>

        </div>

    <?php endif; ?>

<div class="col-12 col-lg-6">

                        <!-- Participante -->
                        <div class="form-section p-3 shadow-sm mb-3">

                            <div class="d-flex align-items-center gap-2 mb-3">
                                <div class="rounded-3 bg-info-subtle text-info d-flex align-items-center justify-content-center"
                                     style="width:38px;height:38px;">
                                    <i class="fa-solid fa-user-plus"></i>
                                </div>

                                <div>
                                    <h6 class="fw-bold mb-0 text-info">Criar Participante</h6>
                                    <div class="small text-muted">Cadastre o acesso de um novo comandante.</div>
                                </div>
                            </div>

                            <form method="POST" class="small">

                                <input type="hidden" name="cadastrar_usuario" value="1">

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Nome Completo</label>
                                    <input type="text"
                                           name="nome"
                                           class="form-control form-control-sm"
                                           placeholder="Nome Completo"
                                           required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">E-mail</label>
                                    <input type="email"
                                           name="email"
                                           class="form-control form-control-sm"
                                           placeholder="E-mail de Login"
                                           required>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Senha Inicial</label>
                                    <input type="password"
                                           name="senha"
                                           class="form-control form-control-sm"
                                           placeholder="Senha Inicial"
                                           required>
                                </div>

                                <div class="row g-2 mb-2">

                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Perfil</label>
                                        <select name="perfil" class="form-select form-select-sm">
                                            <option value="user">User</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>

                                    <div class="col-6">
                                        <label class="form-label small fw-bold">Clã</label>
                                        <select name="grupo_id" class="form-select form-select-sm">
                                            <option value="">Nenhum Clã</option>

                                            <?php foreach ($grupos as $g): ?>
                                                <option value="<?= $g['id'] ?>">
                                                    <?= htmlspecialchars($g['nome']) ?>
                                                </option>
                                            <?php endforeach; ?>

                                        </select>
                                    </div>

                                </div>

                                <button type="submit"
                                        class="btn btn-info btn-sm text-white w-100 fw-bold py-2 rounded-3">
                                    <i class="fa-solid fa-user-plus me-1"></i>
                                    Registar Usuário
                                </button>

                            </form>

                        </div>

                    </div>
    <!-- ==========================================================
         TABELA DESKTOP
    =========================================================== -->

    <div class="hidden md:block bg-white shadow rounded-xl overflow-hidden border border-gray-200">

        <table class="min-w-full divide-y divide-gray-200">

            <thead class="bg-gray-50">

                <tr>

                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        Nome
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        E-mail
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        Grupo
                    </th>

                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        Acesso
                    </th>

                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        Ações
                    </th>

                </tr>

            </thead>

            <tbody class="bg-white divide-y divide-gray-200">

            <?php if (empty($usuarios)): ?>

                <tr>

                    <td
                        colspan="5"
                        class="px-6 py-8 text-center text-sm text-gray-500"
                    >
                        Nenhum usuário encontrado.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($usuarios as $usuario): ?>

                    <?php

                    $isAdmin =
                        strtolower((string)($usuario['nivel'] ?? '')) === 'admin'
                        ||
                        (string)($usuario['nivel'] ?? '') === '1';

                    $textoAcesso = $isAdmin
                        ? 'Admin'
                        : 'Participante';

                    $classeBadge = $isAdmin
                        ? 'bg-purple-100 text-purple-800'
                        : 'bg-green-100 text-green-800';

                    $nomeDoGrupo = !empty($usuario['grupo_nome'])
                        ? $usuario['grupo_nome']
                        : 'Sem Grupo';

                    ?>

                    <tr class="hover:bg-gray-50 transition-colors">

                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($usuario['usuario_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= htmlspecialchars($nomeDoGrupo, ENT_QUOTES, 'UTF-8') ?>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm">

                            <span class="px-2.5 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $classeBadge ?>">
                                <?= $textoAcesso ?>
                            </span>

                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">

                            <button
                                type="button"
                                onclick="abrirModal(
                                    <?= (int)$usuario['id'] ?>,
                                    <?= htmlspecialchars(json_encode($usuario['usuario_nome'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($usuario['email'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode((string)($usuario['grupo_id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>,
                                    <?= htmlspecialchars(json_encode($isAdmin ? 'admin' : 'participante'), ENT_QUOTES, 'UTF-8') ?>
                                )"
                                class="text-indigo-600 hover:text-indigo-950 font-semibold"
                            >
                                Editar
                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- ==========================================================
         CARDS MOBILE
    =========================================================== -->

    <div class="grid grid-cols-1 gap-4 md:hidden">

        <?php if (empty($usuarios)): ?>

            <div class="bg-white p-6 rounded-xl border border-gray-200 text-center text-sm text-gray-500">
                Nenhum usuário encontrado.
            </div>

        <?php else: ?>

            <?php foreach ($usuarios as $usuario): ?>

                <?php

                $isAdmin =
                    strtolower((string)($usuario['nivel'] ?? '')) === 'admin'
                    ||
                    (string)($usuario['nivel'] ?? '') === '1';

                $textoAcesso = $isAdmin
                    ? 'Admin'
                    : 'Participante';

                $classeBadge = $isAdmin
                    ? 'bg-purple-100 text-purple-800'
                    : 'bg-green-100 text-green-800';

                $nomeDoGrupo = !empty($usuario['grupo_nome'])
                    ? $usuario['grupo_nome']
                    : 'Sem Grupo';

                ?>

                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200 flex flex-col justify-between">

                    <div>

                        <div class="flex justify-between items-start mb-2">

                            <h3 class="text-base font-bold text-gray-900 break-words max-w-[70%]">
                                <?= htmlspecialchars($usuario['usuario_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </h3>

                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full <?= $classeBadge ?>">
                                <?= $textoAcesso ?>
                            </span>

                        </div>

                        <p class="text-sm text-gray-600 break-all mb-3">
                            <?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </p>

                        <div class="flex items-center text-xs text-gray-500 bg-gray-50 px-3 py-1.5 rounded-md w-fit">

                            <span class="font-medium mr-1">
                                Grupo:
                            </span>

                            <?= htmlspecialchars($nomeDoGrupo, ENT_QUOTES, 'UTF-8') ?>

                        </div>

                    </div>

                    <div class="mt-4 pt-3 border-t border-gray-100">

                        <button
                            type="button"
                            onclick="abrirModal(
                                <?= (int)$usuario['id'] ?>,
                                <?= htmlspecialchars(json_encode($usuario['usuario_nome'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars(json_encode($usuario['email'] ?? '', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars(json_encode((string)($usuario['grupo_id'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars(json_encode($isAdmin ? 'admin' : 'participante'), ENT_QUOTES, 'UTF-8') ?>
                            )"
                            class="w-full text-center bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-200 text-indigo-600 font-semibold py-2 rounded-lg text-sm transition-all"
                        >
                            Editar Cadastro
                        </button>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>


<!-- ==============================================================
     MODAL EDITAR
=============================================================== -->

<div
    id="modalEditar"
    class="fixed inset-0 bg-gray-900 bg-opacity-60 hidden justify-center items-end sm:items-center z-50 p-0 sm:p-4 transition-all"
>

    <div class="bg-white rounded-t-2xl sm:rounded-2xl shadow-2xl w-full max-w-md p-6 max-h-[92vh] overflow-y-auto">

        <div class="flex justify-between items-center mb-5 pb-2 border-b border-gray-100">

            <h3 class="text-lg font-bold text-gray-900">
                Editar Participante
            </h3>

            <button
                type="button"
                onclick="fecharModal()"
                class="text-gray-400 hover:text-gray-600 text-3xl p-1 leading-none"
            >
                &times;
            </button>

        </div>


        <!-- ======================================================
             FORMULÁRIO
        ======================================================= -->

        <form
            action="processar_participantes.php"
            method="POST"
            id="formEditar"
            class="space-y-4"
        >

            <input
                type="hidden"
                name="usuario_id"
                id="modal_id"
            >

            <input
                type="hidden"
                name="acao"
                id="modal_acao"
                value="atualizar"
            >


            <!-- NOME -->

            <div>

                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Nome Completo
                </label>

                <input
                    type="text"
                    name="nome"
                    id="modal_nome"
                    required
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 focus:bg-white transition-all"
                >

            </div>


            <!-- EMAIL -->

            <div>

                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    E-mail
                </label>

                <input
                    type="email"
                    name="email"
                    id="modal_email"
                    required
                    class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-gray-50 focus:bg-white transition-all"
                >

            </div>


            <!-- GRUPO -->

            <div>

                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Grupo / Setor
                </label>

                <select
                    name="grupo"
                    id="modal_grupo"
                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 focus:bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                >

                    <option value="">
                        Nenhum (Acesso Admin / Sem Grupo)
                    </option>

                    <?php foreach ($grupos as $grupoItem): ?>

                        <option value="<?= (int)$grupoItem['id'] ?>">
                            <?= htmlspecialchars($grupoItem['nome'], ENT_QUOTES, 'UTF-8') ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TIPO DE ACESSO -->

            <div>

                <label class="block text-xs font-semibold text-gray-700 uppercase tracking-wider mb-1">
                    Tipo de Acesso
                </label>

                <select
                    name="tipo_acesso"
                    id="modal_acesso"
                    class="w-full px-3 py-2.5 border border-gray-300 bg-gray-50 focus:bg-white rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                >

                    <option value="participante">
                        Participante
                    </option>

                    <option value="admin">
                        Admin
                    </option>

                </select>

            </div>


            <!-- ==================================================
                 RESET DE SENHA
            =================================================== -->

            <div class="p-4 bg-orange-50 border border-orange-200 rounded-xl">

                <div class="flex items-center justify-between gap-4">

                    <div class="flex-1">

                        <p class="text-xs font-bold text-orange-800 uppercase tracking-wider">
                            Acesso Master
                        </p>

                        <p class="text-xs text-orange-600 mt-0.5">

                            Reseta para:

                            <span class="font-mono font-bold bg-white px-1.5 py-0.5 rounded border border-orange-200 text-sm">
                                123456
                            </span>

                        </p>

                    </div>

                    <button
                        type="button"
                        onclick="resetarSenha()"
                        class="whitespace-nowrap px-3 py-2 bg-orange-600 hover:bg-orange-700 active:scale-95 text-white text-xs font-semibold rounded-lg shadow-sm transition-all"
                    >
                        Resetar Senha
                    </button>

                </div>

            </div>


            <!-- ==================================================
                 BOTÕES
            =================================================== -->

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2 border-t border-gray-100">

                <button
                    type="button"
                    onclick="fecharModal()"
                    class="w-full sm:w-auto px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-semibold rounded-lg transition-all text-center"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-lg shadow-md transition-all text-center"
                >
                    Salvar Alterações
                </button>

            </div>

        </form>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| ABRIR MODAL
|--------------------------------------------------------------------------
*/
function abrirModal(id, nome, email, grupo, acesso) {

    document.getElementById('modal_id').value = id;
    document.getElementById('modal_nome').value = nome;
    document.getElementById('modal_email').value = email;
    document.getElementById('modal_grupo').value = grupo || '';
    document.getElementById('modal_acesso').value = acesso;
    document.getElementById('modal_acao').value = 'atualizar';

    const modal = document.getElementById('modalEditar');

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.body.style.overflow = 'hidden';
}


/*
|--------------------------------------------------------------------------
| FECHAR MODAL
|--------------------------------------------------------------------------
*/
function fecharModal() {

    const modal = document.getElementById('modalEditar');

    modal.classList.remove('flex');
    modal.classList.add('hidden');

    document.body.style.overflow = '';
}


/*
|--------------------------------------------------------------------------
| RESETAR SENHA
|--------------------------------------------------------------------------
*/
function resetarSenha() {

    const id = document.getElementById('modal_id').value;
    const nome = document.getElementById('modal_nome').value;

    if (!id) {

        alert('Usuário não identificado.');

        return;
    }

    const mensagem =
        "ATENÇÃO!\n\n" +
        "Você está prestes a redefinir a senha de:\n\n" +
        nome +
        "\n\n" +
        "A nova senha será:\n" +
        "123456\n\n" +
        "Deseja realmente continuar?";

    if (!confirm(mensagem)) {
        return;
    }

    /*
    |------------------------------------------------------------------
    | Define a ação como reset de senha
    |------------------------------------------------------------------
    */
    document.getElementById('modal_acao').value = 'resetar_senha';

    /*
    |------------------------------------------------------------------
    | Envia para o arquivo CORRETO:
    | processar_participantes.php
    |------------------------------------------------------------------
    */
    document.getElementById('formEditar').submit();
}


/*
|--------------------------------------------------------------------------
| FECHAR ALERTA
|--------------------------------------------------------------------------
*/
function fecharAlerta() {

    const alerta = document.getElementById('alertaResultado');

    if (alerta) {
        alerta.remove();
    }
}


/*
|--------------------------------------------------------------------------
| FECHAR MODAL CLICANDO FORA
|--------------------------------------------------------------------------
*/
document.getElementById('modalEditar').addEventListener('click', function(event) {

    if (event.target === this) {
        fecharModal();
    }

});


/*
|--------------------------------------------------------------------------
| ESC FECHA MODAL
|--------------------------------------------------------------------------
*/
document.addEventListener('keydown', function(event) {

    if (event.key === 'Escape') {
        fecharModal();
    }

});

</script>

</body>
</html>