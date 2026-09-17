<?php
// 1. IMPORTAÇÃO DO SEU ARQUIVO DE CONFIGURAÇÃO / BANCO DE DADOS
require __DIR__ . '/config.php'; 

// 2. IMPORTAÇÃO MANUAL DO PHPMAILER
require __DIR__ . '/PHPMailer-master/src/Exception.php';
require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // 3. CONSULTA ATUALIZADA:
    // Vincula usuários e provas vigentes que o usuário ainda não respondeu.
    $sql = "SELECT 
    u.id AS usuario_id,
    u.nome AS usuario_nome, 
    u.email AS usuario_email,
    p.id AS prova_id,
    p.titulo, 
    p.descricao, 
    p.pontos, 
    p.data_fim
FROM usuarios u
INNER JOIN provas p ON (p.tipo = 'global' OR p.grupo_id = u.grupo_id)
WHERE DATE(p.data_inicio) <= CURDATE()
  AND DATE(p.data_fim) >= CURDATE()
  AND p.titulo NOT LIKE '%Check-in no Culto %'
  AND p.id NOT IN (1, 2)
  AND NOT EXISTS (
      SELECT 1 
      FROM historico_pontos hp 
      WHERE hp.usuario_id = u.id 
        AND hp.prova_id = p.id
        AND DATE(hp.criado_em) = CURDATE()
  )
ORDER BY u.id ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($resultados)) {
        echo "Nenhuma prova vigente e pendente encontrada para os usuários hoje.\n";
        exit;
    }

    // 4. AGRUPAR AS PROVAS POR USUÁRIO
    $usuarios_provas = [];
    foreach ($resultados as $linha) {
        $uid = $linha['usuario_id'];
        if (!isset($usuarios_provas[$uid])) {
            $usuarios_provas[$uid] = [
                'nome'   => $linha['usuario_nome'],
                'email'  => $linha['usuario_email'],
                'provas' => []
            ];
        }
        $usuarios_provas[$uid]['provas'][] = [
            'titulo'    => $linha['titulo'],
            'descricao' => $linha['descricao'],
            'pontos'    => $linha['pontos'],
            'data_fim'  => $linha['data_fim']
        ];
    }

    // 5. CONFIGURAÇÃO DO SERVIDOR SMTP (GO DADDY) - Otimizado para manter conexão viva
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'mail.site.com.br';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'no-reply@site.com.br';
    $mail->Password   = 'password';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('no-reply@site.com.br', 'Missão Rocket');
    
    // 🔥 CONFIGURAÇÃO CHAVE PARA VELOCIDADE:
    // Mantém o túnel SMTP aberto para enviar o lote inteiro sem reconectar toda vez.
    $mail->SMTPKeepAlive = true; 

    // 6. LOOP DE ENVIO (Um e-mail compilado por Usuário)
    foreach ($usuarios_provas as $id_usuario => $dados) {
        try {
            $mail->clearAddresses(); 
            $mail->addAddress($dados['email'], $dados['nome']);

            // Monta a lista visual de todas as provas pendentes daquele usuário
            $listaProvasHTML = "";
            foreach ($dados['provas'] as $prova) {
                $dataLimite = date('d/m/Y', strtotime($prova['data_fim']));
                
                $listaProvasHTML .= "
                <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; margin-bottom: 15px;'>
                    <p style='margin: 0 0 6px 0; font-size: 1rem; color: #8b5cf6;'><strong>🏆 Prova:</strong> {$prova['titulo']}</p>
                    <p style='margin: 0 0 6px 0; font-size: 0.9rem; color: #334155; line-height: 1.4;'><strong>📋 Descrição:</strong> {$prova['descricao']}</p>
                    <p style='margin: 0 0 6px 0; font-size: 0.9rem; color: #10b981;'><strong>💎 Valor:</strong> {$prova['pontos']} pontos</p>
                    <p style='margin: 0; font-size: 0.9rem; color: #ef4444;'><strong>📅 Data Limite:</strong> {$dataLimite}</p>
                </div>";
            }

            // LAYOUT GERAL DA MISSÃO ROCKET
            $mensagemHTML = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Suas Missões do Dia</title>
            </head>
            <body style='font-family: sans-serif; background-color: #f7f9fc; color: #1e293b; padding: 20px; margin: 0;'>
                <div style='max-width: 420px; margin: 0 auto; background: #ffffff; border-radius: 18px; padding: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <span style='background: linear-gradient(135deg, #febb12 0%, #a066ff 100%); color: white; padding: 10px 20px; font-weight: 800; border-radius: 12px; display: inline-block; font-size: 1.2rem;'>
                            🚀 MISSÃO ROCKET
                        </span>
                    </div>
                    <h2 style='color: #0f172a; margin-bottom: 5px; font-weight: 800;'>Olá, {$dados['nome']}!</h2>
                    <p style='color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-top: 0;'>Você possui novas atividades vigentes aguardando resposta na Arena. Confira o seu resumo diário:</p>
                    
                    <p style='margin: 20px 0 10px 0; font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;'>📋 Provas Disponíveis Hoje:</p>
                    
                    {$listaProvasHTML}
                    
                    <p style='color: #64748b; font-size: 0.8rem; text-align: center; margin-top: 20px;'>Fique atento aos prazos para não perder pontos na Arena!</p>
                    
                    <div style='text-align: center; margin-top: 25px;'>
                        <a href='https://missaorocket.com.br/mobile/' style='background-color: #8b5cf6; color: white; text-decoration: none; padding: 12px 30px; font-weight: bold; border-radius: 12px; display: inline-block; box-shadow: 0 4px 10px rgba(139, 92, 246, 0.2);'>
                            Entrar na Arena
                        </a>
                    </div>
                </div>
            </body>
            </html>
            ";

            $mail->isHTML(true);
            $mail->Subject = '🚀 Resumo Diário: Novas Provas Disponíveis na Arena';
            $mail->Body    = $mensagemHTML;
            $mail->AltBody = "Olá {$dados['nome']}, você possui novas provas pendentes e vigentes na Missão Rocket. Acesse a Arena para conferir.";

            $mail->send();
            echo "Resumo de e-mail enviado com sucesso para: {$dados['email']}\n";

        } catch (Exception $e) {
            echo "Erro ao enviar para {$dados['email']}: {$mail->ErrorInfo}\n";
            
            // Se houver desconexão abrupta do servidor SMTP da GoDaddy, limpa para reiniciar na próxima volta
            if (!$mail->getSMTPInstance()->connected()) {
                $mail->smtpClose();
            }
        }
    }

    // 🔥 Fecha a conexão persistente depois de processar todos os tripulantes
    $mail->smtpClose();

} catch (PDOException $e) {
    die("Erro no banco de dados: " . $e->getMessage());
}