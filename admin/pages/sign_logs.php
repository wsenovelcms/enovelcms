<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_sign_logs', '签到日志');

$filters = array(
    'user'  => g('search_user', '', 'str'),
    'start' => g('start_date', '', 'str'),
    'end'   => g('end_date', '', 'str'),
);
$page = max(1, (int)($_GET['page'] ?? 1));
$result = GoldLogModel::adminSignLogs($filters, $page, 20);
?>
<div class="card">
    <h2><?= t_admin('adm_sign_title', '签到日志（共 %s 条）', number_format($result['total'])) ?></h2>
    <form method="get" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="action" value="sign_logs">
        <div class="form-group" style="margin:0"><label><?= t_admin('adm_sign_user', '用户名') ?></label><input type="text" name="search_user" class="form-control" value="<?= htmlspecialchars($filters['user']) ?>"></div>
        <div class="form-group" style="margin:0"><label><?= t_admin('adm_sign_start', '开始日期') ?></label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filters['start']) ?>"></div>
        <div class="form-group" style="margin:0"><label><?= t_admin('adm_sign_end', '结束日期') ?></label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filters['end']) ?>"></div>
        <button class="btn btn-outline" type="submit"><i class="fas fa-search"></i> <?= t_admin('adm_filter', '筛选') ?></button>
        <a href="<?= htmlspecialchars(admin_url('sign_logs')) ?>" class="btn btn-outline"><i class="fas fa-rotate-left"></i> <?= t_admin('adm_reset', '重置') ?></a>
    </form>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_sign_col_user', '用户') ?></th><th><?= t_admin('adm_sign_col_gold', '获得金币') ?></th><th><?= t_admin('adm_sign_col_after', '变动后') ?></th><th><?= t_admin('adm_sign_col_remark', '备注') ?></th><th><?= t_admin('adm_sign_col_time', '时间') ?></th><?= PluginTable::renderHead('admin.sign_logs') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $log): ?>
        <tr>
            <td><?= (int)$log['id'] ?></td>
            <td><?= htmlspecialchars((string)$log['username']) ?> (ID:<?= (int)$log['user_id'] ?>)</td>
            <td class="text-green">+<?= number_format((int)$log['gold_change']) ?></td>
            <td><?= number_format((int)$log['gold_after']) ?></td>
            <td><?= htmlspecialchars($log['remark'] ?: '-') ?></td>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
        <?= PluginTable::renderCells('admin.sign_logs', $log) ?></tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 6 + PluginTable::count('admin.sign_logs') ?>" class="empty"><?= t_admin('adm_sign_empty', '暂无签到记录') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], array('action' => 'sign_logs', 'search_user' => $filters['user'], 'start_date' => $filters['start'], 'end_date' => $filters['end'])) ?>
</div>
