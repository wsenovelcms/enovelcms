<?php

class PluginTable
{
    private static array $columns = [];

    public static function addColumn(string $tableKey, array $def): void
    {
        if ($tableKey === '' || empty($def['key']) || empty($def['label'])) return;
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$def['key']);
        if ($key === '') return;
        $def['key'] = $key;
        $def['render'] = (isset($def['render']) && is_callable($def['render'])) ? $def['render'] : null;
        $def['plugin_id'] = class_exists('Plugin') ? (string)Plugin::currentId() : '';
        foreach (self::$columns[$tableKey] ?? [] as $exists) {
            if ($exists['key'] === $key) return;
        }
        self::$columns[$tableKey][] = $def;
    }

    public static function columns(string $tableKey): array
    {
        return self::$columns[$tableKey] ?? [];
    }

    public static function count(string $tableKey): int
    {
        return count(self::$columns[$tableKey] ?? []);
    }

    public static function renderHead(string $tableKey): string
    {
        $cols = self::columns($tableKey);
        if (!$cols) return '';
        $html = '';
        foreach ($cols as $c) {
            $style = '';
            if (!empty($c['width'])) $style .= 'width:' . htmlspecialchars((string)$c['width']) . ';';
            if (!empty($c['align'])) $style .= 'text-align:' . htmlspecialchars((string)$c['align']) . ';';
            $html .= '<th' . ($style !== '' ? ' style="' . $style . '"' : '') . '>' . htmlspecialchars((string)$c['label']) . '</th>';
        }
        return $html;
    }

    public static function renderCells(string $tableKey, array $row): string
    {
        $cols = self::columns($tableKey);
        if (!$cols) return '';
        $html = '';
        foreach ($cols as $c) {
            $val = '';
            if ($c['render'] !== null) {
                try {
                    $val = (string)call_user_func($c['render'], $row);
                } catch (\Throwable $e) {
                    error_log('[table ' . $tableKey . '/' . $c['key'] . '] ' . $e->getMessage());
                }
            }
            $cls = !empty($c['class']) ? ' class="' . htmlspecialchars((string)$c['class']) . '"' : '';
            $html .= '<td data-col="' . htmlspecialchars((string)$c['key']) . '"' . $cls . '>' . $val . '</td>';
        }
        return $html;
    }
}
