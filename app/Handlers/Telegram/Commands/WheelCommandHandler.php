<?php
declare(strict_types=1);

namespace App\Handlers\Telegram\Commands;

use App\Helpers\Push;
use App\Helpers\WheelHelper;
use App\Helpers\WheelRedisHelper;
use App\Models\WheelSpin;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\Update;

class WheelCommandHandler extends AbstractCommandHandler
{
    /**
     * Handle the /wheel command
     *
     * @param Update $update
     * @return void
     */
    public function handle(Update $update): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        
        // Check if wheel is enabled
        if (!WheelHelper::isWheelEnabled()) {
            Push::text($chatId, 'Колесо фортуны временно недоступно.', 'wheel_disabled', 2);
            return;
        }
        
        // Check if user can spin
        $canSpin = WheelRedisHelper::canUserSpin($chatId);
        
        if (!$canSpin) {
            $timeLeft = WheelRedisHelper::getTimeUntilNextSpin($chatId);
            
            if ($timeLeft !== null) {
                $timeLeftFormatted = WheelHelper::formatDuration($timeLeft);
                Push::text($chatId, "Вы уже крутили колесо! Следующая прокрутка будет доступна через {$timeLeftFormatted}.", 'wheel_cooldown', 2);
            } else {
                Push::text($chatId, 'Вы уже крутили колесо и больше не можете этого делать.', 'wheel_once_only', 2);
            }
            return;
        }
        
        // Create inline keyboard with spin button
        $keyboard = new InlineKeyboard([
            ['text' => '🎰 Крутить колесо', 'callback_data' => 'wheel_spin']
        ]);
        
        $webAppUrl = $_ENV['WEB_APP_URL'] ?? $_ENV['APP_URL'] ?? '';
        if ($webAppUrl) {
            $keyboard->addRow([
                ['text' => '🎡 Колесо фортуны (Mini App)', 'web_app' => ['url' => $webAppUrl . '/miniapp/wheel']]
            ]);
        }
        
        // Send message with spin button
        Push::text($chatId, "Добро пожаловать в Колесо Фортуны! 🎰\n\nНажмите кнопку ниже, чтобы испытать удачу!", 'wheel_welcome', 2, [
            'reply_markup' => $keyboard
        ]);
    }
}