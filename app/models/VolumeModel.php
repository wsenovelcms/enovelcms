<?php

class VolumeModel extends Model
{
    protected static string $table = 'volumes';

    public static function getByNovel(int $novelId): array
    {
        return self::all(['novel_id' => $novelId], 'sort ASC, id ASC');
    }

    public static function countChapters(int $volumeId): int
    {
        return ChapterModel::count(['volume_id' => $volumeId]);
    }

    public static function firstVolume(int $novelId): ?array
    {
        $rows = self::all(['novel_id' => $novelId], 'sort ASC, id ASC', 1);
        return $rows ? $rows[0] : null;
    }

    public static function deleteByNovel(int $novelId): int
    {
        return self::delete(['novel_id' => $novelId]);
    }
}
