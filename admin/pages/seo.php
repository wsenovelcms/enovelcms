<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_tdk_settings', 'TDK设置');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('save_seo', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $seoInput = is_array($_POST['seo'] ?? null) ? $_POST['seo'] : array();
        foreach ($seoInput as $page => $data) {
            $page = Security::safeFilename($page);
            $title = trim((string)($data['title'] ?? ''));
            $kw = trim((string)($data['keywords'] ?? ''));
            $desc = trim((string)($data['description'] ?? ''));
            $existing = SeoModel::getByPage($page);
            if ($existing) {
                SeoModel::update(array('title' => $title, 'keywords' => $kw, 'description' => $desc), array('page' => $page));
            } else {
                SeoModel::create(array('page' => $page, 'title' => $title, 'keywords' => $kw, 'description' => $desc));
            }
        }
        admin_flash_redirect(t_admin('adm_seo_saved', 'SEO 设置已保存'), 'seo');
    }
}

$seoList = SeoModel::getAllKeyed();

$globalVars = [
    '{site_name}' => t_admin('adm_seo_global_var_name', '网站名称（取自系统设置）'),
    '{site_url}'  => t_admin('adm_seo_global_var_url', '网站域名（如 https://www.example.com）'),
];

$pages = array(
    'library' => array('name' => t_admin('adm_seo_page_library', '书库页'), 'vars' => array(
        '{page}' => t_admin('adm_seo_var_page', '当前页码'),
        '{total_pages}' => t_admin('adm_seo_var_total_pages', '总页数'),
        '{total}' => t_admin('adm_seo_var_total', '小说总数'),
        '{category_name}' => t_admin('adm_seo_var_category_name', '当前分类名'),
        '{status_label}' => t_admin('adm_seo_var_status_label', '连载状态（全部/连载/完本）'),
        '{order_by_label}' => t_admin('adm_seo_var_order_by_label', '排序方式（最新更新/最多点击…）'),
        '{vip_filter_label}' => t_admin('adm_seo_var_vip_filter_label', '作品类型（全部/VIP/免费）'),
        '{min_words}' => t_admin('adm_seo_var_min_words', '最小字数筛选'),
        '{max_words}' => t_admin('adm_seo_var_max_words', '最大字数筛选'),
    )),
    'rank' => array('name' => t_admin('adm_seo_page_rank', '排行榜页'), 'vars' => array(
        '{rank_type}' => t_admin('adm_seo_var_rank_type', '榜单标识（views/favorites/words/new/chapters）'),
        '{rank_type_label}' => t_admin('adm_seo_var_rank_type_label', '榜单中文名（点击榜/收藏榜/字数榜…）'),
    )),
    'authors' => array('name' => t_admin('adm_seo_page_authors', '作者列表页'), 'vars' => array(
        '{page}' => t_admin('adm_seo_var_page', '当前页码'),
        '{total_pages}' => t_admin('adm_seo_var_total_pages', '总页数'),
        '{total}' => t_admin('adm_seo_var_author_total', '作者总数'),
    )),
    'author_detail' => array('name' => t_admin('adm_seo_page_author_detail', '作者详情页'), 'vars' => array(
        '{author}' => t_admin('adm_seo_var_author', '作者笔名'),
        '{author_username}' => t_admin('adm_seo_var_author_username', '作者用户名'),
        '{novel_count}' => t_admin('adm_seo_var_novel_count', '作品数'),
        '{chapter_count}' => t_admin('adm_seo_var_chapter_count', '章节总数'),
        '{total_words}' => t_admin('adm_seo_var_total_words', '总字数'),
        '{introduction}' => t_admin('adm_seo_var_introduction', '作者简介前150字'),
        '{page}' => t_admin('adm_seo_var_author_page', '当前作品页码'),
        '{total_pages}' => t_admin('adm_seo_var_author_total_pages', '作品总页数'),
    )),
    'novel_detail' => array('name' => t_admin('adm_seo_page_novel_detail', '小说详情页'), 'vars' => array(
        '{novel_title}' => t_admin('adm_seo_var_novel_title', '小说标题'),
        '{author}' => t_admin('adm_seo_var_author', '作者笔名'),
        '{category_name}' => t_admin('adm_seo_var_category_name2', '所属分类'),
        '{status_label}' => t_admin('adm_seo_var_status_label2', '连载状态（连载/完本）'),
        '{total_words}' => t_admin('adm_seo_var_total_words', '总字数'),
        '{total_chapters}' => t_admin('adm_seo_var_total_chapters', '章节数'),
        '{views}' => t_admin('adm_seo_var_views', '点击量'),
        '{favorites}' => t_admin('adm_seo_var_favorites', '收藏数'),
        '{description_preview}' => t_admin('adm_seo_var_desc_preview', '简介前150字'),
        '{chapter_page}' => t_admin('adm_seo_var_chapter_page', '章节列表当前页'),
        '{chapter_total_pages}' => t_admin('adm_seo_var_chapter_total_pages', '章节列表总页数'),
    )),
    'read' => array('name' => t_admin('adm_seo_page_read', '章节阅读页'), 'vars' => array(
        '{novel_title}' => t_admin('adm_seo_var_novel_title', '小说标题'),
        '{chapter_title}' => t_admin('adm_seo_var_chapter_title', '章节标题'),
        '{author}' => t_admin('adm_seo_var_author', '作者笔名'),
        '{category_name}' => t_admin('adm_seo_var_category_name2', '所属分类'),
        '{chapter_word_count}' => t_admin('adm_seo_var_chapter_word_count', '本章字数'),
        '{chapter_intro}' => t_admin('adm_seo_var_chapter_intro', '本章内容前150字'),
        '{chapter_views}' => t_admin('adm_seo_var_chapter_views', '本章点击量'),
        '{total_chapters}' => t_admin('adm_seo_var_total_chapters2', '全书总章节数'),
    )),
    'search' => array('name' => t_admin('adm_seo_page_search', '搜索页'), 'vars' => array(
        '{keyword}' => t_admin('adm_seo_var_keyword', '搜索关键词'),
        '{page}' => t_admin('adm_seo_var_page', '当前页码'),
        '{total_results}' => t_admin('adm_seo_var_total_results', '结果总数'),
    )),
);
?>
<div class="card" style="margin-bottom:18px;background:#faf7f2;display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 18px">
    <span style="font-weight:600;color:#333"><i class="fas fa-info-circle"></i> <?= t_admin('adm_seo_global_title', '全局通用参数') ?></span>
    <div class="var-chips" style="margin:0">
        <?php foreach ($globalVars as $var => $d): ?>
        <span class="var-chip" title="<?= htmlspecialchars($d) ?>"><code><?= htmlspecialchars($var) ?></code></span>
        <?php endforeach; ?>
    </div>
