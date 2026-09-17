<?php

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÕES GERAIS
|--------------------------------------------------------------------------
*/

date_default_timezone_set('America/Recife');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| COOKIE DE AUTENTICAÇÃO PERSISTENTE
|--------------------------------------------------------------------------
*/

define('REMEMBER_COOKIE', 'rocket_device');

/*
 * Quantidade de dias que o dispositivo permanece autenticado.
 */
define('REMEMBER_DIAS', 90);

/*
 * Quantidade máxima de dispositivos simultaneamente ativos por usuário.
 */
define('REMEMBER_MAX_DISPOSITIVOS', 5);

/*
|--------------------------------------------------------------------------
| BANCO DE DADOS
|--------------------------------------------------------------------------
*/

$host = 'localhost';
$db   = 'missaorocket';
$user = 'missaorocket';
$pass = 'password';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        $options
    );

} catch (PDOException $e) {

    /*
     * Em produção, evite exibir detalhes do banco para o usuário.
     * O erro continua sendo lançado para ser registrado pelo servidor.
     */
    throw new PDOException(
        $e->getMessage(),
        (int)$e->getCode(),
        $e
    );
}


/*
|--------------------------------------------------------------------------
| ATUALIZA NÍVEL DO USUÁRIO
|--------------------------------------------------------------------------
*/

