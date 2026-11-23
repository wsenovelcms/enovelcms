<?php

class CrawlTaskModel extends Model
{
    protected static string $table = 'crawl_tasks';

    public static function getByStatus(string $status): array
    {
        return self::all(['status' => $status], 'id DESC');
    }

    public static function updateProgress(int $id, array $data): void
    {
        self::updateById($data, $id);
    }

    public static function requestStop(int $id): void
    {
        self::updateById(['stop_flag' => 1], $id);
    }

    public static function claimPending(): ?array
    {
        $t = self::tableName();
        for ($i = 0; $i < 30; $i++) {
            $next = self::db()->query(
                "SELECT id FROM {$t} WHERE status='pending' ORDER BY id ASC LIMIT 1"
            )->fetch();
            if (!$next) return null;
            $id = (int)$next['id'];
            $res = self::db()->query(
                "UPDATE {$t} SET status='running', stop_flag=0, started_at=NOW(),
                        finished_at=NULL, current_novel='', error=''
                 WHERE id=? AND status='pending'",
                array($id)
            );
            if ($res->rowCount() === 1) {
                return self::findById($id);
            }
            usleep(100000);
        }
        return null;
    }

    public static function enqueue(int $ruleId, string $options = ''): int
    {
        return self::create(array(
            'rule_id' => $ruleId,
            'status' => 'pending',
            'options' => $options,
            'error' => '',
            'stop_flag' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    public static function hasActiveForRule(int $ruleId): bool
    {
        $t = self::tableName();
        $row = self::db()->query(
            "SELECT id FROM {$t} WHERE rule_id=? AND status IN ('pending','running') ORDER BY id DESC LIMIT 1",
            array($ruleId)
        )->fetch();
        return !empty($row);
    }

    public static function getActiveRuleIds(): array
    {
        $t = self::tableName();
        $rows = self::db()->query(
            "SELECT DISTINCT rule_id FROM {$t} WHERE status IN ('pending','running')"
        )->fetchAll();
        $ids = array();
        foreach ($rows as $r) $ids[(int)$r['rule_id']] = true;
        return $ids;
    }

    public static function getRunningWithRule(): array
    {
        $t = self::tableName();
        $r = self::db()->table('crawl_rules');
        return self::db()->query(
            "SELECT t.*, r.name AS rule_name FROM {$t} t
             LEFT JOIN {$r} r ON r.id=t.rule_id
             WHERE t.status='running' ORDER BY t.id ASC"
        )->fetchAll();
    }

    public static function getRecentWithRule(int $limit = 15): array
    {
        $t = self::tableName();
        $r = self::db()->table('crawl_rules');
        return self::db()->query(
            "SELECT t.*, r.name AS rule_name FROM {$t} t
             LEFT JOIN {$r} r ON r.id=t.rule_id
             ORDER BY t.id DESC LIMIT " . (int)$limit
        )->fetchAll();
    }

    public static function getTotals(): array
    {
        $t = self::tableName();
        $row = self::db()->query(
            "SELECT COUNT(*) AS total_tasks,
                    COALESCE(SUM(processed_novels),0) AS total_novels,
                    COALESCE(SUM(fetched_chapters),0) AS total_chapters
             FROM {$t}"
        )->fetch();
        return $row ?: array('total_tasks' => 0, 'total_novels' => 0, 'total_chapters' => 0);
    }

    public static function requeue(int $id): void
    {
        self::updateById(array(
            'status' => 'pending',
            'stop_flag' => 0,
            'current_novel' => '',
            'error' => '',
            'started_at' => null,
            'finished_at' => null,
        ), $id);
    }

    public static function markStatus(int $id, string $status, string $error = ''): void
    {
        $data = array(
            'status' => $status,
            'stop_flag' => 0,
            'current_novel' => '',
            'finished_at' => date('Y-m-d H:i:s'),
        );
        if ($error !== '') $data['error'] = $error;
        self::updateById($data, $id);
    }

    public static function incFetched(int $id): void
    {
        $t = self::tableName();
        self::db()->query(
            "UPDATE {$t} SET fetched_chapters=fetched_chapters+1 WHERE id=?",
            array($id)
        );
    }

    public static function incProcessed(int $id): void
    {
        $t = self::tableName();
        self::db()->query(
            "UPDATE {$t} SET processed_novels=processed_novels+1 WHERE id=?",
            array($id)
        );
    }

    public static function getStatusFlag(int $id): ?array
    {
        $t = self::tableName();
        $row = self::db()->query(
            "SELECT status, stop_flag FROM {$t} WHERE id=?",
            array($id)
        )->fetch();
        return $row ?: null;
    }
}
