<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_cron', '计划任务');

if (g('toggle', null, 'raw') !== null && Security::verifyCsrf()) {
    $key = preg_replace('/[^a-z0-9_:]/', '', (string)g('toggle', '', 'str'));
    $task = $key !== '' ? Model::db()->find(Cron::TASK_TABLE, ['task_key' => $key]) : null;
    if ($task) {
        $next = (int)$task['status'] === 1 ? 0 : 1;
        Model::db()->update(Cron::TASK_TABLE, ['status' => $next], ['id' => $task['id']]);
        admin_flash_redirect($next ? t_admin('adm_cron_task_on', '任务已启用') : t_admin('adm_cron_task_off', '任务已停用'), 'cron');
    }
}

if (g('run', null, 'raw') !== null && Security::verifyCsrf()) {
    $key = preg_replace('/[^a-z0-9_:]/', '', (string)g('run', '', 'str'));
    if ($key !== '') {
        $res = Cron::runTask($key, true);
        admin_flash_redirect(($res['ok'] ? t_admin('adm_cron_task_done', '任务执行完成') : t_admin('adm_cron_task_fail', '任务执行失败：%s', $res['message'])) . ($res['message'] ? '（' . $res['message'] . '）' : ''), 'cron');
    }
}

if (g('run_all', null, 'raw') !== null && Security::verifyCsrf()) {
    $cron = new Cron();
    $cron->runAll();
    admin_flash_redirect(t_admin('adm_cron_task_done', '任务执行完成'), 'cron');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('save_ip', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $raw = trim((string)p('cron_web_ip_allow', '', 'str'));
        $ips = [];
        foreach (explode('|', $raw) as $item) {
            $item = trim($item);
            if ($item !== '' && filter_var($item, FILTER_VALIDATE_IP)) $ips[] = $item;
        }
        SettingModel::set('cron_web_ip_allow', implode('|', $ips));
        admin_flash_redirect(t_admin('adm_cron_ip_saved', 'IP 白名单已保存'), 'cron');
    }
}

$allTasks = Cron::allTasks();
$pluginMap = Plugin::all();
$webKey = substr(SECRET_KEY, 0, 8);
$webUrl = BASE_URL . '/cli/cron.php?key=' . $webKey;
$phpPath = Cron::resolveCliBinary();
$cliPath = ROOT_PATH . 'cli/cron.php';
$ipAllow = (string)SettingModel::get('cron_web_ip_allow', '');

$perPage = 15;
$page = g('page', null, 'raw') !== null ? max(1, g('page', 1, 'int')) : 1;
$total = count($allTasks);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$tasks = array_slice($allTasks, $offset, $perPage);

$formatInterval = function (int $sec): string {
    if ($sec <= 0) return t_admin('adm_cron_interval_each', '每次');
    if ($sec < 60) return t_admin('adm_cron_interval_sec', '每 %d 秒', $sec);
    if ($sec < 3600) return t_admin('adm_cron_interval_min', '每 %d 分钟', (int)round($sec / 60));
    if ($sec < 86400) return t_admin('adm_cron_interval_hour', '每 %d 小时', (int)round($sec / 3600));
    return t_admin('adm_cron_interval_day', '每 %d 天', (int)round($sec / 86400));
};

