<?php

declare(strict_types=1);

namespace App\Helpers;

use Throwable;

/**
 * Helper for working with scheduled batches while keeping legacy installs running.
 */
final class Scheduled
{
    /**
     * Validates that the given identifier contains only safe characters.
     */
    private static function isSafeIdentifier(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z0-9_]+$/', $value) === 1;
    }

    /**
     * Checks if a column exists in the given table.
     */
    private static function columnExists(\PDO $db, string $table, string $column): bool
    {
        if (!self::isSafeIdentifier($table) || !self::isSafeIdentifier($column)) {
            return false;
        }

        try {
            $sql = sprintf(
                'SHOW COLUMNS FROM `%s` LIKE %s',
                $table,
                $db->quote($column)
            );
            $stmt = $db->query($sql);
            return $stmt !== false && (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Checks if the given table is available in the current schema.
     */
    private static function tableExists(\PDO $db, string $table): bool
    {
        if (!self::isSafeIdentifier($table)) {
            return false;
        }

        try {
            $sql = sprintf('SHOW TABLES LIKE %s', $db->quote($table));
            $stmt = $db->query($sql);
            return $stmt !== false && (bool)$stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Creates a scheduled batch with optional targeting.
     *
     * @param string $method   Telegram Bot API method (for example, sendMessage)
     * @param array  $payload  Data payload passed to the API
     * @param string $type     Custom type that reflects the payload content
     * @param int    $priority Priority (2, 1, 0)
     * @param string $sendAfter Datetime when the batch should be processed (Y-m-d H:i:s)
     * @param array  $target   ['type' => 'all'|'group'|'selected', 'group_id' => int?, 'user_ids' => int[]?]
     */
    public static function createBatch(
        string $method,
        array $payload,
        string $type,
        int $priority,
        string $sendAfter,
        array $target
    ): bool {
        $db = Database::getInstance();

        if (empty($target['type']) || !in_array($target['type'], ['all', 'group', 'selected'], true)) {
            Logger::error('Scheduled.createBatch: invalid target type', ['target' => $target]);
            return false;
        }
        $targetType = $target['type'];
        $targetGroupId = null;
        $fixedUserIds = [];
        if ($targetType === 'group') {
            $gid = (int)($target['group_id'] ?? 0);
            if ($gid <= 0) {
                Logger::error('Scheduled.createBatch: group_id is required for group target');
                return false;
            }
            $targetGroupId = $gid;
        } elseif ($targetType === 'selected') {
            $fixedUserIds = array_values(array_unique(array_map('intval', (array)($target['user_ids'] ?? []))));
            $fixedUserIds = array_values(array_filter($fixedUserIds, static fn ($v) => $v > 0));
            if (!$fixedUserIds) {
                Logger::error('Scheduled.createBatch: user_ids required for selected target');
                return false;
            }
        }

        $hasTargetTypeColumn = self::columnExists($db, 'telegram_scheduled_messages', 'target_type');
        $hasTargetGroupColumn = self::columnExists($db, 'telegram_scheduled_messages', 'target_group_id');
        $hasSelectedCountColumn = self::columnExists($db, 'telegram_scheduled_messages', 'selected_count');
        $hasSelectedTargetsTable = self::tableExists($db, 'telegram_scheduled_targets');

        if (!$hasTargetTypeColumn && $targetType !== 'all') {
            Logger::error('Scheduled.createBatch: targeting not available until DB migration (only type=all allowed)', [
                'requested_type' => $targetType,
            ]);
            return false;
        }

        if ($targetType === 'group' && !$hasTargetGroupColumn) {
            Logger::error('Scheduled.createBatch: group targeting requires target_group_id column', [
                'requested_type' => $targetType,
            ]);
            return false;
        }

        if ($targetType === 'selected' && !$hasSelectedTargetsTable) {
            Logger::error('Scheduled.createBatch: selected targeting requires telegram_scheduled_targets table', [
                'requested_type' => $targetType,
            ]);
            return false;
        }

        try {
            $db->beginTransaction();

            $columns = ['method', 'type', 'data', 'priority', 'send_after', 'status'];
            $placeholders = [':method', ':type', ':data', ':priority', ':send_after', ':status'];
            $params = [
                'method' => $method,
                'type' => $type,
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'priority' => $priority,
                'send_after' => $sendAfter,
                'status' => 'pending',
            ];

            if ($hasTargetTypeColumn) {
                $columns[] = 'target_type';
                $placeholders[] = ':target_type';
                $params['target_type'] = $targetType;
            }

            if ($hasTargetGroupColumn) {
                $columns[] = 'target_group_id';
                $placeholders[] = ':target_group_id';
                $params['target_group_id'] = $targetGroupId;
            }

            if ($hasSelectedCountColumn) {
                $columns[] = 'selected_count';
                $placeholders[] = ':selected_count';
                $params['selected_count'] = $targetType === 'selected' ? count($fixedUserIds) : 0;
            }

            $columnsSql = '`' . implode('`, `', $columns) . '`, `created_at`';
            $valuesSql = implode(', ', $placeholders) . ', NOW()';

            $stmt = $db->prepare(
                sprintf(
                    'INSERT INTO `telegram_scheduled_messages` (%s) VALUES (%s)',
                    $columnsSql,
                    $valuesSql
                )
            );
            $stmt->execute($params);

            $scheduledId = (int)$db->lastInsertId();

            if ($targetType === 'selected' && $fixedUserIds && $hasSelectedTargetsTable) {
                $ins = $db->prepare('INSERT INTO `telegram_scheduled_targets` (`scheduled_id`, `target_user_id`) VALUES (:sid, :uid)');
                foreach ($fixedUserIds as $uid) {
                    $ins->execute(['sid' => $scheduledId, 'uid' => $uid]);
                }
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            try {
                $db->rollBack();
            } catch (Throwable) {
            }
            Logger::error('Failed to create scheduled batch', [
                'exception' => $e,
                'target' => $target,
            ]);
        }

        return false;
    }
}
