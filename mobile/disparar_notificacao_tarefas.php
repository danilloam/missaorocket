<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../config.php';


/**
 * Envia uma notificação para TODOS os dispositivos/aplicativos
 * que possuem uma inscrição registrada na tabela usuarios_notificacoes.
 *
 * @param PDO $pdo
 * @param array|null $dadosCustomizados ['title', 'body', 'url']
 * @return array
 */
function dispararNotificacaoGlobal($pdo, $dadosCustomizados = null)
{
    // Chaves VAPID
    $auth = [
        'VAPID' => [
            'subject'    => 'https://www.site.com.br',
            'publicKey'  => '',
            'privateKey' => '',
        ],
    ];

    // Dados da notificação
    $titulo = $dadosCustomizados['title'] ?? '🚀 Missão Rocket';
    $corpo  = $dadosCustomizados['body']  ?? 'Novas missões foram atribuidas para você na Arena!';
    $url    = $dadosCustomizados['url']   ?? 'dashboard.php';

    // Payload enviado ao Service Worker
    $payload = json_encode([
        'title' => $titulo,
        'body'  => $corpo,
        'url'   => $url
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {

        /*
         * Busca TODOS os dispositivos cadastrados.
         *
         * Não existe mais:
         * WHERE usuario_id = ?
         */
        $stmt = $pdo->query("
            SELECT id, usuario_id, endpoint, p256dh, auth
            FROM usuarios_notificacoes
            WHERE endpoint IS NOT NULL
              AND p256dh IS NOT NULL
              AND auth IS NOT NULL
        ");

        $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dispositivos)) {
            return [
                'status' => 'sem_dispositivos',
                'mensagem' => 'Nenhum aplicativo/dispositivo possui inscrição de notificação.'
            ];
        }

        $webPush = new WebPush($auth);

        $total = 0;

        /*
         * Coloca TODOS os dispositivos na fila.
         */
        foreach ($dispositivos as $disp) {

            try {

                $subscription = Subscription::create([
                    'endpoint'  => $disp['endpoint'],
                    'publicKey' => $disp['p256dh'],
                    'authToken' => $disp['auth'],
                ]);

                $webPush->queueNotification(
                    $subscription,
                    $payload
                );

                $total++;

            } catch (Exception $e) {

                error_log(
                    'Erro ao adicionar dispositivo à fila: ' .
                    $e->getMessage()
                );
            }
        }

        $sucessos = 0;
        $falhas   = 0;
        $removidos = 0;

        /*
         * Dispara todas as notificações.
         */
        foreach ($webPush->flush() as $report) {

            $endpoint = $report->getEndpoint();

            if ($report->isSuccess()) {

                $sucessos++;

            } else {

                $falhas++;

                $motivo = strtolower($report->getReason());

                /*
                 * Endpoint inválido/expirado.
                 *
                 * 404/410 normalmente indicam que a inscrição
                 * não é mais válida.
                 */
                if (
                    strpos($motivo, 'expired') !== false ||
                    strpos($motivo, 'gone') !== false ||
                    strpos($motivo, '404') !== false ||
                    strpos($motivo, '410') !== false
                ) {

                    $stmtDelete = $pdo->prepare("
                        DELETE FROM usuarios_notificacoes
                        WHERE endpoint = ?
                    ");

                    $stmtDelete->execute([$endpoint]);

                    $removidos++;
                }

                error_log(
                    'Falha no Push: ' . $motivo
                );
            }
        }

        return [
            'status'      => 'processado',
            'total'       => $total,
            'sucessos'    => $sucessos,
            'falhas'      => $falhas,
            'removidos'   => $removidos
        ];

    } catch (Exception $e) {

        error_log(
            'Erro geral no Push Global: ' .
            $e->getMessage()
        );

        return [
            'status'   => 'erro',
            'mensagem' => $e->getMessage()
        ];
    }
}

 $resultadoPush = dispararNotificacaoGlobal($pdo);