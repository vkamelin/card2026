<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSettingsTable extends AbstractMigration
{

    public function up(): void
    {
        $this->execute("CREATE TABLE IF NOT EXISTS `settings` (
            `key` VARCHAR(255) NOT NULL,
            `type` ENUM('string', 'integer', 'float', 'boolean', 'array') NOT NULL,
            `string_value` TEXT NULL,
            `integer_value` BIGINT NULL,
            `float_value` DOUBLE NULL,
            `boolean_value` TINYINT(1) NULL,
            `array_value` JSON NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE = utf8mb4_unicode_ci;");
    }

    public function down(): void
    {
        $this->execute("DROP TABLE IF EXISTS `settings`;");
    }
}
