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
 * Контроллер для управления пользователями.
 *
 * Обрабатывает маршруты /api/users.
 */
final class UsersController
{
    /**
     * @param PDO $db Подключение к базе данных
     */
    public function __construct(private PDO $db)
    {
    }

    /**
     * Возвращает список пользователей.
     *
     * @param Req $req HTTP-запрос
     * @param Res $res HTTP-ответ
     * @return Res JSON со списком пользователей
     */
    public function list(Req $req, Res $res): Res
    {
        $q = $this->db->query('SELECT id, email, created_at FROM users ORDER BY id DESC LIMIT 100');
        $rows = $q->fetchAll();
        return Response::json($res, 200, ['items' => $rows]);
    }

    /**
     * Создаёт нового пользователя.
     *
     * @param Req $req HTTP-запрос
     * @param Res $res HTTP-ответ
     * @return Res JSON с идентификатором созданного пользователя или ошибкой
     */
    public function create(Req $req, Res $res): Res
    {
        $data = (array)$req->getParsedBody();
        $email = trim((string)($data['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Response::problem($res, 400, 'Validation error', ['errors' => ['email' => 'invalid']]);
        }
        $stmt = $this->db->prepare('INSERT INTO users(email, created_at) VALUES(?, NOW())');
        $stmt->execute([$email]);
        return Response::json($res, 201, ['id' => (int)$this->db->lastInsertId()]);
    }
}