function atualizarNivelUsuario(PDO $pdo, int $usuario_id): void
{
    if ($usuario_id <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(pontos), 0) AS total
        FROM historico_pontos
        WHERE usuario_id = ?
          AND status = 'aprovado'
    ");

    $stmt->execute([
        $usuario_id
    ]);

    $resultado = $stmt->fetch();

    $total = (int)($resultado['total'] ?? 0);

    $nivel = 'Bronze';

    if ($total >= 100) {
        $nivel = 'Diamante';

    } elseif ($total >= 60) {
        $nivel = 'Ouro';

    } elseif ($total >= 30) {
        $nivel = 'Prata';
    }

    $stmtUpdate = $pdo->prepare("
        UPDATE usuarios
        SET nivel = ?
        WHERE id = ?
    ");

    $stmtUpdate->execute([
        $nivel,
        $usuario_id
    ]);
}


/*
|--------------------------------------------------------------------------
| AUTENTICAÇÃO DA SESSÃO PHP
|--------------------------------------------------------------------------
*/

function autenticarUsuarioSessao(array $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    /*
     * Evita Session Fixation.
     */
    session_regenerate_id(true);

    $_SESSION['user_id'] = (int)($user['id'] ?? 0);

    $_SESSION['grupo_id'] =
        isset($user['grupo_id']) && $user['grupo_id'] !== ''
            ? $user['grupo_id']
            : null;

    $_SESSION['nome'] =
        $user['nome'] ?? '';

    $_SESSION['email'] =
        $user['email'] ?? '';

    $_SESSION['perfil'] =
        $user['perfil'] ?? '';

    $_SESSION['nivel'] =
        $user['nivel'] ?? 'Bronze';
    $_SESSION['foto'] =
    $user['foto'] ?? '';
    $_SESSION['autenticado'] = true;
    

}


/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO DO COOKIE
|--------------------------------------------------------------------------
*/

function configurarCookieDispositivo(string $token, int $expires): void
{
    $secure = (
        !empty($_SERVER['HTTPS']) &&
        strtolower((string)$_SERVER['HTTPS']) !== 'off'
    );

    setcookie(
        REMEMBER_COOKIE,
        $token,
        [
            'expires'  => $expires,
            'path'     => '/mobile',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}


/*
|--------------------------------------------------------------------------
| REMOVE COOKIE DO DISPOSITIVO
|--------------------------------------------------------------------------
*/

function removerCookieDispositivo(): void
{
    $secure = (
        !empty($_SERVER['HTTPS']) &&
        strtolower((string)$_SERVER['HTTPS']) !== 'off'
    );

    setcookie(
        REMEMBER_COOKIE,
        '',
        [
            'expires'  => time() - 3600,
            'path'     => '/mobile',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    /*
     * Remove também da memória desta requisição.
     */
    unset($_COOKIE[REMEMBER_COOKIE]);
}


/*
|--------------------------------------------------------------------------
| CRIA TOKEN PERSISTENTE DO DISPOSITIVO
|--------------------------------------------------------------------------
*/

function criarTokenDispositivo(PDO $pdo, int $usuarioId): void
{
    if ($usuarioId <= 0) {
        return;
    }

    /*
     * Token criptograficamente seguro.
     *
     * 32 bytes = 256 bits de entropia.
     */
    $token = bin2hex(random_bytes(32));

    /*
     * Nunca armazenamos o token original no banco.
     */
    $tokenHash = hash(
        'sha256',
        $token
    );

    /*
     * Identificação apenas informativa do dispositivo.
     */
    $dispositivo = substr(
        $_SERVER['HTTP_USER_AGENT'] ?? 'Dispositivo desconhecido',
        0,
        255
    );

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $expiraEm = date(
        'Y-m-d H:i:s',
        time() + (REMEMBER_DIAS * 86400)
    );

    /*
    |--------------------------------------------------------------------------
    | LIMPA TOKENS EXPIRADOS
    |--------------------------------------------------------------------------
    */

    $stmtLimpeza = $pdo->prepare("
        DELETE FROM usuarios_sessoes
        WHERE expira_em <= NOW()
           OR ativo = 0
    ");

    $stmtLimpeza->execute();


    /*
    |--------------------------------------------------------------------------
    | LIMITA DISPOSITIVOS ATIVOS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT id
        FROM usuarios_sessoes
        WHERE usuario_id = ?
          AND ativo = 1
        ORDER BY ultimo_acesso ASC
    ");

    $stmt->execute([
        $usuarioId
    ]);

    $sessoes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $quantidadeAtiva = count($sessoes);

    if ($quantidadeAtiva >= REMEMBER_MAX_DISPOSITIVOS) {

        $quantidadeRemover =
            $quantidadeAtiva - REMEMBER_MAX_DISPOSITIVOS + 1;

        for (
            $i = 0;
            $i < $quantidadeRemover;
            $i++
        ) {

            if (!isset($sessoes[$i])) {
                break;
            }

            $stmtExcluir = $pdo->prepare("
                DELETE FROM usuarios_sessoes
                WHERE id = ?
                LIMIT 1
            ");

            $stmtExcluir->execute([
                $sessoes[$i]
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GRAVA TOKEN
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO usuarios_sessoes
        (
            usuario_id,
            token_hash,
            dispositivo,
            ip_criacao,
            ip_ultimo_acesso,
            criado_em,
            ultimo_acesso,
            expira_em,
            ativo
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW(),
            NOW(),
            ?,
            1
        )
    ");

    $stmt->execute([
        $usuarioId,
        $tokenHash,
        $dispositivo,
        $ip,
        $ip,
        $expiraEm
    ]);


    /*
    |--------------------------------------------------------------------------
    | ENVIA TOKEN PARA O NAVEGADOR
    |--------------------------------------------------------------------------
    */

    configurarCookieDispositivo(
        $token,
        time() + (REMEMBER_DIAS * 86400)
    );
}


/*
|--------------------------------------------------------------------------
| AUTENTICA AUTOMATICAMENTE PELO DISPOSITIVO
|--------------------------------------------------------------------------
*/

function autenticarPorDispositivo(PDO $pdo): bool
{
    /*
     * Se já existe sessão válida, não precisa consultar o token.
     */
    if (
        isset($_SESSION['user_id']) &&
        (int)$_SESSION['user_id'] > 0 &&
        !empty($_SESSION['autenticado'])
    ) {
        return true;
    }

    /*
     * Não existe cookie.
     */
    if (empty($_COOKIE[REMEMBER_COOKIE])) {
        return false;
    }

    $token = (string)$_COOKIE[REMEMBER_COOKIE];


    /*
    |--------------------------------------------------------------------------
    | VALIDA FORMATO
    |--------------------------------------------------------------------------
    |
    | bin2hex(random_bytes(32)) = 64 caracteres hexadecimais.
    |
    */

    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {

        removerCookieDispositivo();

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | HASH DO TOKEN
    |--------------------------------------------------------------------------
    */

    $tokenHash = hash(
        'sha256',
        $token
    );


    /*
    |--------------------------------------------------------------------------
    | LOCALIZA SESSÃO PERSISTENTE
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            s.id AS sessao_id,
            s.usuario_id,

            u.id,
            u.grupo_id,
            u.nome,
            u.email,
            u.perfil,
            u.nivel

        FROM usuarios_sessoes s

        INNER JOIN usuarios u
            ON u.id = s.usuario_id

        WHERE s.token_hash = ?
          AND s.ativo = 1
          AND s.expira_em > NOW()

        LIMIT 1
    ");

    $stmt->execute([
        $tokenHash
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | TOKEN INVÁLIDO / EXPIRADO
    |--------------------------------------------------------------------------
    */

    if (!$user) {

        removerCookieDispositivo();

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | RECONSTRÓI A SESSÃO
    |--------------------------------------------------------------------------
    */

    autenticarUsuarioSessao($user);


    /*
    |--------------------------------------------------------------------------
    | ATUALIZA ÚLTIMO ACESSO E RENOVA TOKEN
    |--------------------------------------------------------------------------
    */

    $novaExpiracao = date(
        'Y-m-d H:i:s',
        time() + (REMEMBER_DIAS * 86400)
    );

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmtUpdate = $pdo->prepare("
        UPDATE usuarios_sessoes
        SET
            ultimo_acesso = NOW(),
            ip_ultimo_acesso = ?,
            expira_em = ?
        WHERE id = ?
          AND ativo = 1
        LIMIT 1
    ");

    $stmtUpdate->execute([
        $ip,
        $novaExpiracao,
        $user['sessao_id']
    ]);


    /*
    |--------------------------------------------------------------------------
    | RENOVA O COOKIE
    |--------------------------------------------------------------------------
    */

    configurarCookieDispositivo(
        $token,
        time() + (REMEMBER_DIAS * 86400)
    );

    return true;
}

function exigirAdministrador(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $usuarioId = (int)($_SESSION['user_id'] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | USUÁRIO NÃO AUTENTICADO
    |--------------------------------------------------------------------------
    */
    if ($usuarioId <= 0) {
        header('Location: index.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | BUSCA O PERFIL ATUAL NO BANCO
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->prepare("
        SELECT id, perfil, nome
        FROM usuarios
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$usuarioId]);

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | USUÁRIO NÃO EXISTE
    |--------------------------------------------------------------------------
    */
    if (!$usuario) {
        session_unset();
        session_destroy();

        header('Location: index.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | PERFIL
    |--------------------------------------------------------------------------
    */
    $perfil = strtolower(trim((string)($usuario['perfil'] ?? '')));

    $perfisAdministrativos = [
        'admin',
        'administrador',
        'superadmin',
        'super_admin'
    ];

    /*
    |--------------------------------------------------------------------------
    | SEM PERMISSÃO ADMINISTRATIVA
    |--------------------------------------------------------------------------
    */
    if (!in_array($perfil, $perfisAdministrativos, true)) {

        /*
        |--------------------------------------------------------------------------
        | NÃO USA header()
        |--------------------------------------------------------------------------
        | Aqui não fazemos redirecionamento HTTP porque queremos mostrar
        | primeiro a tela de acesso negado.
        |--------------------------------------------------------------------------
        */

        if (!headers_sent()) {
            http_response_code(403);
        }

        $nomeUsuario = htmlspecialchars(
            (string)($usuario['nome'] ?? 'Usuário'),
            ENT_QUOTES,
            'UTF-8'
        );

        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">

        <head>
            <meta charset="UTF-8">

            <meta
                name="viewport"
                content="width=device-width, initial-scale=1.0"
            >

            <title>Acesso restrito | Missão Rocket</title>

            <meta
                name="theme-color"
                content="#7c3aed"
            >

            <style>
                * {
                    box-sizing: border-box;
                    margin: 0;
                    padding: 0;
                }

                html,
                body {
                    width: 100%;
                    min-height: 100%;
                }

                body {
                    min-height: 100vh;
                    background:
                        radial-gradient(
                            circle at top,
                            #f3e8ff 0%,
                            #f8fafc 42%,
                            #eef2ff 100%
                        );

                    font-family:
                        -apple-system,
                        BlinkMacSystemFont,
                        "Segoe UI",
                        Roboto,
                        Helvetica,
                        Arial,
                        sans-serif;

                    color: #1e293b;

                    display: flex;
                    align-items: center;
                    justify-content: center;

                    padding: 1.25rem;
                }

                .access-wrapper {
                    width: 100%;
                    max-width: 430px;
                }

                .access-card {
                    background: rgba(255, 255, 255, .97);

                    border: 1px solid #f1f5f9;

                    border-radius: 28px;

                    padding: 2rem 1.5rem 1.5rem;

                    text-align: center;

                    box-shadow:
                        0 20px 50px rgba(15, 23, 42, .10),
                        0 4px 15px rgba(124, 58, 237, .05);

                    animation: cardIn .45s ease-out;
                }

                @keyframes cardIn {
                    from {
                        opacity: 0;
                        transform: translateY(18px) scale(.98);
                    }

                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }

                .rocket-icon {
                    width: 78px;
                    height: 78px;

                    margin: 0 auto 1.25rem;

                    border-radius: 24px;

                    background:
                        linear-gradient(
                            135deg,
                            #a855f7,
                            #6366f1
                        );

                    color: #fff;

                    display: flex;
                    align-items: center;
                    justify-content: center;

                    font-size: 2rem;

                    box-shadow:
                        0 12px 28px rgba(124, 58, 237, .25);
                }

                .rocket-icon svg {
                    width: 38px;
                    height: 38px;
                    fill: none;
                    stroke: currentColor;
                    stroke-width: 1.8;
                    stroke-linecap: round;
                    stroke-linejoin: round;
                }

                .badge {
                    display: inline-flex;
                    align-items: center;
                    gap: .4rem;

                    background: #fef2f2;
                    color: #dc2626;

                    border: 1px solid #fee2e2;

                    border-radius: 999px;

                    padding: .4rem .75rem;

                    font-size: .72rem;
                    font-weight: 800;

                    margin-bottom: .9rem;
                }

                .badge-dot {
                    width: 7px;
                    height: 7px;

                    border-radius: 50%;

                    background: #ef4444;
                }

                h1 {
                    font-size: 1.45rem;
                    line-height: 1.2;

                    font-weight: 900;

                    color: #1e293b;

                    margin-bottom: .65rem;
                }

                .description {
                    color: #64748b;

                    font-size: .9rem;
                    line-height: 1.55;

                    margin-bottom: 1.25rem;
                }

                .user-box {
                    background: #f8fafc;

                    border: 1px solid #f1f5f9;

                    border-radius: 15px;

                    padding: .75rem 1rem;

                    margin-bottom: 1.25rem;

                    color: #475569;

                    font-size: .82rem;
                }

                .user-box strong {
                    display: block;

                    color: #1e293b;

                    font-size: .9rem;

                    margin-top: .15rem;
                }

                .redirect-box {
                    background: #faf5ff;

                    border: 1px solid #ede9fe;

                    border-radius: 16px;

                    padding: .9rem 1rem;
                }

                .redirect-title {
                    color: #7c3aed;

                    font-size: .78rem;

                    font-weight: 800;

                    margin-bottom: .65rem;
                }

                .progress {
                    width: 100%;
                    height: 7px;

                    background: #e2e8f0;

                    border-radius: 999px;

                    overflow: hidden;
                }

                .progress-bar {
                    width: 100%;
                    height: 100%;

                    background:
                        linear-gradient(
                            90deg,
                            #a855f7,
                            #6366f1
                        );

                    border-radius: inherit;

                    transform-origin: left;

                    animation: countdown 5s linear forwards;
                }

                @keyframes countdown {
                    from {
                        transform: scaleX(1);
                    }

                    to {
                        transform: scaleX(0);
                    }
                }

                .seconds {
                    color: #94a3b8;

                    font-size: .7rem;

                    margin-top: .55rem;
                }

                .manual-link {
                    display: inline-block;

                    margin-top: 1rem;

                    color: #7c3aed;

                    font-size: .78rem;

                    font-weight: 800;

                    text-decoration: none;
                }

                .manual-link:hover {
                    text-decoration: underline;
                }

                .brand {
                    text-align: center;

                    margin-top: 1rem;

                    color: #94a3b8;

                    font-size: .7rem;

                    font-weight: 700;
                }

                @media (max-width: 380px) {
                    .access-card {
                        padding: 1.5rem 1.1rem 1.25rem;
                        border-radius: 23px;
                    }

                    .rocket-icon {
                        width: 68px;
                        height: 68px;
                    }

                    h1 {
                        font-size: 1.3rem;
                    }
                }
            </style>
        </head>

        <body>

            <div class="access-wrapper">

                <div class="access-card">

                    <div class="rocket-icon">
                        <svg
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path d="M12 14c-2.5 0-4.5 2-4.5 4.5V21l4.5-2 4.5 2v-2.5C16.5 16 14.5 14 12 14Z"></path>
                            <path d="M12 14c-1.8 0-3.5-1.2-4-3l-.5-2C7 5.5 9.3 2 12 2s5 3.5 4.5 7l-.5 2c-.5 1.8-2.2 3-4 3Z"></path>
                            <circle cx="12" cy="8" r="1.5"></circle>
                        </svg>
                    </div>

                    <div class="badge">
                        <span class="badge-dot"></span>
                        ÁREA RESTRITA
                    </div>

                    <h1>
                        Acesso não autorizado
                    </h1>

                    <p class="description">
                        Olá, <?= $nomeUsuario ?>.
                        Esta área é exclusiva para usuários
                        com permissão administrativa.
                    </p>

                    <div class="user-box">
                        Seu acesso atual
                        <strong>
                            Não possui permissão administrativa
                        </strong>
                    </div>

                    <div class="redirect-box">

                        <div class="redirect-title">
                            VOLTANDO PARA O DASHBOARD
                        </div>

                        <div class="progress">
                            <div class="progress-bar"></div>
                        </div>

                        <div class="seconds">
                            Você será redirecionado em
                            <strong id="contador">5</strong>
                            segundos.
                        </div>

                    </div>

                    <a
                        href="dashboard.php"
                        class="manual-link"
                    >
                        Voltar agora para o dashboard
                    </a>

                </div>

                <div class="brand">
                    MISSÃO ROCKET • ARENA DE PERFORMANCE
                </div>

            </div>

            <script>
                let segundos = 5;

                const contador =
                    document.getElementById('contador');

                const intervalo = setInterval(() => {

                    segundos--;

                    if (segundos >= 0) {
                        contador.textContent = segundos;
                    }

                    if (segundos <= 0) {
                        clearInterval(intervalo);
                    }

                }, 1000);

                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 5000);
            </script>

        </body>
        </html>
        <?php

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | USUÁRIO AUTORIZADO
    |--------------------------------------------------------------------------
    */
    $_SESSION['perfil'] = $usuario['perfil'];
}
?>
