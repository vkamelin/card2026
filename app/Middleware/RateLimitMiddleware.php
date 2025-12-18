<?php

/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\RedisHelper;
use App\Helpers\Response;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;

/**
 * Middleware для ограничения количества запросов.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param array $cfg Параметры лимитирования
     */
    public function __construct(private array $cfg)
    {
    }

    /**
     * Применяет лимит запросов на основе IP или Telegram пользователя.
     *
     * @param Req $req HTTP-запрос
     * @param Handler $handler Следующий обработчик
     * @return Res Ответ после проверки
     */
    public function process(Req $req, Handler $handler): Res
    {
        $bucket = ($this->cfg['bucket'] ?? 'ip') === 'user' ? 'user' : 'ip';
        $limit = (int)($this->cfg['limit'] ?? 60);
        $windowSec = (int)($this->cfg['window_sec'] ?? 60);
        $prefix = (string)($this->cfg['redis_prefix'] ?? 'rl:');

        $server = $req->getServerParams();
        $clientIp = $server['REMOTE_ADDR'] ?? 'anon';
        $telegramUser = $req->getAttribute('telegramUser') ?? [];
        $userId = is_array($telegramUser) ? ($telegramUser['id'] ?? null) : null;

        $id = ($bucket === 'user' && $userId) ? ('u:' . (string)$userId) : ('ip:' . (string)$clientIp);

        // Fixed window slot
        $now = time();
        $slot = (string)floor($now / max(1, $windowSec));
        $key = $prefix . $bucket . ':' . $id . ':' . $slot;

        // Default headers values
        $remaining = null;

        try {
            $redis = RedisHelper::getInstance();
            $cur = (int)$redis->incr($key);
            if ($cur === 1) {
                // Set TTL only on first increment in the slot
                $redis->expire($key, $windowSec);
            }

            $remaining = max(0, $limit - $cur);

            if ($cur > $limit) {
                $retryAfter = (int)($windowSec - ($now % max(1, $windowSec)));
                $res429 = Response::problem(new \Slim\Psr7\Response(), 429, 'Too Many Requests', [
                    'retry_after' => $retryAfter,
                ]);

                return $res429
                    ->withHeader('Retry-After', (string)$retryAfter)
                    ->withHeader('X-RateLimit-Limit', (string)$limit)
                    ->withHeader('X-RateLimit-Remaining', '0');
            }

            $res = $handler->handle($req);
            return $res
                ->withHeader('X-RateLimit-Limit', (string)$limit)
                ->withHeader('X-RateLimit-Remaining', (string)$remaining);
        } catch (\Throwable) {
            // Fail-open: если Redis недоступен, не блокируем запросы
            return $handler->handle($req);
        }
    }
}
