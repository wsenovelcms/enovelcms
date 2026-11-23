<?php

if (!defined('ADMIN_BOOT')) exit;

$menuGroups = array(
    array('title' => t_admin('adm_group_system', '系统设置'), 'items' => array(
        array('settings',        t_admin('adm_settings', '系统设置'),   'fa-gear'),
        array('cron',            t_admin('adm_cron', '计划任务'),   'fa-clock'),
        array('language',        t_admin('adm_language', '语言管理'),   'fa-globe'),
        array('announcements',   t_admin('adm_announcements', '公告管理'),   'fa-bullhorn'),
        array('upgrade',         t_admin('adm_upgrade', '在线升级'),   'fa-cloud-upload-alt'),
        array('diy_blocks',      t_admin('adm_diy_blocks', '自定义榜单'), 'fa-list-check'),
        array('cache',           t_admin('adm_cache', '缓存管理'),   'fa-bolt'),
        array('ads',             t_admin('adm_ads', '广告管理'),   'fa-rectangle-ad'),
    )),
    array('title' => t_admin('adm_group_seo', 'SEO设置'), 'items' => array(
        array('seo',             t_admin('adm_tdk_settings', 'TDK设置'),    'fa-magnifying-glass'),
        array('rewrite',         t_admin('adm_rewrite', '伪静态设置'), 'fa-code-branch'),
    )),
    array('title' => t_admin('adm_group_security', '安全管理'), 'items' => array(
        array('security_stats',  t_admin('adm_security_stats', '安全统计'),   'fa-shield-halved'),
        array('security_logs',   t_admin('adm_security_logs', '安全日志'),   'fa-clipboard-list'),
        array('settings?tab=system', t_admin('adm_security_settings', '安全设置'), 'fa-lock'),
    )),
    array('title' => t_admin('adm_group_user', '用户管理'), 'items' => array(
        array('users',               t_admin('adm_users', '用户管理'),   'fa-users'),
        array('author_applications', t_admin('adm_author_applications', '作者审核'), 'fa-user-plus'),
        array('authors',             t_admin('adm_authors', '作家管理'), 'fa-user-pen'),
    )),
    array('title' => t_admin('adm_group_app', '应用管理'), 'items' => array(
        array('appcenter',       t_admin('adm_appcenter', '应用中心'),   'fa-store'),
        array('themes',          t_admin('adm_themes', '主题管理'),   'fa-paint-roller'),
    )),
    array('title' => t_admin('adm_group_finance', '财务中心'), 'items' => array(
        array('orders',         t_admin('adm_orders', '订单管理'), 'fa-cart-shopping'),
        array('sign_logs',      t_admin('adm_sign_logs', '签到日志'), 'fa-calendar-check'),
        array('vip_stats',      t_admin('adm_vip_stats', 'VIP统计'),  'fa-crown'),
        array('sales_stats',     t_admin('adm_sales_stats', '销售统计'), 'fa-coins'),
        array('withdraw',       t_admin('adm_withdraw', '提现管理'), 'fa-wallet'),
    )),
    array('title' => t_admin('adm_group_content', '内容管理'), 'items' => array(
        array('novels',     t_admin('adm_novels', '小说管理'), 'fa-book'),
        array('chapters',   t_admin('adm_chapters', '章节管理'), 'fa-list'),
        array('categories', t_admin('adm_categories', '分类管理'), 'fa-tags'),
        array('tags',       t_admin('adm_tags', '标签管理'), 'fa-bookmark'),
        array('txt_import', t_admin('adm_txt_import', 'TXT导入'),  'fa-file-import'),
        array('vip_review', t_admin('adm_vip_review', '签约审核'), 'fa-star'),
    )),
);

$menuGroups = Hook::applyFilters('admin_menu', $menuGroups);

$extMenus = class_exists('Extension') ? Extension::menuItems() : array();
if ($extMenus) {
    $extGroupIndex = null;
    foreach ($extMenus as $em) {
        $actionStr = (string)($em['action'] ?? '');
        if ($actionStr === '') continue;
        $item = array($actionStr, (string)($em['label'] ?? $actionStr), (string)($em['icon'] ?? 'fa-circle'));
        $groupName = (string)($em['group'] ?? '');
        $placed = false;
        if ($groupName !== '') {
            foreach ($menuGroups as $gi => $mg) {
                if ((string)($mg['title'] ?? '') === $groupName) {
                    $menuGroups[$gi]['items'][] = $item;
                    $placed = true;
                    break;
                }
            }
        }
        if (!$placed) {
            if ($extGroupIndex === null) {
                $menuGroups[] = array('title' => t_admin('adm_group_ext', '扩展功能'), 'items' => array());
                $extGroupIndex = count($menuGroups) - 1;
            }
            $menuGroups[$extGroupIndex]['items'][] = $item;
        }
    }
}

$groupFallback = array(
    'user_edit'    => 'users',
    'novel_edit'   => 'novels',
    'chapter_edit' => 'chapters',
    'volumes'      => 'chapters',
);
$fallbackAction = $groupFallback[$action] ?? null;

