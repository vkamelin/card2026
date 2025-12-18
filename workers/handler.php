<?php
/**
 * Точка входа обработчика обновлений Telegram.
 *
 * Назначение:
 * - Принимает входящий апдейт и запускает соответствующий хендлер;
 * - Инкапсулирует общую инфраструктуру и обработку ошибок.
 */
/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

use App\Helpers\Database;
use App\Helpers\Logger;
use App\Helpers\RedisHelper;
use App\Handlers\Telegram\CallbackQueryHandler;
use App\Handlers\Telegram\MessageHandler;
use App\Handlers\Telegram\EditedMessageHandler;
use App\Handlers\Telegram\ChannelPostHandler;
use App\Handlers\Telegram\EditedChannelPostHandler;
use App\Handlers\Telegram\InlineQueryHandler;
use App\Handlers\Telegram\MessageReactionHandler;
use App\Handlers\Telegram\MessageReactionCountHandler;
use App\Handlers\Telegram\ChosenInlineResultHandler;
use App\Handlers\Telegram\ShippingQueryHandler;
use App\Handlers\Telegram\PreCheckoutQueryHandler;
use App\Handlers\Telegram\PollHandler;
use App\Handlers\Telegram\PollAnswerHandler;
use App\Handlers\Telegram\MyChatMemberHandler;
use App\Handlers\Telegram\ChatMemberHandler;
use App\Handlers\Telegram\ChatJoinRequestHandler;
use App\Handlers\Telegram\ChatBoostHandler;
use App\Handlers\Telegram\RemovedChatBoostHandler;
use App\Telegram\UpdateHelper as TelegramUpdateHelper;
use App\Helpers\RedisKeyHelper;
use App\Telegram\UpdateHelper;
use Dotenv\Dotenv;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;

require_once __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$payload = $payload ?? ($argv[1] ?? null);

try {
    if ($_ENV['BOT_API_SERVER'] === 'local') {
        $apiBaseUri = 'http://' . $_ENV['BOT_LOCAL_API_HOST'] . ':' . $_ENV['BOT_LOCAL_API_PORT'];
        $apiBaseDownloadUri = '/root/telegram-bot-api/' . $_ENV['BOT_TOKEN'];
        Request::setCustomBotApiUri($apiBaseUri, $apiBaseDownloadUri);
    }
    
    $telegram = new Telegram($_ENV['BOT_TOKEN'], $_ENV['BOT_NAME']);
} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    logMessage("error", "Telegram initialization failed: {$e->getMessage()}");
    exit();
}

