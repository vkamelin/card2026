<?php

/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Helpers\Response;
use PDO;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

/**
 * WebApp metrics controller: records opens and returns counters.
 */
final class WebAppController
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Records a Mini App open for a Telegram user (unique per user).
     */
    public function open(Req $req, Res $res): Res
    {
        /** @var array{id?:int|null} $telegramUser */
        $telegramUser = (array)$req->getAttribute('telegramUser', []);
        $userId = isset($telegramUser['id']) ? (int)$telegramUser['id'] : 0;

        if ($userId <= 0) {
            return Response::problem($res, 400, 'Missing telegram user id');
        }

        // Insert unique user open (first time only)
        $stmt = $this->db->prepare('INSERT IGNORE INTO web_app_open_users(user_id, opened_at) VALUES(?, NOW())');
        $stmt->execute([$userId]);

        // Return current total unique users opened
        $count = (int)$this->db->query('SELECT COUNT(*) AS c FROM web_app_open_users')->fetchColumn();
        return Response::json($res, 200, [
            'ok' => true,
            'total_unique' => $count,
        ]);
    }

    /**
     * Returns WebApp counters (unique users).
     */
    public function metrics(Req $req, Res $res): Res
    {
        $count = (int)$this->db->query('SELECT COUNT(*) AS c FROM web_app_open_users')->fetchColumn();
        return Response::json($res, 200, [
            'total_unique' => $count,
        ]);
    }
}

