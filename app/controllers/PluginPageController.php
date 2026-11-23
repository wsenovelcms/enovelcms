<?php

class PluginPageController extends Controller
{
    public function handle(string $pluginId, string $page = 'index'): void
    {
        if (!Security::validPluginId($pluginId)) {
            http_response_code(404);
            echo Language::get('plugin_page_not_exist', '插件不存在');
            return;
        }

        if (!Plugin::isEnabled($pluginId)) {
            http_response_code(404);
            echo Language::get('plugin_page_disabled', '插件未启用');
            return;
        }
        $page = preg_replace('/[^a-z0-9_]/', '', $page);
        $file = ROOT_PATH . 'plugins/' . $pluginId . '/pages/' . $page . '.php';
        if (!is_file($file)) {
            http_response_code(404);
            echo Language::get('plugin_page_missing_fmt', '插件页面不存在：%s', htmlspecialchars($pluginId . '/' . $page));
            return;
        }
        $db = $this->db;
        $request = $this->request;
        $pluginId = $pluginId;
        $siteName = (string)SettingModel::get('site_name', 'EnovelCms');
        $themeUrl = BASE_URL . '/templates/' . View::getTheme();
        $pluginUrl = Extension::url('plugin', $pluginId);
        define('PLUGIN_PAGE_ACCESS', true);
        Extension::begin('plugin', $pluginId);
        try {
            Hook::doAction('plugin_page_' . $pluginId . '_' . $page, $this);
            require $file;
        } finally {
            Extension::end();
        }
    }
}
