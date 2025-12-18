<?php

declare(strict_types=1);

use Dotenv\Dotenv;

Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

return [
    'app_env' => $_ENV['APP_ENV'] ?? 'prod',
    'debug' => (bool)($_ENV['APP_DEBUG'] ?? false),
    'bot_token' => $_ENV['BOT_TOKEN'] ?? null,
    // Default chat ID for operations where a chat must be specified explicitly
    'default_chat_id' => (int)($_ENV['DEFAULT_CHAT_ID'] ?? 0),

    'db' => [
        'dsn' => $_ENV['DB_DSN'] ?? null,   // 'mysql:host=127.0.0.1;dbname=app;charset=utf8mb4'
        'user' => $_ENV['DB_USER'] ?? null,
        'pass' => $_ENV['DB_PASS'] ?? null,
        'opts' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ],
    ],

    'jwt' => [
        'secret' => $_ENV['JWT_SECRET'] ?? null,
        'alg' => 'HS256',
        'ttl' => 3600,
        // time to live for refresh tokens (30 days by default)
        'refresh_ttl' => 60 * 60 * 24 * 30,
    ],

    // API routes list for documentation/tests
    'routes' => [
        'POST /api/auth/login',
        'POST /api/auth/refresh',
    ],

    'cors' => [
        'origins' => explode(',', $_ENV['CORS_ORIGINS'] ?? ''),
        'methods' => 'GET,POST,PUT,PATCH,DELETE,OPTIONS',
        'headers' => 'Authorization,Content-Type,X-Request-Id',
    ],

    'rate_limit' => [
        // По чему лимитировать: 'ip' или 'user'
        'bucket' => $_ENV['RATE_LIMIT_BUCKET'] ?? 'ip',
        // Кол-во запросов за окно
        'limit' => (int)($_ENV['RATE_LIMIT'] ?? 60),
        // Размер окна в секундах (fixed-window)
        'window_sec' => (int)($_ENV['RATE_LIMIT_WINDOW_SEC'] ?? 60),
        // Префикс ключей в Redis
        'redis_prefix' => $_ENV['RATE_LIMIT_REDIS_PREFIX'] ?? 'rl:',
    ],

    // Global request size limit (bytes). Defaults to 1 MiB
    'request_size_limit' => (int)($_ENV['REQUEST_SIZE_LIMIT'] ?? 1048576),
    // Optional per-path overrides for larger uploads (bytes)
    'request_size_overrides' => [
        // Allow larger uploads on dashboard message sending and files upload endpoints
        '/dashboard/messages/send' => (int)($_ENV['REQUEST_SIZE_LIMIT_UPLOAD'] ?? 50 * 1024 * 1024),
        '/dashboard/files' => (int)($_ENV['REQUEST_SIZE_LIMIT_UPLOAD'] ?? 50 * 1024 * 1024),
    ],

    // Время жизни ключа идемпотентности по умолчанию
    'IDEMPOTENCY_KEY_TTL' => (int)($_ENV['IDEMPOTENCY_KEY_TTL'] ?? 60),

    'sector_types' => [
        'custom' => 'Кастомный',
        'promocode' => 'Промокод',
        'link' => 'Ссылка',
        'coins' => 'Монеты',
        'nothing' => 'Ничего'
    ],
];
