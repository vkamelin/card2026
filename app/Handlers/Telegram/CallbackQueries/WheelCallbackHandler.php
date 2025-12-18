<?php
declare(strict_types=1);

namespace App\Handlers\Telegram\CallbackQueries;

use App\Helpers\Push;
use App\Helpers\WheelHelper;
use App\Helpers\WheelRedisHelper;
use App\Models\WheelPrize;
use App\Models\WheelSpin;
use Longman\TelegramBot\Entities\Update;
use Longman\TelegramBot\Request;

class WheelCallbackHandler
{
    /**
     * Handle wheel callback queries
     *
     * @param Update $update
     * @return void
     */
    public function handle(Update $update): void
    {
        $callbackQuery = $update->getCallbackQuery();
        $data = $callbackQuery->getData();
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        
        // Answer callback query to remove loading state
        Request::answerCallbackQuery([
            'callback_query_id' => $callbackQuery->getId(),
        ]);
        
        // Handle spin action
        if ($data === 'wheel_spin') {
            $this->handleSpin($chatId, $messageId);
        } elseif ($data === 'wheel') {
            $this->showWheel($update);
        }
    }
    
    /**
     * Show wheel interface
     *
     * @param Update $update
     * @return void
     */
    private function showWheel(Update $update): void
    {
        // Check if wheel is enabled
        if (!WheelHelper::isWheelEnabled()) {
            Push::text($update->getCallbackQuery()->getMessage()->getChat()->getId(), 'Колесо фортуны временно недоступно.', 'wheel_disabled', 2);
            return;
        }
        
        // Create web app URL with initData
        $webAppUrl = $_ENV['WEB_APP_URL'] ?? $_ENV['APP_URL'] ?? '';
        if (empty($webAppUrl)) {
            Push::text($update->getCallbackQuery()->getMessage()->getChat()->getId(), 'Колесо фортуны временно недоступно.', 'wheel_disabled', 2);
            return;
        }
        
        // Get initData from the update
        $initData = $update->getCallbackQuery()->getChatInstance();
        
        // Create web app button
        $keyboard = new \Longman\TelegramBot\Entities\InlineKeyboard([
            ['text' => '🎰 Крутить колесо', 'web_app' => ['url' => $webAppUrl . '/miniapp/wheel']]
        ]);
        
        // Send message with web app button
        Push::text($update->getCallbackQuery()->getMessage()->getChat()->getId(), 'Добро пожаловать в Колесо Фортуны! Нажмите кнопку ниже, чтобы начать.', 'wheel_welcome', 2, [
            'reply_markup' => $keyboard
        ]);
    }
    
    /**
     * Handle wheel spin action
     *
     * @param int $chatId
     * @param int $messageId
     * @return void
     */
    private function handleSpin(int $chatId, int $messageId): void
    {
        // Check if wheel is enabled
        if (!WheelHelper::isWheelEnabled()) {
            Push::text($chatId, 'Колесо фортуны временно недоступно.', 'wheel_disabled', 2);
            return;
        }
        
        // Check if user can spin using Redis for faster check
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
        
        // Select a random prize
        $prize = WheelHelper::selectRandomPrize();
        
        if ($prize === null) {
            Push::text($chatId, 'К сожалению, в данный момент нет доступных призов.', 'wheel_no_prizes', 2);
            return;
        }
        
        // Generate promo code if needed
        $promoCode = null;
        if ($prize->type !== 'consolation') {
            $promoCode = WheelHelper::generatePromoCode($prize);
        }
        
        // Calculate expiration date for prizes if needed
        $expiresAt = null;
        if ($promoCode !== null) {
            // Set expiration to 30 days from now
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
        
        // Determine if this is a winning spin
        $isWinning = $prize->type !== 'consolation';
        
        // Record the spin in database
        $spinId = WheelSpin::create(
            $chatId,
            $prize->id,
            $prize->name,
            $prize->value,
            $promoCode,
            $expiresAt,
            $isWinning
        );
        
        // Record spin in Redis for faster checks
        WheelRedisHelper::recordUserSpin($chatId, $isWinning);
        
        // Increment total spins counter
        WheelRedisHelper::incrementTotalSpins();
        
        // Increment daily total spins counter
        $today = date('Y-m-d');
        \App\Models\WheelDailyLimit::incrementTotalSpins($today);
        
        // If this is a winning spin, increment daily winning spins counter
        if ($isWinning) {
            \App\Models\WheelDailyLimit::incrementWinningSpins($today);
        }
        
        // Send result message
        $this->sendPrizeResult($chatId, $prize, $promoCode);
    }
    
    /**
     * Send prize result to user
     *
     * @param int $chatId
     * @param WheelPrize $prize
     * @param string|null $promoCode
     * @return void
     */
    private function sendPrizeResult(int $chatId, WheelPrize $prize, ?string $promoCode): void
    {
        $prizeText = WheelHelper::getPrizeDisplayText($prize);
        
        if ($prize->type === 'consolation') {
            $message = "🎰 К сожалению, в этот раз вам не повезло!\n\nПопробуйте снова позже!";
            Push::text($chatId, $message, 'wheel_consolation', 2);
            return;
        }
        
        $message = "🎰 Поздравляем! Вы выиграли:\n\n<b>{$prizeText}</b>";
        
        if ($promoCode !== null) {
            $message .= "\n\nВаш промокод: <code>{$promoCode}</code>";
            $message .= "\nИспользуйте его при следующем заказе!";
        }
        
        // Add expiration info if applicable
        if ($prize->type === 'discount' && $prize->value) {
            $message .= "\n\nСкидка составляет {$prize->value}% на любую покупку!";
        } elseif ($prize->type === 'free_item' && $prize->value) {
            $message .= "\n\nВы получаете бесплатный {$prize->value}!";
        } elseif ($prize->type === 'free_delivery') {
            $message .= "\n\nБесплатная доставка на ваш следующий заказ!";
        }
        
        $message .= "\n\nСпасибо за участие в Колесе Фортуны!";
        
        Push::text($chatId, $message, 'wheel_win', 2, [
            'reply_markup' => null // Remove buttons
        ]);
    }
}