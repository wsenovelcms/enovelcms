<?php

class AuthorApplicationModel extends Model
{
    protected static string $table = 'author_applications';

    public static function getByUser(int $userId): ?array
    {
        return self::find(['user_id' => $userId]);
    }

    public static function getApprovedRealName(int $userId): string
    {
        $row = self::db()->query(
            'SELECT real_name FROM ' . self::tableName() . ' WHERE user_id = ? AND status = 1 ORDER BY id DESC LIMIT 1',
            [$userId]
        )->fetch();
        return $row ? trim((string)$row['real_name']) : '';
    }

    public static function getPending(int $page = 1, int $perPage = 20): array
    {
        return self::paginate(['status' => 0], 'id DESC', $page, $perPage);
    }

    public static function getAllWithUser(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $result = self::db()->from('author_applications', 'a')
            ->select('a.*, u.username')
            ->leftJoin('users', 'u', 'u.id = a.user_id')
            ->orderBy('a.id DESC')
            ->paginate($perPage, $page);
        return ['list' => $result['list'], 'total' => $result['total'], 'page' => $page];
    }

    public static function audit(int $id, int $status, ?string $reason = null): void
    {
        $data = ['status' => $status];
        if ($status === 2 && $reason !== null) {
            $data['reject_reason'] = $reason;
        }
        self::updateById($data, $id);
    }

    public static function deleteByUser(int $userId): void
    {
        self::db()->delete(static::$table, ['user_id' => $userId]);
    }
}
