<?php

class CrawlNovelClaimModel extends Model
{

    const CLAIM_TTL = 900;

    protected static string $table = 'crawl_novel_claims';

    public static function isClaimed(int $ruleId, string $sourceNovelId): bool
    {
        $t = self::tableName();
        $row = self::db()->query(
            "SELECT rule_id FROM {$t} WHERE rule_id = ? AND source_novel_id = ? LIMIT 1",
            array($ruleId, $sourceNovelId)
        )->fetch();
        return !empty($row);
    }

    public static function claim(int $ruleId, string $sourceNovelId, int $taskId): bool
    {
        $t = self::tableName();
        $now = date('Y-m-d H:i:s');
        $threshold = date('Y-m-d H:i:s', time() - self::CLAIM_TTL);
        self::db()->query(
            "INSERT INTO {$t} (rule_id, source_novel_id, task_id, claimed_at) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                task_id = IF(claimed_at < ?, VALUES(task_id), task_id),
                claimed_at = IF(claimed_at < ?, VALUES(claimed_at), claimed_at)",
            array($ruleId, $sourceNovelId, $taskId, $now, $threshold, $threshold)
        );
        $row = self::db()->query(
            "SELECT task_id FROM {$t} WHERE rule_id=? AND source_novel_id=?",
            array($ruleId, $sourceNovelId)
        )->fetch();
        return $row && (int)$row['task_id'] === $taskId;
    }

    public static function releaseByTask(int $taskId): int
    {
        $t = self::tableName();
        $stmt = self::db()->query("DELETE FROM {$t} WHERE task_id=?", array($taskId));
        return $stmt->rowCount();
    }
}
