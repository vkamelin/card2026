<?php
/**
 * Воркер отправки сообщений Telegram.
 *
 * Назначение:
 * - Забирает задачи из Redis-очередей (по приоритетам);
 * - Отправляет сообщения через Telegram Bot API с учётом лимитов RPS;
 * - Переоткладывает и помечает ошибки с ретраями.
 *
 * Основные настройки окружения:
 * - BOT_API_SERVER/BOT_LOCAL_API_HOST/BOT_LOCAL_API_PORT — параметры локального API;
 * - BOT_MAX_RPS, WORKERS_BOT_PROCS — ограничения на скорость и кол-во воркеров.
 */

declare(strict_types=1);

use App\Helpers\Logger;
use App\Helpers\Database;
use App\Helpers\RedisHelper;
use App\Telemetry;
use App\Config;
use App\Helpers\RedisKeyHelper;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

require_once __DIR__ . '/../vendor/autoload.php';

$config = Config::getInstance();

try {
    if ($_ENV['BOT_API_SERVER'] === 'local') {
        $apiBaseUri = 'http://' . $_ENV['BOT_LOCAL_API_HOST'] . ':' . $_ENV['BOT_LOCAL_API_PORT'];
        $apiBaseDownloadUri = '/root/telegram-bot-api/' . $_ENV['BOT_TOKEN'];
        Request::setCustomBotApiUri($apiBaseUri, $apiBaseDownloadUri);
    }

    $telegram = new Telegram($_ENV['BOT_TOKEN'], $_ENV['BOT_NAME']);
    Logger::info('Процесс отправки Telegram запущен');
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    Logger::error("Telegram initialization failed: {$e->getMessage()}");
    exit();
}

// Максимальное количество запросов в секунду для всех воркеров
$limit = 9;

$queues = [
    RedisKeyHelper::key(RedisHelper::REDIS_MESSAGES_QUEUE_KEY, '2') => ['priority' => 2],
    RedisKeyHelper::key(RedisHelper::REDIS_MESSAGES_QUEUE_KEY, '1') => ['priority' => 1],
    RedisKeyHelper::key(RedisHelper::REDIS_MESSAGES_QUEUE_KEY, '0') => ['priority' => 0],
];

