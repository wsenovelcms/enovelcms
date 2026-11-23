<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_up', '系统在线升级');

$upgradeService = new UpgradeService($db, ENOVELCMS_VERSION, BASE_URL);

function upgrade_json(array $payload): void
{
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = g('action', '', 'str');
$subAction = g('do', '', 'str');

if ($subAction === 'check') {
    try {
        $all = $upgradeService->getRemoteUpgrades();
        $path = $upgradeService->getUpgradePath($all);
        upgrade_json(array('code' => 1, 'data' => $path, 'current' => ENOVELCMS_VERSION));
    } catch (Exception $e) {
        upgrade_json(array('code' => 0, 'message' => $e->getMessage()));
    }
}

if ($subAction === 'latest') {
    $latest = $upgradeService->getLatestVersion();
    upgrade_json(array(
        'code' => 1,
        'hasUpdate' => $latest !== null,
        'version' => $latest,
        'title' => $latest !== null
            ? t_admin('adm_up_new_available', '有新版本 v%s 可用，点击前往升级', $latest)
            : t_admin('adm_version_tip', '当前版本，点击检查更新'),
    ));
}

if ($subAction === 'run') {
    if (!Security::verifyCsrf()) {
        upgrade_json(array('code' => 0, 'message' => t_admin('adm_up_csrf', 'CSRF验证失败，请刷新页面重试')));
    }
    $item = array(
        'version' => p('version', '', 'str'),
        'file_url' => p('file_url', '', 'str'),
        'data_url' => p('data_url', '', 'str'),
        'file_hash' => p('file_hash', '', 'str'),
        'data_hash' => p('data_hash', '', 'str'),
    );
    if (empty($item['version'])) {
        upgrade_json(array('code' => 0, 'message' => t_admin('adm_up_no_version', '版本号缺失')));
    }
    if (empty($item['file_url']) && empty($item['data_url'])) {
        upgrade_json(array('code' => 0, 'message' => t_admin('adm_up_no_package', '升级包和数据包不能同时为空')));
    }
    if (!version_compare($item['version'], ENOVELCMS_VERSION, '>')) {
        upgrade_json(array('code' => 0, 'message' => t_admin('adm_up_not_newer', '目标版本不高于当前版本，拒绝执行')));
    }
    $res = $upgradeService->upgradeTo($item);
    upgrade_json(array(
        'code' => $res['success'] ? 1 : 0,
        'message' => $res['success'] ? t_admin('adm_up_success', '升级成功') : ($res['error'] ?? t_admin('adm_up_fail', '升级失败')),
        'log' => $res['log'],
        'current' => ENOVELCMS_VERSION,
    ));
}

$currentVersion = ENOVELCMS_VERSION;
$csrfToken = Security::csrfToken();
?>
<style>
.up-version-bar { display:flex; align-items:center; gap:18px; background:var(--gray-50); border:1px solid var(--gray-200);
    border-radius:12px; padding:14px 20px; margin-bottom:1.2rem; flex-wrap:wrap; }
.up-version-item { display:flex; align-items:baseline; gap:8px; white-space:nowrap; }
.up-version-item .label { font-size:.8rem; color:var(--gray-500); }
.up-version-item .value { font-size:1.1rem; font-weight:700; color:var(--gray-700); font-family:Consolas,monospace; }
.up-version-item .value.latest.has-update { color:#d97706; }
.up-version-item .value.latest.up-to-date { color:#16a34a; }
.up-version-item .value.latest.checking { color:var(--gray-400); font-weight:500; font-size:.95rem; font-family:inherit; }
.up-version-divider { color:var(--gray-300); }
.up-version-bar .btn { margin-left:auto; white-space:nowrap; }
.up-tips { background:#f8fafc; border:1px solid var(--gray-200); border-left:4px solid #cbd5e1;
    padding:.75rem 1.1rem; border-radius:8px; font-size:.85rem; color:var(--gray-600); line-height:1.7; margin-bottom:1.4rem; }
.up-tips code { background:#eef2f6; padding:1px 6px; border-radius:4px; font-size:.8rem; }
.up-table { width:100%; border-collapse:collapse; font-size:.9rem; table-layout:fixed; }
.up-table thead th { background:#fafbfc; padding:10px 18px; text-align:left; font-weight:600; color:var(--gray-500);
    border-bottom:1px solid var(--gray-200); font-size:.78rem; }
.up-table tbody td { padding:12px 18px; border-bottom:1px solid var(--gray-100); vertical-align:middle;
    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.up-table tbody tr:hover { background:var(--gray-50); }
.up-table tbody tr.done { background:#f0fdf4; }
.up-table tbody tr.error { background:#fef2f2; }
.up-ver-tag { font-family:Consolas,monospace; font-weight:700; color:var(--gray-700); }
.up-cell-actions { display:flex; align-items:center; gap:10px; }
.up-row-status { font-size:.75rem; padding:3px 10px; border-radius:14px; background:#fff; border:1px solid var(--gray-200);
    color:var(--gray-400); white-space:nowrap; }
.up-row-status.done { background:#d1fae5; color:#065f46; border-color:#86efac; font-weight:600; }
.up-row-status.error { background:#fee2e2; color:#991b1b; border-color:#fca5a5; font-weight:600; }
.up-empty { text-align:center; padding:2rem 0; color:var(--gray-400); font-size:.9rem; }
.up-log { background:#0f172a; color:#e2e8f0; font-family:Consolas,monospace; font-size:.82rem; padding:14px 16px;
    border-radius:10px; max-height:340px; overflow-y:auto; white-space:pre-wrap; line-height:1.6; margin:0; }
.up-modal-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9999;
    align-items:center; justify-content:center; padding:20px; }
.up-modal-overlay.active { display:flex; }
.up-modal-box { background:#fff; border-radius:16px; max-width:600px; width:100%; max-height:80vh;
    display:flex; flex-direction:column; overflow:hidden; box-shadow:0 20px 50px rgba(0,0,0,.2); }
.up-modal-header { display:flex; justify-content:space-between; align-items:center; padding:1rem 1.4rem;
    border-bottom:1px solid var(--gray-200); background:var(--gray-50); }
.up-modal-header h3 { margin:0; font-size:1rem; }
.up-modal-close { background:none; border:none; font-size:1.4rem; color:var(--gray-400); cursor:pointer; }
.up-modal-body { padding:1.4rem; overflow-y:auto; line-height:1.8; font-size:.9rem; color:var(--gray-700); }
.up-modal-meta { display:flex; gap:14px; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px dashed var(--gray-200);
    font-size:.8rem; color:var(--gray-500); flex-wrap:wrap; }
</style>

<div class="up-version-bar">
    <div class="up-version-item">
        <span class="label"><?= t_admin('adm_up_current', '当前版本') ?></span>
        <span class="value">v<?= htmlspecialchars($currentVersion) ?></span>
    </div>
    <span class="up-version-divider"><i class="fas fa-arrow-right"></i></span>
    <div class="up-version-item">
        <span class="label"><?= t_admin('adm_up_latest', '最新版本') ?></span>
        <span class="value latest checking" id="latestVersion"><?= t_admin('adm_up_checking', '检查中...') ?></span>
    </div>
    <button id="btnCheck" class="btn btn-outline btn-sm">
        <i class="fas fa-sync-alt"></i> <?= t_admin('adm_up_refresh', '刷新') ?>
    </button>
</div>

<div class="up-tips">
    <i class="fas fa-shield-alt"></i>
    <?= t_admin('adm_up_tips', '升级前请备份网站和数据库；data/、install/、config/config.php 不会被覆盖；升级包自动进行 SHA256 完整性校验。') ?>
</div>

<div class="card">
    <h2><i class="fas fa-list-ul"></i> <?= t_admin('adm_up_available', '可用升级') ?> <span id="upgradeCount" style="font-size:.85rem;color:var(--gray-500);">(0)</span></h2>
    <div class="table-wrap">
        <table class="up-table">
            <thead>
                <tr><th style="width:150px;"><?= t_admin('adm_up_col_version', '版本号') ?></th><th style="width:150px;"><?= t_admin('adm_up_col_date', '发布日期') ?></th><th><?= t_admin('adm_operation', '操作') ?></th></tr>
            </thead>
            <tbody id="upgradeList">
                <tr><td colspan="3" class="up-empty"><i class="fas fa-spinner fa-spin"></i> <?= t_admin('adm_up_loading', '正在加载升级列表...') ?></td></tr>
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem;">
        <button id="btnStart" class="btn btn-primary" disabled>
            <i class="fas fa-play"></i> <?= t_admin('adm_up_start_all', '开始逐个升级') ?>
        </button>
    </div>
</div>

<div class="card" id="logCard" style="display:none;margin-top:1.4rem;">
    <h2><i class="fas fa-terminal"></i> <?= t_admin('adm_up_log', '升级日志') ?></h2>
    <pre class="up-log" id="logBox"></pre>
</div>

<div class="up-modal-overlay" id="detailModal">
    <div class="up-modal-box">
        <div class="up-modal-header">
            <h3 id="modalTitle"><?= t_admin('adm_up_changelog', '更新内容') ?></h3>
            <button class="up-modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="up-modal-body" id="modalBody"></div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const L = {
    checking: '<?= t_admin('adm_up_checking', '检查中...') ?>',
    loading: '<?= t_admin('adm_up_loading', '正在加载升级列表...') ?>',
    fetchFail: '<?= t_admin('adm_up_fetch_fail', '获取失败') ?>',
    upToDate: '<?= t_admin('adm_up_up_to_date', '已是最新') ?>',
    allLatest: '<?= t_admin('adm_up_all_latest', '当前版本已是最新') ?>',
    networkError: '<?= t_admin('adm_up_network_error', '网络错误') ?>',
    filePkg: '<?= t_admin('adm_up_file_pkg', '程序文件包') ?>',
    dataPkg: '<?= t_admin('adm_up_data_pkg', '数据升级包') ?>',
    filePkgTitle: '<?= t_admin('adm_up_file_pkg_title', '包含程序文件包') ?>',
    dataPkgTitle: '<?= t_admin('adm_up_data_pkg_title', '包含数据升级包') ?>',
    noDesc: '<?= t_admin('adm_up_no_desc', '（本次升级未提供详细说明）') ?>',
    viewChangelog: '<?= t_admin('adm_up_view_changelog', '查看更新内容') ?>',
    pending: '<?= t_admin('adm_up_pending', '待升级') ?>',
    allDone: '<?= t_admin('adm_up_all_done', '全部升级完成') ?>',
    allDoneLog: '<?= t_admin('adm_up_all_done_log', '==== 所有版本升级完成，请刷新页面 ====') ?>',
    running: '<?= t_admin('adm_up_running', '升级中') ?>',
    runningLogPrefix: '<?= t_admin('adm_up_running_log', '----- 开始升级 v') ?>',
    runningLogSuffix: '<?= t_admin('adm_up_running_end', ' -----') ?>',
    done: '<?= t_admin('adm_up_done', '已完成') ?>',
    success: '<?= t_admin('adm_up_success', '升级成功') ?>',
    fail: '<?= t_admin('adm_up_fail', '失败') ?>',
    failPrefix: '<?= t_admin('adm_up_fail_prefix', '升级失败：') ?>',
    retry: '<?= t_admin('adm_up_retry', '重试') ?>',
    requestFail: '<?= t_admin('adm_up_request_fail', '请求失败：') ?>',
    batchRunning: '<?= t_admin('adm_up_batch_running', '升级中...') ?>',
    batchStartPrefix: '<?= t_admin('adm_up_batch_start', '==== 开始批量升级，共 ') ?>',
    batchStartSuffix: '<?= t_admin('adm_up_batch_end', ' 个版本 ====') ?>',
    changelogSuffix: '<?= t_admin('adm_up_changelog_suffix', ' 更新内容') ?>'
};
let upgradeQueue = [];
let upgradedCount = 0;

function log(text, color) {
    const box = document.getElementById('logBox');
    document.getElementById('logCard').style.display = 'block';
    const div = document.createElement('div');
    div.textContent = text;
    if (color) div.style.color = color;
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}

function showDetail(idx) {
    const item = upgradeQueue[idx];
    if (!item) return;
    document.getElementById('modalTitle').textContent = 'v' + item.version + L.changelogSuffix;
    const body = document.getElementById('modalBody');
    body.innerHTML = '';
    const meta = document.createElement('div');
    meta.className = 'up-modal-meta';
    let metaHtml = '';
    if (item.release_date) metaHtml += '<span><i class="fas fa-calendar-alt"></i> ' + item.release_date + '</span>';
    if (item.file_url) metaHtml += '<span><i class="fas fa-file-archive"></i> ' + L.filePkg + '</span>';
    if (item.data_url) metaHtml += '<span><i class="fas fa-database"></i> ' + L.dataPkg + '</span>';
    meta.innerHTML = metaHtml;
    body.appendChild(meta);
    const desc = document.createElement('div');
    desc.textContent = (item.description && item.description.trim()) ? item.description : L.noDesc;
    body.appendChild(desc);
    document.getElementById('detailModal').classList.add('active');
}

function closeModal() {
    document.getElementById('detailModal').classList.remove('active');
}
document.getElementById('detailModal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});

function checkUpdate() {
    const btn = document.getElementById('btnCheck');
    const latestEl = document.getElementById('latestVersion');
    btn.disabled = true;
    latestEl.className = 'value latest checking';
    latestEl.textContent = L.checking;
    const tbody = document.getElementById('upgradeList');
    tbody.innerHTML = '<tr><td colspan="3" class="up-empty"><i class="fas fa-spinner fa-spin"></i> ' + L.loading + '</td></tr>';
    document.getElementById('upgradeCount').textContent = '(0)';
    document.getElementById('btnStart').disabled = true;

    fetch(<?= json_encode(admin_url('upgrade', ['do' => 'check'])) ?>)
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            if (data.code !== 1) {
                latestEl.className = 'value latest has-update';
                latestEl.textContent = L.fetchFail;
                tbody.innerHTML = '<tr><td colspan="3" class="up-empty" style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</td></tr>';
                return;
            }
            upgradeQueue = data.data || [];
            upgradedCount = 0;
            if (upgradeQueue.length === 0) {
                latestEl.className = 'value latest up-to-date';
                latestEl.textContent = L.upToDate;
                tbody.innerHTML = '<tr><td colspan="3" class="up-empty"><i class="fas fa-check-circle" style="color:#16a34a;"></i> ' + L.allLatest + '</td></tr>';
                document.getElementById('btnStart').disabled = true;
                return;
            }
            const latest = upgradeQueue[upgradeQueue.length - 1].version;
            latestEl.className = 'value latest has-update';
            latestEl.textContent = 'v' + latest;
            document.getElementById('upgradeCount').textContent = '(' + upgradeQueue.length + ')';
            document.getElementById('btnStart').disabled = false;
            renderList();
        })
        .catch(err => {
            btn.disabled = false;
            latestEl.className = 'value latest has-update';
            latestEl.textContent = L.networkError;
            tbody.innerHTML = '<tr><td colspan="3" class="up-empty" style="color:#dc2626;"><i class="fas fa-exclamation-circle"></i> ' + err.message + '</td></tr>';
        });
}

function renderList() {
    const tbody = document.getElementById('upgradeList');
    tbody.innerHTML = '';
    upgradeQueue.forEach((item, idx) => {
        const tr = document.createElement('tr');
        tr.id = 'up-row-' + idx;
        let icons = '';
        if (item.file_url) icons += '<i class="fas fa-file-archive" title="' + L.filePkgTitle + '" style="margin-left:6px;"></i>';
        if (item.data_url) icons += '<i class="fas fa-database" title="' + L.dataPkgTitle + '" style="margin-left:6px;"></i>';
        tr.innerHTML =
            '<td><span class="up-ver-tag">v' + item.version + '</span>' + icons + '</td>' +
            '<td>' + (item.release_date || '-') + '</td>' +
            '<td><div class="up-cell-actions">' +
                '<button class="btn btn-outline btn-sm" onclick="showDetail(' + idx + ')"><i class="fas fa-file-alt"></i> ' + L.viewChangelog + '</button>' +
                '<span class="up-row-status" id="up-status-' + idx + '">' + L.pending + '</span>' +
            '</div></td>';
        tbody.appendChild(tr);
    });
}

function doNext() {
    if (upgradedCount >= upgradeQueue.length) {
        document.getElementById('btnStart').innerHTML = '<i class="fas fa-check"></i> ' + L.allDone;
        log(L.allDoneLog, '#4ade80');
        return;
    }
    const idx = upgradedCount;
    const item = upgradeQueue[idx];
    const statusEl = document.getElementById('up-status-' + idx);
    const rowEl = document.getElementById('up-row-' + idx);
    statusEl.textContent = L.running;
    log(L.runningLogPrefix + item.version + L.runningLogSuffix, '#cbd5e1');

    const fd = new FormData();
    fd.append('_csrf', CSRF_TOKEN);
    fd.append('version', item.version);
    fd.append('file_url', item.file_url || '');
    fd.append('data_url', item.data_url || '');
    fd.append('file_hash', item.file_hash || '');
    fd.append('data_hash', item.data_hash || '');

    fetch(<?= json_encode(admin_url('upgrade', ['do' => 'run'])) ?>, { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.log) data.log.forEach(l => log(l));
            if (data.code === 1) {
                statusEl.className = 'up-row-status done';
                statusEl.textContent = L.done;
                rowEl.classList.add('done');
                log('[OK] v' + item.version + ' ' + L.success, '#4ade80');
                upgradedCount++;
                setTimeout(doNext, 500);
            } else {
                statusEl.className = 'up-row-status error';
                statusEl.textContent = L.fail;
                rowEl.classList.add('error');
                log('[ERROR] v' + item.version + ' ' + L.failPrefix + data.message, '#f87171');
                const startBtn = document.getElementById('btnStart');
                startBtn.disabled = false;
                startBtn.innerHTML = '<i class="fas fa-redo"></i> ' + L.retry;
            }
        })
        .catch(err => {
            statusEl.className = 'up-row-status error';
            statusEl.textContent = L.networkError;
            rowEl.classList.add('error');
            log('[ERROR] ' + L.requestFail + err.message, '#f87171');
            const startBtn = document.getElementById('btnStart');
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="fas fa-redo"></i> ' + L.retry;
        });
}

document.getElementById('btnCheck').addEventListener('click', checkUpdate);
document.getElementById('btnStart').addEventListener('click', function () {
    if (upgradeQueue.length === 0) return;
    this.disabled = true;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + L.batchRunning;
    log(L.batchStartPrefix + upgradeQueue.length + L.batchStartSuffix, '#94a3b8');
    doNext();
});
window.addEventListener('DOMContentLoaded', checkUpdate);
</script>
