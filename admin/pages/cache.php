<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_cache', '缓存管理');

$upsertDefine = function (string $content, string $key, string $value, bool $isString): string {
    $render = $isString ? "'" . addslashes($value) . "'" : (string)(int)$value;
    $line = "define('{$key}', {$render});";
    $replacer = function () use ($line) { return $line; };
    $commentPattern = '/^\s*\/\/\s*define\(\'' . $key . '\',\s*[^;]*\);\s*$/m';
    if (preg_match($commentPattern, $content)) {
        return preg_replace_callback($commentPattern, $replacer, $content, 1);
    }
    $definePattern = '/^\s*define\(\'' . $key . '\',\s*[^;]*\);\s*$/m';
    if (preg_match($definePattern, $content)) {
        return preg_replace_callback($definePattern, $replacer, $content, 1);
    }
    return preg_replace_callback("/(define\('CACHE_TTL',\s*\d+\);[^\n]*\n)/", function ($m) use ($line) {
        return $m[1] . '    ' . $line . "\n";
    }, $content, 1);
};

if (p('save_cache', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        admin_flash_redirect(t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试'), 'cache');
    }
    $configFile = ROOT_PATH . 'config/config.php';
    $content = file_get_contents($configFile);

    $driver = in_array(p('driver', '', 'str'), ['file', 'memcached', 'redis', 'yac']) ? p('driver', 'file', 'str') : 'file';
    $ttl = max(60, p('ttl', 300, 'int'));
    $enable = p('enable', null, 'raw') !== null ? 'true' : 'false';

    $mcHost = trim(p('mc_host', '127.0.0.1', 'str'));
    $mcPort = (int)p('mc_port', 11211, 'int');
    $rdHost = trim(p('rd_host', '127.0.0.1', 'str'));
    $rdPort = (int)p('rd_port', 6379, 'int');
    $rdPass = (string)p('rd_password', '', 'raw');
    if (!preg_match('/^[a-zA-Z0-9_.\-]{1,253}$/', $mcHost)) $mcHost = '127.0.0.1';
    if ($mcPort < 1 || $mcPort > 65535) $mcPort = 11211;
    if (!preg_match('/^[a-zA-Z0-9_.\-]{1,253}$/', $rdHost)) $rdHost = '127.0.0.1';
    if ($rdPort < 1 || $rdPort > 65535) $rdPort = 6379;

    $content = preg_replace("/define\('CACHE_ENABLE',\s*(true|false)\);/", "define('CACHE_ENABLE', $enable);", $content);
    $content = preg_replace("/define\('CACHE_DRIVER',\s*'[^']*'\);/", "define('CACHE_DRIVER', '$driver');", $content);
    $content = preg_replace("/define\('CACHE_TTL',\s*\d+\);/", "define('CACHE_TTL', $ttl);", $content);
    $content = $upsertDefine($content, 'MEMCACHED_HOST', $mcHost, true);
    $content = $upsertDefine($content, 'MEMCACHED_PORT', (string)$mcPort, false);
    $content = $upsertDefine($content, 'REDIS_HOST', $rdHost, true);
    $content = $upsertDefine($content, 'REDIS_PORT', (string)$rdPort, false);
    $content = $upsertDefine($content, 'REDIS_PASSWORD', $rdPass, true);

    file_put_contents($configFile, $content);
    Cache::flush();
    admin_flash_redirect(t_admin('adm_cache_saved', '配置已保存，缓存已清空'), 'cache');
}

if (g('flush', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        Cache::flush();
        admin_flash_redirect(t_admin('adm_cache_flushed', '缓存已清空'), 'cache');
    }
}

if (g('probe', null, 'raw') !== null) {
    header('Content-Type: application/json; charset=utf-8');

    $driverStatus = [];
    $driverStatus['file'] = ['ext' => true, 'conn' => true, 'msg' => t_admin('adm_cache_driver_ok', '可用')];

    if (extension_loaded('memcached')) {
        try {
            $mcHost = defined('MEMCACHED_HOST') ? MEMCACHED_HOST : '127.0.0.1';
            $mcPort = defined('MEMCACHED_PORT') ? MEMCACHED_PORT : 11211;
            $mc = new Memcached();
            $mc->addServer($mcHost, $mcPort);
            $okSet = $mc->set('test_conn', 'ok', 5);
            $connOk = $okSet && $mc->get('test_conn') === 'ok';
            $driverStatus['memcached'] = $connOk
                ? ['ext' => true, 'conn' => true, 'msg' => t_admin('adm_cache_driver_ext_ok', '扩展已加载，连接成功')]
                : ['ext' => true, 'conn' => false, 'msg' => t_admin('adm_cache_driver_ext_no_conn', '扩展已加载，但无法连接 %s 服务（%s）', 'Memcached', $mcHost . ':' . $mcPort)];
        } catch (\Throwable $e) {
            $driverStatus['memcached'] = ['ext' => true, 'conn' => false, 'msg' => t_admin('adm_cache_driver_conn_fail', '扩展已加载，但连接失败: %s', $e->getMessage())];
        }
    } else {
        $driverStatus['memcached'] = ['ext' => false, 'conn' => false, 'msg' => t_admin('adm_cache_driver_missing', '%s 扩展未加载', 'memcached')];
    }

    if (extension_loaded('redis')) {
        try {
            $rdHost = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
            $rdPort = defined('REDIS_PORT') ? REDIS_PORT : 6379;
            $r = new Redis();
            $r->connect($rdHost, $rdPort, 2.0);
            if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
                $r->auth(REDIS_PASSWORD);
            }
            $r->setex('test_conn', 5, 'ok');
            $connOk = $r->get('test_conn') === 'ok';
            $driverStatus['redis'] = $connOk
                ? ['ext' => true, 'conn' => true, 'msg' => t_admin('adm_cache_driver_ext_ok', '扩展已加载，连接成功')]
                : ['ext' => true, 'conn' => false, 'msg' => t_admin('adm_cache_driver_ext_no_conn', '扩展已加载，但无法连接 %s 服务（%s）', 'Redis', $rdHost . ':' . $rdPort)];
        } catch (\Throwable $e) {
            $driverStatus['redis'] = ['ext' => true, 'conn' => false, 'msg' => t_admin('adm_cache_driver_conn_fail', '扩展已加载，但连接失败: %s', $e->getMessage())];
        }
    } else {
        $driverStatus['redis'] = ['ext' => false, 'conn' => false, 'msg' => t_admin('adm_cache_driver_missing', '%s 扩展未加载', 'redis')];
    }

    $driverStatus['yac'] = Cache::isAvailable('yac')
        ? ['ext' => true, 'conn' => true, 'msg' => t_admin('adm_cache_driver_ok', '可用')]
        : ['ext' => extension_loaded('yac') ? true : false, 'conn' => false, 'msg' => extension_loaded('yac') ? t_admin('adm_cache_yac_not_enabled', 'YAC 扩展已加载但未启用（CLI 需 yac.enable_cli=1）') : t_admin('adm_cache_driver_missing', '%s 扩展未加载', 'yac')];

    echo json_encode($driverStatus);
    exit;
}

