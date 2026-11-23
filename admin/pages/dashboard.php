<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_dashboard', '后台首页');

$novelCount   = NovelModel::count();
$chapterCount = ChapterModel::count();
$userCount    = UserModel::count();
$orderCount   = OrderModel::count();
$totalViews   = (int)NovelModel::sum('views');
$totalWords   = (int)NovelModel::sum('total_words');
$pendingApps  = AuthorApplicationModel::count(array('status' => 0));
$pendingWithdraw = WithdrawRequestModel::count(array('status' => 0));

$serverInfo = array(
    array('fa-server',     t_admin('adm_dash_info_program', '程序版本'),  'EnovelCms v' . ENOVELCMS_VERSION),
    array('fa-code',       t_admin('adm_dash_info_runtime', '运行环境'),  'PHP ' . PHP_VERSION),
    array('fa-microchip',  t_admin('adm_dash_info_server', '服务器软件'), $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'),
    array('fa-window-restore', t_admin('adm_dash_info_os', '操作系统'), PHP_OS),
    array('fa-globe',      t_admin('adm_dash_info_site', '站点地址'),  BASE_URL),
    array('fa-upload',     t_admin('adm_dash_info_upload', '上传限制'),  ini_get('upload_max_filesize') . ' / POST ' . ini_get('post_max_size')),
    array('fa-memory',     t_admin('adm_dash_info_memory', '内存限制'),  ini_get('memory_limit')),
    array('fa-clock',      t_admin('adm_dash_info_time', '执行时间'),  ini_get('max_execution_time') . 's'),
);
try {
    $mysqlVer = $db->query("SELECT VERSION() AS v")->fetch();
    if (!empty($mysqlVer['v'])) {
        $serverInfo[] = array('fa-database', t_admin('adm_dash_info_mysql', 'MySQL 版本'), $mysqlVer['v']);
    }
} catch (Exception $e) {}

$notices = array();
$noticeOffline = false;
try {
    $upgradeService = new UpgradeService($db, ENOVELCMS_VERSION, BASE_URL);
    $notices = $upgradeService->getOfficialNotices();
    if (empty($notices)) $noticeOffline = true;
} catch (Exception $e) {
    $noticeOffline = true;
}
$noticeTotal = count($notices);
$notices = array_slice($notices, 0, 100);
$noticePerPage = 10;
$noticeTotalPages = max(1, (int)ceil($noticeTotal / $noticePerPage));
$noticePage = max(1, min($noticeTotalPages, (int)($_GET['notice_page'] ?? 1)));
$noticePageItems = array_slice($notices, ($noticePage - 1) * $noticePerPage, $noticePerPage);
Hook::doAction('admin_dashboard_top');
?>
<div class="stat-grid stat-grid-8">
    <div class="stat-box"><div class="stat-num"><?= $novelCount ?></div><div class="stat-label"><?= t_admin('adm_dash_novels', '小说') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= $chapterCount ?></div><div class="stat-label"><?= t_admin('adm_dash_chapters', '章节') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= $userCount ?></div><div class="stat-label"><?= t_admin('adm_dash_users', '用户') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= $orderCount ?></div><div class="stat-label"><?= t_admin('adm_dash_orders', '订单') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= number_format($totalViews) ?></div><div class="stat-label"><?= t_admin('adm_dash_views', '点击') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= number_format($totalWords) ?></div><div class="stat-label"><?= t_admin('adm_dash_words', '字数') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= $pendingApps ?></div><div class="stat-label"><?= t_admin('adm_dash_pending_author', '待审作者') ?></div></div>
    <div class="stat-box"><div class="stat-num"><?= $pendingWithdraw ?></div><div class="stat-label"><?= t_admin('adm_dash_pending_withdraw', '待提现') ?></div></div>
</div>
<?php Hook::doAction('admin_dashboard_stats_after'); ?>

<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:16px;margin-top:16px;align-items:stretch;">
    <div class="card" style="padding:0;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);font-weight:600;font-size:14px;">
            <i class="fas fa-server" style="color:var(--primary);margin-right:6px;"></i><?= t_admin('adm_dash_server_info', '服务器信息') ?>
        </div>
        <div style="padding:6px 18px 14px;">
            <?php foreach ($serverInfo as $row): ?>
            <div style="display:flex;align-items:center;padding:9px 0;border-bottom:1px dashed #f1f5f9;font-size:13px;">
                <i class="fas <?= $row[0] ?>" style="width:22px;color:#94a3b8;"></i>
                <span style="width:90px;color:#64748b;flex-shrink:0;"><?= htmlspecialchars($row[1]) ?></span>
                <span style="color:#1e293b;word-break:break-all;"><?= htmlspecialchars($row[2]) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <div style="font-weight:600;font-size:14px;">
                <i class="fas fa-bell" style="color:var(--primary);margin-right:6px;"></i><?= t_admin('adm_dash_notices', '官方通知') ?>
                <span style="font-weight:400;font-size:12px;color:#94a3b8;margin-left:6px;"><?= t_admin('adm_dash_notice_count', '共 %d 条', $noticeTotal) ?></span>
            </div>
            <a href="<?= htmlspecialchars(UpgradeService::OFFICIAL_API) ?>" target="_blank" style="font-size:12.5px;color:var(--primary);text-decoration:none;">
                <i class="fas fa-arrow-up-right-from-square"></i> <?= t_admin('adm_official_site', '官网') ?>
            </a>
        </div>
        <?php if ($noticeOffline): ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:30px;color:#92400e;">
                <i class="fas fa-wifi" style="font-size:34px;color:#f59e0b;"></i>
                <p style="margin:12px 0 0;font-size:13px;text-align:center;line-height:1.7;">
                    <?= t_admin('adm_dash_offline_line1', '无法连接到官方服务器。') ?><br>
                    <?= t_admin('adm_dash_offline_line2', '可能是网络不通或官网暂时不可用，通知将在网络恢复后自动加载。') ?>
                </p>
            </div>
        <?php else: ?>
            <div style="flex:1;overflow-y:auto;max-height:calc(100vh - 400px);min-height:260px;">
                <?php foreach ($noticePageItems as $n):
                    $levelClass = in_array($n['level'], array('warning','danger','success'), true) ? $n['level'] : 'info';
                    $levelText = array('info' => t_admin('adm_dash_level_notice', '通知'), 'warning' => t_admin('adm_dash_level_warning', '提醒'), 'danger' => t_admin('adm_dash_level_danger', '重要'), 'success' => t_admin('adm_dash_level_success', '动态'))[$levelClass];
                ?>
                <div style="display:flex;align-items:flex-start;gap:10px;padding:11px 18px;border-bottom:1px solid #f1f5f9;font-size:13.5px;line-height:1.6;">
                    <span class="notice-badge <?= $levelClass ?>" style="flex-shrink:0;margin-top:2px;"><?= $levelText ?></span>
                    <div style="flex:1;min-width:0;">
                        <?php if (!empty($n['url']) && $n['url'] !== '#'): ?>
                            <a href="<?= htmlspecialchars((string)$n['url']) ?>" target="_blank" style="color:#1e293b;text-decoration:none;"><?= htmlspecialchars((string)$n['title']) ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars((string)$n['title']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="padding:10px 18px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;font-size:12.5px;color:#64748b;">
                <span><?= t_admin('adm_dash_page_line', '第 %d / %d 页，共 %d 条', $noticePage, $noticeTotalPages, $noticeTotal) ?></span>
                <span style="display:flex;gap:6px;">
                    <?php if ($noticePage > 1): ?>
                        <a href="<?= htmlspecialchars(admin_url('dashboard', ['notice_page' => $noticePage - 1])) ?>" style="padding:4px 10px;border:1px solid var(--border);border-radius:6px;color:var(--primary);text-decoration:none;"><?= t_admin('adm_dash_prev', '上一页') ?></a>
                    <?php else: ?>
                        <span style="padding:4px 10px;border:1px solid var(--border);border-radius:6px;color:#cbd5e1;"><?= t_admin('adm_dash_prev', '上一页') ?></span>
                    <?php endif; ?>
                    <?php if ($noticePage < $noticeTotalPages): ?>
                        <a href="<?= htmlspecialchars(admin_url('dashboard', ['notice_page' => $noticePage + 1])) ?>" style="padding:4px 10px;border:1px solid var(--border);border-radius:6px;color:var(--primary);text-decoration:none;"><?= t_admin('adm_dash_next', '下一页') ?></a>
                    <?php else: ?>
                        <span style="padding:4px 10px;border:1px solid var(--border);border-radius:6px;color:#cbd5e1;"><?= t_admin('adm_dash_next', '下一页') ?></span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div style="margin-top:16px;padding:12px 4px;color:#94a3b8;font-size:12px;text-align:center;border-top:1px solid var(--border);">
    EnovelCms v<?= htmlspecialchars(ENOVELCMS_VERSION) ?> · <?= t_admin('adm_dash_footer_maker', '文煞出品') ?> · <a href="https://www.enovelcms.cn/" target="_blank" style="color:var(--primary);text-decoration:none;">www.enovelcms.cn</a>
</div>
<?php Hook::doAction('admin_dashboard_bottom'); ?>
