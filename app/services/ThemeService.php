<?php

class ThemeService
{
    private $db = null;

    public function __construct(?object $db = null)
    {
        $this->db = $db;
    }

    public function installFromZip(string $zipPath, bool $overwrite): array
    {
        if (!class_exists('ZipArchive')) {
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_no_zip', '当前 PHP 未启用 zip 扩展'));
        }
        $tempDir = ROOT_PATH . 'data/temp';
        if (!is_dir($tempDir)) { @mkdir($tempDir, 0755, true); }
        $extractDir = $tempDir . '/theme_' . uniqid();
        if (!@mkdir($extractDir, 0755, true)) {
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_temp_dir', '无法创建临时目录'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_open_zip', '无法打开 ZIP 文件'));
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;
            $norm = str_replace('\\', '/', $name);
            if ($norm === '' || strpos($norm, '..') !== false
                || strpos($norm, '/') === 0
                || preg_match('#^[a-zA-Z]:#', $norm)) {
                $zip->close();
                $this->removeTree($extractDir);
                return array('ok' => false, 'msg' => t_admin('adm_theme_svc_bad_path', 'ZIP 包含非法路径（目录穿越）：%s', $name));
            }
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $themeDir = '';
        $items = scandir($extractDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $extractDir . '/' . $item;
            if (is_dir($full) && file_exists($full . '/theme.xml')) {
                $themeDir = $item;
                break;
            }
        }
        if ($themeDir === '') {
            $this->removeTree($extractDir);
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_no_meta', 'ZIP 中未找到有效主题（缺少 theme.xml）'));
        }

        $source = $extractDir . '/' . $themeDir;
        if (!Theme::validThemeId($themeDir)) {
            $this->removeTree($extractDir);
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_name_bad', '主题文件夹名只能含字母数字下划线'));
        }
        $target = ROOT_PATH . 'templates/' . $themeDir;

        if (is_dir($target) && !$overwrite) {
            $this->removeTree($extractDir);
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_exists', '主题已存在，如需覆盖请勾选覆盖选项'));
        }

        if (!$this->overwriteInstall($source, $target)) {
            $this->removeTree($extractDir);
            return array('ok' => false, 'msg' => t_admin('adm_theme_svc_move_fail', '移动主题目录失败，请检查权限'));
        }
        $this->removeTree($extractDir);

        if ($this->db && class_exists('Theme') && Theme::isRegistered($themeDir)) {
            Theme::maybeUpgrade($themeDir, $this->db);
        }

        return array('ok' => true, 'msg' => t_admin('adm_theme_svc_installed', '主题安装成功：%s', $themeDir));
    }

    private function overwriteInstall(string $source, string $target): bool
    {
        if (!is_dir($target)) {
            if (@rename($source, $target)) return true;
        }
        if (!$this->copyDir($source, $target)) return false;
        $this->pruneRemoved($target, $source);
        return true;
    }

    private function copyDir(string $source, string $target): bool
    {
        if (!@mkdir($target, 0755, true) && !is_dir($target)) return false;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($source));
            $dest = $target . $rel;
            if ($item->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                if (!@copy($item->getPathname(), $dest)) return false;
            }
        }
        return true;
    }

    private function pruneRemoved(string $target, string $source): void
    {
        if (!is_dir($target)) return;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($target));
            if (!file_exists($source . $rel)) {
                if ($item->isDir()) @rmdir($item->getRealPath());
                else @unlink($item->getRealPath());
            }
        }
    }

    private function removeTree(string $dir): bool
    {
        if (!is_dir($dir)) return false;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                if ($item->isDir()) { @rmdir($item->getRealPath()); }
                else { @unlink($item->getRealPath()); }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return @rmdir($dir);
    }
}
