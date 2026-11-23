<?php

class AutoPurchaseModel extends Model
{
    protected static string $table = 'auto_purchase';

    public static function isOn(int $userId, int $novelId): bool
    {
        if ($userId <= 0 || $novelId <= 0) return false;
        $row = self::find(['user_id' => $userId, 'novel_id' => $novelId], 'enabled');
        return $row && (int)$row['enabled'] === 1;
    }

    public static function set(int $userId, int $novelId, bool $enabled): void
    {
        if ($userId <= 0 || $novelId <= 0) return;
        $row = self::find(['user_id' => $userId, 'novel_id' => $novelId], 'id');
        $enabledFlag = $enabled ? 1 : 0;
        if ($row) {
            self::update(['enabled' => $enabledFlag], ['id' => (int)$row['id']]);
        } else {
            self::create([
                'user_id'  => $userId,
                'novel_id' => $novelId,
                'enabled'  => $enabledFlag,
            ]);
        }
    }

    public static function listByUser(int $userId): array
    {
        $t = self::tableName();
        $nt = NovelModel::tableName();
        return self::db()->query(
            "SELECT a.id, a.novel_id, a.enabled, a.updated_at, n.title AS novel_title "
            . "FROM {$t} a LEFT JOIN {$nt} n ON a.novel_id = n.id "
            . "WHERE a.user_id = ? AND a.enabled = 1 ORDER BY a.id DESC",
            [$userId]
        )->fetchAll();
    }

    public static function novelIdsByUser(int $userId): array
    {
        $rows = self::all(['user_id' => $userId, 'enabled' => 1], '', 0, 0, 'novel_id');
        return array_map('intval', array_column($rows, 'novel_id'));
    }
}
