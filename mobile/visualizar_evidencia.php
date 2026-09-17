<?php

require_once '../config.php';

date_default_timezone_set('America/Recife');

/*
|--------------------------------------------------------------------------
| SEGURANÇA
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] !== 'admin') {
    http_response_code(403);
    exit('Acesso negado.');
}

/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    http_response_code(400);
    exit('ID da evidência inválido.');
}

/*
|--------------------------------------------------------------------------
| BUSCAR EVIDÊNCIA
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT evidencia
    FROM historico_pontos
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$evidencia = $stmt->fetchColumn();

if ($evidencia === false || $evidencia === null || $evidencia === '') {
    http_response_code(404);
    exit('Evidência não encontrada.');
}

/*
|--------------------------------------------------------------------------
| IDENTIFICAR MIME
|--------------------------------------------------------------------------
*/
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->buffer($evidencia);

$tiposPermitidos = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'application/pdf'
];

if (!in_array($mime, $tiposPermitidos, true)) {
    $mime = 'application/octet-stream';
}

/*
|--------------------------------------------------------------------------
| DOWNLOAD ORIGINAL
|--------------------------------------------------------------------------
|
| Quando ?download=1:
| - NÃO redimensiona
| - NÃO comprime
| - entrega exatamente o BLOB original
|
*/
$download = isset($_GET['download']) && $_GET['download'] === '1';

if ($download) {

    $extensoes = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
        'image/gif'        => 'gif',
        'application/pdf'  => 'pdf'
    ];

    $ext = $extensoes[$mime] ?? 'bin';

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($evidencia));

    header(
        'Content-Disposition: attachment; filename="evidencia_' .
        $id .
        '.' .
        $ext .
        '"'
    );

    echo $evidencia;
    exit;
}

/*
|--------------------------------------------------------------------------
| VISUALIZAÇÃO DE IMAGEM
|--------------------------------------------------------------------------
|
| Aqui está a otimização.
|
| O arquivo original pode ter vários MB.
| Para visualização:
|
| - máximo 1200 x 1200
| - JPEG qualidade 72
|
*/
if (strpos($mime, 'image/') === 0) {

    /*
    |--------------------------------------------------------------------------
    | Verificar GD
    |--------------------------------------------------------------------------
    */
    if (!function_exists('imagecreatefromstring')) {

        // Caso GD não esteja habilitado,
        // entrega o original para não quebrar a visualização.

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($evidencia));
        header('Cache-Control: private, max-age=3600');

        echo $evidencia;
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Criar imagem
    |--------------------------------------------------------------------------
    */
    $imagem = @imagecreatefromstring($evidencia);

    if ($imagem === false) {

        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($evidencia));

        echo $evidencia;
        exit;
    }

    $larguraOriginal = imagesx($imagem);
    $alturaOriginal  = imagesy($imagem);

    /*
    |--------------------------------------------------------------------------
    | Limite da visualização
    |--------------------------------------------------------------------------
    */
    $maxLargura = 1200;
    $maxAltura  = 1200;

    $escala = min(
        $maxLargura / $larguraOriginal,
        $maxAltura / $alturaOriginal,
        1
    );

    $novaLargura = max(
        1,
        (int) round($larguraOriginal * $escala)
    );

    $novaAltura = max(
        1,
        (int) round($alturaOriginal * $escala)
    );

    /*
    |--------------------------------------------------------------------------
    | Criar imagem reduzida
    |--------------------------------------------------------------------------
    */
    $novaImagem = imagecreatetruecolor(
        $novaLargura,
        $novaAltura
    );

    /*
    |--------------------------------------------------------------------------
    | Fundo branco
    |--------------------------------------------------------------------------
    |
    | Evita problemas de transparência ao converter PNG/WebP
    | para JPEG.
    |
    */
    $branco = imagecolorallocate(
        $novaImagem,
        255,
        255,
        255
    );

    imagefill(
        $novaImagem,
        0,
        0,
        $branco
    );

    /*
    |--------------------------------------------------------------------------
    | Redimensionar
    |--------------------------------------------------------------------------
    */
    imagecopyresampled(
        $novaImagem,
        $imagem,
        0,
        0,
        0,
        0,
        $novaLargura,
        $novaAltura,
        $larguraOriginal,
        $alturaOriginal
    );

    /*
    |--------------------------------------------------------------------------
    | Gerar JPEG otimizado
    |--------------------------------------------------------------------------
    */
    ob_start();

    imagejpeg(
        $novaImagem,
        null,
        72
    );

    $saida = ob_get_clean();

    /*
    |--------------------------------------------------------------------------
    | Liberar memória
    |--------------------------------------------------------------------------
    */
    imagedestroy($imagem);
    imagedestroy($novaImagem);

    /*
    |--------------------------------------------------------------------------
    | CACHE DO NAVEGADOR
    |--------------------------------------------------------------------------
    */
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . strlen($saida));

    header(
        'Cache-Control: private, max-age=3600'
    );

    echo $saida;
    exit;
}

/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/
if ($mime === 'application/pdf') {

    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($evidencia));

    header('Content-Disposition: inline');

    header(
        'Cache-Control: private, max-age=3600'
    );

    echo $evidencia;
    exit;
}

/*
|--------------------------------------------------------------------------
| OUTROS FORMATOS
|--------------------------------------------------------------------------
*/
http_response_code(415);

echo 'Este tipo de arquivo não pode ser visualizado diretamente.';

exit;