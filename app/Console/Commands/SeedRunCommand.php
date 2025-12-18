<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Command;
use App\Console\Kernel;
use Phinx\Console\Command\SeedRun as PhinxSeedRun;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Команда запуска seed'ов базы данных.
 */
class SeedRunCommand extends Command
{
    public string $signature = 'seed:run';
    public string $description = 'Запустить seed\'ы';

    /**
     * Выполняет seed'ы через Phinx с отслеживанием выполненных seed'ов.
     *
     * @param array<int,string> $arguments Аргументы команды (не используются)
     * @param Kernel $kernel Ядро (не используется)
     * @return int Код выхода
     */
    public function handle(array $arguments, Kernel $kernel): int
    {
        // Запуск seed'ов через Phinx
        $application = new PhinxApplication();
        $command = new PhinxSeedRun();
        $command->setApplication($application);

        $config = dirname(__DIR__, 3) . '/phinx.php';
        $env = $_ENV['APP_ENV'] ?? 'dev';

        // Добавляем verbose output для диагностики
        $input = new ArrayInput([
            '--configuration' => $config,
            '--environment' => $env,
            '-v' => true, // verbose output
        ]);
        $output = new ConsoleOutput();

        echo "Running seeds with config: {$config}" . PHP_EOL;
        echo "Environment: {$env}" . PHP_EOL;
        echo "Seed path: " . dirname(__DIR__, 3) . '/database/seeds' . PHP_EOL;

        $exitCode = $command->run($input, $output);

        if ($exitCode === 0) {
            echo 'Seed\'ы успешно выполнены.' . PHP_EOL;
        }

        return $exitCode;
    }
}