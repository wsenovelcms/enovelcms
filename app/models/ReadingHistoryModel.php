<?php

class ReadingHistoryModel extends Model
{
    protected static string $table = 'reading_history';

    public static function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $t = self::tableName();
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT h.*, n.title AS novel_title, n.cover
                FROM {$t} h
                INNER JOIN (
                    SELECT novel_id, MAX(id) AS max_id
                    FROM {$t} WHERE user_id = ?
                    GROUP BY novel_id
                ) latest ON latest.novel_id = h.novel_id AND latest.max_id = h.id
                LEFT JOIN " . tb('novels') . " n ON n.id = h.novel_id
                WHERE h.user_id = ?
                ORDER BY h.read_at DESC, h.id DESC
                LIMIT {$offset}, {$perPage}";
        $list = self::db()->query($sql, [$userId, $userId])->fetchAll();

        $chapterIds = array_column($list, 'chapter_id');
        $titles = ChapterPurchaseModel::getChapterTitlesByIds($chapterIds);
        foreach ($list as &$row) {
            $row['chapter_title'] = $titles[(int)$row['chapter_id']] ?? '';
        }
        unset($row);

        $cntRow = self::db()->query(
            "SELECT COUNT(DISTINCT novel_id) AS c FROM {$t} WHERE user_id = ?",
            [$userId]
        )->fetch();
        $total = (int)($cntRow['c'] ?? 0);

        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function record(int $userId, int $novelId, int $chapterId): void
    {
        $existing = self::find(['user_id' => $userId, 'novel_id' => $novelId, 'chapter_id' => $chapterId]);
        if ($existing) {
            self::update(['read_at' => date('Y-m-d H:i:s')], ['id' => $existing['id']]);
        } else {
            self::create(['user_id' => $userId, 'novel_id' => $novelId, 'chapter_id' => $chapterId]);
        }
    }
}
