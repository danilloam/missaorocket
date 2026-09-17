<script>
    let video, btnScan, feedback;
    let biometriaCadastrada = null;
    let localizacaoAtual = null;

    function atualizarFeedback(mensagem, classe) {
        if (feedback) {
            feedback.className = `alert alert-${classe} py-2 small`;
            feedback.innerHTML = mensaje = mensagem; 
        }
    }

    // Captura as coordenadas do usuário de forma nativa
    function obterGeolocalizacao() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    localizacaoAtual = {
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    };
                },
                (error) => {
                    console.warn("Erro ao obter localização: " + error.message);
                    atualizarFeedback("⚠️ Você precisa permitir o acesso à localização para fazer check-in.", "danger");
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            atualizarFeedback("Seu navegador não suporta geolocalização.", "danger");
        }
    }

    window.addEventListener('DOMContentLoaded', async () => {
        video = document.getElementById('video');
        btnScan = document.getElementById('btn-scan');
        feedback = document.getElementById('feedback-biometria');

        atualizarFeedback("Carregando chaves criptográficas faciais e GPS...", "warning");
        obterGeolocalizacao();

        try {
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            // CORREÇÃO: Mudado para ssdMobilenetv1 para bater exatamente com o modelo do cadastro
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

            $.ajax({
                url: 'buscar_minha_biometria.php',
                type: 'GET',
                dataType: 'json',
                success: async function(res) {
                    if(res.status === 'sucesso' && res.vetor) {
                        biometriaCadastrada = new Float32Array(JSON.parse(res.vetor));
                        atualizarFeedback("Aponte para a câmera e clique em Escanear Rosto!", "info");
                        
                        const stream = await navigator.mediaDevices.getUserMedia({ video: {} });
                        video.srcObject = stream;
                    } else {
                        atualizarFeedback("Você ainda não possui Face ID cadastrado. <a href='cadastrar_biometria.php' class='alert-link'>Cadastre aqui primeiro!</a>", "danger");
                        if(btnScan) btnScan.disabled = true;
                    }
                },
                error: function() {
                    atualizarFeedback("Erro ao estabelecer conexão segura com a nuvem biométrica.", "danger");
                }
            });

            if (btnScan) {
                btnScan.addEventListener('click', executarVerificacaoFacial);
            }

        } catch (error) {
            atualizarFeedback("Falha crítica nos módulos de IA: " + error.message, "danger");
        }
    });

    async function executarVerificacaoFacial() {
        if(!biometriaCadastrada) return;

        // Se o GPS falhar ou o usuário rejeitar, bloqueia o clique
        if (!localizacaoAtual) {
            atualizarFeedback("Aguardando sinal do GPS... Certifique-se de que a localização está ativa.", "warning");
            obterGeolocalizacao();
            return;
        }

        atualizarFeedback("Analisando autenticidade e localização...", "info");

        // CORREÇÃO: Mudado para detecção precisa acompanhando o ssdMobilenetv1 carregado
        const deteccaoAtual = await faceapi.detectSingleFace(video)
                                            .withFaceLandmarks()
                                            .withFaceDescriptor();

        if(!deteccaoAtual) {
            atualizarFeedback("Nenhum rosto focado. Fique imóvel em frente à câmera.", "danger");
            return;
        }

        const distancia = faceapi.euclideanDistance(biometriaCadastrada, deteccaoAtual.descriptor);

        if (distancia < 0.50) {
            atualizarFeedback("Biometria confirmada! Validando perímetro do evento...", "info");

            // Captura um frame do vídeo para enviar uma imagem real em vez da string estática corrompida
            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const imagemBase64 = canvas.toDataURL('image/jpeg');

            // Envia a biometria + as coordenadas coletadas
            $.ajax({
                url: 'processar_checkin.php',
                type: 'POST',
                data: { 
                    imagem_rosto: imagemBase64,
                    lat: localizacaoAtual.latitude,
                    lng: localizacaoAtual.longitude
                }, 
                dataType: 'json',
                success: function(response) {
                    if(response.status === 'sucesso') {
                        atualizarFeedback(`Sucesso total! ${response.mensagem}`, "success");
                        if(btnScan) {
                            btnScan.disabled = true;
                            btnScan.innerHTML = "<i class='fa-solid fa-check'></i> CHECK-IN REALIZADO";
                        }
                    } else {
                        atualizarFeedback(response.mensagem, "danger");
                    }
                },
                error: function() {
                    atualizarFeedback("Erro na comunicação com o servidor de validação.", "danger");
                }
            });
        } else {
            atualizarFeedback("❌ Impostor ou falha crítica de correspondência!", "danger");
        }
    }
</script>