<?php

class CrawlLogModel extends Model
{
    protected static string $table = 'crawl_logs';

    public static function getByTask(int $taskId, int $limit = 100): array
    {
        return self::all(['task_id' => $taskId], 'id DESC', $limit);
    }

    public static function add(int $taskId, string $level, string $message,
                              string $url = '', int $httpCode = 0): int
    {
        return self::create(array(
            'task_id' => $taskId,
            'level' => $level,
            'message' => $message,
            'url' => excerpt($url, 500, ''),
            'http_code' => $httpCode,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public static function deleteByTask(int $taskId): int
    {
        return self::delete(['task_id' => $taskId]);
    }

    public static function getForTasks(array $taskIds, int $limit = 150): array
    {
        if (empty($taskIds)) return array();
        $t = self::tableName();
        $ph = implode(',', array_fill(0, count($taskIds), '?'));
        return self::db()->query(
            "SELECT task_id, level, message, http_code, created_at
             FROM {$t} WHERE task_id IN ({$ph}) ORDER BY id DESC LIMIT " . (int)$limit,
            array_values(array_map('intval', $taskIds))
        )->fetchAll();
    }

    public static function getForLastTask(int $taskId, int $limit = 100): array
    {
        $t = self::tableName();
        return self::db()->query(
            "SELECT task_id, level, message, http_code, created_at
             FROM {$t} WHERE task_id=? ORDER BY id DESC LIMIT " . (int)$limit,
            array($taskId)
        )->fetchAll();
    }
}
