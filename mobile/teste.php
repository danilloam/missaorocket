<?php

// Exibir erros (somente para testes)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/../config.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Envia uma notificação para todos os dispositivos
 * cadastrados de um usuário.
 *
 * @param PDO $pdo
 * @param int $usuario_id
 * @return array
 */
function dispararNotificacaoTeste(PDO $pdo, int $usuario_id): array
{
    // Chaves VAPID
    $auth = [
        'VAPID' => [
            'subject'    => 'https://www.missaorocket.com.br',
            'publicKey'  => 'BBbFobVqYPKTUYZXKXtbyqIuzHtCOGUBL1wKIXVWffY0hsBQWfrOZc0vo0M4rz6gxGuw_8_89XvxFvMSpOShQlA',
            'privateKey' => 'RWgwhBRa6wBnHfo4TTO0cs76HGxBj7xot6MzJcSltwg',
        ],
    ];

    try {

        // Busca todos os dispositivos do usuário
        $stmt = $pdo->prepare("
            SELECT id, endpoint, p256dh, auth
            FROM usuarios_notificacoes
            WHERE usuario_id = ?
        ");

        $stmt->execute([$usuario_id]);

        $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($dispositivos)) {
            return [
                'status' => 'info',
                'message' => "Nenhum dispositivo encontrado para o usuário {$usuario_id}"
            ];
        }

        $webPush = new WebPush($auth);

        foreach ($dispositivos as $disp) {

            // Payload individual para cada dispositivo
            $payload = json_encode([
                'title' => '🚀 Missão Rocket',
                'body'  => 'Eu sou o ID: ' . $disp['id'],
                'url'   => 'dashboard.php'
            ]);

            $subscription = Subscription::create([
                'endpoint'  => $disp['endpoint'],
                'publicKey' => $disp['p256dh'],
                'authToken' => $disp['auth'],
            ]);

            $webPush->queueNotification($subscription, $payload);
        }

        $sucessos = 0;
        $falhas = 0;
        $erros = [];

        foreach ($webPush->flush() as $report) {

            $endpoint = $report->getEndpoint();

            if ($report->isSuccess()) {

                $sucessos++;

            } else {

                $falhas++;

                $motivo = $report->getReason();

                $erros[] = [
                    'endpoint' => $endpoint,
                    'erro' => $motivo
                ];

                // Remove inscrições inválidas
                if (
                    stripos($motivo, 'expired') !== false ||
                    stripos($motivo, 'gone') !== false ||
                    stripos($motivo, 'unsubscribe') !== false
                ) {
                    $stmtDelete = $pdo->prepare("
                        DELETE FROM usuarios_notificacoes
                        WHERE endpoint = ?
                    ");

                    $stmtDelete->execute([$endpoint]);
                }
            }
        }

        return [
            'status' => 'processado',
            'usuario_id' => $usuario_id,
            'total_dispositivos' => count($dispositivos),
            'sucessos' => $sucessos,
            'falhas' => $falhas,
            'erros' => $erros
        ];

    } catch (Exception $e) {

        return [
            'status' => 'erro',
            'message' => $e->getMessage()
        ];
    }
}

/*
|--------------------------------------------------------------------------
| TESTE
|--------------------------------------------------------------------------
*/

$usuario_id = 1;

$resultado = dispararNotificacaoTeste($pdo, $usuario_id);

echo '<pre>';
print_r($resultado);
echo '</pre>';