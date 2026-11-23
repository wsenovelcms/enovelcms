<?php

class AdsModel extends Model
{
    protected static string $table = 'ads';

    public static function getByPosition(string $position): array
    {
        return self::all(['position' => $position, 'enabled' => 1], 'sort ASC, id ASC');
    }

    public static function render(string $position): string
    {
        $html = '';
        foreach (self::getByPosition($position) as $ad) {
            $html .= $ad['html_code'] . "\n";
        }
        return $html;
    }
}