$drivers = Cache::drivers();
$currentDriver = Cache::driverType();
$mcHost = defined('MEMCACHED_HOST') ? MEMCACHED_HOST : '127.0.0.1';
$mcPort = defined('MEMCACHED_PORT') ? MEMCACHED_PORT : 11211;
$rdHost = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
$rdPort = defined('REDIS_PORT') ? REDIS_PORT : 6379;
$rdPass = defined('REDIS_PASSWORD') ? REDIS_PASSWORD : '';
$driverName = function (string $key, array $d): string {
    return $key === 'file' ? t_admin('adm_cache_driver_file_name', '文件缓存') : $d['name'];
};
?>
<div class="card">
    <h2 style="justify-content:space-between">
        <span><?= t_admin('adm_cache', '缓存管理') ?></span>
        <button type="button" class="btn btn-outline btn-sm" onclick="openConnModal()">
            <i class="fas fa-table"></i> <?= t_admin('adm_cache_conn_config', '连接配置') ?>
        </button>
    </h2>

    <style>
        #cacheForm .form-group { margin-bottom: 14px; }
        #cacheForm .form-group label { margin-bottom: 6px; }
        .driver-status-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 4px 0 0; }
        .driver-badge { display: inline-flex; align-items: center; gap: 7px; padding: 7px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 12.5px; background: #fff; color: #334155; }
        .driver-badge .dot { width: 7px; height: 7px; border-radius: 50%; background: #cbd5e1; flex-shrink: 0; }
        .driver-badge .driver-name { font-weight: 500; }
        .driver-badge .driver-state { color: #888; }
        .cache-conn-table td, .cache-conn-table th { padding: 9px 12px; }
        .cache-conn-table .form-control { padding: 8px 11px; }
    </style>

    <form method="post" id="cacheForm" style="max-width:600px">
        <input type="hidden" name="_csrf" value="<?= urlencode(Security::csrfToken()) ?>">

        <div class="form-group">
            <label><?= t_admin('adm_cache_enable_label', '启用缓存') ?></label>
            <label style="display:inline-flex;align-items:center;gap:6px;font-weight:normal">
                <input type="checkbox" name="enable" value="1" <?= CACHE_ENABLE ? 'checked' : '' ?>>
            </label>
        </div>

        <div class="form-row" style="display:flex;gap:16px;flex-wrap:wrap">
            <div class="form-group" style="flex:1;min-width:220px;margin-bottom:14px">
                <label><?= t_admin('adm_cache_driver', '缓存驱动') ?></label>
                <select name="driver" id="cacheDriver" class="form-control">
                    <?php foreach ($drivers as $key => $d): ?>
                    <option value="<?= $key ?>" data-driver="<?= $key ?>"
                        <?= $currentDriver === $key ? 'selected' : '' ?>>
                        <?= $driverName($key, $d) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="width:200px;margin-bottom:14px">
                <label><?= t_admin('adm_cache_ttl_label', '默认缓存时间（秒）') ?></label>
                <input type="number" name="ttl" class="form-control" value="<?= CACHE_TTL ?>" min="60" max="86400">
            </div>
        </div>

        <div id="driverStatus" class="driver-status-row">
            <?php foreach ($drivers as $key => $d): ?>
            <span class="driver-badge" data-driver="<?= $key ?>">
                <span class="dot"></span>
                <span class="driver-name"><?= $driverName($key, $d) ?></span>
                <span class="driver-state"><?= t_admin('adm_cache_driver_checking', '检测中…') ?></span>
            </span>
            <?php endforeach; ?>
        </div>

        <div class="form-group" style="margin-bottom:0">
            <button type="submit" name="save_cache" class="btn btn-primary"><?= t_admin('adm_cache_save_config', '保存配置') ?></button>
            <a href="<?= htmlspecialchars(admin_url('cache', ['flush' => 1, '_csrf' => Security::csrfToken()])) ?>"
               class="btn btn-outline" data-confirm="<?= t_admin('adm_cache_flush_confirm', '确认清空所有缓存？') ?>">
               <i class="fas fa-trash"></i> <?= t_admin('adm_cache_flush_now', '立即清空缓存') ?>
            </a>
        </div>

        <div class="modal-mask" id="connModal">
            <div class="modal" style="width:560px">
                <div class="modal-head">
                    <h3><i class="fas fa-network-wired"></i> <?= t_admin('adm_cache_conn_config', '连接配置') ?></h3>
                    <button type="button" class="modal-close" aria-label="<?= t_admin('adm_cache_conn_close', '关闭') ?>" onclick="closeConnModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <table class="data cache-conn-table">
                        <thead>
                            <tr>
                                <th style="width:130px"><?= t_admin('adm_cache_conn_item', '配置项') ?></th>
                                <th><?= t_admin('adm_cache_conn_value', '配置值') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="conn-section" data-conn="memcached">
                                <td colspan="3" style="background:#f1f5fb;font-weight:600;color:#334155"><?= t_admin('adm_cache_mc_conn', 'Memcached 连接') ?></td>
                            </tr>
                            <tr data-conn="memcached">
                                <td><?= t_admin('adm_cache_conn_host', '主机地址') ?></td>
                                <td><input type="text" name="mc_host" class="form-control" value="<?= htmlspecialchars($mcHost) ?>" placeholder="127.0.0.1"></td>
                            </tr>
                            <tr data-conn="memcached">
                                <td><?= t_admin('adm_cache_conn_port', '端口') ?></td>
                                <td><input type="number" name="mc_port" class="form-control" value="<?= (int)$mcPort ?>" min="1" max="65535"></td>
                            </tr>

                            <tr class="conn-section" data-conn="redis">
                                <td colspan="3" style="background:#f1f5fb;font-weight:600;color:#334155"><?= t_admin('adm_cache_rd_conn', 'Redis 连接') ?></td>
                            </tr>
                            <tr data-conn="redis">
                                <td><?= t_admin('adm_cache_conn_host', '主机地址') ?></td>
                                <td><input type="text" name="rd_host" class="form-control" value="<?= htmlspecialchars($rdHost) ?>" placeholder="127.0.0.1"></td>
                            </tr>
                            <tr data-conn="redis">
                                <td><?= t_admin('adm_cache_conn_port', '端口') ?></td>
                                <td><input type="number" name="rd_port" class="form-control" value="<?= (int)$rdPort ?>" min="1" max="65535"></td>
                            </tr>
                            <tr data-conn="redis">
                                <td><?= t_admin('adm_cache_rd_password', '密码') ?></td>
                                <td><input type="password" name="rd_password" class="form-control" value="<?= htmlspecialchars($rdPass) ?>" autocomplete="new-password"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" onclick="closeConnModal()"><?= t_admin('adm_cache_conn_close', '关闭') ?></button>
                    <button type="submit" name="save_cache" class="btn btn-primary"><?= t_admin('adm_cache_save_config', '保存配置') ?></button>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <h2><?= t_admin('adm_cache_overview', '已缓存的数据概览') ?></h2>
    <table class="data">
        <thead><tr><th><?= t_admin('adm_cache_col_key', '缓存键') ?></th><th><?= t_admin('adm_cache_col_desc', '说明') ?></th><th>TTL</th></tr></thead>
        <tbody>
            <tr><td><code>library_*</code></td><td><?= t_admin('adm_cache_library', '书库列表数据（第 1–10 页，按筛选条件分键）') ?></td><td>300s</td></tr>
            <tr><td><code>novel_rank_*</code></td><td><?= t_admin('adm_cache_novel_rank', '排行榜 TOP 数据（点击/收藏/字数，按时间范围分键）') ?></td><td>600s</td></tr>
            <tr><td><code>settings_all</code></td><td><?= t_admin('adm_cache_settings_all', '系统设置全站配置') ?></td><td>3600s</td></tr>
            <tr><td><code>official_notices</code></td><td><?= t_admin('adm_cache_official_notices', '官网公告（在线升级页）') ?></td><td>3600s</td></tr>
            <tr><td><code>ext_manifest_*</code></td><td><?= t_admin('adm_cache_ext_manifest', '插件/主题清单（键含 mtime+size，清单改动即失效）') ?></td><td>86400s</td></tr>
            <tr><td><code>ext_cache_*</code></td><td><?= t_admin('adm_cache_ext_cache', '插件/主题业务缓存（由 Extension::cacheSet 写入，停用/卸载自动清空）') ?></td><td><?= t_admin('adm_cache_ext_cache_ttl', '按单元设定') ?></td></tr>
        </tbody>
    </table>
    <p style="margin-top:12px;color:#888;font-size:13px">
        <?= t_admin('adm_cache_current_driver', '当前驱动：') ?><strong><?= isset($drivers[$currentDriver]) ? $driverName($currentDriver, $drivers[$currentDriver]) : t_admin('adm_cache_driver_file', '文件') ?></strong>
        ｜ <?= t_admin('adm_cache_switch', '缓存开关：') ?><?= CACHE_ENABLE ? '<span style="color:#22a06b">' . t_admin('adm_on', '开启') . '</span>' : '<span style="color:#cc3333">' . t_admin('adm_off', '关闭') . '</span>' ?>
        ｜ <?= t_admin('adm_cache_php_version', 'Web PHP 版本：') ?><strong><?= PHP_VERSION ?></strong>
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var connModal = document.getElementById('connModal');
    window.openConnModal = function () { if (connModal) { connModal.classList.add('show'); } };
    window.closeConnModal = function () { if (connModal) { connModal.classList.remove('show'); } };
    document.addEventListener('click', function (e) { if (connModal && e.target === connModal) { closeConnModal(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeConnModal(); } });

    fetch(<?= json_encode(admin_url('cache', ['probe' => 1])) ?>, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (status) {
            Object.keys(status).forEach(function (key) {
                var s = status[key];
                var badge = document.querySelector('#driverStatus .driver-badge[data-driver="' + key + '"]');
                if (badge) {
                    var state = badge.querySelector('.driver-state');
                    var dot = badge.querySelector('.dot');
                    var color;
                    if (s.conn) {
                        color = '#22a06b';
                        state.textContent = '<?= t_admin('adm_cache_ready', '✓ 已就绪') ?>';
                    } else {
                        color = '#cc3333';
                        state.textContent = '<?= t_admin('adm_cache_state_offline', '不可用') ?>';
                    }
                    state.style.color = color;
                    if (dot) { dot.style.background = color; }
                }
                var opt = document.querySelector('#cacheDriver option[data-driver="' + key + '"]');
                if (opt && !s.conn) {
                    opt.disabled = true;
                    opt.textContent = opt.textContent.replace(/\(不可用\)$/, '') + '<?= t_admin('adm_cache_unavailable', '(不可用)') ?>';
                }
            });
        })
        .catch(function () {
            document.querySelectorAll('#driverStatus .driver-state').forEach(function (el) {
                el.style.color = '#cc3333';
                el.textContent = '<?= t_admin('adm_cache_check_fail', '检测失败，请刷新重试') ?>';
            });
        });
});
</script>
