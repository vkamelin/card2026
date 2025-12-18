<?php

/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Helpers\RefreshTokenService;
use App\Helpers\Response;
use PDO;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

/**
 * Контроллер авторизации пользователей.
 *
 * Обрабатывает выдачу JWT токена.
 */
final class AuthController
{
    /**
     * @param PDO   $db
     * @param array $jwtCfg Настройки JWT (secret, alg, ttl)
     */
    public function __construct(private PDO $db, private array $jwtCfg)
    {
    }

    /**
     * Выполняет вход пользователя и возвращает JWT.
     *
     * @param Req $req HTTP-запрос
     * @param Res $res HTTP-ответ
     * @return Res JSON с токеном или сообщением об ошибке
     */
    public function login(Req $req, Res $res): Res
    {
        $data = (array)$req->getParsedBody();
        $schema = \App\Helpers\SchemaLoader::load('auth_login');
        $resSchema = \App\Helpers\JsonSchemaValidator::validate($data, $schema);
        if (!($resSchema['ok'] ?? false)) {
            throw new \App\Exceptions\ValidationException($resSchema['errors'] ?? [], 'Invalid payload');
        }
        // normalization
        $email = isset($data['email']) && is_string($data['email']) ? trim($data['email']) : '';
        $pass = isset($data['password']) && is_string($data['password']) ? (string)$data['password'] : '';

        $stmt = $this->db->prepare('SELECT id, password FROM users WHERE email=? LIMIT 1');
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($pass, $u['password'])) {
            return Response::problem($res, 401, 'Invalid credentials');
        }

        $jti = bin2hex(random_bytes(16));
        $payload = ['uid' => (int)$u['id'], 'exp' => time() + $this->jwtCfg['ttl'], 'jti' => $jti];
        $token = \Firebase\JWT\JWT::encode($payload, $this->jwtCfg['secret'], $this->jwtCfg['alg']);

        $refresh = (new RefreshTokenService($this->db, $this->jwtCfg['refresh_ttl'] ?? 2592000))->generate((int)$u['id'], $jti);

        return Response::json($res, 200, ['token' => $token, 'refresh_token' => $refresh]);
    }

    /**
     * Обновляет JWT по предоставленному refresh-токену.
     *
     * Проверяет валидность refresh-токена, отзывает его и выдаёт новую
     * пару: access-токен и refresh-токен.
     *
     * @param Req $req HTTP-запрос
     * @param Res $res HTTP-ответ
     * @return Res JSON с новой парой токенов или ошибкой
     */
    public function refresh(Req $req, Res $res): Res
    {
        $data = (array)$req->getParsedBody();
        $schema = \App\Helpers\SchemaLoader::load('auth_refresh');
        $resSchema = \App\Helpers\JsonSchemaValidator::validate($data, $schema);
        if (!($resSchema['ok'] ?? false)) {
            throw new \App\Exceptions\ValidationException($resSchema['errors'] ?? [], 'Invalid payload');
        }
        $refresh = isset($data['refresh_token']) && is_string($data['refresh_token']) ? (string)$data['refresh_token'] : '';

        $svc = new RefreshTokenService($this->db, $this->jwtCfg['refresh_ttl'] ?? 2592000);
        $row = $svc->validate($refresh);
        if ($row === null) {
            return Response::problem($res, 401, 'Invalid refresh token');
        }

        // revoke old token to prevent reuse
        $svc->revoke($refresh);

        $jti = bin2hex(random_bytes(16));
        $payload = ['uid' => (int)$row['user_id'], 'exp' => time() + $this->jwtCfg['ttl'], 'jti' => $jti];
        $token = \Firebase\JWT\JWT::encode($payload, $this->jwtCfg['secret'], $this->jwtCfg['alg']);

        $newRefresh = $svc->generate((int)$row['user_id'], $jti);

        return Response::json($res, 200, ['token' => $token, 'refresh_token' => $newRefresh]);
    }
}
