```php
<?php
/**
 * index.php
 * Missão: ROCKET
 *
 * Login:
 * - Autenticação tradicional por e-mail e senha.
 * - Após login válido, cria autenticação persistente do dispositivo.
 * - Nas próximas visitas, tenta autenticar automaticamente pelo dispositivo.
 */

require_once 'config.php';

/*
|--------------------------------------------------------------------------
| AUTENTICAÇÃO AUTOMÁTICA
|--------------------------------------------------------------------------
| Se o usuário ainda não possui uma sessão PHP válida, tenta recuperar
| a autenticação através do token persistente armazenado no dispositivo.
|--------------------------------------------------------------------------
*/
if (
    !isset($_SESSION['user_id']) ||
    (int)$_SESSION['user_id'] <= 0
) {
    if (function_exists('autenticarPorDispositivo')) {
        if (autenticarPorDispositivo($pdo)) {
            header('Location: dashboard.php');
            exit;
        }
    }
}

/*
|--------------------------------------------------------------------------
| SE JÁ ESTÁ AUTENTICADO
|--------------------------------------------------------------------------
*/
if (
    isset($_SESSION['user_id']) &&
    (int)$_SESSION['user_id'] > 0
) {
    header('Location: dashboard.php');
    exit;
}

$erro = '';

/*
|--------------------------------------------------------------------------
| LOGIN MANUAL
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = isset($_POST['email'])
        ? strtolower(trim($_POST['email']))
        : '';

    $senha = isset($_POST['senha'])
        ? $_POST['senha']
        : '';

    /*
    |--------------------------------------------------------------------------
    | VALIDAÇÃO BÁSICA
    |--------------------------------------------------------------------------
    */
    if ($email === '' || $senha === '') {

        $erro = 'Por favor, preencha todos os campos.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = 'Informe um endereço de e-mail válido.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | BUSCA USUÁRIO
        |--------------------------------------------------------------------------
        */
        $stmt = $pdo->prepare("
            SELECT
                id,
                grupo_id,
                nome,
                email,
                senha,
                perfil,
                nivel
            FROM usuarios
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | USUÁRIO ENCONTRADO
        |--------------------------------------------------------------------------
        */
        if ($user) {

            /*
            |--------------------------------------------------------------------------
            | VALIDAÇÃO REAL DA SENHA
            |--------------------------------------------------------------------------
            */
            if (password_verify($senha, $user['senha'])) {

                /*
                |--------------------------------------------------------------------------
                | AUTENTICAÇÃO DA SESSÃO
                |--------------------------------------------------------------------------
                |
                | Preferimos utilizar a função centralizada, caso exista no
                | config.php, evitando duplicação da lógica de sessão.
                |--------------------------------------------------------------------------
                */
                if (function_exists('autenticarUsuarioSessao')) {

                    autenticarUsuarioSessao($user);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | FALLBACK
                    |--------------------------------------------------------------------------
                    | Mantém compatibilidade caso a função ainda não tenha sido
                    | criada no config.php.
                    |--------------------------------------------------------------------------
                    */
                    session_regenerate_id(true);

                    $_SESSION['user_id']      = (int)$user['id'];
                    $_SESSION['grupo_id']     = $user['grupo_id'];
                    $_SESSION['nome']         = $user['nome'];
                    $_SESSION['email']        = $user['email'];
                    $_SESSION['perfil']       = $user['perfil'];
                    $_SESSION['nivel']        = $user['nivel'];
                    $_SESSION['autenticado']  = true;
                }

                /*
                |--------------------------------------------------------------------------
                | CRIA AUTENTICAÇÃO PERSISTENTE
                |--------------------------------------------------------------------------
                |
                | Esse é o ponto fundamental.
                |
                | O usuário acabou de provar sua identidade através da senha.
                | Portanto, podemos criar um token seguro para este dispositivo.
                |--------------------------------------------------------------------------
                */
                if (function_exists('criarTokenDispositivo')) {

                    criarTokenDispositivo(
                        $pdo,
                        (int)$user['id']
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | REDIRECIONAMENTO
                |--------------------------------------------------------------------------
                */
                header('Location: dashboard.php');
                exit;

            } else {

                $erro = 'Senha incorreta para este usuário.';
            }

        } else {

            $erro = 'E-mail não encontrado no sistema.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="theme-color"
        content="#212529"
    >

    <title>Login - Missão: ROCKET</title>

    <link
        rel="manifest"
        href="/manifest.json"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

</head>

<body
    class="bg-secondary d-flex align-items-center"
    style="min-height:100vh;"
>

<div class="container">

    <div
        class="card p-4 shadow mx-auto"
        style="max-width:400px; background:#fff;"
    >

        <div class="text-center mb-4">

            <div
                class="mb-2"
                style="font-size:42px;"
            >
                🚀
            </div>

            <h3 class="mb-1">
                Missão: ROCKET
            </h3>

            <small class="text-muted">
                Arena de Performance
            </small>

        </div>

        <?php if (!empty($erro)): ?>

            <div
                class="alert alert-danger text-center py-2"
                role="alert"
            >
                <?= htmlspecialchars(
                    $erro,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form
            method="POST"
            action="index.php"
            autocomplete="on"
        >

            <div class="mb-3">

                <label
                    for="email"
                    class="form-label"
                >
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    class="form-control"
                    placeholder="Digite seu e-mail"
                    autocomplete="username"
                    required
                    autofocus
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

            </div>

            <div class="mb-3">

                <label
                    for="senha"
                    class="form-label"
                >
                    Senha
                </label>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    class="form-control"
                    placeholder="Digite sua senha"
                    autocomplete="current-password"
                    required
                >

            </div>

            <button
                type="submit"
                class="btn btn-primary w-100"
            >
                <i class="fa-solid fa-right-to-bracket"></i>
                Entrar
            </button>

        </form>

        <div class="text-center mt-3">

            <small class="text-muted">
                Após o primeiro acesso, este dispositivo poderá ser
                reconhecido automaticamente.
            </small>

        </div>

    </div>

</div>

<script>
if ('serviceWorker' in navigator) {

    window.addEventListener('load', () => {

        navigator.serviceWorker.register('/sw.js')
            .then((registration) => {

                console.log(
                    'Service Worker registrado com sucesso:',
                    registration.scope
                );

            })
            .catch((error) => {

                console.error(
                    'Erro ao registrar Service Worker:',
                    error
                );

            });

    });

}
</script>

</body>
</html>
```

**Mas há uma condição:** o `config.php` precisa realmente conter as funções `autenticarPorDispositivo()`, `autenticarUsuarioSessao()` e `criarTokenDispositivo()` e a tabela `usuarios_sessoes` precisa existir.

E há uma questão importante sobre o que você pediu anteriormente: **os usuários que já configuraram notificações antes dessa alteração não ganharão automaticamente o acesso apenas por terem uma inscrição de notificação existente**. Isso seria inseguro, porque uma inscrição push não deve ser usada como senha.

O ideal para o seu sistema é aproveitar o momento em que o usuário já estiver autenticado para criar o token persistente. Assim, depois do primeiro login com senha, aquele navegador/dispositivo passa a entrar diretamente.

Se você me enviar agora o seu **`config.php` atual**, eu consigo encaixar essas funções nele sem correr o risco de conflitar com o seu `$pdo`, sessão ou outras funções existentes.
