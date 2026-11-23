<?php

class SeoModel extends Model
{
    protected static string $table = 'seo_settings';

    public static function getByPage(string $page): ?array
    {
        return self::find(['page' => $page]);
    }

    public static function getAllKeyed(): array
    {
        $result = [];
        foreach (self::all() as $row) {
            $result[$row['page']] = $row;
        }
        return $result;
    }
}
