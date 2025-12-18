<?php

/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Response;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Random\RandomException;

/**
 * Middleware для проверки CSRF-токена.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * Проверяет CSRF-токен для небезопасных методов.
     *
     * @param Req $req HTTP-запрос
     * @param Handler $handler Следующий обработчик
     * @return Res Ответ после проверки
     * @throws RandomException
     */
    public function process(Req $req, Handler $handler): Res
    {
        $csrfName = $_ENV['CSRF_TOKEN_NAME'] ?? '_csrf_token';
        
        // Генерируем CSRF-токен для всех запросов и сохраняем в сессии
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (!isset($_SESSION[$csrfName]) || empty($_SESSION[$csrfName])) {
                $_SESSION[$csrfName] = bin2hex(random_bytes(32));
            }
        }

        // Проверяем CSRF-токен для небезопасных методов
        if (in_array($req->getMethod(), ['POST','PUT','PATCH','DELETE'], true)) {
            $body = (array)$req->getParsedBody();
            $formToken = (string)($body[$csrfName] ?? '');
            $sessionToken = session_status() === PHP_SESSION_ACTIVE ? (string)($_SESSION[$csrfName] ?? '') : '';

            if ($formToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $formToken)) {
                return Response::problem(new \Slim\Psr7\Response(), 403, 'CSRF check failed');
            }
        }
        
        return $handler->handle($req);
    }
}
