<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTelegramFilesTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `telegram_files` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `type` VARCHAR(255) NOT NULL,
            `file_name` VARCHAR(255) NOT NULL,
            `file_path` VARCHAR(255) NOT NULL,
            `mime_type` VARCHAR(255) NOT NULL,
            `size` BIGINT UNSIGNED NOT NULL,
            `file_id` VARCHAR(255) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
}
