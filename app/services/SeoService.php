<?php

class SeoService
{

    public static function pageLabels(): array
    {
        return [
            'home'           => t_admin('adm_seo_page_home', '首页'),
            'library'        => t_admin('adm_seo_page_library', '书库页'),
            'rank'           => t_admin('adm_seo_page_rank', '排行榜页'),
            'authors'        => t_admin('adm_seo_page_authors', '作者列表页'),
            'author_detail'  => t_admin('adm_seo_page_author_detail', '作者详情页'),
            'novel_detail'   => t_admin('adm_seo_page_novel_detail', '小说详情页'),
            'read'           => t_admin('adm_seo_page_read', '章节阅读页'),
            'search'         => t_admin('adm_seo_page_search', '搜索页'),
        ];
    }

    public static function getMeta(string $page, array $vars = []): array
    {
        $siteName = SettingModel::get('site_name', 'EnovelCms');
        $defaultKw = SettingModel::get('site_keywords', '');
        $defaultDesc = SettingModel::get('site_description', '');

        $seo = SeoModel::getByPage($page);
        if (!$seo && $page !== 'home') {
            $seo = SeoModel::getByPage('home');
        }

        if (!$seo) {
            return [
                'title' => $siteName,
                'keywords' => $defaultKw,
                'description' => $defaultDesc,
            ];
        }

        $replace = array_merge([
            'site_name' => $siteName,
            'site_url'  => rtrim(BASE_URL, '/'),
        ], $vars);

        $title = $seo['title'];
        $keywords = $seo['keywords'];
        $description = $seo['description'];

        foreach ($replace as $k => $v) {
            if (is_array($v)) continue;
            $title = str_replace('{' . $k . '}', (string)$v, $title);
            $keywords = str_replace('{' . $k . '}', (string)$v, $keywords);
            $description = str_replace('{' . $k . '}', (string)$v, $description);
        }

        $title = preg_replace('/\{[a-z_]+\}/i', '', $title);
        $keywords = preg_replace('/\{[a-z_]+\}/i', '', $keywords);
        $description = preg_replace('/\{[a-z_]+\}/i', '', $description);

        return [
            'title' => trim($title) ?: $siteName,
            'keywords' => trim($keywords) ?: $defaultKw,
            'description' => trim($description) ?: $defaultDesc,
        ];
    }
}
