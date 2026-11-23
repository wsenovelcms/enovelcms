<?php

class Logger
{
    private const MAX_BYTES = 2097152;
    private const CHANNELS = array('app', 'admin', 'upgrade', 'appcenter', 'cron', 'api', 'security');
    private const SCOPES = array('plugins', 'themes');

    public static function write(string $channel, string $content, array $context = array()): bool
    {
        $target = self::resolve($channel);
        if ($target === null) {
            error_log('[logger] unknown channel: ' . $channel);
            return false;
        }
        return self::put($target[0], $target[1], $content, $context);
    }

    public static function plugin(string $id, string $content, array $context = array()): bool
    {
        $id = strtolower(trim($id));
        if (!self::validId($id) || !is_dir(ROOT_PATH . 'plugins/' . $id)) return false;
        return self::put(self::extDir('plugins', $id), $id, $content, $context);
    }

    public static function theme(string $id, string $content, array $context = array()): bool
    {
        $id = strtolower(trim($id));
        if (!self::validId($id) || !is_dir(ROOT_PATH . 'templates/' . $id)) return false;
        return self::put(self::extDir('themes', $id), $id, $content, $context);
    }

    public static function read(string $channel, int $lines = 200): array
    {
        $target = self::resolve($channel);
        return $target === null ? array() : self::rows($target[0], $target[1], $lines);
    }

    public static function readPlugin(string $id, int $lines = 200): array
    {
        $id = strtolower(trim($id));
        if (!self::validId($id)) return array();
        return self::rows(self::extDir('plugins', $id), $id, $lines);
    }

    public static function readTheme(string $id, int $lines = 200): array
    {
        $id = strtolower(trim($id));
        if (!self::validId($id)) return array();
        return self::rows(self::extDir('themes', $id), $id, $lines);
    }

    public static function clear(string $channel): bool
    {
        $target = self::resolve($channel);
        if ($target === null) return false;
        return self::purge($target[0], $target[1]);
    }

    public static function clearPlugin(string $id): bool
    {
        $id = strtolower(trim($id));
        if (!self::validId($id)) return false;
        return self::purge(self::extDir('plugins', $id), $id);
    }

    public static function clearTheme(string $id): bool
    {
        $id = strtolower(trim($id));
        if (!self::validId($id)) return false;
        return self::purge(self::extDir('themes', $id), $id);
    }

    public static function channels(): array
    {
        return self::CHANNELS;
    }

    public static function extensions(): array
    {
        $out = array('plugins' => array(), 'themes' => array());
        foreach (self::SCOPES as $scope) {
            $dirs = glob(ROOT_PATH . 'data/logs/' . $scope . '/*', GLOB_ONLYDIR);
            foreach ((array)$dirs as $dir) {
                $id = basename($dir);
                if (self::validId($id) && self::files($dir, $id)) $out[$scope][] = $id;
            }
        }
        return $out;
    }

    private static function resolve(string $channel): ?array
    {
        $sys = self::channel(strtolower(trim($channel)));
        if ($sys === null) return null;
        return array(self::systemDir(), $sys);
    }

    private static function channel(string $channel): ?string
    {
        if (!in_array($channel, self::CHANNELS, true)) return null;
        return $channel;
    }

    private static function validId(string $id): bool
    {
        return (bool)preg_match('/^[a-z0-9_\-]{1,32}$/', $id);
    }

    private static function systemDir(): string
    {
        return ROOT_PATH . 'data/logs/';
    }

    private static function extDir(string $scope, string $id): string
    {
        return ROOT_PATH . 'data/logs/' . $scope . '/' . $id . '/';
    }

    private static function put(string $dir, string $name, string $content, array $context): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        $files = self::files($dir, $name);
        $file = $files ? $files[count($files) - 1] : $dir . $name . '.' . self::rand() . '.log';
        self::rotate($dir, $name, $file);

        $line = '[' . date('Y-m-d H:i:s') . '] ' . self::flatten($content);
        if ($context) {
            $line .= ' ' . json_encode(self::mask($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) !== false;
    }

    private static function rows(string $dir, string $name, int $lines): array
    {
        $rows = array();
        foreach (self::files($dir, $name) as $file) {
            $chunk = preg_split('/\r\n|\r|\n/', (string)@file_get_contents($file));
            foreach ($chunk as $row) {
                if ($row !== '') $rows[] = $row;
            }
        }
        return $lines > 0 ? array_slice($rows, -$lines) : $rows;
    }

    private static function purge(string $dir, string $name): bool
    {
        foreach (self::files($dir, $name) as $file) @unlink($file);
        return true;
    }

    private static function files(string $dir, string $name): array
    {
        $dir = rtrim($dir, '/\\') . '/';
        $files = glob($dir . $name . '.*.log');
        if (!$files) return array();
        usort($files, function ($a, $b) { return filemtime($a) <=> filemtime($b); });
        return $files;
    }

    private static function rand(): string
    {
        return bin2hex(random_bytes(6));
    }

    private static function flatten(string $content): string
    {
        return str_replace(array("\r\n", "\r", "\n"), ' | ', trim($content));
    }

    private static function mask(array $context): array
    {
        foreach ($context as $k => $v) {
            if (is_array($v)) {
                $context[$k] = self::mask($v);
                continue;
            }
            if (preg_match('/(password|passwd|pwd|token|secret|cookie|authorization|api_?key|private_?key)/i', (string)$k)) {
                $context[$k] = '***';
            }
        }
        return $context;
    }

    private static function rotate(string $dir, string $name, string &$file): void
    {
        if (!is_file($file) || @filesize($file) < self::MAX_BYTES) return;
        $stamp = date('YmdHis');
        if (@rename($file, $dir . $name . '.' . self::rand() . '.' . $stamp . '.log')) {
            $file = $dir . $name . '.' . self::rand() . '.log';
        }
    }
}
