<?php

require_once 'config.php';

/*
|--------------------------------------------------------------------------
| LOGOUT COMPLETO
|--------------------------------------------------------------------------
|
| Encerra:
| - sessão PHP
| - token persistente do dispositivo
| - cookie rocket_device
|
*/

/*
|--------------------------------------------------------------------------
| INVALIDA O LOGIN PERSISTENTE
|--------------------------------------------------------------------------
*/

try {

    if (!empty($_COOKIE[REMEMBER_COOKIE])) {

        $token = $_COOKIE[REMEMBER_COOKIE];

        /*
         * O token deve possuir exatamente 64 caracteres hexadecimais.
         */
        if (preg_match('/^[a-f0-9]{64}$/i', $token)) {

            $tokenHash = hash('sha256', $token);

            $stmt = $pdo->prepare("
                UPDATE usuarios_sessoes
                SET ativo = 0
                WHERE token_hash = ?
                LIMIT 1
            ");

            $stmt->execute([
                $tokenHash
            ]);
        }
    }

} catch (Throwable $e) {

    /*
     * Mesmo que ocorra erro no banco,
     * o logout da sessão continuará.
     */
    error_log(
        'ERRO LOGOUT MISSÃO ROCKET: ' .
        $e->getMessage()
    );
}


/*
|--------------------------------------------------------------------------
| APAGA COOKIE rocket_device
|--------------------------------------------------------------------------
*/

setcookie(
    REMEMBER_COOKIE,
    '',
    [
        'expires'  => time() - 3600,
        'path'     => '/mobile',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]
);


/*
|--------------------------------------------------------------------------
| LIMPA VARIÁVEIS DA SESSÃO
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| APAGA COOKIE DA SESSÃO PHP
|--------------------------------------------------------------------------
*/

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}


/*
|--------------------------------------------------------------------------
| DESTROI A SESSÃO
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}


/*
|--------------------------------------------------------------------------
| VOLTA PARA O LOGIN
|--------------------------------------------------------------------------
*/

header('Location: index.php');
exit;
?>