while (true) {
    $startTime = microtime(true);
    $messagesProcessed = 0;

    try {
        $redis = RedisHelper::getInstance();
    } catch (\RedisException $e) {
        Logger::error('Redis connection failed: ' . $e->getMessage());
        return;
    }

    // Отложенные сообщения более не обрабатываются воркером

    $totalQueue = 0;
    foreach ($queues as $queueKey => $queueInfo) {
        $len = $redis->lLen($queueKey);
        $totalQueue += \is_int($len) ? $len : 0;
        if ($messagesProcessed >= $limit) {
            break;
        }

        try {
            while ($messagesProcessed < $limit) {
                // Попытка достать элемент из списка
                try {
                    $queueValue = $redis->lPop($queueKey);
                } catch (\RedisException $e) {
                    Logger::error("Redis lPop error on {$queueKey}: {$e->getMessage()}");
                    $queueValue = false;
                }

                if ($queueValue === false) {
                    // Очередь пуста — переходим к следующей приоритету
                    break;
                }

                $messageData = $queueValue;
                if (!is_array($messageData)) {
                    continue;
                }

                $id = (int)$messageData['id'];
                $messageKey = RedisKeyHelper::key('telegram', 'message', (string)$id);

                // Попытка получить тело сообщения
                $raw = $redis->get($messageKey);
                if ($raw === false || $raw === null) {
                    // Попытка получить сообщение из базы данных как fallback

                    $db = Database::getInstance();
                    $stmt = $db->prepare('SELECT * FROM telegram_messages WHERE id = :id LIMIT 1');
                    $stmt->execute(['id' => $id]);
                    $dbMessage = $stmt->fetch();

                    if ($dbMessage) {
                        // Восстанавливаем сообщение из базы данных
                        $raw = [
                            'user_id' => $dbMessage['user_id'],
                            'method' => $dbMessage['method'],
                            'data' => $dbMessage['data'],
                            'type' => $dbMessage['type'],
                            'priority' => $dbMessage['priority'],
                            'key' => (string)$id
                        ];
                        Logger::info("Сообщение {$id} восстановлено из базы данных");
                    } else {
                        Logger::info("Сообщение {$id} не найдено в базе данных");
                        continue;
                    }
                }

                $message = $raw;
                if (!is_array($message)) {
                    continue;
                }

                try {
                    $data = json_decode($message['data'], true, 512, JSON_THROW_ON_ERROR) ?? [];
                } catch (Throwable $e) {
                    Logger::error("Invalid JSON for message ID {$id}: {$e->getMessage()}");
                    continue;
                }

                $method = $message['method'];
                $attempts = (int)($messageData['attempts'] ?? 0);

                // Определяем, нужно ли кодировать файл
                $fileIndex = match ($method) {
                    'sendPhoto' => 'photo',
                    'sendVideo' => 'video',
                    'sendDocument' => 'document',
                    'sendAudio' => 'audio',
                    'sendAnimation' => 'animation',
                    'sendVoice' => 'voice',
                    'sendVideoNote' => 'video_note',
                    'sendSticker' => 'sticker',
                    default => null,
                };

                if ($fileIndex !== null && file_exists((string)$data[$fileIndex])) {
                    $data[$fileIndex] = Request::encodeFile($data[$fileIndex]);
                }

                try {
                    /** @var ServerResponse $response */
                    $response = Request::$method($data);
                    Logger::debug("Sent message ID {$id}: " . ($response->isOk() ? 'ok' : 'failed'));
                } catch (\Exception $e) {
                    Logger::error("Exception sending message ID {$id}: {$e->getMessage()}");
                    $response = new ServerResponse([
                        'ok' => false,
                        'error_code' => $e->getCode(),
                        'description' => $e->getMessage(),
                    ]);
                }

                $reason = $response->getDescription();

                if ($response->isOk()) {
                    if (Telemetry::enabled()) {
                        Telemetry::incrementTelegramSent();
                    }
                } else {
                    if (Telemetry::enabled()) {
                        Telemetry::recordTelegramSendFailure($reason);
                    }
                }

                // Сохраняем результат и удаляем из Redis
                saveUpdate($id, $response, $queueKey);
                $messagesProcessed++;
            }
        } catch (Throwable $e) {
            echo "Queue processing error: {$e->getMessage()}\n";
            Logger::error("Queue processing error: {$e->getMessage()}");
        }
    }

    if (Telemetry::enabled()) {
        Telemetry::setTelegramQueueSize($totalQueue);
        $dlqLen = $redis->lLen(RedisKeyHelper::key('telegram', 'dlq'));
        Telemetry::setDlqSize(\is_int($dlqLen) ? $dlqLen : 0);
    }

    // Пауза, чтобы не превышать лимит запросов
    $elapsed = microtime(true) - $startTime;
    if ($messagesProcessed === 0 || $elapsed < 1.0) {
        $sleep = max(1_000_000 - (int)($elapsed * 1_000_000), 100_000);
        usleep($sleep);
    }

    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }
}

/**
 * Сохраняет результат отправки и удаляет запись из Redis
 */
