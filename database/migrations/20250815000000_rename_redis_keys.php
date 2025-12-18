<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use App\Helpers\RedisHelper;
use App\Config;
use RedisException;

final class RenameRedisKeys extends AbstractMigration
{
    public function up(): void
    {
        try {
            $redis = RedisHelper::getInstance();
            $config = Config::getInstance();
            $prefix = $config->get('APP_NAME') . ':' . $config->get('PROJECT_ENV') . ':';
            
            // Проверяем доступность Redis
            if (!$redis->ping()) {
                echo "Redis is not available, skipping migration\n";
                return;
            }
            
            foreach ($redis->keys('gpt:*') as $key) {
                $redis->rename($key, $prefix . $key);
            }
            foreach ($redis->keys('telegram:*') as $key) {
                $redis->rename($key, $prefix . $key);
            }
            foreach ($redis->keys('rate:*') as $key) {
                $redis->rename($key, $prefix . $key);
            }
        } catch (RedisException $e) {
            // Игнорируем ошибки Redis, просто выводим сообщение
            echo "Redis error (ignored): " . $e->getMessage() . "\n";
        } catch (Exception $e) {
            // Игнорируем другие ошибки
            echo "Error (ignored): " . $e->getMessage() . "\n";
        }
    }
}
