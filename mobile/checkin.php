<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'header.php';

/*
|--------------------------------------------------------------------------
| AUTENTICAÇÃO
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

date_default_timezone_set('America/Recife');

$user_id = (int)$_SESSION['user_id'];
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

    .scanner-box {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 1.5rem;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.02);
        text-align: center;
    }

    #reader {
        width: 100%;
        border-radius: 12px;
        overflow: hidden;
        background: #000;
        min-height: 0;
    }

    #reader video {
        border-radius: 12px;
    }

    .btn-action {
        background-color: #8b5cf6;
        color: #fff;
        border: none;
        font-weight: 700;
        border-radius: 12px;
        padding: 0.75rem;
        width: 100%;
        transition: background 0.2s;
    }

    .btn-action:hover {
        background-color: #7c3aed;
        color: #fff;
    }

    .btn-action:active {
        background-color: #6d28d9;
        color: #fff;
    }

    .btn-action:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }

    .scanner-status {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 0.75rem;
    }

    .qr-info {
        display: none;
        margin-top: 1rem;
        padding: 0.75rem;
        background: #f1f5f9;
        border-radius: 12px;
        font-size: 0.8rem;
        color: #475569;
        word-break: break-word;
    }
</style>

<div class="main-wrapper">

    <!-- CABEÇALHO -->
    <div class="d-flex align-items-center mb-4">
        <a href="dashboard.php"
           class="btn btn-sm btn-outline-secondary rounded-3 me-3"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>

        <h4 class="fw-bold my-0">
            Check-in via QR Code
        </h4>
    </div>

    <!-- SCANNER -->
    <div class="scanner-box">

        <p class="text-muted small mb-3">
            Aponte a câmera para o QR Code projetado no telão do culto
            para validar sua presença.
        </p>

        <div id="reader" class="mb-3"></div>

        <!-- STATUS -->
        <div id="scannedResult" class="d-none">
            <div
                class="alert alert-info py-2 small text-center"
                id="statusMessage"
                role="alert"
            >
                Processando validação...
            </div>
        </div>

        <!-- INFORMAÇÃO DO QR -->
        <div id="qrInfo" class="qr-info"></div>

        <!-- BOTÃO -->
        <button
            type="button"
            class="btn-action"
            id="startBtn"
            onclick="iniciarScanner()"
        >
            <i class="fa-solid fa-camera me-2"></i>
            Abrir Câmera
        </button>

        <div id="scannerStatus" class="scanner-status">
            Câmera desligada
        </div>

    </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>

<script>
    /*
    |--------------------------------------------------------------------------
    | CONTROLE DO SCANNER
    |--------------------------------------------------------------------------
    */

    let html5QrcodeScanner = null;
    let scannerAtivo = false;
    let processamentoEmAndamento = false;


    /*
    |--------------------------------------------------------------------------
    | ELEMENTOS
    |--------------------------------------------------------------------------
    */

    const reader = document.getElementById('reader');
    const startBtn = document.getElementById('startBtn');
    const scannedResult = document.getElementById('scannedResult');
    const statusMessage = document.getElementById('statusMessage');
    const scannerStatus = document.getElementById('scannerStatus');
    const qrInfo = document.getElementById('qrInfo');


    /*
    |--------------------------------------------------------------------------
    | INICIAR SCANNER
    |--------------------------------------------------------------------------
    */

    function iniciarScanner() {

        if (scannerAtivo || processamentoEmAndamento) {
            return;
        }

        startBtn.disabled = true;
        startBtn.classList.add('d-none');

        scannedResult.classList.add('d-none');
        qrInfo.style.display = 'none';

        reader.style.display = 'block';

        scannerStatus.textContent = 'Inicializando câmera...';

        html5QrcodeScanner = new Html5Qrcode('reader');

        const config = {
            fps: 10,
            qrbox: {
                width: 250,
                height: 250
            },
            aspectRatio: 1.0
        };

        html5QrcodeScanner
            .start(
                {
                    facingMode: 'environment'
                },
                config,
                onScanSuccess,
                onScanError
            )
            .then(() => {

                scannerAtivo = true;

                startBtn.disabled = false;

                scannerStatus.textContent =
                    'Câmera ativa. Aponte para o QR Code.';

            })
            .catch((err) => {

                scannerAtivo = false;

                console.error('Erro ao iniciar câmera:', err);

                reader.style.display = 'none';

                startBtn.classList.remove('d-none');
                startBtn.disabled = false;

                scannerStatus.textContent =
                    'Não foi possível acessar a câmera.';

                mostrarStatus(
                    'danger',
                    'Não foi possível acessar a câmera. Verifique a permissão do navegador.'
                );
            });
    }


    /*
    |--------------------------------------------------------------------------
    | CALLBACK DE ERRO DO SCANNER
    |--------------------------------------------------------------------------
    |
    | Não mostramos erro para cada frame sem QR.
    |
    */

    function onScanError(errorMessage) {
        // Silencioso propositalmente.
    }


    /*
    |--------------------------------------------------------------------------
    | QR CODE ENCONTRADO
    |--------------------------------------------------------------------------
    */

    function onScanSuccess(decodedText, decodedResult) {

        if (processamentoEmAndamento) {
            return;
        }

        processamentoEmAndamento = true;

        /*
        |----------------------------------------------------------------------
        | PARA A CÂMERA IMEDIATAMENTE
        |----------------------------------------------------------------------
        */

        pararScanner()
            .finally(() => {

                reader.style.display = 'none';

                scannedResult.classList.remove('d-none');

                scannerStatus.textContent =
                    'QR Code identificado. Validando...';

                /*
                |--------------------------------------------------------------
                | VALIDAÇÃO DO CONTEÚDO
                |--------------------------------------------------------------
                */

                const provaId = extrairProvaId(decodedText);

                if (!provaId) {

                    processamentoEmAndamento = false;

                    mostrarStatus(
                        'danger',
                        'QR Code inválido. Utilize o código projetado para o check-in.'
                    );

                    mostrarBotaoNovamente();

                    return;
                }

                /*
                |--------------------------------------------------------------
                | MOSTRA APENAS O ID VALIDADO
                |--------------------------------------------------------------
                */

                qrInfo.textContent =
                    'Código identificado: #' + provaId;

                qrInfo.style.display = 'block';

                /*
                |--------------------------------------------------------------
                | ENVIA AO SERVIDOR
                |--------------------------------------------------------------
                */

                enviarCheckin(provaId);
            });
    }


    /*
    |--------------------------------------------------------------------------
    | EXTRAI O ID DA PROVA
    |--------------------------------------------------------------------------
    |
    | O QR atual do painel contém o ID da prova.
    |
    | Exemplos aceitos:
    |
    | 123
    | "123"
    |
    | Não aceitamos:
    |
    | https://...
    | javascript:...
    | texto livre
    | IDs negativos
    | IDs com caracteres
    |
    */

    function extrairProvaId(valor) {

        if (typeof valor !== 'string') {
            return null;
        }

        valor = valor.trim();

        /*
        | ID deve conter somente números.
        */

        if (!/^\d+$/.test(valor)) {
            return null;
        }

        const provaId = Number(valor);

        /*
        | Evita zero, negativos e valores absurdamente grandes.
        */

        if (!Number.isSafeInteger(provaId) || provaId <= 0) {
            return null;
        }

        return provaId;
    }


    /*
    |--------------------------------------------------------------------------
    | ENVIA CHECK-IN PARA O SERVIDOR
    |--------------------------------------------------------------------------
    */

    function enviarCheckin(provaId) {

        mostrarStatus(
            'info',
            'Processando seu check-in...'
        );

        /*
        | FormData evita problemas de encoding manual.
        */

        const formData = new URLSearchParams();

        formData.append('prova_id', String(provaId));

        fetch('processar_checkin.php', {
            method: 'POST',
            headers: {
                'Content-Type':
                    'application/x-www-form-urlencoded; charset=UTF-8',

                'X-Requested-With':
                    'XMLHttpRequest'
            },
            body: formData.toString(),
            credentials: 'same-origin'
        })

        .then(async (response) => {

            /*
            |--------------------------------------------------------------
            | TENTA LER JSON
            |--------------------------------------------------------------
            */

            const texto = await response.text();

            let data;

            try {
                data = JSON.parse(texto);
            } catch (e) {

                console.error(
                    'Resposta inválida do servidor:',
                    texto
                );

                throw new Error(
                    'O servidor retornou uma resposta inválida.'
                );
            }

            /*
            | Mesmo HTTP 200 pode trazer success=false.
            */

            return data;
        })

        .then((data) => {

            if (data && data.success === true) {

                mostrarStatus(
                    'success',
                    '🎉 ' + escaparHtml(
                        data.message || 'Check-in realizado com sucesso!'
                    )
                );

                scannerStatus.textContent =
                    'Check-in confirmado!';

                /*
                | Redireciona após confirmação.
                */

                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 2500);

                return;
            }

            /*
            |--------------------------------------------------------------
            | ERRO DE VALIDAÇÃO
            |--------------------------------------------------------------
            |
            | Aqui podem chegar mensagens como:
            |
            | - Evento não encontrado
            | - Check-in encerrado
            | - Check-in não disponível
            | - Usuário não autorizado
            | - Já realizou este check-in
            |
            */

            const mensagem =
                data && data.message
                    ? data.message
                    : 'Não foi possível realizar o check-in.';

            mostrarStatus(
                'danger',
                '❌ ' + escaparHtml(mensagem)
            );

            scannerStatus.textContent =
                'Check-in não realizado.';

            mostrarBotaoNovamente();
        })

        .catch((error) => {

            console.error(
                'Erro no processamento do check-in:',
                error
            );

            mostrarStatus(
                'danger',
                '❌ ' + escaparHtml(
                    error.message ||
                    'Erro de conexão com o servidor.'
                )
            );

            scannerStatus.textContent =
                'Falha na comunicação com o servidor.';

            mostrarBotaoNovamente();
        });
    }


    /*
    |--------------------------------------------------------------------------
    | PARA SCANNER
    |--------------------------------------------------------------------------
    */

    function pararScanner() {

        if (!html5QrcodeScanner || !scannerAtivo) {
            return Promise.resolve();
        }

        return html5QrcodeScanner
            .stop()
            .catch((err) => {
                console.warn(
                    'Não foi possível parar o scanner:',
                    err
                );
            })
            .finally(() => {

                scannerAtivo = false;

                try {
                    html5QrcodeScanner.clear();
                } catch (e) {
                    console.warn(
                        'Erro ao limpar scanner:',
                        e
                    );
                }

                html5QrcodeScanner = null;
            });
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRA STATUS
    |--------------------------------------------------------------------------
    */

    function mostrarStatus(tipo, mensagem) {

        statusMessage.className =
            'alert alert-' +
            tipo +
            ' py-2 small text-center fw-bold';

        statusMessage.innerHTML = mensagem;

        scannedResult.classList.remove('d-none');
    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR BOTÃO NOVAMENTE
    |--------------------------------------------------------------------------
    */

    function mostrarBotaoNovamente() {

        startBtn.disabled = false;
        startBtn.classList.remove('d-none');

        /*
        | Pequeno atraso para evitar clique imediato
        | enquanto o scanner ainda está sendo liberado.
        */

        setTimeout(() => {
            processamentoEmAndamento = false;
        }, 300);
    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    |
    | A mensagem do servidor nunca deve ser inserida diretamente no HTML.
    |
    */

    function escaparHtml(texto) {

        const div = document.createElement('div');

        div.textContent = String(texto);

        return div.innerHTML;
    }


    /*
    |--------------------------------------------------------------------------
    | LIMPEZA AO SAIR DA PÁGINA
    |--------------------------------------------------------------------------
    */

    window.addEventListener('beforeunload', () => {

        if (html5QrcodeScanner && scannerAtivo) {

            html5QrcodeScanner
                .stop()
                .catch(() => {});
        }
    });

</script>

</body>
</html>