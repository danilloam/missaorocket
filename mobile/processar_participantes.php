<?php
/**
 * processar_participantes.php
 * Missão Rocket
 *
 * Processamento administrativo:
 * - Reset de senha
 * - Atualização cadastral
 */

declare(strict_types=1);

require_once '../config.php';

date_default_timezone_set('America/Recife');

/*
|--------------------------------------------------------------------------
| FUNÇÃO DE REDIRECIONAMENTO
|--------------------------------------------------------------------------
*/
function voltar(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/*
|--------------------------------------------------------------------------
| SOMENTE POST
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltar('gerenciar_participantes.php?erro=acao_invalida');
}

/*
|--------------------------------------------------------------------------
| DADOS RECEBIDOS
|--------------------------------------------------------------------------
*/
$id = filter_input(
    INPUT_POST,
    'usuario_id',
    FILTER_VALIDATE_INT
);

$acao = trim((string)($_POST['acao'] ?? ''));

if (!$id || $id <= 0) {
    voltar('gerenciar_participantes.php?erro=usuario_invalido');
}

/*
|--------------------------------------------------------------------------
| RESET DE SENHA
|--------------------------------------------------------------------------
*/
if ($acao === 'resetar_senha') {

    try {

        /*
        |--------------------------------------------------------------
        | Localiza o usuário antes de alterar
        |--------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT id, nome
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            voltar('gerenciar_participantes.php?erro=usuario_nao_encontrado');
        }

        /*
        |--------------------------------------------------------------
        | Senha padrão
        |--------------------------------------------------------------
        */
        $senhaPadrao = '123456';

        $hash = password_hash(
            $senhaPadrao,
            PASSWORD_DEFAULT
        );

        if ($hash === false) {
            throw new RuntimeException(
                'Não foi possível gerar o hash da senha.'
            );
        }

        /*
        |--------------------------------------------------------------
        | Atualiza senha
        |--------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET senha = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $hash,
            $id
        ]);

        /*
        |--------------------------------------------------------------
        | Confirma se a senha realmente foi gravada
        |--------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT senha
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        $usuarioAtualizado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioAtualizado) {
            voltar('gerenciar_participantes.php?erro=usuario_nao_encontrado');
        }

        /*
        |--------------------------------------------------------------
        | Teste interno do hash
        |--------------------------------------------------------------
        */
        if (
            empty($usuarioAtualizado['senha']) ||
            !password_verify(
                $senhaPadrao,
                $usuarioAtualizado['senha']
            )
        ) {
            throw new RuntimeException(
                'A senha foi gravada, mas não pôde ser validada.'
            );
        }

        /*
        |--------------------------------------------------------------
        | SUCESSO
        |--------------------------------------------------------------
        */
        voltar(
            'gerenciar_participantes.php?sucesso=senha_resetada'
        );

    } catch (Throwable $e) {

        error_log(
            '[Missão Rocket] Erro no reset de senha. ' .
            'Usuario ID: ' . $id .
            ' | Erro: ' . $e->getMessage()
        );

        /*
        |--------------------------------------------------------------
        | Em desenvolvimento, podemos retornar a mensagem.
        | Em produção, mantemos mensagem amigável.
        |--------------------------------------------------------------
        */
        voltar(
            'gerenciar_participantes.php?erro=reset_senha'
        );
    }
}

/*
|--------------------------------------------------------------------------
| ATUALIZAÇÃO CADASTRAL
|--------------------------------------------------------------------------
*/
if ($acao === 'atualizar') {

    $nome = trim(
        (string)($_POST['nome'] ?? '')
    );

    $email = trim(
        (string)($_POST['email'] ?? '')
    );

    $nivel = trim(
        (string)($_POST['tipo_acesso'] ?? '')
    );

    /*
    |--------------------------------------------------------------
    | Valida nome
    |--------------------------------------------------------------
    */
    if ($nome === '') {
        voltar(
            'gerenciar_participantes.php?erro=nome_obrigatorio'
        );
    }

    /*
    |--------------------------------------------------------------
    | Valida e-mail
    |--------------------------------------------------------------
    */
    if (
        $email === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        voltar(
            'gerenciar_participantes.php?erro=email_invalido'
        );
    }

    /*
    |--------------------------------------------------------------
    | Valida nível
    |--------------------------------------------------------------
    */
    $niveisPermitidos = [
        'admin',
        'participante'
    ];

    if (!in_array($nivel, $niveisPermitidos, true)) {
        voltar(
            'gerenciar_participantes.php?erro=nivel_invalido'
        );
    }

    /*
    |--------------------------------------------------------------
    | Grupo
    |
    | Administrador pode ficar sem grupo.
    | Portanto grupo_id pode ser NULL.
    |--------------------------------------------------------------
    */
    $grupo_id = null;

    if (
        isset($_POST['grupo']) &&
        $_POST['grupo'] !== '' &&
        $_POST['grupo'] !== null
    ) {

        $grupo_id = filter_var(
            $_POST['grupo'],
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1
                ]
            ]
        );

        if ($grupo_id === false) {
            $grupo_id = null;
        }
    }

    try {

        /*
        |--------------------------------------------------------------
        | Confirma usuário
        |--------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            voltar(
                'gerenciar_participantes.php?erro=usuario_nao_encontrado'
            );
        }

        /*
        |--------------------------------------------------------------
        | Atualiza cadastro
        |--------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET
                nome = ?,
                email = ?,
                grupo_id = ?,
                nivel = ?
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $nome,
            $email,
            $grupo_id,
            $nivel,
            $id
        ]);

        /*
        |--------------------------------------------------------------
        | SUCESSO
        |--------------------------------------------------------------
        */
        voltar(
            'gerenciar_participantes.php?sucesso=atualizado'
        );

    } catch (Throwable $e) {

        error_log(
            '[Missão Rocket] Erro ao atualizar participante. ' .
            'Usuario ID: ' . $id .
            ' | Erro: ' . $e->getMessage()
        );

        voltar(
            'gerenciar_participantes.php?erro=atualizacao'
        );
    }
}

/*
|--------------------------------------------------------------------------
| AÇÃO DESCONHECIDA
|--------------------------------------------------------------------------
*/
voltar(
    'gerenciar_participantes.php?erro=acao_invalida'
);