$itemIsActive = function ($itemAction) use ($action) {
    $itemAction = (string)$itemAction;
    if ($action === $itemAction && !($itemAction === 'settings' && (string)g('tab', '', 'str') === 'system')) return true;
    if (strpos($itemAction, 'plugins') === 0 && g('filter', null, 'raw') !== null && strpos($itemAction, 'filter=' . $_GET['filter']) !== false) return true;
    if ($action === 'plugin_page' && (strpos($itemAction, 'plugin_page?') === 0 || strpos($itemAction, 'plugin_page&') === 0)) {
        $qParts = explode('?', $itemAction, 2);
        parse_str($qParts[1] ?? '', $q);
        if (($q['page'] ?? '') === (string)g('page', '', 'str')) return true;
    }
    if ($action === 'settings' && $itemAction === 'settings?tab=system' && (string)g('tab', '', 'str') === 'system') return true;
    return false;
};

foreach ($menuGroups as $gi => $g) {
    if (($g['title'] ?? '') === t_admin('adm_group_app', '应用管理')) {
        $menuGroups[$gi]['items'] = array(
            array('appcenter',                 t_admin('adm_appcenter', '应用中心'),   'fa-store'),
            array('themes',                    t_admin('adm_themes', '主题管理'),   'fa-paint-roller'),
            array('plugins?filter=enabled',   t_admin('adm_enabled_plugins', '已启用插件'), 'fa-toggle-on'),
            array('plugins?filter=disabled',  t_admin('adm_disabled_plugins', '未启用插件'), 'fa-toggle-off'),
        );
        break;
    }
}
?>
<aside class="sidebar">
    <a class="brand" href="<?= htmlspecialchars(admin_url('dashboard')) ?>" style="text-decoration:none;color:#fff;display:flex;align-items:center;gap:8px;padding:20px;border-bottom:1px solid rgba(255,255,255,.08);font-size:17px;font-weight:700;white-space:nowrap;"><i class="fas fa-book-open"></i><span class="brand-text">EnovelCms 3.0</span></a>
    <nav class="sidebar-nav">
    <?php Hook::doAction('admin_sidebar_top'); ?>
    <?php foreach ($menuGroups as $group):
        $groupItems = $group['items'] ?? [];
        $groupOpen = false;
        foreach ($groupItems as $fbItem) {
            $fbAction = is_array($fbItem) ? $fbItem[0] : ($fbItem['action'] ?? '');
            if (($fallbackAction !== null && $fbAction === $fallbackAction) || $itemIsActive($fbAction)) {
                $groupOpen = true;
                break;
            }
        }
    ?>
        <div class="nav-group<?= $groupOpen ? ' open' : '' ?>">
            <button type="button" class="group-toggle">
                <span class="group-name"><?= htmlspecialchars($group['title']) ?></span>
                <i class="fas fa-chevron-down group-arrow"></i>
            </button>
            <div class="nav-items">
                <?php foreach ($groupItems as $item):
                    $itemAction = is_array($item) ? $item[0] : $item['action'];
                    $itemLabel  = is_array($item) ? $item[1] : $item['label'];
                    $itemIcon   = is_array($item) ? $item[2] : ($item['icon'] ?? 'fa-circle');
                    if (is_array($item) && strpos($itemAction, '?') !== false) {
                        $itemParts = explode('?', $itemAction, 2);
                        parse_str($itemParts[1] ?? '', $itemParams);
                        $itemUrl = admin_url($itemParts[0], $itemParams);
                    } else {
                        $itemUrl = is_array($item) ? admin_url($itemAction) : ($item['url'] ?? '#');
                    }
                    $isActive = $itemIsActive($itemAction);
                ?>
                <a class="nav-link <?= $isActive ? 'active' : '' ?>" href="<?= htmlspecialchars($itemUrl) ?>" data-tip="<?= htmlspecialchars($itemLabel) ?>">
                    <i class="fas <?= htmlspecialchars($itemIcon) ?>"></i><span class="nav-text"><?= htmlspecialchars($itemLabel) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <?php Hook::doAction('admin_sidebar_bottom'); ?>
    </nav>
    <div class="sidebar-footer" style="padding:14px 18px;border-top:1px solid rgba(255,255,255,.06);font-size:12px;line-height:1.9;color:#94a3b8;">
        <div><?= t_admin('adm_sidebar_author', '作者：文煞') ?></div>
        <div><?= t_admin('adm_sidebar_program', '程序：EnovelCms v 3.0') ?></div>
        <div><?= t_admin('adm_sidebar_site', '官网：') ?><a href="https://www.enovelcms.cn/" target="_blank" style="color:#93c5fd;text-decoration:none;">www.enovelcms.cn</a></div>
        <div><?= t_admin('adm_sidebar_tech', '技术：') ?><a href="https://www.wslogs.cn/" target="_blank" style="color:#93c5fd;text-decoration:none;">www.wslogs.cn</a></div>
        <div><?= t_admin('adm_sidebar_forum', '论坛：') ?><a href="https://www.enovelbbs.cn/" target="_blank" style="color:#93c5fd;text-decoration:none;">www.enovelbbs.cn</a></div>
        <div><?= t_admin('adm_sidebar_qq', 'QQ群：') ?>162244086</div>
    </div>
</aside>
