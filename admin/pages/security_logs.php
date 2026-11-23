<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_slog', '安全日志');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        admin_flash_redirect(t_admin('adm_slog_csrf', 'CSRF 验证失败，请刷新重试'), 'security_logs');
    }
    $do = p('do', '', 'str');

    if ($do === 'clear') {
        SecurityLogModel::clearAll();
        admin_flash_redirect(t_admin('adm_slog_cleared', '安全日志已清空'), 'security_logs');
    }

    if ($do === 'delete_one') {
        $id = (int)p('id', 0, 'int');
        if ($id > 0) SecurityLogModel::deleteById($id);
        admin_flash_redirect(t_admin('adm_slog_deleted', '记录已删除'), 'security_logs');
    }

    if ($do === 'delete_ip') {
        $ip = trim((string)p('ip', '', 'str'));
        if ($ip !== '') SecurityLogModel::deleteByIp($ip);
        admin_flash_redirect(t_admin('adm_slog_deleted_ip', '该 IP 的日志已删除'), 'security_logs');
    }

    if ($do === 'ban_ip') {
        $ip = trim((string)p('ip', '', 'str'));
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            $existing = (string)SettingModel::get('sg_blacklist_ip', '');
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $existing) ?: []));
            if (!in_array($ip, $lines, true)) {
                $lines[] = $ip;
                SettingModel::set('sg_blacklist_ip', implode("\n", $lines));
                SettingModel::clearCache();
            }
            admin_flash_redirect(t_admin('adm_slog_banned', '已将 %s 加入 IP 黑名单', $ip), 'security_logs');
        }
        admin_flash_redirect(t_admin('adm_slog_ban_bad_ip', 'IP 格式不正确，未加入黑名单'), 'security_logs');
    }
}

$filters = array(
    'type'    => g('type', '', 'str'),
    'ip'      => g('ip', '', 'str'),
    'keyword' => g('keyword', '', 'str'),
    'start'   => g('start_date', '', 'str'),
    'end'     => g('end_date', '', 'str'),
);
$page = max(1, (int)($_GET['page'] ?? 1));
$result = SecurityLogModel::adminLogs($filters, $page, 20);
$typeLabels = SecurityLogModel::typeLabels();

