<?php

class LanguageMetaModel extends Model
{
    protected static string $table = 'languages';

    public static function record(string $scope, string $owner, string $code, string $name, string $file): void
    {
        $scope = preg_replace('/[^a-z]/', '', $scope);
        $owner = preg_replace('/[^a-z0-9_\-]/', '', $owner);
        $code  = strtolower(trim($code));
        if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) return;
        $name  = mb_substr(trim($name), 0, 100);
        $file  = mb_substr($file, 0, 255);
        $row = self::find(['scope' => $scope, 'owner' => $owner, 'code' => $code]);
        if ($row) {
            self::update(['name' => $name, 'file' => $file], ['id' => $row['id']]);
        } else {
            self::create(compact('scope', 'owner', 'code', 'name', 'file'));
        }
    }

    public static function remove(string $scope, string $owner, string $code): void
    {
        $scope = preg_replace('/[^a-z]/', '', $scope);
        $owner = preg_replace('/[^a-z0-9_\-]/', '', $owner);
        $code  = strtolower(trim($code));
        if ($code === '') return;
        self::delete(['scope' => $scope, 'owner' => $owner, 'code' => $code]);
    }

    public static function nameMap(string $scope, string $owner): array
    {
        $scope = preg_replace('/[^a-z]/', '', $scope);
        $owner = preg_replace('/[^a-z0-9_\-]/', '', $owner);
        $out = [];
        try {
            foreach (self::all(['scope' => $scope, 'owner' => $owner]) as $r) {
                if ($r['name'] !== '') $out[$r['code']] = $r['name'];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public static function allNames(): array
    {
        $out = [];
        try {
            foreach (self::all() as $r) {
                if ($r['name'] !== '' && !isset($out[$r['code']])) $out[$r['code']] = $r['name'];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    public static function registerPluginLanguages(string $id): void
    {
        $id = preg_replace('/[^a-z0-9_\-]/', '', $id);
        if ($id === '' || !self::db()) return;
        $dir = ROOT_PATH . 'plugins/' . $id . '/lang/';
        if (!is_dir($dir)) return;
        $names = self::allNames();
        foreach (glob($dir . '*.php') as $file) {
            $code = basename($file, '.php');
            if (!preg_match('/^[a-z]{2}-[a-z]{2}$/', $code)) continue;
            $row = self::find(['scope' => 'plugin', 'owner' => $id, 'code' => $code]);
            if (!$row) {
                $name = ($names[$code] ?? '') ?: strtoupper($code);
                self::record('plugin', $id, $code, $name, 'plugins/' . $id . '/lang/' . $code . '.php');
            }
        }
    }

    public static function removeByOwner(string $scope, string $owner): void
    {
        $scope = preg_replace('/[^a-z]/', '', $scope);
        $owner = preg_replace('/[^a-z0-9_\-]/', '', $owner);
        if ($owner === '' || !self::db()) return;
        self::delete(['scope' => $scope, 'owner' => $owner]);
    }
}
