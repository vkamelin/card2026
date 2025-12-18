# AGENTS.md

## Setup commands
- Install deps: `composer install` (или аналог для PHP/Slim).
- Start dev server: `php -S localhost:8000 -t public/`.
- Run tests: `vendor/bin/phpunit` (добавьте, если тесты есть).

## Code style
- PSR-12, в каждом файле `declare(strict_types=1)`.
- Короткие, однонаправленные методы; избегать «божественных» объектов.
- Именование: понятные, самодокументирующиеся имена; без однобуквенных переменных.
- PHPDoc — для публичных методов и там, где типы неочевидны.
- Контроллеры: Возвращать ответы только через `Helpers\Response`.
- Ошибки: RFC 7807 (`problem+json`).
- Валидация: `trim`, `filter_var`, явные проверки диапазонов и размеров.
- БД: Только подготовленные выражения (`prepare/execute`); никаких строковых конкатенаций для SQL с пользовательскими данными; `SELECT` с `LIMIT`, явными полями, индексами.
- Исключения: Для исключительных ситуаций; пользовательские сообщения — без деталей реализации; логи через `Helpers\Logger` с `X-Request-Id`.

## Project structure
- Минималистичный HTTP-слой на Slim 4.
- Зоны маршрутов: API (`/api/*`), Dashboard (`/dashboard/*`), Miniapp (`/miniapp/*`).
- Без ORM, моделей, DDD: прямой PDO с подготовленными выражениями.
- Защита: JWT, CSRF, Rate-Limit, Security Headers, ограничение размера тела запроса, RFC 7807.
- Фоновые воркеры: long polling Telegram, обработчик обновлений, планировщик рассылок, утилитарные задачи.
- Redis (опционально): хранение оффсета long polling и правил фильтрации апдейтов, кеш данных.
- Ошибки и ответы: Единый формат — RFC 7807 (`application/problem+json` с `type`, `title`, `status`, `detail`, `instance`); успешные — `application/json` с `items`, `meta`.
- Middleware: `ErrorMiddleware(bool $debug)`, `RequestIdMiddleware`, `RequestSizeLimitMiddleware(int $bytes)`, `SecurityHeadersMiddleware`, `SessionMiddleware`, `CsrfMiddleware`, `JwtMiddleware`, `RateLimitMiddleware`, `TelegramInitDataMiddleware($botToken)`.
- Хелперы и сервисы: `Database` (singleton PDO), `Response` (json/problem), `Logger` (Monolog), `Push` (Telegram), `MediaBuilder` (InputMedia*), `RedisHelper`/`RedisKeyHelper`, `RefreshTokenService`, `MessageStorage`, `FileService`, `Path`, `View`, `JsonHelper`, `Telemetry` (заглушка для метрик).

## Boundaries & Rules

### Always do
- Использовать подготовленные SQL; валидировать вход; логировать с `X-Request-Id`.
- Писать только рабочий код, решающий задачу.
- Если нужно проверить — делай это вручную через пример вызова в комментарии или сразу в маршруте/скрипте.
- Все комментарии, PHPDoc, сообщения коммитов и PR — только на русском языке.
- Названия сущностей в коде (классы, методы, переменные, файлы) — только на английском.

### Ask first
- Перед рефакторингом middleware или БД-сервисов.

### Never do
- Изменять third-party зависимости.
- Конкатенировать SQL.
- Возвращать сырые ответы без `Helpers\Response`.
- Создавать любые unit-тесты, integration-тесты или файлы в папке `tests/`.
- Запускать тесты автоматически.
- Предлагать писать или добавлять тесты.
- Писать комментарии, докблоки, коммиты, PR на английском или транслите.
- Тратить токены на написание/запуск тестов — тестирование только моя зона ответственности.

### Always do
- Пиши только рабочий код, который решает задачу.
- Если нужно проверить — делай это вручную через пример вызова в комментарии или сразу в маршруте/скрипте.

### Language rules
- Все комментарии в коде — только на русском языке.
- Все PHPDoc-блоки — только на русском языке.
- Все сообщения в коммитах — только на русском языке.
- Все названия PR и описания — только на русском языке.
- Названия переменных, функций, классов, файлов — только на английском (транслит запрещён).
- Строки в коде (логи, ошибки для пользователя, ответы API) — на русском, если предназначены русскоязычным пользователям.

## PR instructions
- Title: `[Component] <Title>` (e.g., `[API] Add validation`).
- Run: `vendor/bin/phpcs` (lint), `vendor/bin/phpunit` перед commit.

@./ENVIRONMENT.md