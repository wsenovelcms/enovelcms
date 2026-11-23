<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_vipstat', 'VIP统计');

$page = max(1, (int)($_GET['page'] ?? 1));
$d = UserModel::adminVipStats($page, 20);
?>
<div class="stat-grid">
    <div class="stat-box"><div class="stat-icon icon-purple"><i class="fas fa-crown"></i></div><div class="stat-num"><?= $d['active_vip'] ?></div><div class="stat-label"><?= t_admin('adm_vipstat_active_total', '当前VIP / %s 总用户', $d['total_users']) ?></div></div>
    <div class="stat-box"><div class="stat-icon icon-blue"><i class="fas fa-calendar"></i></div><div class="stat-num"><?= $d['month_buyers'] ?></div><div class="stat-label"><?= t_admin('adm_vipstat_month_buyers', '本月购买用户') ?></div></div>
    <div class="stat-box"><div class="stat-icon icon-green"><i class="fas fa-users"></i></div><div class="stat-num"><?= $d['all_buyers'] ?></div><div class="stat-label"><?= t_admin('adm_vipstat_all_buyers', '累计购买用户') ?></div></div>
</div>
<div class="card">
    <h2><?= t_admin('adm_vipstat_active_count', '当前VIP用户（%s 人）', $d['active_vip']) ?></h2>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_vipstat_col_username', '用户名') ?></th><th><?= t_admin('adm_vipstat_col_pen', '笔名') ?></th><th><?= t_admin('adm_vipstat_col_level', '等级') ?></th><th><?= t_admin('adm_vipstat_col_expire', '到期时间') ?></th><th><?= t_admin('adm_vipstat_col_buy_count', '购买次数') ?></th><th><?= t_admin('adm_vipstat_col_spend', '累计消费') ?></th><th><?= t_admin('adm_vipstat_col_gold', '金币') ?></th><?= PluginTable::renderHead('admin.vip_stats') ?></tr></thead>
        <tbody>
        <?php foreach ($d['list'] as $u): ?>
        <tr>
            <td><?= (int)$u['id'] ?></td>
            <td><?= htmlspecialchars((string)$u['username']) ?></td>
            <td><?= htmlspecialchars($u['pen_name'] ?: '-') ?></td>
            <td>VIP<?= (int)$u['vip_level'] ?></td>
            <td><?= htmlspecialchars($u['vip_expire']) ?></td>
            <td><?= (int)$u['buy_count'] ?></td>
            <td><?= number_format((int)$u['spend']) ?></td>
            <td><?= number_format((int)$u['gold']) ?></td>
        <?= PluginTable::renderCells('admin.vip_stats', $u) ?></tr>
        <?php endforeach; ?>
        <?php if (empty($d['list'])): ?><tr><td colspan="<?= 8 + PluginTable::count('admin.vip_stats') ?>" class="empty"><?= t_admin('adm_vipstat_empty', '暂无VIP用户') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($d['page'], $d['totalPages'], array('action' => 'vip_stats')) ?>
</div>
