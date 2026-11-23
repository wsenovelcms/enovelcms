<?php

class Ui
{

    public static function registerFront(): void
    {
        Hook::addAction('head_end', [self::class, 'frontCss']);
        Hook::addAction('body_end', [self::class, 'frontJs']);
    }

    public static function registerAdmin(): void
    {
        Hook::addAction('admin_head', [self::class, 'adminCss']);
        Hook::addAction('admin_footer', [self::class, 'adminJs']);
        Hook::addAction('admin_login_footer', [self::class, 'adminJs']);
    }

    private static function resolveThemeFile(string $rel): ?array
    {
        $theme = View::getTheme();
        $candidates = $theme === 'default' ? ['default'] : [$theme, 'default'];
        foreach ($candidates as $t) {
            $abs = ROOT_PATH . 'templates/' . $t . '/' . $rel;
            if (is_file($abs)) {
                return [$abs, BASE_URL . '/templates/' . $t . '/' . $rel];
            }
        }
        return null;
    }

    public static function frontCss(): void
    {
        $f = self::resolveThemeFile('css/ui.css');
        if ($f) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($f[1]) . '?v=' . filemtime($f[0]) . '">';
        }
    }

    public static function frontJs(): void
    {
        $f = self::resolveThemeFile('js/uikit.js');
        if ($f) {
            echo '<script src="' . htmlspecialchars($f[1]) . '?v=' . filemtime($f[0]) . '"></script>';
        }
    }

    public static function adminCss(): void
    {
        $abs = ROOT_PATH . 'admin/assets/ui.css';
        if (is_file($abs)) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars(admin_asset('ui.css')) . '?v=' . filemtime($abs) . '">';
        }
    }

    public static function adminJs(): void
    {
        $abs = ROOT_PATH . 'admin/assets/ui.js';
        if (is_file($abs)) {
            echo '<script src="' . htmlspecialchars(admin_asset('ui.js')) . '?v=' . filemtime($abs) . '"></script>';
        }
    }
}
