<?php

/**
 * Copyright (c) 2025. Vitaliy Kamelin <v.kamelin@gmail.com>
 */

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Helpers\PromoCodeHelper;
use App\Helpers\Response;
use PDO;
use Psr\Http\Message\ResponseInterface as Res;
use Psr\Http\Message\ServerRequestInterface as Req;

final class PromoCodeController
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Загрузка CSV с кодами. Создаёт батч и вставляет коды транзакционно.
     * Body: multipart/form-data, file: CSV
     */
    public function upload(Req $req, Res $res): Res
    {
        $files = $req->getUploadedFiles();
        if (!isset($files['file'])) {
            return Response::problem($res, 400, 'File is required');
        }
        $file = $files['file'];
        $filename = $file->getClientFilename() ?? 'upload.csv';
        $size = $file->getSize() ?? 0;
        if ($size <= 0) {
            return Response::problem($res, 400, 'Empty file');
        }

        $jwt = (array)($req->getAttribute('jwt') ?? []);
        $createdBy = (int)($jwt['uid'] ?? 0);

        $codes = [];
        try {
            $codes = PromoCodeHelper::parseCsv($file->getStream());
        } catch (\Throwable $e) {
            return Response::problem($res, 400, 'Invalid CSV', ['detail' => $e->getMessage()]);
        }
        if ($codes === []) {
            return Response::problem($res, 400, 'No codes found');
        }

        $this->db->beginTransaction();
        try {
            // Создаём батч
            $stmt = $this->db->prepare('INSERT INTO promo_code_batches(filename, created_by, total, created_at) VALUES(?, ?, 0, NOW())');
            $stmt->execute([$filename, $createdBy ?: null]);
            $batchId = (int)$this->db->lastInsertId();

            // Вставляем коды
            PromoCodeHelper::insertCodes($this->db, $batchId, $codes);

            // Обновляем счётчик
            $stmt = $this->db->prepare('UPDATE promo_code_batches SET total = ? WHERE id = ? LIMIT 1');
            $stmt->execute([count($codes), $batchId]);

            $this->db->commit();
            return Response::json($res, 201, ['batch_id' => $batchId, 'inserted' => count($codes)]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate')) {
                return Response::problem($res, 409, 'Duplicate codes', ['detail' => $msg]);
            }
            return Response::problem($res, 400, 'Upload failed', ['detail' => $msg]);
        }
    }

    /**
     * Список промокодов с фильтрами.
     * Query: status, batch_id, q (поиск по code), page, per_page
     */
    public function listCodes(Req $req, Res $res): Res
    {
        $params = $req->getQueryParams();
        $status = isset($params['status']) ? (string)$params['status'] : null;
        $batchId = isset($params['batch_id']) ? (int)$params['batch_id'] : null;
        $q = isset($params['q']) ? trim((string)$params['q']) : null;
        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(200, max(1, (int)($params['per_page'] ?? 50)));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $args = [];
        if ($status) {
            $where[] = 'status = ?';
            $args[] = $status;
        }
        if ($batchId) {
            $where[] = 'batch_id = ?';
            $args[] = $batchId;
        }
        if ($q) {
            $where[] = 'code LIKE ?';
            $args[] = '%' . $q . '%';
        }
        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $stmt = $this->db->prepare("SELECT SQL_CALC_FOUND_ROWS id, batch_id, code, status, expires_at, issued_at FROM promo_codes {$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
        $stmt->execute($args);
        $items = $stmt->fetchAll();
        $total = (int)$this->db->query('SELECT FOUND_ROWS()')->fetchColumn();

        return Response::json($res, 200, [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
    }

    /**
     * Выдаёт промокод пользователю по telegram user_id.
     * Body: { user_id: number, batch_id?: number }
     */
    public function issue(Req $req, Res $res): Res
    {
        $data = (array)$req->getParsedBody();
        $userId = (int)($data['user_id'] ?? 0);
        $batchId = isset($data['batch_id']) ? (int)$data['batch_id'] : null;
        if ($userId <= 0) {
            return Response::problem($res, 422, 'Validation error', ['errors' => ['user_id' => 'required']]);
        }

        // Проверим, что пользователь существует
        $uStmt = $this->db->prepare('SELECT id FROM telegram_users WHERE user_id = ? LIMIT 1');
        $uStmt->execute([$userId]);
        if (!$uStmt->fetch()) {
            return Response::problem($res, 404, 'User not found');
        }

        $jwt = (array)($req->getAttribute('jwt') ?? []);
        $issuedBy = (int)($jwt['uid'] ?? 0);

        $this->db->beginTransaction();
        try {
            // Находим доступный неистёкший код и блокируем его
            $where = 'status = \"available\" AND (expires_at IS NULL OR expires_at > NOW())';
            $args = [];
            if ($batchId) {
                $where .= ' AND batch_id = ?';
                $args[] = $batchId;
            }
            $sql = "SELECT id, code FROM promo_codes WHERE {$where} ORDER BY id ASC LIMIT 1 FOR UPDATE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($args);
            $codeRow = $stmt->fetch();
            if (!$codeRow) {
                $this->db->rollBack();
                return Response::problem($res, 409, 'No available promo codes');
            }

            $codeId = (int)$codeRow['id'];
            // Обновляем статус
            $upd = $this->db->prepare('UPDATE promo_codes SET status=\"issued\", issued_at = NOW() WHERE id = ? LIMIT 1');
            $upd->execute([$codeId]);

            // Логируем выдачу
            $ins = $this->db->prepare('INSERT INTO promo_code_issues(code_id, telegram_user_id, issued_by, issued_at) VALUES(?, ?, ?, NOW())');
            $ins->execute([$codeId, $userId, $issuedBy ?: null]);

            $this->db->commit();
            return Response::json($res, 201, [
                'code_id' => $codeId,
                'code' => (string)$codeRow['code'],
            ]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return Response::problem($res, 400, 'Issue failed', ['detail' => $e->getMessage()]);
        }
    }

    /**
     * Список батчей.
     */
    public function batches(Req $req, Res $res): Res
    {
        $q = $this->db->query('SELECT id, filename, created_by, total, created_at FROM promo_code_batches ORDER BY id DESC LIMIT 200');
        return Response::json($res, 200, ['items' => $q->fetchAll()]);
    }

    /**
     * Отчёт по выдачам.
     */
    public function issues(Req $req, Res $res): Res
    {
        $params = $req->getQueryParams();
        $limit = min(200, max(1, (int)($params['limit'] ?? 100)));
        $stmt = $this->db->prepare(
            'SELECT i.id, i.code_id, i.telegram_user_id, i.issued_by, i.issued_at,
                    c.code, c.batch_id, b.filename, tu.username
             FROM promo_code_issues i
             JOIN promo_codes c ON c.id = i.code_id
             LEFT JOIN promo_code_batches b ON b.id = c.batch_id
             LEFT JOIN telegram_users tu ON tu.user_id = i.telegram_user_id
             ORDER BY i.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return Response::json($res, 200, ['items' => $stmt->fetchAll()]);
    }
}