$filterQuery = array(
    'action' => 'security_logs', 'type' => $filters['type'], 'ip' => $filters['ip'],
    'keyword' => $filters['keyword'], 'start_date' => $filters['start'], 'end_date' => $filters['end'],
);
?>
<div class="card">
    <h2><?= t_admin('adm_slog_title', '安全日志（共 %s 条）', number_format($result['total'])) ?></h2>

    <form method="get" style="margin-bottom:16px;display:flex;gap:10px;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="action" value="security_logs">
        <div class="form-group" style="margin:0">
            <label><?= t_admin('adm_slog_type', '类型') ?></label>
            <select name="type" class="form-control">
                <option value=""><?= t_admin('adm_all', '全部') ?></option>
                <?php foreach ($typeLabels as $tv => $tl): ?>
                <option value="<?= htmlspecialchars($tv) ?>" <?= $filters['type'] === $tv ? 'selected' : '' ?>><?= htmlspecialchars($tl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0"><label>IP</label><input type="text" name="ip" class="form-control" value="<?= htmlspecialchars((string)$filters['ip']) ?>"></div>
        <div class="form-group" style="margin:0"><label><?= t_admin('adm_slog_keyword', '关键词') ?></label><input type="text" name="keyword" class="form-control" value="<?= htmlspecialchars($filters['keyword']) ?>" placeholder="<?= t_admin('adm_slog_keyword_ph', 'URL / 原因 / UA') ?>"></div>
        <div class="form-group" style="margin:0"><label><?= t_admin('adm_slog_start', '开始日期') ?></label><input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filters['start']) ?>"></div>
        <div class="form-group" style="margin:0"><label><?= t_admin('adm_slog_end', '结束日期') ?></label><input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filters['end']) ?>"></div>
        <button class="btn btn-outline" type="submit"><i class="fas fa-search"></i> <?= t_admin('adm_filter', '筛选') ?></button>
        <a href="<?= htmlspecialchars(admin_url('security_logs')) ?>" class="btn btn-outline"><i class="fas fa-rotate-left"></i> <?= t_admin('adm_reset', '重置') ?></a>
    </form>

    <form method="post" style="margin-bottom:12px">
        <?= Security::csrfField() ?>
        <button type="submit" name="do" value="clear" class="btn btn-outline btn-sm" data-confirm="<?= t_admin('adm_slog_clear_confirm', '确认清空全部安全日志？此操作不可恢复。') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_slog_clear', '清空日志') ?></button>
    </form>

    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>ID</th><th><?= t_admin('adm_slog_col_time', '时间') ?></th><th>IP</th><th><?= t_admin('adm_slog_type', '类型') ?></th><th><?= t_admin('adm_slog_col_reason', '原因') ?></th><th><?= t_admin('adm_slog_col_method_url', '方法 / URL') ?></th><th>UA</th><th><?= t_admin('adm_operation', '操作') ?></th><?= PluginTable::renderHead('admin.security_logs') ?></tr></thead>
        <tbody>
        <?php foreach ($result['list'] as $log):
            $label = $typeLabels[$log['type']] ?? $log['type'];
        ?>
        <tr>
            <td><?= (int)$log['id'] ?></td>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
            <td><?= htmlspecialchars($log['ip'] ?: '-') ?></td>
            <td><span class="notice-badge danger"><?= htmlspecialchars($label) ?></span></td>
            <td><?= htmlspecialchars($log['reason'] ?: '-') ?></td>
            <td style="font-size:12.5px;max-width:240px;">
                <?= htmlspecialchars((string)$log['method']) ?><br>
                <span style="color:#64748b;word-break:break-all;"><?= htmlspecialchars((string)mb_substr($log['url'], 0, 120)) ?></span>
            </td>
            <td style="font-size:12px;max-width:220px;color:#64748b;word-break:break-all;"><?= htmlspecialchars((string)mb_substr($log['ua'], 0, 80)) ?></td>
            <td style="white-space:nowrap;">
                <?php if (filter_var($log['ip'], FILTER_VALIDATE_IP)): ?>
                <form method="post" style="display:inline" data-confirm="<?= t_admin('adm_slog_ban_confirm', '确认将 %s 加入 IP 黑名单？', htmlspecialchars((string)$log['ip'])) ?>">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="do" value="ban_ip">
                    <input type="hidden" name="ip" value="<?= htmlspecialchars((string)$log['ip']) ?>">
                    <button type="submit" class="btn btn-outline btn-sm" title="<?= t_admin('adm_slog_ban_title', '加入黑名单') ?>"><i class="fas fa-ban"></i></button>
                </form>
                <form method="post" style="display:inline" data-confirm="<?= t_admin('adm_slog_delete_ip_confirm', '确认删除该 IP 的全部日志？') ?>">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="do" value="delete_ip">
                    <input type="hidden" name="ip" value="<?= htmlspecialchars((string)$log['ip']) ?>">
                    <button type="submit" class="btn btn-outline btn-sm" title="<?= t_admin('adm_slog_delete_ip_title', '删除该 IP 日志') ?>"><i class="fas fa-eraser"></i></button>
                </form>
                <?php endif; ?>
                <form method="post" style="display:inline" data-confirm="<?= t_admin('adm_slog_delete_confirm', '确认删除这条记录？') ?>">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="do" value="delete_one">
                    <input type="hidden" name="id" value="<?= (int)$log['id'] ?>">
                    <button type="submit" class="btn btn-outline btn-sm" title="<?= t_admin('adm_slog_delete_title', '删除记录') ?>"><i class="fas fa-trash"></i></button>
                </form>
            </td>
        <?= PluginTable::renderCells('admin.security_logs', $log) ?></tr>
        <?php endforeach; ?>
        <?php if (empty($result['list'])): ?><tr><td colspan="<?= 8 + PluginTable::count('admin.security_logs') ?>" class="empty"><?= t_admin('adm_slog_empty', '暂无安全日志') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($result['page'], $result['totalPages'], $filterQuery) ?>
</div>
