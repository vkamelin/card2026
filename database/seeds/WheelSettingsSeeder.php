<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class WheelSettingsSeeder extends AbstractSeed
{
    /**
     * Запуск seeder'а.
     *
     * @return void
     */
    public function run(): void
    {
        $className = get_class($this);

        $existsQuery = $this->query('SELECT * FROM `seedes` WHERE `name` = :name', ['name' => $className]);
        $exists = $existsQuery && $existsQuery->fetch();
        if ($exists) {
            return;
        }

        $table = $this->table('settings');
        $data = [
            ['key' => 'wheel.spins.per.day', 'type' => 'integer', 'integer_value' => 5],
            ['key' => 'wheel.wins.per.day', 'type' => 'integer', 'integer_value' => 1],
        ];
        $table->insert($data)->saveData();

        $this->execute('INSERT INTO `seedes` (`name`) VALUES (:name)', ['name' => $className]);
    }
}
