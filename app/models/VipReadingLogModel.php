<?php

class VipReadingLogModel extends Model
{
    protected static string $table = 'vip_reading_logs';

    public static function log(int $userId, int $novelId, int $chapterId, int $wordCount): int
    {
        return self::create([
            'user_id' => $userId, 'novel_id' => $novelId,
            'chapter_id' => $chapterId, 'word_count' => $wordCount,
        ]);
    }

    public static function countByUser(int $userId): int
    {
        return self::count(['user_id' => $userId]);
    }
}
