<?php
// Ativa a exibição de erros para testes caso necessário (recomenda-se desativar em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../config.php'; // Garante o caminho correto partindo de onde o script está

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Função para disparar push notification baseado no status da evidência ou dados customizados
 * * @param PDO $pdo Instância de conexão do banco de dados
 * @param int $usuario_id ID do comandante que receberá o aviso
 * @param string $status Aceita variações de aprovação ou reprovação
 * @param array|null $dadosCustomizados Array contendo ['title', 'body', 'url'] de forma dinâmica
 * @return array Retorna um resumo com o status dos envios
 */
function dispararNotificacaoEvidencia($pdo, $usuario_id, $status, $dadosCustomizados = null) {
    // Chaves de criptografia VAPID
    $auth = [
        'VAPID' => [
            'subject'    => 'https://www.site.com.br', 
            'publicKey'  => '',
            'privateKey' => '', 
        ],
    ];

    $statusLimpo = strtolower(trim($status));
    $targetUrl = 'dashboard.php';

    // 1. Se NÃO passar dados customizados, usa a lógica condicional baseada no status
    if (empty($dadosCustomizados)) {
        if ($statusLimpo === 'aprovada' || $statusLimpo === 'aprovado') {
            $titulo = '🚀 Missão Rocket';
            $corpo  = 'Sua evidência foi aprovada! Muito bom, Comandante!';
            $targetUrl = 'gerenciar.php'; 
        } elseif ($statusLimpo === 'reprovada' || $statusLimpo === 'rejeitado' || $statusLimpo === 'rejeitada') {
            $titulo = '⚠️ Missão Rocket';
            $corpo  = 'Sua evidência foi reprovada! Entre no aplicativo e revise o envio.';
            $targetUrl = 'gerenciar.php';
        } else {
            $titulo = '🚀 Missão Rocket';
            $corpo  = 'Sua evidência foi revisada! Entre na arena para conferir.';
        }
    } else {
        // 2. Se PASSAR dados customizados, assume o controle completo (Como feito em provas.php)
        $titulo    = $dadosCustomizados['title'] ?? '🚀 Missão Rocket';
        $corpo     = $dadosCustomizados['body'] ?? 'Nova atualização na Arena!';
        $targetUrl = $dadosCustomizados['url'] ?? 'dashboard.php';
    }

    // Estrutura o payload JSON que o Service Worker (sw.js) vai interpretar
    $payload = json_encode([
        'title' => $titulo,
        'body'  => $corpo,
        'url'   => $targetUrl
    ]);

    try {
        // Busca os dispositivos inscritos do usuário alvo
        $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth FROM usuarios_notificacoes WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dispositivos)) {
            return [
                'status' => 'sem_dispositivos',
                'mensagem' => 'Nenhum dispositivo cadastrado para este usuário.'
            ];
        }

        $webPush = new WebPush($auth);

        foreach ($dispositivos as $disp) {
            $subscription = Subscription::create([
                'endpoint'  => $disp['endpoint'],
                'publicKey' => $disp['p256dh'],
                'authToken' => $disp['auth'],
            ]);

            $webPush->queueNotification($subscription, $payload);
        }

        $sucessos = 0;
        $falhas = 0;

        // Processa a fila de disparos e limpa tokens expirados automaticamente
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if ($report->isSuccess()) {
                $sucessos++;
            } else {
                $falhas++;
                $motivo = $report->getReason();
                
                // Se o token expirou ou o usuário removeu a permissão nativa, deleta do banco para otimizar os próximos disparos
                if (strpos($motivo, 'expired') !== false || strpos($motivo, 'gone') !== false) {
                    $stmtDelete = $pdo->prepare("DELETE FROM usuarios_notificacoes WHERE endpoint = ?");
                    $stmtDelete->execute([$endpoint]);
                }
            }
        }

        return [
            'status' => 'processado',
            'sucessos' => $sucessos,
            'falhas' => $falhas
        ];

    } catch (Exception $e) {
        return [
            'status' => 'erro',
            'mensagem' => $e->getMessage()
        ];
    }
}