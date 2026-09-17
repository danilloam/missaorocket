<?php

/*
|--------------------------------------------------------------------------
| AJAX
|--------------------------------------------------------------------------
| Detectamos antes do header.php para podermos descartar qualquer HTML
| produzido pelo header quando a requisição for AJAX.
|--------------------------------------------------------------------------
*/

$isAjaxUploadRequest =
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

if ($isAjaxUploadRequest) {
    ob_start();
}

require_once 'header.php';

$msg = '';

$user_id  = (int)($_SESSION['user_id'] ?? 0);
$grupo_id = null;


/*
|--------------------------------------------------------------------------
| GRUPO DO USUÁRIO
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['grupo_id']) &&
    $_SESSION['grupo_id'] !== '' &&
    $_SESSION['grupo_id'] !== null &&
    (int)$_SESSION['grupo_id'] > 0
) {
    $grupo_id = (int)$_SESSION['grupo_id'];
}


/*
|--------------------------------------------------------------------------
| CONFIGURAÇÕES
|--------------------------------------------------------------------------
|
| MEDIUMBLOB:
| 16.777.215 bytes
|
| Limite da aplicação:
| 15 MB
|
*/

const MAX_UPLOAD_SIZE = 15 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {

    try {

        $_SESSION['csrf_token'] = bin2hex(
            random_bytes(32)
        );

    } catch (Throwable $e) {

        $_SESSION['csrf_token'] = hash(
            'sha256',
            session_id() .
            microtime(true) .
            mt_rand()
        );
    }
}

$csrfToken = $_SESSION['csrf_token'];


/*
|--------------------------------------------------------------------------
| MENSAGEM HTML
|--------------------------------------------------------------------------
*/

function mensagemAlerta(
    string $tipo,
    string $icone,
    string $mensagem
): string {

    return '
        <div class="alert alert-' .
        htmlspecialchars(
            $tipo,
            ENT_QUOTES,
            'UTF-8'
        ) .
        ' py-2 small text-center rounded-3">
            <i class="fa-solid ' .
            htmlspecialchars(
                $icone,
                ENT_QUOTES,
                'UTF-8'
            ) .
            ' me-1"></i>
            ' .
            htmlspecialchars(
                $mensagem,
                ENT_QUOTES,
                'UTF-8'
            ) .
        '
        </div>
    ';
}


/*
|--------------------------------------------------------------------------
| RESULTADO PADRONIZADO
|--------------------------------------------------------------------------
*/

function resultadoUpload(
    bool $sucesso,
    string $tipo,
    string $icone,
    string $mensagem
): array {

    return [
        'success' => $sucesso,
        'type'    => $tipo,
        'icon'    => $icone,
        'message' => $mensagem
    ];
}


/*
|--------------------------------------------------------------------------
| VALIDAÇÃO REAL DO CONTEÚDO
|--------------------------------------------------------------------------
*/

