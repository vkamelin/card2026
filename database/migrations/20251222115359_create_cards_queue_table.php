<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateCardsQueueTable extends AbstractMigration
{

    public function up(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `cards_queue` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `file_id` BIGINT UNSIGNED NOT NULL,
            `stage` ENUM('queue', 'cut', 'merge', 'sent') NOT NULL DEFAULT 'queue',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE = utf8mb4_unicode_ci;");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `cards_queue`");
    }
}
