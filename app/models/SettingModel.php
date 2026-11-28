<?php

class SettingModel extends Model
{
    protected static string $table = 'settings';

    private static array $cache = [];

    private static bool $preloaded = false;

    public static function get(string $key, $default = null)
    {
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        self::preload();
        if (array_key_exists($key, self::$cache)) return self::$cache[$key];
        return $default;
    }

    private static function preload(): void
    {
        if (self::$preloaded) return;
        self::$preloaded = true;
        try {
            foreach (self::all() as $row) {
                self::$cache[(string)$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
        }
    }

    public static function set(string $key, string $value): void
    {
        $existing = self::find(['key' => $key]);
        if ($existing) {
            self::update(['value' => $value], ['key' => $key]);
        } else {
            self::create(['key' => $key, 'value' => $value]);
        }
        self::$cache[$key] = $value;
    }

    public static function getAll(): array
    {
        return Cache::remember('settings_all', 3600, function () {
            $rows = self::all();
            $result = [];
            foreach ($rows as $row) $result[$row['key']] = $row['value'];
            return $result;
        });
    }

    public static function clearCache(): void
    {
        Cache::delete('settings_all');
        self::$cache = [];
        self::$preloaded = false;
    }
}