$statusBadge = function (array $t): string {
    if (!$t['available']) {
        return '<span class="badge badge-gray">' . t_admin('adm_cron_callback_missing', '回调不可用') . '</span>';
    }
    $status = (string)$t['last_status'];
    if ($status === 'success') return '<span class="badge badge-green">' . t_admin('adm_cron_status_success', '成功') . '</span>';
    if ($status === 'failed') return '<span class="badge badge-red">' . t_admin('adm_cron_status_failed', '失败') . '</span>';
    return '<span class="badge badge-gray">' . t_admin('adm_cron_status_never', '未执行') . '</span>';
};
?>
<div class="card">
    <h2><?= t_admin('adm_cron', '计划任务') ?></h2>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
            <h3 style="margin:0 0 12px;font-size:15px;color:#333"><i class="fab fa-linux" style="color:#e11d48"></i> <?= t_admin('adm_cron_bt_panel', 'Linux 宝塔面板') ?></h3>
            <p style="font-size:13px;color:#666;margin-bottom:8px"><?= t_admin('adm_cron_bt_desc', '在宝塔「计划任务」中新增 Shell 脚本，执行间隔建议 1 分钟一次：') ?></p>
            <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:0"><?php echo htmlspecialchars($phpPath); ?> <?php echo htmlspecialchars($cliPath); ?> > /dev/null 2>&1</pre>
        </div>

        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
            <h3 style="margin:0 0 12px;font-size:15px;color:#333"><i class="fab fa-windows" style="color:#3b82f6"></i> <?= t_admin('adm_cron_win_task', 'Windows 任务计划程序') ?></h3>
            <p style="font-size:13px;color:#666;margin-bottom:8px"><?= t_admin('adm_cron_win_desc', '新建基本任务，触发器间隔建议 1 分钟一次；操作选「启动程序」，分别填写程序与参数：') ?></p>
            <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:0"><?= t_admin('adm_cron_program_label', '程序：') ?> <?= htmlspecialchars($phpPath) . "\n" . t_admin('adm_cron_param_label', '参数：') ?> <?= htmlspecialchars($cliPath) ?></pre>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
            <h3 style="margin:0 0 12px;font-size:15px;color:#333"><i class="fas fa-globe" style="color:#2563eb"></i> <?= t_admin('adm_cron_web_title', '备用：Web URL 访问方式') ?></h3>
            <p style="font-size:13px;color:#666;margin-bottom:8px"><?= t_admin('adm_cron_web_desc', '不方便用命令行时，可用宝塔「访问 URL」任务，间隔建议 1 分钟访问：') ?></p>
            <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:6px;font-size:12px;overflow-x:auto;margin:0"><?php echo htmlspecialchars($webUrl); ?></pre>
        </div>

        <form method="post" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
            <?= Security::csrfField() ?>
            <input type="hidden" name="save_ip" value="1">
            <h3 style="margin:0 0 12px;font-size:15px;color:#333"><i class="fas fa-shield-alt" style="color:#2563eb"></i> <?= t_admin('adm_cron_ip_label', 'URL 访问允许 IP') ?></h3>
            <p style="font-size:13px;color:#666;margin-bottom:8px"><?= t_admin('adm_cron_ip_tip', '仅在通过 URL 调用计划任务时校验来源 IP；留空表示不限制。') ?></p>
            <input type="text" name="cron_web_ip_allow" class="form-control" value="<?= htmlspecialchars($ipAllow) ?>" placeholder="<?= t_admin('adm_cron_ip_placeholder', '多个 IP 用 | 分隔，如 127.0.0.1|1.2.3.4') ?>">
            <div style="margin-top:10px">
                <button class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <h2><?= t_admin('adm_cron_builtin', '任务列表') ?>（<?= (int)$total ?>）</h2>
    <div class="table-wrap">
    <table class="data">
        <thead><tr>
            <th><?= t_admin('adm_cron_col_name', '任务名') ?></th>
            <th><?= t_admin('adm_cron_col_source', '来源') ?></th>
            <th><?= t_admin('adm_cron_col_created', '建立时间') ?></th>
            <th><?= t_admin('adm_cron_col_interval', '建议频率') ?></th>
            <th><?= t_admin('adm_cron_col_status', '状态') ?></th>
            <th><?= t_admin('adm_cron_col_last_run', '最后执行') ?></th>
            <th><?= t_admin('adm_cron_col_last_status', '最后结果') ?></th>
            <th><?= t_admin('adm_cron_col_runs', '执行次数') ?></th>
            <th><?= t_admin('adm_cron_col_success', '成功') ?></th>
            <th><?= t_admin('adm_cron_col_fail', '失败') ?></th>
            <th><?= t_admin('adm_cron_col_action', '操作') ?></th>
            <?= PluginTable::renderHead('admin.cron') ?>
        </tr></thead>
        <tbody>
        <?php foreach ($tasks as $t):
            $isSystem = $t['source'] === Cron::SOURCE_SYSTEM;
            $sourceLabel = $isSystem
                ? t_admin('adm_cron_source_system', '系统')
                : ($pluginMap[$t['plugin_id']]['name'] ?? $t['plugin_id']);
            $enabled = (int)$t['status'] === 1;
        ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars((string)$t['name']) ?></strong>
                <?php if ($t['description'] !== ''): ?><div class="text-muted" style="font-size:12px;margin-top:2px"><?= htmlspecialchars((string)$t['description']) ?></div><?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)$sourceLabel) ?></td>
            <td><?= htmlspecialchars((string)$t['created_at']) ?></td>
            <td><?= htmlspecialchars($formatInterval((int)$t['interval'])) ?></td>
            <td><?= $enabled ? '<span class="badge badge-green">' . t_admin('adm_cron_enabled', '启用') . '</span>' : '<span class="badge badge-gray">' . t_admin('adm_cron_disabled', '停用') . '</span>' ?></td>
            <td><?= $t['last_run_at'] ? htmlspecialchars((string)$t['last_run_at']) : '<span class="text-muted">—</span>' ?></td>
            <td><?= $statusBadge($t) ?></td>
            <td><?= (int)$t['run_count'] ?></td>
            <td><span style="color:#10b981"><?= (int)$t['success_count'] ?></span></td>
            <td><span style="color:#ef4444"><?= (int)$t['fail_count'] ?></span></td>
            <td style="white-space:nowrap">
                <?php if ($t['available']): ?>
                <a href="<?= htmlspecialchars(admin_url('cron', ['run' => $t['task_key'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm btn-primary" title="<?= t_admin('adm_cron_run_once', '立即执行一次') ?>"><i class="fas fa-play"></i></a>
                <a href="<?= htmlspecialchars(admin_url('cron', ['toggle' => $t['task_key'], '_csrf' => Security::csrfToken()])) ?>" class="btn btn-sm <?= $enabled ? 'btn-outline' : 'btn-green' ?>" title="<?= $enabled ? t_admin('adm_cron_task_off', '停用') : t_admin('adm_cron_task_on', '启用') ?>"><i class="fas <?= $enabled ? 'fa-pause' : 'fa-play' ?>"></i></a>
                <?php else: ?>
                <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <?= PluginTable::renderCells('admin.cron', $t) ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tasks)): ?><tr><td colspan="<?= 10 + PluginTable::count('admin.cron') ?>" class="empty"><?= t_admin('adm_cron_empty', '暂无计划任务') ?></td></tr><?php endif; ?>
        </tbody>
    </table>
    </div>
    <?= admin_pagination($page, $totalPages, ['action' => 'cron']) ?>
    <div style="margin-top:16px">
        <a href="<?= htmlspecialchars(admin_url('cron', ['run_all' => 1, '_csrf' => Security::csrfToken()])) ?>" class="btn btn-primary">
            <i class="fas fa-play"></i> <?= t_admin('adm_cron_run_all', '手动触发全部任务') ?>
        </a>
    </div>
</div>
