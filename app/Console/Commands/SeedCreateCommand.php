<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Kernel;

/**
 * Команда создания файла seed.
 */
class SeedCreateCommand extends Command
{
    public string $signature = 'seed:create';
    public string $description = 'Создать новый seed';

    /**
     * Создаёт новый файл seed.
     *
     * @param array<int,string> $arguments [name] — имя seeder'а
     * @param Kernel $kernel Ядро (не используется)
     * @return int Код выхода (0 — успех)
     */
    public function handle(array $arguments, Kernel $kernel): int
    {
        $name = $arguments[0] ?? null;
        if ($name === null || $name === '') {
            echo 'Seeder name required.' . PHP_EOL;
            return 1;
        }

        // Формируем имя класса
        $className = str_replace(' ', '', ucwords(implode(' ', explode('_', $name))));

        // Формируем имя файла (без timestamp для совместимости с Phinx)
        $fileName = $name . '.php';
        $filePath = dirname(__DIR__, 3) . '/database/seeds/' . $fileName;

        // Создаем директорию, если не существует
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Шаблон содержимого файла
        $content = <<<PHP
<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class {$className} extends AbstractSeed
{
    /**
     * Запуск seeder'а.
     *
     * @return void
     */
    public function run(): void
    {
        \$className = get_class(\$this);

        \$exists = \$this->execute('SELECT * FROM `seedes` WHERE `name` = :name', ['name' => \$className]);
        if (\$exists) {
            return;
        }
        
        // Добавьте сюда логику заполнения таблиц данными
        \$table = \$this->table('table_name');
        \$data = [
            // ['column1' => 'value1', 'column2' => 'value2'],
        ];
        \$table->insert(\$data)->saveData();
        
        \$this->execute('INSERT INTO `seedes` (`name`) VALUES (:name)', ['name' => \$className]);
    }
}

PHP;

        // Записываем файл
        if (file_put_contents($filePath, $content) !== false) {
            echo "Seed created: {$filePath}" . PHP_EOL;
            return 0;
        } else {
            echo "Failed to create seed file." . PHP_EOL;
            return 1;
        }
    }
}