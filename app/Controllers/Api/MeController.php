<?php

/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Helpers\Response;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

/**
 * Контроллер для получения информации о текущем пользователе.
 */
final class MeController
{
    /**
     * Возвращает данные авторизованного пользователя.
     *
     * @param Req $req HTTP-запрос
     * @param Res $res HTTP-ответ
     * @return Res JSON с данными пользователя
     */
    public function show(Req $req, Res $res): Res
    {
        $telegramUser = $req->getAttribute('telegramUser');
        if (!is_array($telegramUser)) {
            return Response::problem($res, 403, 'Forbidden');
        }
        return Response::json($res, 200, ['user' => $telegramUser]);
    }
}
