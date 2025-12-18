<?php

declare(strict_types=1);

namespace App;

use App\Helpers\Database;
use PDO;

final class Config
{
    private array $data = [];
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $instance = new self();

        $instance->data = require __DIR__ . '/Config/config.php';

        $stmt = Database::getInstance()->query('SELECT * FROM settings');

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $instance->data[$row['key']] = match ($row['type']) {
                'integer' => (int)$row['integer_value'],
                'float' => (float)$row['float_value'],
                'boolean' => (bool)$row['boolean_value'],
                'array' => json_decode($row['array_value'] ?? '', true) ?? [],
                default => $row['string_value'],
            };
        }

        self::$instance = $instance;

        return $instance;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): bool
    {
        $db = Database::getInstance();

        $stmt = $db->prepare('SELECT `type` FROM `settings` WHERE `key` = :key');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return false;

        $type = $row['type'];
        $field = match ($type) {
            'integer' => 'integer_value',
            'float' => 'float_value',
            'boolean' => 'boolean_value',
            'array' => 'array_value',
            default => 'string_value',
        };

        $prepared = match ($type) {
            'integer' => (int)$value,
            'float' => (float)$value,
            'boolean' => (bool)$value,
            'array' => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string)$value,
        };

        $result = $db->prepare("UPDATE `settings` SET `{$field}` = :value WHERE `key` = :key")
            ->execute([':value' => $prepared, ':key' => $key]);

        if ($result) {
            $this->data[$key] = $value;
        }

        return $result;
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }
}