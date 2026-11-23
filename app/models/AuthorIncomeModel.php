<?php

class AuthorIncomeModel extends Model
{
    protected static string $table = 'author_incomes';

    public static function getByAuthor(int $userId, int $page = 1, int $perPage = 30): array
    {
        return self::paginate(['user_id' => $userId], 'id DESC', $page, $perPage);
    }

    public static function log(int $userId, int $novelId, int $chapterId, float $amount, string $type = 'sale', int $status = 0): int
    {
        return self::create([
            'user_id' => $userId, 'novel_id' => $novelId, 'chapter_id' => $chapterId,
            'amount' => $amount, 'type' => $type, 'status' => $status,
        ]);
    }

    public static function stats(int $userId): array
    {
        $t = self::tableName();
        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN type='sale' THEN amount ELSE 0 END),0) AS total_sale,
                    COALESCE(SUM(CASE WHEN type='withdraw' THEN amount ELSE 0 END),0) AS withdraw,
                    COALESCE(SUM(CASE WHEN type='adjust' THEN amount ELSE 0 END),0) AS adjust,
                    COALESCE(SUM(CASE WHEN status=0 AND type='sale' THEN amount ELSE 0 END),0) AS unsettled
                FROM {$t} WHERE user_id = ?";
        return self::db()->query($sql, [$userId])->fetch();
    }

    public static function byNovel(int $userId): array
    {
        $t = self::tableName();
        $sql = "SELECT novel_id, COALESCE(SUM(amount),0) AS amount, COUNT(*) AS cnt
                FROM {$t} WHERE user_id = ? AND type='sale' GROUP BY novel_id";
        return self::db()->query($sql, [$userId])->fetchAll();
    }
}