</div>

<form method="post">
    <?= Security::csrfField() ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(380px,1fr));gap:18px">
    <?php foreach ($pages as $page => $info):
        $seo = $seoList[$page] ?? array('title' => '', 'keywords' => '', 'description' => '');
    ?>
    <div class="card" style="margin-bottom:0">
        <h2><?= htmlspecialchars((string)$info['name']) ?> <span class="badge badge-blue"><?= htmlspecialchars($page) ?></span></h2>
        <div class="form-group"><label><?= t_admin('adm_seo_label_title', '标题') ?></label><input type="text" class="form-control" name="seo[<?= htmlspecialchars($page) ?>][title]" value="<?= htmlspecialchars((string)$seo['title']) ?>" placeholder="<?= t_admin('adm_seo_inherit_ph', '留空则继承首页') ?>"></div>
        <div class="form-group"><label><?= t_admin('adm_seo_label_keywords', '关键词') ?></label><input type="text" class="form-control" name="seo[<?= htmlspecialchars($page) ?>][keywords]" value="<?= htmlspecialchars((string)$seo['keywords']) ?>" placeholder="<?= t_admin('adm_seo_inherit_ph', '留空则继承首页') ?>"></div>
        <div class="form-group"><label><?= t_admin('adm_seo_label_description', '描述') ?></label><textarea class="form-control" rows="2" name="seo[<?= htmlspecialchars($page) ?>][description]" style="min-height:56px" placeholder="<?= t_admin('adm_seo_inherit_ph', '留空则继承首页') ?>"><?= htmlspecialchars((string)$seo['description']) ?></textarea></div>
        <?php if (!empty($info['vars'])): ?>
        <div style="margin-top:10px">
            <div style="font-size:12px;color:#888;margin-bottom:6px"><i class="fas fa-puzzle-piece"></i> <?= t_admin('adm_seo_available_vars', '可用参数：') ?></div>
            <div class="var-chips">
                <?php foreach ($info['vars'] as $var => $d): ?>
                <span class="var-chip" title="<?= htmlspecialchars($d) ?>"><code><?= htmlspecialchars($var) ?></code></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <div style="margin-top:18px;text-align:right">
        <button type="submit" name="save_seo" class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_seo_save_all', '保存所有 SEO 设置') ?></button>
    </div>
</form>