try {
    if (empty($payload)) {
        logMessage("error", "Empty payload provided");
        throw new RuntimeException('Empty payload provided');
    }

    // Получаем данные из аргументов командной строки
    $updateData = json_decode(base64_decode($payload, true), true, 512, JSON_THROW_ON_ERROR);
    
    // Передаем данные обновления в экземпляр класса Longman\TelegramBot\Entities\Update
    $update = new Update($updateData, $_ENV['BOT_NAME']);
    // Получаем тип обновления
    $updateType = $update->getUpdateType();
    $messageReaction = TelegramUpdateHelper::getMessageReaction($update);
    $messageReactionCount = TelegramUpdateHelper::getMessageReactionCount($update);
    
    logMessage("info", "Update type: {$updateType}");

    try {
        $redis = RedisHelper::getInstance();
        $dedupKey = RedisKeyHelper::key('telegram', 'update', (string)$update->getUpdateId());
        $stored = $redis->set($dedupKey, 1, ['nx', 'ex' => 60]);
        if ($stored === false) {
            logMessage("Duplicate update skipped. Update ID: {$update->getUpdateId()}");
            exit();
        }
    } catch (\RedisException $e) {
        logMessage("error",  "Redis initialization failed: {$e->getMessage()}");
    }
    
    if (empty($updateData)) {
        logMessage('error', 'Empty update data');
        throw new RuntimeException("Не удалось декодировать данные.");
    }
    
    $db = Database::getInstance();
    
    $userId = UpdateHelper::getUserId($update);
    if ($userId === null) {
        $typesNoUserIdArray = [
            Update::TYPE_POLL,
            Update::TYPE_MESSAGE_REACTION_COUNT,
            Update::TYPE_CHAT_BOOST,
            Update::TYPE_REMOVED_CHAT_BOOST
        ];
        if (in_array($updateType, $typesNoUserIdArray, true)) {
            $userId = 0;
        } else {
            logMessage("error", 'User ID not found in update');
            throw new RuntimeException('User ID not found in update');
        }
    }

    $messageId = match ($updateType) {
        Update::TYPE_MESSAGE => $update->getMessage()->getMessageId(),
        Update::TYPE_EDITED_MESSAGE => $update->getEditedMessage()->getMessageId(),
        Update::TYPE_CHANNEL_POST => $update->getChannelPost()->getMessageId(),
        Update::TYPE_EDITED_CHANNEL_POST => $update->getEditedChannelPost()->getMessageId(),
        Update::TYPE_MESSAGE_REACTION => $messageReaction['message_id'] ?? null,
        Update::TYPE_MESSAGE_REACTION_COUNT => $messageReactionCount['message_id'] ?? null,
        default => null,
    };

    $date = match ($updateType) {
        Update::TYPE_MESSAGE => $update->getMessage()->getDate(),
        Update::TYPE_EDITED_MESSAGE => $update->getEditedMessage()->getEditDate() ?? time(),
        Update::TYPE_CHANNEL_POST => $update->getChannelPost()->getDate(),
        Update::TYPE_EDITED_CHANNEL_POST => $update->getEditedChannelPost()->getEditDate() ?? time(),
        Update::TYPE_MESSAGE_REACTION => $messageReaction['date'] ?? time(),
        Update::TYPE_MESSAGE_REACTION_COUNT => $messageReactionCount['date'] ?? time(),
        Update::TYPE_MY_CHAT_MEMBER => $update->getMyChatMember()->getDate(),
        Update::TYPE_CHAT_MEMBER => $update->getChatMember()->getDate(),
        Update::TYPE_CHAT_JOIN_REQUEST => $update->getChatJoinRequest()->getDate(),
        default => time(),
    };
    
    $stmt = $db->prepare(
        "INSERT INTO `telegram_updates` (`update_id`, `user_id`, `message_id`, `type`, `data`, `sent_at`) VALUES (:update_id, :user_id, :message_id, :type, :data, :sent_at)"
    );
    $result = $stmt->execute([
        'update_id' => $update->getUpdateId(),
        'user_id' => $userId,
        'message_id' => $messageId,
        'type' => $updateType,
        'data' => json_encode($update->getRawData(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        'sent_at' => date('Y-m-d H:i:s', $date)
    ]);
    
    if (!$result) {
        echo "Не удалось сохранить данные обновления в базу данных: {$stmt->errorInfo()[2]}";
        logMessage('error', "Не удалось сохранить данные обновления в базу данных: {$stmt->errorInfo()[2]}");
    }
    
    // Определяем обработчик в зависимости от типа обновления
    $updateType = $update->getUpdateType();
    $handler = match ($updateType) {
        Update::TYPE_MESSAGE => new MessageHandler(),
        Update::TYPE_EDITED_MESSAGE => new EditedMessageHandler(),
        Update::TYPE_CHANNEL_POST => new ChannelPostHandler(),
        Update::TYPE_EDITED_CHANNEL_POST => new EditedChannelPostHandler(),
        Update::TYPE_INLINE_QUERY => new InlineQueryHandler(),
        Update::TYPE_CHOSEN_INLINE_RESULT => new ChosenInlineResultHandler(),
        Update::TYPE_CALLBACK_QUERY => new CallbackQueryHandler(),
        Update::TYPE_SHIPPING_QUERY => new ShippingQueryHandler(),
        Update::TYPE_PRE_CHECKOUT_QUERY => new PreCheckoutQueryHandler(),
        Update::TYPE_MESSAGE_REACTION => new MessageReactionHandler(),
        Update::TYPE_MESSAGE_REACTION_COUNT => new MessageReactionCountHandler(),
        Update::TYPE_POLL => new PollHandler(),
        Update::TYPE_POLL_ANSWER => new PollAnswerHandler(),
        Update::TYPE_MY_CHAT_MEMBER => new MyChatMemberHandler(),
        Update::TYPE_CHAT_MEMBER => new ChatMemberHandler(),
        Update::TYPE_CHAT_JOIN_REQUEST => new ChatJoinRequestHandler(),
        'chat_boost' => new ChatBoostHandler(),
        'removed_chat_boost' => new RemovedChatBoostHandler(),
    };

    // Обрабатываем обновление
    $handler->handle($update);
} catch (\Throwable $e) {
    echo "Ошибка при обработке обновления: {$e->getMessage()}";
    logMessage('error', "Ошибка при обработке обновления: {$e->getMessage()}");
}

function logMessage(string $logFile = 'error', string $message = null): void
{
    file_put_contents($logFile . '.log', $message . PHP_EOL, FILE_APPEND);
}
