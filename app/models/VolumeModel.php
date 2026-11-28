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

    public static function create(array $data): int
    {
        $id = parent::create($data);
        if (!empty($data['novel_id'])) ChapterModel::forgetNovelList((int)$data['novel_id']);
        return $id;
    }

    public static function update(array $data, array $where): int
    {
        $affected = parent::update($data, $where);
        if ($affected > 0) self::forgetLists($where);
        return $affected;
    }

    public static function delete(array $where): int
    {
        self::forgetLists($where);
        return parent::delete($where);
    }

    private static function forgetLists(array $where): void
    {
        if (isset($where['novel_id'])) {
            ChapterModel::forgetNovelList((int)$where['novel_id']);
            return;
        }
        if (isset($where['id'])) {
            $vol = self::findById((int)$where['id'], 'novel_id');
            if ($vol) ChapterModel::forgetNovelList((int)$vol['novel_id']);
        }
    }
}
