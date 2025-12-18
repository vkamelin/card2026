<?php

declare(strict_types=1);

namespace App\Handlers\Telegram\Commands;

use App\Helpers\Logger;
use App\Helpers\MessageStorage;
use App\Helpers\Push;
use Exception;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;

class StartCommandHandler extends AbstractCommandHandler
{
    /**
     * @param Update $update
     *
     * @return void
     * @throws Exception
     */
    public function handle(Update $update): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $firstName = $message->getChat()->getFirstName();
        $lastName = $message->getChat()->getLastName();
        $username = $message->getChat()->getUsername();
        $languageCode = $message->getFrom()->getLanguageCode();
        $isPremium = $message->getFrom()->getIsPremium() ? 1 : 0;
        $isBot = $message->getFrom()->getIsBot();
        $isPrivateChat = $message->getChat()->isPrivateChat();
        $messageText = $message->getText() ?? '';

        // Проверяем, является ли пользователь ботом
        if ($isBot) {
            return;
        }

        // Проверяем, является ли чат приватным
        if (!$isPrivateChat) {
            return;
        }

        $ref = $this->checkReferralCode($messageText);
        $invitedUserId = $ref['user_id'] ?? null;
        $viaCode = $ref['code'] ?? null;

        if ($invitedUserId === null) {
            $utmString = $this->convertUtmStringToUrlFormat($messageText) ?? '';
        } else {
            $utmString = '';
        }

        // Проверяем, существует ли пользователь в базе данных
        $stmt = $this->db->prepare('SELECT `id` FROM telegram_users WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $chatId]);
        $userExists = $stmt->fetch();

        if (!$userExists) {
            // Создаём уникальный реферальный код (URL-safe)
            $referralCode = $this->generateReferralCode();

            $stmt = $this->db->prepare(
                'INSERT INTO telegram_users (user_id, username, first_name, last_name, language_code, utm, is_premium, referral_code, invited_user_id) VALUES (:user_id, :username, :first_name, :last_name, :language_code, :utm, :is_premium, :referral_code, :invited_user_id)'
            );
            $stmt->execute([
                'user_id' => $chatId,
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'language_code' => $languageCode,
                'utm' => $utmString,
                'is_premium' => $isPremium,
                'referral_code' => $referralCode,
                'invited_user_id' => $invitedUserId,
            ]);

            // Зафиксируем реферала в таблице referrals (если пришли по коду)
            if ($invitedUserId !== null && $invitedUserId !== $chatId) {
                try {
                    $insRef = $this->db->prepare('INSERT IGNORE INTO referrals(inviter_user_id, invitee_user_id, via_code, created_at) VALUES(?, ?, ?, NOW())');
                    $insRef->execute([$invitedUserId, $chatId, $viaCode]);
                } catch (\Throwable) {
                    // ignore
                }
            }
        } else {
            $stmt = $this->db->prepare(
                'UPDATE telegram_users SET username = :username, first_name = :first_name, last_name = :last_name, language_code = :language_code, utm = :utm, is_premium = :is_premium, is_user_banned = 0 WHERE user_id = :user_id'
            );
            $stmt->execute([
                'user_id' => $chatId,
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'language_code' => $languageCode,
                'utm' => $utmString,
                'is_premium' => $isPremium,
            ]);

            Logger::info("Пользователь уже зарегистрирован: {$chatId}");
        }

        $appUrl = $_ENV['APP_URL'];

        // Создаем инлайн-клавиатуру
        $keyboard = new InlineKeyboard(
            [
                ['text' => '🎰 Крутить колесо', 'web_app' => ['url' => $appUrl . '/miniapp/wheel']]
            ],
            [
                ['text' => 'ℹ️ О проекте', 'callback_data' => 'about'],
            ]
        );

        $caption = MessageStorage::read('start') ?? '';

        // Отправляем стартовое сообщение с кнопкой
        Push::text($chatId, $caption, 'start', 2, [
            'reply_markup' => $keyboard,
            'link_preview_options' => ['is_disabled' => true],
        ]);

        Request::setChatMenuButton([
            'chat_id' => $chatId,
            'menu_button' => json_encode([
                'type' => 'web_app',
                'text' => 'Играть',
                'web_app' => [
                    'url' => $_ENV['WEB_APP_URL'] . '/miniapp/wheel',
                ],
            ], JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Преобразование строки UTM-параметров в URL-совместимый формат
     *
     * @param string $messageText Строка UTM-параметров
     *
     * @return string|null
     */
    private function convertUtmStringToUrlFormat(string $messageText): ?string
    {
        if (preg_match('/^\/start\s+(.+)/', $messageText, $matches)) {
            $utmString = $matches[1];

            // Разбиваем строку на пары ключ-значение по разделителю "___"
            $pairs = explode('___', $utmString);
            $urlParams = [];

            foreach ($pairs as $pair) {
                [$key, $value] = explode('--', $pair) + [null, null];
                if ($key && $value) {
                    $urlParams[] = urlencode($key) . '=' . urlencode($value);
                }
            }

            // Возвращаем строку в формате URL: utm_source=yandex&utm_medium=cpc
            return implode('&', $urlParams);
        }

        return null;
    }

    /**
     * Проверяет есть ли реферальный код и находит пользователя с этим кодом
     *
     * @param string $messageText Текст сообщения
     * @return array{user_id:int,code:string}|null Информация о реферальном коде
     */
    public function checkReferralCode(string $messageText): ?array
    {
        if (preg_match('/^\/start\s+(.+)/', $messageText, $matches)) {
            $string = trim($matches[1]);

            if (str_starts_with($string, 'code___')) {
                $referralCode = substr($string, strlen('code___'));

                // Проверяем, существует ли реферальный код в базе данных
                $stmt = $this->db->prepare('SELECT `user_id` FROM telegram_users WHERE referral_code = :referral_code LIMIT 1');
                $stmt->execute(['referral_code' => $referralCode]);
                $result = $stmt->fetch();

                if ($result && isset($result['user_id'])) {
                    return ['user_id' => (int)$result['user_id'], 'code' => $referralCode];
                }
            }
        }

        return null; // Если реферальный код не найден
    }

    /**
     * Генерация безопасного реферального кода
     */
    private function generateReferralCode(): string
    {
        try {
            return bin2hex(random_bytes(6)); // 12 символов [0-9a-f]
        } catch (\Exception) {
            return substr(sha1((string)mt_rand()), 0, 12);
        }
    }
}
