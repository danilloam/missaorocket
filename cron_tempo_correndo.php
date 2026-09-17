<?php
// 1. DECLARAÇÃO DE NAMESPACES (OBRIGATORIAMENTE NO TOPO DO ARQUIVO)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 2. IMPORTAÇÃO DO SEU ARQUIVO DE CONFIGURAÇÃO / BANCO DE DADOS
require __DIR__ . '/config.php'; 

// Pegamos o início e o fim do dia atual de forma estática
$inicioHoje = date('Y-m-d 00:00:00');
$fimHoje    = date('Y-m-d 23:59:59');
$hoje       = date('Y-m-d');

try {
    
    // 3. CONSULTA ULTRA RESTRITA (Roda instantaneamente em localhost)
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
            LEFT JOIN historico_pontos hp ON (
                hp.usuario_id = u.id 
                AND hp.prova_id = p.id 
                AND hp.criado_em BETWEEN :inicio_hoje AND :fim_hoje
            )
            WHERE p.data_inicio <= :hoje_1
              AND p.data_fim >= :hoje_2
              AND p.titulo NOT LIKE '%Check-in no Culto %'
              AND p.id NOT IN (1, 2)
              AND hp.id IS NULL
            ORDER BY u.id ASC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'inicio_hoje' => $inicioHoje,
        'fim_hoje'    => $fimHoje,
        'hoje_1'      => $hoje,
        'hoje_2'      => $hoje
    ]);

    // Agrupamento rápido em memória
    $usuarios_provas = [];
    while ($linha = $stmt->fetch(PDO::FETCH_ASSOC)) {
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

    $stmt->closeCursor();

    // Se não tem ninguém para receber, o script morre aqui de forma instantânea
    if (empty($usuarios_provas)) {
        echo "Nenhum tripulante com provas pendentes para lembrar hoje.\n";
        exit;
    }

    // 4. INCLUSÃO DOS ARQUIVOS FÍSICOS DO PHPMAILER (Apenas se houver e-mails para processar)
    require __DIR__ . '/PHPMailer-master/src/Exception.php';
    require __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    require __DIR__ . '/PHPMailer-master/src/SMTP.php';

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
    $mail->SMTPKeepAlive = true; 

    // 🔥 OTIMIZAÇÕES ANTI-LATÊNCIA DA GODADDY
    $mail->Hostname = 'site'; // Pula a busca lenta de DNS reverso local
    $mail->XMailer  = ' ';                   // Corta cabeçalhos redundantes

    // Desativa a verificação estrita de SSL local
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];

    // 5. LOOP DE ENVIO
    foreach ($usuarios_provas as $id_usuario => $dados) {
        try {
            $mail->clearAddresses(); 
            $mail->addAddress($dados['email'], $dados['nome']);

            $listaProvasHTML = "";
            foreach ($dados['provas'] as $prova) {
                $dataLimiteOriginal = date('d/m/Y', strtotime($prova['data_fim']));
                
                if ($prova['data_fim'] === $hoje) {
                    $textoPrazo = "<span style='background-color: #dc2626; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 900; font-size: 0.8rem; letter-spacing: 0.5px; display: inline-block;'>⚠️ EXPIRA HOJE!</span>";
                } else {
                    $textoPrazo = "<span style='color: #475569; font-weight: 700;'>⏰ Até: {$dataLimiteOriginal}</span>";
                }
                
                $listaProvasHTML .= "
                <div style='background-color: #fdfdfd; border: 1px solid #e2e8f0; border-left: 5px solid #f59e0b; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);'>
                    <p style='margin: 0 0 6px 0; font-size: 1rem; color: #1e293b; font-weight: 700;'>⚡ {$prova['titulo']}</p>
                    <p style='margin: 0 0 12px 0; font-size: 0.88rem; color: #475569; line-height: 1.4;'>{$prova['descricao']}</p>
                    <div style='display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e2e8f0;'>
                        <span style='background-color: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; display: inline-block;'>💎 +{$prova['pontos']} PTS</span>
                        <div style='font-size: 0.85rem;'>{$textoPrazo}</div>
                    </div>
                </div>";
            }
            
            $dataHoraEnvio = date('d/m/Y \à\s H:i:s');
            $idTransacao   = strtoupper(uniqid('MR-'));
            
            $mensagemHTML = "
            <html>
            <head><meta charset='UTF-8'></head>
            <body style='font-family: sans-serif; background-color: #f1f5f9; color: #1e293b; padding: 20px; margin: 0;'>
                <div style='max-width: 440px; margin: 0 auto; background: #ffffff; border-radius: 24px; padding: 30px 25px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;'>
                    <div style='text-align: center; margin-bottom: 25px;'>
                        <span style='background: #ef4444; color: white; padding: 6px 18px; font-weight: 800; border-radius: 30px; display: inline-block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1.2px;'>⏰ CRONÔMETRO ATIVADO</span>
                    </div>
                    <h2 style='color: #0f172a; margin: 0 0 8px 0; font-weight: 900; font-size: 1.45rem; text-align: center;'>Não perca o prazo, {$dados['nome']}!</h2>
                    <p style='color: #475569; font-size: 0.95rem; line-height: 1.6; margin: 0 0 20px 0; text-align: center;'>O painel de controle detectou que a sua conta ainda possui <strong>atividades pendentes</strong> hoje.</p>
                    <div style='background: linear-gradient(90deg, rgba(245,158,11,0) 0%, #f59e0b 50%, rgba(245,158,11,0) 100%); height: 2px; margin: 20px 0;'></div>
                    <p style='margin: 0 0 14px 0; font-size: 0.8rem; color: #64748b; text-transform: uppercase; font-weight: 800; letter-spacing: 0.8px;'>⚠️ NÃO DEIXE PARA DEPOIS:</p>
                    {$listaProvasHTML}
                    <p style='color: #64748b; font-size: 0.85rem; text-align: center; margin: 25px 0 15px 0; line-height: 1.5;'>Garantir esses pontos é fundamental para a sua evolução e para a sua equipe na Arena!</p>
                    <div style='text-align: center; margin-top: 15px; margin-bottom: 25px;'>
                        <a href='https://missaorocket.com.br/mobile/' style='background: linear-gradient(135deg, #7c3aed 0%, #4c1d95 100%); color: white; text-decoration: none; padding: 15px 40px; font-weight: 800; border-radius: 14px; display: inline-block; box-shadow: 0 6px 20px rgba(124, 58, 237, 0.4); font-size: 1.05rem; text-transform: uppercase;'>🚀 RESPONDER AGORA</a>
                    </div>
                    <div style='border-top: 1px solid #f1f5f9; padding-top: 15px; text-align: center;'>
                        <p style='margin: 0; font-size: 0.72rem; color: #94a3b8; font-family: monospace; line-height: 1.4;'>
                            Notificação gerada automaticamente em: {$dataHoraEnvio}<br>
                            Ref: {$idTransacao} | Missão Rocket Sistema de Controle
                        </p>
                    </div>
                </div>
            </body>
            </html>";

            $mail->isHTML(true);
            $mail->Subject = '⏰ Não perca seus pontos! Você tem provas pendentes na Arena';
            $mail->Body    = $mensagemHTML;
            $mail->AltBody = "Ei {$dados['nome']}, você ainda tem provas pendentes na Arena.";

            $mail->send();
            echo "Lembrete enviado com sucesso para: {$dados['email']}\n";

        } catch (Exception $e) {
            echo "Erro ao enviar: {$mail->ErrorInfo}\n";
            if (!$mail->getSMTPInstance()->connected()) { $mail->smtpClose(); }
        }
    }
    $mail->smtpClose();
} catch (PDOException $e) { die("Erro: " . $e->getMessage()); }