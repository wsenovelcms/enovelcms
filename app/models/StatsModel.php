<?php

class StatsModel extends Model
{
    protected static string $table = 'stats_visit';

    public static function record(string $date, bool $isSpider): void
    {
        $row = self::find(['visit_date' => $date]);
        if ($row) {
            self::increment('pv', 1, ['visit_date' => $date]);
            self::increment($isSpider ? 'spider_count' : 'real_count', 1, ['visit_date' => $date]);
        } else {
            self::create([
                'visit_date'   => $date,
                'spider_count' => $isSpider ? 1 : 0,
                'real_count'   => $isSpider ? 0 : 1,
                'pv'           => 1,
            ]);
        }
    }

    public static function summary(string $from, string $to): array
    {
        $t = self::tableName();
        $sql = "SELECT COALESCE(SUM(pv),0) AS pv, COALESCE(SUM(real_count),0) AS real,
                       COALESCE(SUM(spider_count),0) AS spider FROM {$t}
                WHERE visit_date BETWEEN ? AND ?";
        return self::db()->query($sql, [$from, $to])->fetch();
    }
}
