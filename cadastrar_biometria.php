<?php 
require_once 'header.php'; 

// BLOQUEIO DE SEGURANÇA GESTOR: Só o admin faz o mapeamento
if (!isset($_SESSION['perfil']) || $_SESSION['perfil'] != 'admin') {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Acesso restrito para administradores da gestão.</div></div>";
    exit;
}

// Resgata o ID do usuário vindo do link do painel gerenciar.php
$usuario_alvo_id = $_GET['id'] ?? null;

if (!$usuario_alvo_id) {
    echo "<div class='container mt-4'><div class='alert alert-warning'>Nenhum usuário selecionado. Volte ao painel de gestão.</div></div>";
    exit;
}

// Busca o nome do usuário para exibir na tela de captura
$stmtUser = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
$stmtUser->execute([$usuario_alvo_id]);
$usuario_alvo = $stmtUser->fetch();

if (!$usuario_alvo) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>Jogador não encontrado no sistema.</div></div>";
    exit;
}
?>

<style>
    body { background-color: #0b0e14 !important; color: #f1f3f5 !important; font-family: 'Poppins', sans-serif; }
    .gamer-card { background: #161b26; border: 2px solid #242b3d; border-radius: 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); }
    .video-box { position: relative; width: 100%; max-width: 400px; margin: 0 auto; border-radius: 15px; overflow: hidden; border: 3px solid #6200ee; }
    #video { width: 100%; height: auto; transform: scaleX(-1); }
    #overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: scaleX(-1); }
</style>

<div class="container text-center mt-4">
    <h2 style="color: #bb86fc; text-shadow: 0 0 10px rgba(187,134,252,0.4);">🧬 ESCANEAMENTO GESTOR</h2>
    <p class="text-muted">Mapeando o rosto do player: <strong class="text-white"><?= htmlspecialchars($usuario_alvo['nome']) ?></strong></p>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card gamer-card p-4">
                <div class="video-box mb-3">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="overlay"></canvas>
                </div>
                
                <div id="status" class="alert alert-warning py-2 small">Carregando módulos de Alta Precisão...</div>
                
                <button id="btn-salvar" class="btn btn-primary w-100 fw-bold" disabled>
                    <i class="fa-solid fa-id-card"></i> GRAVAR BIOMETRIA
                </button>
                <a href="gerenciar.php" class="btn btn-sm btn-outline-secondary mt-3">Voltar ao Painel</a>
            </div>
        </div>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>

<script>
    const video = document.getElementById('video');
    const statusDiv = document.getElementById('status');
    const btnSalvar = document.getElementById('btn-salvar');
    let FaceDescriptorEncontrado = null;

    // Inicializa a câmera e carrega os modelos de IA de alta resolução (SSD Mobilenet)
    window.addEventListener('DOMContentLoaded', async () => {
        try {
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            // Carregamento do motor preciso para evitar falhas de leitura no totem posterior
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            
            statusDiv.innerHTML = "Módulos de precisão ativos! Iniciando lente...";
            statusDiv.className = "alert alert-info py-2 small";

            const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
            video.srcObject = stream;
        } catch (err) {
            statusDiv.innerHTML = "Erro ao iniciar módulos: " + err.message;
            statusDiv.className = "alert alert-danger py-2 small";
        }
    });

    // Rastreia o sensor da câmera em tempo real procurando o rosto completo
    video.addEventListener('play', () => {
        setInterval(async () => {
            // Utiliza o modelo estável padrão (ssdMobilenetv1) sem parâmetros extras
            const deteccao = await faceapi.detectSingleFace(video)
                                           .withFaceLandmarks()
                                           .withFaceDescriptor();
            
            if (deteccao) {
                FaceDescriptorEncontrado = deteccao.descriptor;
                statusDiv.innerHTML = "<i class='fa-solid fa-circle-check text-success'></i> Rosto travado com sucesso! Pronto para gravar.";
                statusDiv.className = "alert alert-success py-2 small";
                btnSalvar.disabled = false;
            } else {
                statusDiv.innerHTML = "<i class='fa-solid fa-triangle-exclamation text-warning'></i> Posicione o participante de frente para a tela.";
                statusDiv.className = "alert alert-warning py-2 small";
                btnSalvar.disabled = true;
            }
        }, 1000);
    });

    // Processa o envio estruturado via AJAX
    btnSalvar.addEventListener('click', () => {
        if(!FaceDescriptorEncontrado) return;

        btnSalvar.disabled = true;
        btnSalvar.innerHTML = "<i class='fa-solid fa-spinner fa-spin'></i> Gravando na Nuvem...";

        // Captura o ID do usuário de forma segura pela URL do navegador (?id=XX)
        const urlParams = new URLSearchParams(window.location.search);
        const idUsuarioAlvo = urlParams.get('id');

        if(!idUsuarioAlvo) {
            statusDiv.className = "alert alert-danger py-2 small";
            statusDiv.innerHTML = "Erro: ID do jogador ausente na barra de endereço.";
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = "GRAVAR BIOMETRIA";
            return;
        }

        $.ajax({
            url: 'salvar_biometria_action.php',
            type: 'POST',
            data: { 
                vetor_facial: JSON.stringify(Array.from(FaceDescriptorEncontrado)),
                usuario_alvo_id: idUsuarioAlvo
            },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'sucesso') {
                    statusDiv.className = "alert alert-success py-2 small";
                    statusDiv.innerHTML = "<i class='fa-solid fa-check-double'></i> Sucesso! Assinatura biométrica guardada na conta.";
                    btnSalvar.innerHTML = "CADASTRADO!";
                } else {
                    statusDiv.className = "alert alert-danger py-2 small";
                    statusDiv.innerHTML = "Erro interno: " + res.mensagem;
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = "TENTAR NOVAMENTE";
                }
            },
            error: function(xhr) {
                statusDiv.className = "alert alert-danger py-2 small";
                statusDiv.innerHTML = "Falha crítica de comunicação com o servidor.";
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = "TENTAR NOVAMENTE";
                console.error(xhr.responseText);
            }
        });
    });
</script>
</body>
</html>