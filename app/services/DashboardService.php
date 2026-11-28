<?php

class DashboardService
{
    private const CACHE_KEY = 'dashboard_stats';

    private const CACHE_TTL = 604800;

    private const MAX_AGE = 3600;

    public static function stats(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['at'], $cached['data']) && time() - (int)$cached['at'] < self::MAX_AGE) {
            return $cached;
        }
        return self::refresh();
    }

    public static function refresh(): array
    {
        $data = [
            'novels'           => NovelModel::count(),
            'chapters'         => ChapterModel::count(),
            'users'            => UserModel::count(),
            'orders'           => OrderModel::count(),
            'views'            => (int)NovelModel::sum('views'),
            'words'            => (int)NovelModel::sum('total_words'),
            'pending_apps'     => AuthorApplicationModel::count(['status' => 0]),
            'pending_withdraw' => WithdrawRequestModel::count(['status' => 0]),
        ];
        $payload = ['at' => time(), 'data' => $data];
        Cache::set(self::CACHE_KEY, $payload, self::CACHE_TTL);
        return $payload;
    }
}