function validarConteudoArquivo(
    string $arquivoTmp,
    string $extensao
): bool {

    if (!is_file($arquivoTmp)) {
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | JPG / JPEG
    |--------------------------------------------------------------------------
    */

    if (
        $extensao === 'jpg' ||
        $extensao === 'jpeg'
    ) {

        $imagem = @getimagesize(
            $arquivoTmp
        );

        if ($imagem === false) {
            return false;
        }

        return isset($imagem[2]) &&
               (int)$imagem[2] === IMAGETYPE_JPEG;
    }


    /*
    |--------------------------------------------------------------------------
    | PNG
    |--------------------------------------------------------------------------
    */

    if ($extensao === 'png') {

        $imagem = @getimagesize(
            $arquivoTmp
        );

        if ($imagem === false) {
            return false;
        }

        return isset($imagem[2]) &&
               (int)$imagem[2] === IMAGETYPE_PNG;
    }


    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    if ($extensao === 'pdf') {

        $handle = @fopen(
            $arquivoTmp,
            'rb'
        );

        if ($handle === false) {
            return false;
        }

        $cabecalho = fread(
            $handle,
            5
        );

        fclose($handle);

        return $cabecalho === '%PDF-';
    }


    /*
    |--------------------------------------------------------------------------
    | ZIP
    |--------------------------------------------------------------------------
    */

    if ($extensao === 'zip') {

        $handle = @fopen(
            $arquivoTmp,
            'rb'
        );

        if ($handle === false) {
            return false;
        }

        $assinatura = fread(
            $handle,
            4
        );

        fclose($handle);

        return
            $assinatura === "PK\x03\x04" ||
            $assinatura === "PK\x05\x06" ||
            $assinatura === "PK\x07\x08";
    }


    return false;
}


/*
|--------------------------------------------------------------------------
| PROCESSAMENTO DA EVIDÊNCIA
|--------------------------------------------------------------------------
*/

function processarUpload(
    PDO $pdo,
    int $user_id,
    ?int $grupo_id,
    int $prova_id,
    string $csrfPost
): array {

    /*
    |--------------------------------------------------------------------------
    | 1. USUÁRIO
    |--------------------------------------------------------------------------
    */

    if ($user_id <= 0) {

        return resultadoUpload(
            false,
            'danger',
            'fa-triangle-exclamation',
            'Sessão de usuário inválida. Faça login novamente.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 2. CSRF
    |--------------------------------------------------------------------------
    */

    if (
        empty($_SESSION['csrf_token']) ||
        empty($csrfPost) ||
        !hash_equals(
            (string)$_SESSION['csrf_token'],
            (string)$csrfPost
        )
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-shield-halved',
            'Solicitação inválida. Atualize a página e tente novamente.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 3. PROVA
    |--------------------------------------------------------------------------
    */

    try {

        $stProva = $pdo->prepare("
            SELECT
                id,
                titulo,
                pontos,
                tipo,
                grupo_id,
                data_inicio,
                data_fim
            FROM provas
            WHERE id = ?
              AND id > 1
              AND (
                    tipo = 'global'
                    OR (
                        tipo <> 'global'
                        AND grupo_id IS NOT NULL
                        AND ? IS NOT NULL
                        AND grupo_id = ?
                    )
              )
              AND data_inicio < DATE_ADD(
                    CURDATE(),
                    INTERVAL 1 DAY
              )
              AND data_fim >= CURDATE()
            LIMIT 1
        ");

        $stProva->execute([
            $prova_id,
            $grupo_id,
            $grupo_id
        ]);

        $prova = $stProva->fetch(
            PDO::FETCH_ASSOC
        );

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            '[MISSÃO ROCKET][UPLOAD][PROVA] ' .
            'user_id=' . $user_id .
            ' grupo_id=' . var_export($grupo_id, true) .
            ' prova_id=' . $prova_id .
            ' erro=' . $e->getMessage()
        );

        return resultadoUpload(
            false,
            'danger',
            'fa-database',
            'Não foi possível validar a prova. Consulte o log do sistema.'
        );
    }


    if (!$prova) {

        return resultadoUpload(
            false,
            'danger',
            'fa-triangle-exclamation',
            'Esta prova não está disponível para envio.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 4. ÚLTIMA SUBMISSÃO
    |--------------------------------------------------------------------------
    */

    try {

        $stHistorico = $pdo->prepare("
            SELECT status
            FROM historico_pontos
            WHERE usuario_id = ?
              AND prova_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $stHistorico->execute([
            $user_id,
            $prova_id
        ]);

        $statusAtual =
            $stHistorico->fetchColumn();

    } catch (Throwable $e) {

        error_log(
            '[MISSÃO ROCKET][UPLOAD][HISTORICO] ' .
            'user_id=' . $user_id .
            ' prova_id=' . $prova_id .
            ' erro=' . $e->getMessage()
        );

        return resultadoUpload(
            false,
            'danger',
            'fa-database',
            'Não foi possível verificar o histórico da prova.'
        );
    }


    if ($statusAtual === 'pendente') {

        return resultadoUpload(
            false,
            'warning',
            'fa-clock',
            'Esta prova já possui uma evidência aguardando análise.'
        );
    }


    if ($statusAtual === 'aprovado') {

        return resultadoUpload(
            false,
            'success',
            'fa-circle-check',
            'Esta prova já foi concluída.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 5. ARQUIVO
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_FILES['evidencia']) ||
        !is_array($_FILES['evidencia'])
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-triangle-exclamation',
            'Nenhum arquivo foi recebido.'
        );
    }

    $arquivo = $_FILES['evidencia'];


    /*
    |--------------------------------------------------------------------------
    | 6. ERROS NATIVOS DO PHP
    |--------------------------------------------------------------------------
    */

    $erroUpload =
        (int)($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($erroUpload !== UPLOAD_ERR_OK) {

        $errosUpload = [

            UPLOAD_ERR_INI_SIZE =>
                'O arquivo excede o limite configurado no servidor.',

            UPLOAD_ERR_FORM_SIZE =>
                'O arquivo excede o limite permitido.',

            UPLOAD_ERR_PARTIAL =>
                'O arquivo foi enviado apenas parcialmente.',

            UPLOAD_ERR_NO_FILE =>
                'Nenhum arquivo foi selecionado.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'O diretório temporário do servidor está indisponível.',

            UPLOAD_ERR_CANT_WRITE =>
                'O servidor não conseguiu gravar o arquivo temporário.',

            UPLOAD_ERR_EXTENSION =>
                'O upload foi interrompido por uma extensão do PHP.'
        ];

        return resultadoUpload(
            false,
            'danger',
            'fa-triangle-exclamation',
            $errosUpload[$erroUpload]
                ?? 'Falha desconhecida durante o envio do arquivo.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 7. UPLOAD REAL
    |--------------------------------------------------------------------------
    */

    $tmpName =
        (string)($arquivo['tmp_name'] ?? '');

    if (
        $tmpName === '' ||
        !is_uploaded_file($tmpName)
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-file-circle-xmark',
            'O arquivo recebido não é um upload válido.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 8. TAMANHO
    |--------------------------------------------------------------------------
    */

    $tamanhoArquivo =
        (int)($arquivo['size'] ?? 0);

    if ($tamanhoArquivo <= 0) {

        return resultadoUpload(
            false,
            'danger',
            'fa-file-circle-xmark',
            'O arquivo enviado está vazio.'
        );
    }


    if ($tamanhoArquivo > MAX_UPLOAD_SIZE) {

        return resultadoUpload(
            false,
            'danger',
            'fa-weight-hanging',
            'O arquivo ultrapassa o limite máximo de 15 MB.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 9. EXTENSÃO
    |--------------------------------------------------------------------------
    */

    $nomeOriginal =
        (string)($arquivo['name'] ?? '');

    $extensao = strtolower(
        pathinfo(
            $nomeOriginal,
            PATHINFO_EXTENSION
        )
    );

    $extensoesPermitidas = [
        'jpg',
        'jpeg',
        'png',
        'pdf',
        'zip'
    ];

    if (
        $extensao === '' ||
        !in_array(
            $extensao,
            $extensoesPermitidas,
            true
        )
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-file-circle-xmark',
            'Formato não permitido. Utilize JPG, JPEG, PNG, PDF ou ZIP.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 10. VALIDAÇÃO REAL DO CONTEÚDO
    |--------------------------------------------------------------------------
    */

    if (
        !validarConteudoArquivo(
            $tmpName,
            $extensao
        )
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-file-circle-xmark',
            'O conteúdo do arquivo não corresponde ao formato informado.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 11. TAMANHO FINAL
    |--------------------------------------------------------------------------
    |
    | Não usamos file_get_contents().
    |
    | O arquivo será lido posteriormente através de stream.
    |--------------------------------------------------------------------------
    */

    $tamanhoRealArquivo =
        @filesize($tmpName);

    if (
        $tamanhoRealArquivo === false ||
        $tamanhoRealArquivo <= 0
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-file-circle-xmark',
            'Não foi possível verificar o tamanho do arquivo enviado.'
        );
    }


    if (
        $tamanhoRealArquivo > MAX_UPLOAD_SIZE
    ) {

        return resultadoUpload(
            false,
            'danger',
            'fa-weight-hanging',
            'O arquivo ultrapassa o limite máximo de 15 MB.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 12. TRANSAÇÃO
    |--------------------------------------------------------------------------
    */

    $streamArquivo = null;

    try {

        $pdo->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | 13. CONFIRMA NOVAMENTE O STATUS
        |--------------------------------------------------------------------------
        */

        $stConfirma = $pdo->prepare("
            SELECT status
            FROM historico_pontos
            WHERE usuario_id = ?
              AND prova_id = ?
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");

        $stConfirma->execute([
            $user_id,
            $prova_id
        ]);

        $statusConfirma =
            $stConfirma->fetchColumn();


        if (
            $statusConfirma === 'pendente' ||
            $statusConfirma === 'aprovado'
        ) {

            $pdo->rollBack();

            return resultadoUpload(
                false,
                'warning',
                'fa-lock',
                'Esta prova já possui uma submissão registrada.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | 14. PREPARA INSERT
        |--------------------------------------------------------------------------
        */

        $stInsere = $pdo->prepare("
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


        /*
        |--------------------------------------------------------------------------
        | USUÁRIO
        |--------------------------------------------------------------------------
        */

        $stInsere->bindValue(
            1,
            $user_id,
            PDO::PARAM_INT
        );


        /*
        |--------------------------------------------------------------------------
        | GRUPO
        |--------------------------------------------------------------------------
        */

        if ($grupo_id === null) {

            $stInsere->bindValue(
                2,
                null,
                PDO::PARAM_NULL
            );

        } else {

            $stInsere->bindValue(
                2,
                $grupo_id,
                PDO::PARAM_INT
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PROVA
        |--------------------------------------------------------------------------
        */

        $stInsere->bindValue(
            3,
            $prova_id,
            PDO::PARAM_INT
        );


        /*
        |--------------------------------------------------------------------------
        | PONTOS
        |--------------------------------------------------------------------------
        */

        $stInsere->bindValue(
            4,
            (int)$prova['pontos'],
            PDO::PARAM_INT
        );


        /*
        |--------------------------------------------------------------------------
        | EVIDÊNCIA VIA STREAM
        |--------------------------------------------------------------------------
        |
        | Em vez de:
        |
        | file_get_contents()
        |
        | abrimos o arquivo como stream.
        |
        | Isso evita carregar os até 15 MB do arquivo inteiro
        | em uma variável PHP antes do INSERT.
        |--------------------------------------------------------------------------
        */

        $streamArquivo = @fopen(
            $tmpName,
            'rb'
        );

        if ($streamArquivo === false) {

            throw new RuntimeException(
                'Não foi possível abrir o arquivo de evidência como stream.'
            );
        }


        $stInsere->bindValue(
            5,
            $streamArquivo,
            PDO::PARAM_LOB
        );


        /*
        |--------------------------------------------------------------------------
        | 15. EXECUTA INSERT
        |--------------------------------------------------------------------------
        */

        $stInsere->execute();


        /*
        |--------------------------------------------------------------------------
        | Fecha o stream somente depois do execute()
        |--------------------------------------------------------------------------
        */

        fclose(
            $streamArquivo
        );

        $streamArquivo = null;


        /*
        |--------------------------------------------------------------------------
        | 16. COMMIT
        |--------------------------------------------------------------------------
        */

        $pdo->commit();


        return resultadoUpload(
            true,
            'success',
            'fa-circle-check',
            'Evidência enviada com sucesso e encaminhada para análise.'
        );


    } catch (Throwable $e) {

        if (is_resource($streamArquivo)) {

            @fclose(
                $streamArquivo
            );
        }

        if ($pdo->inTransaction()) {

            $pdo->rollBack();
        }


        /*
        |--------------------------------------------------------------------------
        | LOG DETALHADO
        |--------------------------------------------------------------------------
        */

        error_log(
            '[MISSÃO ROCKET][UPLOAD][INSERT] ' .
            'user_id=' . $user_id .
            ' grupo_id=' . var_export($grupo_id, true) .
            ' prova_id=' . $prova_id .
            ' arquivo=' . $nomeOriginal .
            ' tamanho=' . $tamanhoRealArquivo .
            ' erro=' . $e->getMessage()
        );


        $erro = strtolower(
            $e->getMessage()
        );


        /*
        |--------------------------------------------------------------------------
        | LIMITE DO BLOB / PACKET
        |--------------------------------------------------------------------------
        */

        if (
            str_contains($erro, 'data too long') ||
            str_contains($erro, '1406') ||
            str_contains($erro, 'max_allowed_packet') ||
            str_contains($erro, 'packet too large') ||
            str_contains($erro, '1153')
        ) {

            return resultadoUpload(
                false,
                'danger',
                'fa-database',
                'O arquivo é grande demais para ser armazenado no banco de dados.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | FOREIGN KEY
        |--------------------------------------------------------------------------
        */

        if (
            str_contains($erro, 'foreign key') ||
            str_contains($erro, '1452')
        ) {

            return resultadoUpload(
                false,
                'danger',
                'fa-users-slash',
                'Não foi possível registrar o grupo associado à evidência.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | NULL
        |--------------------------------------------------------------------------
        */

        if (
            str_contains($erro, 'cannot be null') ||
            str_contains($erro, '1048')
        ) {

            return resultadoUpload(
                false,
                'danger',
                'fa-database',
                'A estrutura do banco não permite registrar uma evidência sem grupo.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ERRO GENÉRICO
        |--------------------------------------------------------------------------
        */

        return resultadoUpload(
            false,
            'danger',
            'fa-triangle-exclamation',
            'Não foi possível registrar sua evidência. Tente novamente.'
        );
    }
}


/*
|--------------------------------------------------------------------------
| PROCESSAMENTO DO POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resultadoPost = null;


    if (!empty($_POST['prova_id'])) {

        $prova_id = filter_var(
            $_POST['prova_id'],
            FILTER_VALIDATE_INT
        );


        if (!$prova_id) {

            $resultadoPost = resultadoUpload(
                false,
                'danger',
                'fa-triangle-exclamation',
                'Prova inválida.'
            );

        } else {

            $csrfPost = (string)(
                $_POST['csrf_token'] ?? ''
            );


            $resultadoPost = processarUpload(
                $pdo,
                $user_id,
                $grupo_id,
                (int)$prova_id,
                $csrfPost
            );
        }

    } else {

        $resultadoPost = resultadoUpload(
            false,
            'danger',
            'fa-triangle-exclamation',
            'Não foi possível identificar a prova.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RESPOSTA AJAX
    |--------------------------------------------------------------------------
    */

    if ($isAjaxUploadRequest) {

        /*
        | Descarta qualquer HTML produzido pelo header.php.
        */

        while (ob_get_level() > 0) {
            ob_end_clean();
        }


        header(
            'Content-Type: application/json; charset=utf-8'
        );


        echo json_encode(
            $resultadoPost,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | ENVIO TRADICIONAL
    |--------------------------------------------------------------------------
    */

    $msg = mensagemAlerta(
        $resultadoPost['type'],
        $resultadoPost['icon'],
        $resultadoPost['message']
    );
}


/*
|--------------------------------------------------------------------------
| BUSCA PROVAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        p.*,

        COALESCE(
            h.status,
            'disponivel'
        ) AS status_usuario

    FROM provas p

    LEFT JOIN (

        SELECT
            hp.prova_id,
            hp.status

        FROM historico_pontos hp

        INNER JOIN (

            SELECT
                prova_id,
                MAX(id) AS ultimo_id

            FROM historico_pontos

            WHERE usuario_id = ?

            GROUP BY prova_id

        ) ultimo

            ON ultimo.ultimo_id = hp.id

    ) h

        ON h.prova_id = p.id

    WHERE
        (
            p.tipo = 'global'

            OR (

                p.tipo <> 'global'

                AND p.grupo_id IS NOT NULL

                AND ? IS NOT NULL

                AND p.grupo_id = ?

            )
        )

        AND p.data_inicio < DATE_ADD(
            CURDATE(),
            INTERVAL 1 DAY
        )

        AND p.data_fim >= CURDATE()

        AND p.id > 2

    ORDER BY p.id DESC
");


$stmt->execute([
    $user_id,
    $grupo_id,
    $grupo_id
]);


$provas = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);

?>

<style>

body {
    background-color: #f8fafc !important;
    color: #1e293b;
}

.main-wrapper {
    max-width: 480px;
    margin: 0 auto;
    padding: 1rem 1rem 5rem 1rem;
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

.icon-btn {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    cursor: pointer;
    transition: all 0.2s;
}

.icon-btn-purple {
    background-color: #f3e8ff;
    color: #a855f7;
    border: none;
}

.search-container {
    position: relative;
    flex-grow: 1;
}

.search-container i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 0.95rem;
}

.search-container input {
    width: 100%;
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 0.65rem 1rem 0.65rem 2.4rem;
    font-size: 0.9rem;
    outline: none;
    color: #334155;
}

.filter-scroll {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
}

.filter-scroll::-webkit-scrollbar {
    display: none;
}

.filter-btn {
    background-color: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 6px 16px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    white-space: nowrap;
    transition: all 0.2s;
}

.filter-btn.active {
    background-color: #ffffff;
    border-color: #8b5cf6;
    color: #8b5cf6;
    box-shadow: 0 2px 6px rgba(139, 92, 246, 0.08);
}

.empty-state {
    text-align: center;
    padding: 2.5rem 1rem;
    display: none;
}

.empty-state i {
    font-size: 2.5rem;
    color: #64748b;
    background-color: #e2e8f0;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    margin: 0 auto 1rem auto;
}

.empty-state h6 {
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 0.25rem;
}

.empty-state p {
    font-size: 0.8rem;
    color: #94a3b8;
}

.task-card {
    background-color: #ffffff;
    border-radius: 16px;
    padding: 1.25rem;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
    margin-bottom: 1rem;
    transition: transform 0.2s;
}

.card-tag-category {
    background-color: #f3e8ff;
    color: #a855f7;
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
}

.card-tag-status {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 8px;
}

.status-disponivel {
    background-color: #ffe4e6;
    color: #f43f5e;
}

.status-pendente {
    background-color: #fef3c7;
    color: #d97706;
}

.status-aprovado {
    background-color: #dcfce7;
    color: #15803d;
}

.status-rejeitado {
    background-color: #fee2e2;
    color: #b91c1c;
}

.task-title {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
    margin-top: 0.75rem;
    margin-bottom: 0.25rem;
}

.task-desc {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 1rem;
    line-height: 1.4;
}

.task-meta-label {
    font-size: 0.72rem;
    color: #94a3b8;
    font-weight: 600;
    margin-bottom: 0.1rem;
}

.task-meta-value {
    font-size: 0.85rem;
    color: #334155;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
}

.link-details {
    color: #8b5cf6;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: none;
    padding: 0;
}

.upload-info {
    font-size: 0.72rem;
    color: #94a3b8;
    margin-top: 6px;
    line-height: 1.35;
}


/*
|--------------------------------------------------------------------------
| BOTÃO DE ENVIO
|--------------------------------------------------------------------------
*/

.btn-enviar-evidencia {
    position: relative;
    width: 100%;
    min-height: 42px;
    transition: all .2s ease;
}

.btn-enviar-evidencia.enviando {
    opacity: .85;
    cursor: wait !important;
    pointer-events: none;
}

.btn-enviar-evidencia .spinner-envio {
    display: none;
    width: 17px;
    height: 17px;
    border: 2px solid rgba(255,255,255,.35);
    border-top-color: #fff;
    border-radius: 50%;
    animation: spinnerEvidencia .7s linear infinite;
    vertical-align: -3px;
    margin-right: 8px;
}

.btn-enviar-evidencia.enviando .spinner-envio {
    display: inline-block;
}

@keyframes spinnerEvidencia {
    to {
        transform: rotate(360deg);
    }
}


/*
|--------------------------------------------------------------------------
| STATUS DO ENVIO
|--------------------------------------------------------------------------
*/

.status-envio-evidencia {
    display: none;
    margin-top: 12px;
    padding: 13px 14px;
    border-radius: 12px;

    background: #f5f3ff;
    border: 1px solid #ddd6fe;

    color: #5b21b6;

    font-size: .78rem;
    line-height: 1.45;

    animation: aparecerStatus .2s ease;
}

.status-envio-evidencia.ativo {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.status-envio-evidencia.erro {
    background: #fef2f2;
    border-color: #fecaca;
    color: #991b1b;
}

.status-envio-evidencia.sucesso {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #166534;
}

.status-envio-evidencia .icone-status {
    width: 28px;
    height: 28px;
    flex: 0 0 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #ede9fe;
    color: #7c3aed;

    border-radius: 8px;

    font-size: 15px;
}

.status-envio-evidencia.erro .icone-status {
    background: #fee2e2;
    color: #dc2626;
}

.status-envio-evidencia.sucesso .icone-status {
    background: #dcfce7;
    color: #16a34a;
}

.status-envio-evidencia strong {
    display: block;
    color: #4c1d95;
    font-size: .8rem;
    margin-bottom: 2px;
}

.status-envio-evidencia.erro strong {
    color: #991b1b;
}

.status-envio-evidencia.sucesso strong {
    color: #166534;
}

.status-envio-evidencia span {
    color: #6d5a8d;
}

.status-envio-evidencia.erro span {
    color: #7f1d1d;
}

.status-envio-evidencia.sucesso span {
    color: #166534;
}

.status-arquivo-envio {
    display: block;
    margin-top: 4px;

    max-width: 100%;

    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;

    color: #7c3aed !important;
    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| BARRA DE PROGRESSO
|--------------------------------------------------------------------------
*/

.upload-progress-container {
    margin-top: 10px;
}

.upload-progress-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
    font-size: .7rem;
    font-weight: 700;
}

.upload-progress {
    width: 100%;
    height: 7px;
    overflow: hidden;
    border-radius: 99px;
    background: #ddd6fe;
}

.upload-progress-bar {
    width: 0%;
    height: 100%;
    border-radius: 99px;
    background: #8b5cf6;
    transition: width .15s ease;
}

.upload-progress-percent {
    min-width: 38px;
    text-align: right;
    color: #7c3aed !important;
    font-weight: 800;
}

@keyframes aparecerStatus {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

</style>


<div class="main-wrapper">

    <div class="d-flex justify-content-between align-items-center screen-header mb-3">

        <div>

            <h2>Provas</h2>

            <p>
                Desafie seus limites e ganhe pontos
            </p>

        </div>

        <div class="icon-btn icon-btn-purple">

            <i class="fa-solid fa-bell"></i>

        </div>

    </div>


    <div class="d-flex gap-2 align-items-center mb-3">

        <div class="search-container">

            <i class="fa-solid fa-magnifying-glass"></i>

            <input
                type="text"
                id="taskSearch"
                placeholder="Buscar prova pelo título..."
                autocomplete="off"
            >

        </div>


        <div class="icon-btn">

            <i class="fa-solid fa-sliders"></i>

        </div>

    </div>


    <div class="filter-scroll mb-3">

        <button
            type="button"
            class="filter-btn active"
            data-filter="todas"
        >
            Todas
        </button>

        <button
            type="button"
            class="filter-btn"
            data-filter="disponivel"
        >
            Ativas
        </button>

        <button
            type="button"
            class="filter-btn"
            data-filter="aprovado"
        >
            Concluídas
        </button>

        <button
            type="button"
            class="filter-btn"
            data-filter="rejeitado"
        >
            Recusadas
        </button>

    </div>


    <div class="d-flex justify-content-between align-items-center mb-3 small fw-bold">

        <span
            class="text-secondary"
            id="countLabel"
        >
            Disponíveis (<?= count($provas) ?>)
        </span>


        <a
            href="dashboard.php"
            class="text-decoration-none"
            style="color:#8b5cf6;"
        >
            Ver Ranking
        </a>

    </div>


    <?= $msg ?>


    <div
        class="empty-state"
        id="emptyStateBox"
    >

        <i class="fa-solid fa-circle-info"></i>

        <h6>
            Nenhuma prova encontrada
        </h6>

        <p>
            Tente mudar o filtro ou buscar outro termo
        </p>

    </div>


    <div id="tasksWrapper">

        <?php foreach ($provas as $p): ?>

            <?php

            $status =
                $p['status_usuario'] ?? 'disponivel';

            $badgeText =
                'Disponível';

            $statusClass =
                'status-disponivel';


            if ($status === 'pendente') {

                $badgeText =
                    'Em Análise';

                $statusClass =
                    'status-pendente';

            } elseif ($status === 'aprovado') {

                $badgeText =
                    'Concluída';

                $statusClass =
                    'status-aprovado';

            } elseif ($status === 'rejeitado') {

                $badgeText =
                    'Recusada';

                $statusClass =
                    'status-rejeitado';
            }


            $prazoFormatado =
                !empty($p['data_fim'])
                ? date(
                    'd/m/Y',
                    strtotime($p['data_fim'])
                )
                : 'Sem prazo';


            $tituloBusca =
                strtolower(
                    (string)$p['titulo']
                );

            ?>

            <div
                class="task-card"
                data-status="<?= htmlspecialchars(
                    $status,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                data-title="<?= htmlspecialchars(
                    $tituloBusca,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

                <div class="d-flex justify-content-between align-items-center">

                    <span class="card-tag-category">

                        <i class="fa-regular fa-circle-question"></i>

                        <?= htmlspecialchars(
                            ucfirst((string)$p['tipo']),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>


                    <span class="card-tag-status <?= $statusClass ?>">

                        <?= htmlspecialchars(
                            $badgeText,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </span>

                </div>


                <div class="task-title">

                    <?= htmlspecialchars(
                        (string)$p['titulo'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <div class="task-desc">

                    <?= htmlspecialchars(
                        (string)($p['descricao'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <hr class="text-muted opacity-25 my-2">


                <div class="d-flex justify-content-between align-items-end">

                    <div>

                        <div class="task-meta-label">
                            Prazo
                        </div>

                        <div class="task-meta-value">

                            <i class="fa-regular fa-calendar-minus text-muted me-1"></i>

                            <?= $prazoFormatado ?>

                        </div>

                    </div>


                    <div>

                        <div class="task-meta-label">
                            Pontos
                        </div>

                        <div class="task-meta-value text-warning">

                            🏆 +<?= (int)$p['pontos'] ?>

                        </div>

                    </div>


                    <div>

                        <button
                            type="button"
                            class="btn link-details"
                            data-bs-toggle="collapse"
                            data-bs-target="#boxUpload<?= (int)$p['id'] ?>"
                        >

                            Detalhes

                            <i class="fa-solid fa-arrow-right"></i>

                        </button>

                    </div>

                </div>


                <div
                    class="collapse mt-3"
                    id="boxUpload<?= (int)$p['id'] ?>"
                >

                    <div class="p-3 bg-light rounded-3 border">

                        <?php if (
                            $status === 'aprovado' ||
                            $status === 'pendente'
                        ): ?>

                            <div class="text-center small py-2 text-muted fw-bold">

                                <i class="fa-solid fa-lock me-1"></i>

                                Submissão fechada para esta prova.

                            </div>

                        <?php else: ?>

                            <form
                                method="POST"
                                enctype="multipart/form-data"
                                autocomplete="off"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(
                                        $csrfToken,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >


                                <input
                                    type="hidden"
                                    name="prova_id"
                                    value="<?= (int)$p['id'] ?>"
                                >


                                <div class="mb-2">

                                    <label class="form-label small fw-bold text-secondary">

                                        Carregar arquivo de evidência:

                                    </label>


                                    <input
                                        type="file"
                                        name="evidencia"
                                        class="form-control form-control-sm rounded-3"
                                        accept=".jpg,.jpeg,.png,.pdf,.zip"
                                        required
                                    >


                                    <div class="upload-info">

                                        JPG, JPEG, PNG, PDF ou ZIP.
                                        Tamanho máximo: 15 MB.

                                    </div>

                                </div>


                                <button
                                    type="submit"
                                    class="btn btn-primary btn-enviar-evidencia"
                                >

                                    <span class="spinner-envio"></span>

                                    <span class="texto-botao">
                                        Enviar evidência
                                    </span>

                                </button>


                                <div
                                    class="status-envio-evidencia"
                                    aria-live="polite"
                                >

                                    <span class="icone-status">

                                        <i class="fa-solid fa-cloud-arrow-up"></i>

                                    </span>


                                    <div style="min-width:0; flex:1;">

                                        <strong class="titulo-status-envio">
                                            Enviando evidência...
                                        </strong>


                                        <span class="texto-status-envio">
                                            Aguarde enquanto sua evidência é enviada e registrada.
                                        </span>


                                        <span class="status-arquivo-envio"></span>


                                        <div class="upload-progress-container">

                                            <div class="upload-progress-top">

                                                <span class="texto-progresso">
                                                    Preparando envio...
                                                </span>

                                                <span class="upload-progress-percent">
                                                    0%
                                                </span>

                                            </div>


                                            <div class="upload-progress">

                                                <div
                                                    class="upload-progress-bar"
                                                    role="progressbar"
                                                    aria-valuemin="0"
                                                    aria-valuemax="100"
                                                    aria-valuenow="0"
                                                ></div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {

        const searchInput =
            document.getElementById('taskSearch');

        const filterButtons =
            document.querySelectorAll('.filter-btn');

        const cards =
            document.querySelectorAll('.task-card');

        const emptyState =
            document.getElementById('emptyStateBox');

        const countLabel =
            document.getElementById('countLabel');


        let currentFilter =
            'todas';

        let currentSearch =
            '';


        function filterTasks() {

            let visibleCount = 0;


            cards.forEach(
                card => {

                    const status =
                        card.getAttribute(
                            'data-status'
                        ) || '';


                    const title =
                        card.getAttribute(
                            'data-title'
                        ) || '';


                    const matchesFilter =
                        currentFilter === 'todas' ||
                        status === currentFilter;


                    const matchesSearch =
                        title.includes(
                            currentSearch
                        );


                    if (
                        matchesFilter &&
                        matchesSearch
                    ) {

                        card.style.display =
                            'block';

                        visibleCount++;

                    } else {

                        card.style.display =
                            'none';
                    }

                }
            );


            countLabel.textContent =
                `Resultados (${visibleCount})`;


            emptyState.style.display =
                visibleCount === 0
                    ? 'block'
                    : 'none';
        }


        if (searchInput) {

            searchInput.addEventListener(
                'input',
                event => {

                    currentSearch =
                        event.target.value
                            .toLowerCase()
                            .trim();

                    filterTasks();
                }
            );
        }


        filterButtons.forEach(
            button => {

                button.addEventListener(
                    'click',
                    () => {

                        filterButtons.forEach(
                            btn => {

                                btn.classList.remove(
                                    'active'
                                );
                            }
                        );


                        button.classList.add(
                            'active'
                        );


                        currentFilter =
                            button.getAttribute(
                                'data-filter'
                            );


                        filterTasks();
                    }
                );

            }
        );

    }
);

</script>


<script>

/*
|--------------------------------------------------------------------------
| UPLOAD AJAX + COMPRESSÃO
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const formularios =
            document.querySelectorAll(
                'form[enctype="multipart/form-data"]'
            );


        /*
        |--------------------------------------------------------------------------
        | Formatação de tamanho
        |--------------------------------------------------------------------------
        */

        function formatarTamanho(bytes) {

            bytes =
                Number(bytes) || 0;


            if (bytes >= 1024 * 1024) {

                return (
                    bytes /
                    (1024 * 1024)
                ).toFixed(2) + ' MB';
            }


            if (bytes >= 1024) {

                return Math.max(
                    1,
                    Math.round(
                        bytes / 1024
                    )
                ) + ' KB';
            }


            return bytes + ' B';
        }


        /*
        |--------------------------------------------------------------------------
        | Atualiza status visual
        |--------------------------------------------------------------------------
        */

        function atualizarStatus(
            status,
            titulo,
            texto,
            icone
        ) {

            if (!status) {
                return;
            }


            const tituloStatus =
                status.querySelector(
                    '.titulo-status-envio'
                );


            const textoStatus =
                status.querySelector(
                    '.texto-status-envio'
                );


            const iconeStatus =
                status.querySelector(
                    '.icone-status i'
                );


            if (tituloStatus) {

                tituloStatus.textContent =
                    titulo;
            }


            if (textoStatus) {

                textoStatus.textContent =
                    texto;
            }


            if (iconeStatus) {

                iconeStatus.className =
                    'fa-solid ' + icone;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Atualiza progresso
        |--------------------------------------------------------------------------
        */

        function atualizarProgresso(
            status,
            percentual,
            texto
        ) {

            if (!status) {
                return;
            }


            percentual =
                Math.max(
                    0,
                    Math.min(
                        100,
                        Number(percentual) || 0
                    )
                );


            const barra =
                status.querySelector(
                    '.upload-progress-bar'
                );


            const porcentagem =
                status.querySelector(
                    '.upload-progress-percent'
                );


            const textoProgresso =
                status.querySelector(
                    '.texto-progresso'
                );


            if (barra) {

                barra.style.width =
                    percentual + '%';

                barra.setAttribute(
                    'aria-valuenow',
                    percentual
                );
            }


            if (porcentagem) {

                porcentagem.textContent =
                    Math.round(percentual) + '%';
            }


            if (textoProgresso && texto) {

                textoProgresso.textContent =
                    texto;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Reseta formulário após erro
        |--------------------------------------------------------------------------
        */

        function liberarFormulario(
            form,
            botao
        ) {

            form.dataset.enviando =
                '0';


            botao.disabled =
                false;


            botao.classList.remove(
                'enviando'
            );


            const textoBotao =
                botao.querySelector(
                    '.texto-botao'
                );


            if (textoBotao) {

                textoBotao.textContent =
                    'Enviar evidência';
            }


            const campos =
                form.querySelectorAll(
                    'select, textarea'
                );


            campos.forEach(
                function (campo) {

                    campo.disabled =
                        false;
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Compressão da imagem
        |--------------------------------------------------------------------------
        |
        | Apenas imagens são comprimidas.
        |
        | PDF e ZIP são enviados sem alteração.
        |
        | Imagens menores que 1 MB também não são processadas,
        | evitando gasto desnecessário de CPU no celular.
        |--------------------------------------------------------------------------
        */

        async function comprimirImagem(
            arquivo
        ) {

            const nome =
                arquivo.name || 'evidencia';


            const extensao =
                nome
                    .split('.')
                    .pop()
                    .toLowerCase();


            const imagens = [
                'jpg',
                'jpeg',
                'png'
            ];


            if (
                !imagens.includes(
                    extensao
                )
            ) {

                return {
                    arquivo: arquivo,
                    comprimido: false
                };
            }


            /*
            |--------------------------------------------------------------------------
            | Arquivos pequenos não precisam ser processados.
            |--------------------------------------------------------------------------
            */

            if (
                arquivo.size <=
                1024 * 1024
            ) {

                return {
                    arquivo: arquivo,
                    comprimido: false
                };
            }


            let imagem = null;
            let objectUrl = null;
            let bitmap = null;


            try {

                /*
                |--------------------------------------------------------------------------
                | Primeiro tenta createImageBitmap
                |--------------------------------------------------------------------------
                */

                if (
                    typeof createImageBitmap ===
                    'function'
                ) {

                    try {

                        bitmap =
                            await createImageBitmap(
                                arquivo,
                                {
                                    imageOrientation:
                                        'from-image'
                                }
                            );

                        imagem =
                            bitmap;

                    } catch (e) {

                        bitmap =
                            await createImageBitmap(
                                arquivo
                            );

                        imagem =
                            bitmap;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | Fallback para navegadores sem createImageBitmap
                |--------------------------------------------------------------------------
                */

                if (!imagem) {

                    imagem =
                        await new Promise(
                            function (
                                resolve,
                                reject
                            ) {

                                objectUrl =
                                    URL.createObjectURL(
                                        arquivo
                                    );


                                const img =
                                    new Image();


                                img.onload =
                                    function () {

                                        resolve(
                                            img
                                        );
                                    };


                                img.onerror =
                                    function () {

                                        reject(
                                            new Error(
                                                'Não foi possível carregar a imagem.'
                                            )
                                        );
                                    };


                                img.src =
                                    objectUrl;
                            }
                        );
                }


                const larguraOriginal =
                    imagem.width ||
                    imagem.naturalWidth;


                const alturaOriginal =
                    imagem.height ||
                    imagem.naturalHeight;


                if (
                    !larguraOriginal ||
                    !alturaOriginal
                ) {

                    throw new Error(
                        'Dimensões da imagem inválidas.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Dimensão máxima
                |--------------------------------------------------------------------------
                |
                | 1920 px é suficiente para uma evidência fotográfica
                | e reduz bastante o tamanho de fotos de celulares.
                |--------------------------------------------------------------------------
                */

                const MAX_DIMENSAO =
                    1920;


                let novaLargura =
                    larguraOriginal;

                let novaAltura =
                    alturaOriginal;


                if (
                    larguraOriginal >
                        MAX_DIMENSAO ||
                    alturaOriginal >
                        MAX_DIMENSAO
                ) {

                    const proporcao =
                        Math.min(
                            MAX_DIMENSAO /
                                larguraOriginal,
                            MAX_DIMENSAO /
                                alturaOriginal
                        );


                    novaLargura =
                        Math.round(
                            larguraOriginal *
                            proporcao
                        );


                    novaAltura =
                        Math.round(
                            alturaOriginal *
                            proporcao
                        );
                }


                const canvas =
                    document.createElement(
                        'canvas'
                    );


                canvas.width =
                    novaLargura;


                canvas.height =
                    novaAltura;


                const contexto =
                    canvas.getContext(
                        '2d',
                        {
                            alpha:
                                extensao ===
                                'png'
                        }
                    );


                if (!contexto) {

                    throw new Error(
                        'Não foi possível preparar a imagem.'
                    );
                }


                contexto.drawImage(
                    imagem,
                    0,
                    0,
                    novaLargura,
                    novaAltura
                );


                /*
                |--------------------------------------------------------------------------
                | JPG/JPEG
                |--------------------------------------------------------------------------
                |
                | Converte para JPEG com qualidade 82%.
                |--------------------------------------------------------------------------
                */

                let mimeType =
                    'image/jpeg';


                let qualidade =
                    0.82;


                let novaExtensao =
                    'jpg';


                /*
                |--------------------------------------------------------------------------
                | PNG
                |--------------------------------------------------------------------------
                |
                | Mantém PNG para não destruir transparência.
                |--------------------------------------------------------------------------
                */

                if (
                    extensao === 'png'
                ) {

                    mimeType =
                        'image/png';

                    qualidade =
                        undefined;

                    novaExtensao =
                        'png';
                }


                const blob =
                    await new Promise(
                        function (
                            resolve,
                            reject
                        ) {

                            canvas.toBlob(
                                function (
                                    resultado
                                ) {

                                    if (
                                        !resultado
                                    ) {

                                        reject(
                                            new Error(
                                                'Não foi possível comprimir a imagem.'
                                            )
                                        );

                                        return;
                                    }


                                    resolve(
                                        resultado
                                    );
                                },
                                mimeType,
                                qualidade
                            );
                        }
                    );


                /*
                |--------------------------------------------------------------------------
                | Se a compressão ficou maior, mantém original.
                |--------------------------------------------------------------------------
                */

                if (
                    !blob ||
                    blob.size >= arquivo.size
                ) {

                    return {
                        arquivo: arquivo,
                        comprimido: false
                    };
                }


                /*
                |--------------------------------------------------------------------------
                | Novo nome
                |--------------------------------------------------------------------------
                */

                const nomeBase =
                    nome.replace(
                        /\.[^/.]+$/,
                        ''
                    );


                const novoNome =
                    nomeBase +
                    '-compactado.' +
                    novaExtensao;


                const novoArquivo =
                    new File(
                        [blob],
                        novoNome,
                        {
                            type:
                                mimeType,
                            lastModified:
                                Date.now()
                        }
                    );


                return {
                    arquivo:
                        novoArquivo,
                    comprimido:
                        true
                };


            } finally {

                if (
                    bitmap &&
                    typeof bitmap.close ===
                    'function'
                ) {

                    try {
                        bitmap.close();
                    } catch (e) {}
                }


                if (objectUrl) {

                    URL.revokeObjectURL(
                        objectUrl
                    );
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Processa cada formulário
        |--------------------------------------------------------------------------
        */

        formularios.forEach(
            function (form) {

                const inputArquivo =
                    form.querySelector(
                        'input[type="file"][name="evidencia"]'
                    );


                const botao =
                    form.querySelector(
                        '.btn-enviar-evidencia'
                    );


                const status =
                    form.querySelector(
                        '.status-envio-evidencia'
                    );


                if (
                    !inputArquivo ||
                    !botao
                ) {

                    return;
                }


                form.addEventListener(
                    'submit',
                    async function (event) {

                        /*
                        |--------------------------------------------------------------------------
                        | Impede o POST tradicional quando o AJAX está funcionando.
                        |--------------------------------------------------------------------------
                        */

                        event.preventDefault();


                        /*
                        |--------------------------------------------------------------------------
                        | Evita envio duplicado.
                        |--------------------------------------------------------------------------
                        */

                        if (
                            form.dataset.enviando ===
                            '1'
                        ) {

                            return;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Verifica arquivo.
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !inputArquivo.files ||
                            inputArquivo.files.length ===
                            0
                        ) {

                            return;
                        }


                        const arquivoOriginal =
                            inputArquivo.files[0];


                        /*
                        |--------------------------------------------------------------------------
                        | Marca imediatamente como enviando.
                        |--------------------------------------------------------------------------
                        */

                        form.dataset.enviando =
                            '1';


                        botao.classList.add(
                            'enviando'
                        );


                        botao.disabled =
                            true;


                        const textoBotao =
                            botao.querySelector(
                                '.texto-botao'
                            );


                        if (textoBotao) {

                            textoBotao.textContent =
                                'Enviando evidência...';
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Status aparece IMEDIATAMENTE.
                        |--------------------------------------------------------------------------
                        */

                        if (status) {

                            status.classList.remove(
                                'erro',
                                'sucesso'
                            );


                            status.classList.add(
                                'ativo'
                            );


                            atualizarStatus(
                                status,
                                'Enviando evidência...',
                                'Aguarde enquanto sua evidência é enviada e registrada.',
                                'fa-cloud-arrow-up'
                            );


                            const arquivoStatus =
                                status.querySelector(
                                    '.status-arquivo-envio'
                                );


                            if (arquivoStatus) {

                                arquivoStatus.textContent =
                                    (
                                        arquivoOriginal.name ||
                                        'Arquivo selecionado'
                                    ) +
                                    ' • ' +
                                    formatarTamanho(
                                        arquivoOriginal.size
                                    );
                            }


                            atualizarProgresso(
                                status,
                                0,
                                'Preparando envio...'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Desabilita apenas campos opcionais.
                        |--------------------------------------------------------------------------
                        */

                        const campos =
                            form.querySelectorAll(
                                'select, textarea'
                            );


                        campos.forEach(
                            function (campo) {

                                campo.disabled =
                                    true;
                            }
                        );


                        let arquivoParaEnviar =
                            arquivoOriginal;


                        try {

                            /*
                            |--------------------------------------------------------------------------
                            | Compressão
                            |--------------------------------------------------------------------------
                            */

                            const extensao =
                                (
                                    arquivoOriginal.name
                                    .split('.')
                                    .pop() || ''
                                ).toLowerCase();


                            if (
                                [
                                    'jpg',
                                    'jpeg',
                                    'png'
                                ].includes(
                                    extensao
                                ) &&
                                arquivoOriginal.size >
                                    1024 * 1024
                            ) {

                                atualizarStatus(
                                    status,
                                    'Preparando evidência...',
                                    'Compactando a foto para acelerar o envio.',
                                    'fa-image'
                                );


                                atualizarProgresso(
                                    status,
                                    0,
                                    'Compactando foto...'
                                );


                                const resultadoCompressao =
                                    await comprimirImagem(
                                        arquivoOriginal
                                    );


                                arquivoParaEnviar =
                                    resultadoCompressao.arquivo;


                                /*
                                |--------------------------------------------------------------------------
                                | Atualiza tamanho visual.
                                |--------------------------------------------------------------------------
                                */

                                const arquivoStatus =
                                    status.querySelector(
                                        '.status-arquivo-envio'
                                    );


                                if (
                                    arquivoStatus
                                ) {

                                    if (
                                        resultadoCompressao.comprimido
                                    ) {

                                        arquivoStatus.textContent =
                                            (
                                                arquivoOriginal.name ||
                                                'Foto'
                                            ) +
                                            ' • ' +
                                            formatarTamanho(
                                                arquivoOriginal.size
                                            ) +
                                            ' → ' +
                                            formatarTamanho(
                                                arquivoParaEnviar.size
                                            );

                                    } else {

                                        arquivoStatus.textContent =
                                            (
                                                arquivoOriginal.name ||
                                                'Arquivo selecionado'
                                            ) +
                                            ' • ' +
                                            formatarTamanho(
                                                arquivoOriginal.size
                                            );
                                    }
                                }
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Status antes do AJAX
                            |--------------------------------------------------------------------------
                            */

                            atualizarStatus(
                                status,
                                'Enviando evidência...',
                                'Aguarde enquanto sua evidência é enviada e registrada.',
                                'fa-cloud-arrow-up'
                            );


                            atualizarProgresso(
                                status,
                                0,
                                'Enviando arquivo...'
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | FormData
                            |--------------------------------------------------------------------------
                            */

                            const formData =
                                new FormData();


                            const camposOcultos =
                                form.querySelectorAll(
                                    'input[type="hidden"]'
                                );


                            camposOcultos.forEach(
                                function (campo) {

                                    formData.append(
                                        campo.name,
                                        campo.value
                                    );
                                }
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | Arquivo final
                            |--------------------------------------------------------------------------
                            */

                            formData.append(
                                'evidencia',
                                arquivoParaEnviar,
                                arquivoParaEnviar.name
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | AJAX
                            |--------------------------------------------------------------------------
                            */

                            const xhr =
                                new XMLHttpRequest();


                            xhr.open(
                                'POST',
                                form.action ||
                                window.location.href,
                                true
                            );


                            xhr.setRequestHeader(
                                'X-Requested-With',
                                'XMLHttpRequest'
                            );


                            xhr.setRequestHeader(
                                'Accept',
                                'application/json'
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | Progresso real do upload
                            |--------------------------------------------------------------------------
                            */

                            xhr.upload.addEventListener(
                                'progress',
                                function (event) {

                                    if (
                                        !event.lengthComputable
                                    ) {

                                        atualizarProgresso(
                                            status,
                                            0,
                                            'Enviando arquivo...'
                                        );

                                        return;
                                    }


                                    const percentual =
                                        (
                                            event.loaded /
                                            event.total
                                        ) *
                                        100;


                                    if (
                                        percentual >=
                                        100
                                    ) {

                                        atualizarProgresso(
                                            status,
                                            100,
                                            'Upload concluído. Finalizando...'
                                        );

                                    } else {

                                        atualizarProgresso(
                                            status,
                                            percentual,
                                            'Enviando arquivo...'
                                        );
                                    }
                                }
                            );


                            /*
                            |--------------------------------------------------------------------------
                            | Requisição concluída
                            |--------------------------------------------------------------------------
                            */

                            xhr.onload =
                                function () {

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Upload chegou ao servidor.
                                    |--------------------------------------------------------------------------
                                    */

                                    atualizarProgresso(
                                        status,
                                        100,
                                        'Arquivo recebido. Registrando evidência...'
                                    );


                                    atualizarStatus(
                                        status,
                                        'Registrando evidência...',
                                        'O arquivo foi enviado. Aguarde a confirmação do registro.',
                                        'fa-database'
                                    );


                                    let resposta;


                                    try {

                                        resposta =
                                            JSON.parse(
                                                xhr.responseText
                                            );

                                    } catch (e) {

                                        liberarFormulario(
                                            form,
                                            botao
                                        );


                                        atualizarStatus(
                                            status,
                                            'Não foi possível concluir',
                                            'O servidor retornou uma resposta inválida. Verifique o log do servidor.',
                                            'fa-triangle-exclamation'
                                        );


                                        status.classList.remove(
                                            'sucesso'
                                        );


                                        status.classList.add(
                                            'erro'
                                        );


                                        return;
                                    }


                                    /*
                                    |--------------------------------------------------------------------------
                                    | Sucesso
                                    |--------------------------------------------------------------------------
                                    */

                                    if (
                                        xhr.status >= 200 &&
                                        xhr.status < 300 &&
                                        resposta &&
                                        resposta.success
                                    ) {

                                        atualizarProgresso(
                                            status,
                                            100,
                                            'Evidência registrada.'
                                        );


                                        atualizarStatus(
                                            status,
                                            'Evidência enviada!',
                                            resposta.message ||
                                            'Sua evidência foi encaminhada para análise.',
                                            'fa-circle-check'
                                        );


                                        status.classList.remove(
                                            'erro'
                                        );


                                        status.classList.add(
                                            'sucesso'
                                        );


                                        const textoBotao =
                                            botao.querySelector(
                                                '.texto-botao'
                                            );


                                        if (
                                            textoBotao
                                        ) {

                                            textoBotao.textContent =
                                                'Evidência enviada!';
                                        }


                                        /*
                                        |--------------------------------------------------------------------------
                                        | Recarrega para atualizar o status da prova.
                                        |--------------------------------------------------------------------------
                                        */

                                        setTimeout(
                                            function () {

                                                window.location.reload();

                                            },
                                            900
                                        );


                                        return;
                                    }


                                    /*
                                    |--------------------------------------------------------------------------
                                    | Erro retornado pelo servidor
                                    |--------------------------------------------------------------------------
                                    */

                                    liberarFormulario(
                                        form,
                                        botao
                                    );


                                    const mensagemErro =
                                        resposta &&
                                        resposta.message
                                            ? resposta.message
                                            : 'Não foi possível registrar sua evidência. Tente novamente.';


                                    atualizarStatus(
                                        status,
                                        'Não foi possível enviar',
                                        mensagemErro,
                                        resposta.icon ||
                                        'fa-triangle-exclamation'
                                    );


                                    atualizarProgresso(
                                        status,
                                        0,
                                        'Envio não concluído.'
                                    );


                                    status.classList.remove(
                                        'sucesso'
                                    );


                                    status.classList.add(
                                        'erro'
                                    );
                                };


                            /*
                            |--------------------------------------------------------------------------
                            | Erro de rede
                            |--------------------------------------------------------------------------
                            */

                            xhr.onerror =
                                function () {

                                    liberarFormulario(
                                        form,
                                        botao
                                    );


                                    atualizarStatus(
                                        status,
                                        'Falha no envio',
                                        'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
                                        'fa-wifi'
                                    );


                                    atualizarProgresso(
                                        status,
                                        0,
                                        'Falha de conexão.'
                                    );


                                    status.classList.remove(
                                        'sucesso'
                                    );


                                    status.classList.add(
                                        'erro'
                                    );
                                };


                            /*
                            |--------------------------------------------------------------------------
                            | Abortado
                            |--------------------------------------------------------------------------
                            */

                            xhr.onabort =
                                function () {

                                    liberarFormulario(
                                        form,
                                        botao
                                    );


                                    atualizarStatus(
                                        status,
                                        'Envio cancelado',
                                        'O envio da evidência foi interrompido.',
                                        'fa-ban'
                                    );


                                    atualizarProgresso(
                                        status,
                                        0,
                                        'Envio cancelado.'
                                    );


                                    status.classList.remove(
                                        'sucesso'
                                    );


                                    status.classList.add(
                                        'erro'
                                    );
                                };


                            /*
                            |--------------------------------------------------------------------------
                            | Timeout
                            |--------------------------------------------------------------------------
                            */

                            xhr.ontimeout =
                                function () {

                                    liberarFormulario(
                                        form,
                                        botao
                                    );


                                    atualizarStatus(
                                        status,
                                        'Tempo excedido',
                                        'O servidor demorou muito para responder. Tente novamente.',
                                        'fa-clock'
                                    );


                                    atualizarProgresso(
                                        status,
                                        0,
                                        'Tempo de envio excedido.'
                                    );


                                    status.classList.remove(
                                        'sucesso'
                                    );


                                    status.classList.add(
                                        'erro'
                                    );
                                };


                            /*
                            |--------------------------------------------------------------------------
                            | Sem timeout artificial.
                            |--------------------------------------------------------------------------
                            |
                            | O servidor pode receber arquivos maiores em conexões
                            | mais lentas.
                            |--------------------------------------------------------------------------
                            */

                            xhr.timeout =
                                0;


                            /*
                            |--------------------------------------------------------------------------
                            | ENVIA
                            |--------------------------------------------------------------------------
                            */

                            xhr.send(
                                formData
                            );


                        } catch (erro) {

                            console.error(
                                '[MISSÃO ROCKET] Erro no envio:',
                                erro
                            );


                            liberarFormulario(
                                form,
                                botao
                            );


                            atualizarStatus(
                                status,
                                'Não foi possível enviar',
                                erro &&
                                erro.message
                                    ? erro.message
                                    : 'Ocorreu um erro ao preparar o arquivo.',
                                'fa-triangle-exclamation'
                            );


                            atualizarProgresso(
                                status,
                                0,
                                'Envio não iniciado.'
                            );


                            status.classList.remove(
                                'sucesso'
                            );


                            status.classList.add(
                                'erro'
                            );
                        }

                    }
                );

            }
        );

    }
);

</script>

</body>
</html>