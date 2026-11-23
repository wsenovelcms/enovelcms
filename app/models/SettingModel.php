<?php

class SettingModel extends Model
{
    protected static string $table = 'settings';

    private static array $cache = [];

    public static function get(string $key, $default = null)
    {
        if (isset(self::$cache[$key])) return self::$cache[$key];
        $row = self::find(['key' => $key]);
        $val = $row ? $row['value'] : $default;
        self::$cache[$key] = $val;
        return $val;
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
    }
}
