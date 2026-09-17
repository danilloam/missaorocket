<?php 
require_once 'header.php'; 

// Proteção: Apenas administradores ou o gerenciador do evento podem abrir o Totem
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso restrito para a gestão iniciar o Totem.</div></div>";
    exit;
}
?>

<style>
    body {
        background-color: #0b0e14 !important;
        color: #f1f3f5 !important;
        font-family: 'Poppins', sans-serif;
    }
    .totem-card {
        background: #161b26;
        border: 2px solid #03dac6;
        border-radius: 24px;
        box-shadow: 0 0 30px rgba(3, 218, 198, 0.2);
        overflow: hidden;
    }
    .video-container {
        position: relative;
        width: 100%;
        max-width: 440px;
        margin: 0 auto;
        border-radius: 20px;
        overflow: hidden;
        border: 4px solid #6200ee;
        box-shadow: 0 0 25px rgba(98, 0, 238, 0.4);
    }
    #video {
        width: 100%;
        height: auto;
        transform: scaleX(-1); /* Espelho */
    }
    .scanner-laser {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 6px;
        background: linear-gradient(to bottom, rgba(0, 240, 255, 0), #00f0ff);
        animation: scan 2.5s linear infinite;
        box-shadow: 0 0 15px #00f0ff;
    }
    @keyframes scan {
        0% { top: 0%; }
        50% { top: 100%; }
        100% { top: 0%; }
    }
    .welcome-box {
        min-height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
</style>

<div class="container text-center mt-3">
    <h1 class="font-weight-bold" style="color: #03dac6; text-shadow: 0 0 15px rgba(3, 218, 198, 0.4); font-family: 'Orbitron', sans-serif;">
        <i class="fa-solid fa-expand"></i> TOTEM FACE ID
    </h1>
    <p class="text-muted mb-4">ESTAÇÃO DE EMBARQUE AUTOMÁTICO — RECONHECIMENTO EM TEMPO REAL</p>

    <div class="row justify-content-center">
        <div class="col-md-7">
            <div class="card totem-card p-4">
                
                <div id="feedback-totem" class="alert alert-info py-3 mb-3 h5 welcome-box">
                    <i class="fa-solid fa-sync fa-spin me-2"></i> Iniciando Scanners Faciais...
                </div>

                <div class="video-container mb-3">
                    <div class="scanner-laser"></div>
                    <video id="video" autoplay muted playsinline></video>
                </div>

                <p class="text-muted small m-0"><i class="fa-solid fa-circle-info"></i> Aproxime-se e olhe fixamente para a tela para pontuar.</p>
            </div>
        </div>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

<script>
    let video, feedback;
    let emProcessamento = false;

    function atualizarStatus(mensagem, classe) {
        if (feedback) {
            feedback.className = `alert alert-${classe} py-3 mb-3 h5 welcome-box`;
            feedback.innerHTML = mensagem;
        }
    }

    window.addEventListener('DOMContentLoaded', async () => {
        video = document.getElementById('video');
        feedback = document.getElementById('feedback-totem');

        try {
            // Carrega os modelos inteligentes de alta precisão (SSD Mobilenet) para bater com o cadastro
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

            atualizarStatus("<i class='fa-solid fa-eye text-neon-cyan'></i> TOTEM ATIVO: Aproxime seu rosto para o Check-in", "dark");

            // Liga a câmera do Totem fixo
            const stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 } });
            video.srcObject = stream;

        } catch (error) {
            atualizarStatus("Falha ao inicializar sensores: " + error.message, "danger");
        }
    });

    // Loop de rastreamento contínuo automático (Sem precisar clicar em botão)
    video.addEventListener('play', () => {
        setInterval(async () => {
            // Se já estiver processando a pontuação de alguém, pula o ciclo para evitar duplicidade
            if (emProcessamento) return;

            // Utiliza o modelo estável padrão (ssdMobilenetv1) sem parâmetros extras para máxima fidelidade
            const deteccao = await faceapi.detectSingleFace(video)
                                           .withFaceLandmarks()
                                           .withFaceDescriptor();

            if (deteccao) {
                emProcessamento = true; // Trava o scanner temporariamente
                atualizarStatus("<i class='fa-solid fa-fingerprint fa-bounce'></i> Identificando Player...", "warning");

                // Envia o vetor do rosto detectado para o banco tentar descobrir de quem é
                $.ajax({
                    url: 'processar_totem_action.php',
                    type: 'POST',
                    data: { vetor_atual: JSON.stringify(Array.from(deteccao.descriptor)) },
                    dataType: 'json',
                    success: function(res) {
                        if (res.status === 'sucesso') {
                            // Sucesso: Mostra uma mensagem gamer estilizada com o nome do jovem
                            atualizarStatus(`⚡ ACESSO LIBERADO!<br>Olá, ${res.nome}! +${res.pontos} Skills na conta!`, "success");
                            
                            // Mantém a mensagem de sucesso por 4 segundos e depois libera para o próximo da fila
                            setTimeout(() => {
                                atualizarStatus("<i class='fa-solid fa-eye'></i> Próximo da fila, aproxime-se...", "dark");
                                emProcessamento = false;
                            }, 4000);
                        } else {
                            // Se der erro (Já fez hoje ou não cadastrado), exibe o motivo
                            atualizarStatus(`❌ ${res.mensagem}`, "danger");
                            
                            // Libera o scanner de novo após 3 segundos
                            setTimeout(() => {
                                atualizarStatus("<i class='fa-solid fa-eye'></i> Pronto para escanear...", "dark");
                                emProcessamento = false;
                            }, 3000);
                        }
                    },
                    error: function() {
                        atualizarStatus("Erro de comunicação com a central.", "danger");
                        emProcessamento = false;
                    }
                });
            }
        }, 1200); // Tenta ler um rosto a cada 1.2 segundos
    });
</script>
</body>
</html>