function saveUpdate(int $id, ServerResponse $response, string $queueKey): void
{
    $db = Database::getInstance();
    try {
        $redis = RedisHelper::getInstance();
    } catch (\RedisException $e) {
        Logger::error('Redis connection failed: ' . $e->getMessage());
        return;
    }

    $messageKey = RedisKeyHelper::key('telegram', 'message', (string)$id);
    $redis->del($messageKey);

    $raw = $response->getRawData();
    if ($response->isOk()) {
        $status = 'success';
        $error = null;
        $errorCode = null;
        $msgId = $raw['result']->message_id ?? null;
    } else {
        $status = 'failed';
        $error = $raw['description'] ?? $response->printError(true);
        $errorCode = $raw['error_code'] ?? $response->getErrorCode();
        $msgId = null;
    }

    Logger::debug("Saving update: id={$id}, status={$status}, code={$errorCode}, error=" . ($error ?? 'none'));

    try {
        $stmt = $db->prepare(
            "UPDATE `telegram_messages`
               SET message_id   = :message_id,
                   status       = :status,
                   response     = :response,
                   error        = :error,
                   code         = :code,
                   processed_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([
            'message_id' => $msgId,
            'status' => $status,
            'response' => json_encode($raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'error' => $error,
            'code' => $errorCode,
            'id' => $id,
        ]);

        // If this message belongs to a scheduled batch, increment its counters
        try {
            $sidStmt = $db->prepare('SELECT scheduled_id, `type` FROM telegram_messages WHERE id = :id');
            $sidStmt->execute(['id' => $id]);
            $row = $sidStmt->fetch();
            $scheduledId = $row['scheduled_id'] ?? null;
            if (($scheduledId === false || $scheduledId === null) && isset($row['type'])) {
                $typeVal = (string)$row['type'];
                if (preg_match('~^scheduled:[^:]*:(\d+)$~', $typeVal, $m) === 1) {
                    $scheduledId = (int)$m[1];
                }
            }
            if ($scheduledId !== false && $scheduledId !== null) {
                if ($status === 'success') {
                    $db->prepare('UPDATE `telegram_scheduled_messages` SET `success_count` = `success_count` + 1 WHERE id = :sid')
                        ->execute(['sid' => (int)$scheduledId]);
                } elseif ($status === 'failed') {
                    $db->prepare('UPDATE `telegram_scheduled_messages` SET `failed_count` = `failed_count` + 1 WHERE id = :sid')
                        ->execute(['sid' => (int)$scheduledId]);
                }

                // If counters reached the selected total, mark batch as completed
                try {
                    $db->prepare(
                        "UPDATE `telegram_scheduled_messages`
                           SET `status` = 'completed', `completed_at` = IFNULL(`completed_at`, NOW())
                         WHERE id = :sid
                           AND `status` = 'processing'
                           AND `selected_count` > 0
                           AND (`success_count` + `failed_count`) >= `selected_count`"
                    )->execute(['sid' => (int)$scheduledId]);

                    // Fallback path: if selected_count is not set, derive it from messages table
                    $meta = $db->prepare('SELECT selected_count, success_count, failed_count FROM telegram_scheduled_messages WHERE id = :sid');
                    $meta->execute(['sid' => (int)$scheduledId]);
                    $m = $meta->fetch();
                    $sel = (int)($m['selected_count'] ?? 0);
                    $sent = (int)($m['success_count'] ?? 0) + (int)($m['failed_count'] ?? 0);
                    if ($sel <= 0) {
                        $planned = 0;
                        try {
                            $c1 = $db->prepare('SELECT COUNT(*) FROM telegram_messages WHERE scheduled_id = :sid');
                            $c1->execute(['sid' => (int)$scheduledId]);
                            $planned = (int)$c1->fetchColumn();
                            if ($planned === 0) {
                                $like = 'scheduled:%:' . (int)$scheduledId;
                                $c2 = $db->prepare('SELECT COUNT(*) FROM telegram_messages WHERE `type` LIKE :t');
                                $c2->execute(['t' => $like]);
                                $planned = (int)$c2->fetchColumn();
                            }
                        } catch (\Throwable) {
                            $planned = 0;
                        }
                        if ($planned > 0 && $sent >= $planned) {
                            $db->prepare('UPDATE telegram_scheduled_messages SET selected_count = :sel, status = \"completed\", completed_at = IFNULL(completed_at, NOW()) WHERE id = :sid AND status = \"processing\"')
                                ->execute(['sel' => $planned, 'sid' => (int)$scheduledId]);
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore if column not exists or DB doesn't support expression
                }
            }
        } catch (Throwable $e) {
            Logger::error('Failed to update scheduled counters: ' . $e->getMessage());
        }
    } catch (\Exception $e) {
        Logger::error("Error saving update for ID {$id}: {$e->getMessage()}");
    }
}
