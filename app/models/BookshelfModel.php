<?php

class BookshelfModel extends Model
{
    protected static string $table = 'bookshelf';

    public static function getByUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $list = self::db()->from('bookshelf', 'b')
            ->select('b.*, n.title, n.author, n.user_id, n.cover, n.is_vip, n.last_chapter_id, n.last_chapter_title')
            ->leftJoin('novels', 'n', 'n.id = b.novel_id')
            ->where('b.user_id = ?', [$userId])
            ->orderBy('b.updated_at DESC')
            ->limit($perPage, $offset)
            ->get();
        $total = self::count(['user_id' => $userId]);
        return [
            'list' => $list,
            'total' => $total,
            'page' => $page,
            'totalPages' => (int)ceil($total / $perPage),
        ];
    }

    public static function exists(int $userId, int $novelId): bool
    {
        return self::count(['user_id' => $userId, 'novel_id' => $novelId]) > 0;
    }

    public static function add(int $userId, int $novelId): int
    {
        if (self::exists($userId, $novelId)) return 0;
        return self::create(['user_id' => $userId, 'novel_id' => $novelId]);
    }

    public static function updateLastRead(int $userId, int $novelId, int $chapterId): void
    {
        if (self::exists($userId, $novelId)) {
            self::update(['last_read_chapter' => $chapterId], ['user_id' => $userId, 'novel_id' => $novelId]);
        }
    }
}
