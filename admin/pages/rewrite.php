<?php

if (!defined('ADMIN_BOOT')) exit;
require_once ROOT_PATH . 'app/core/rewrite_rules.php';
$pageTitle = t_admin('adm_rewrite', '伪静态设置');

$prefixKeys = array(
    'url_library' => array(t_admin('adm_url_prefix_library', '书库页（默认 library）'), 'library'),
    'url_rank'    => array(t_admin('adm_url_prefix_rank', '排行榜页（默认 rank）'), 'rank'),
    'url_novel'   => array(t_admin('adm_url_prefix_novel', '小说详情页（默认 novel）'), 'novel'),
    'url_read'    => array(t_admin('adm_url_prefix_read', '阅读页（默认 read）'), 'read'),
    'url_author'  => array(t_admin('adm_url_prefix_author', '作者主页（默认 author）'), 'author'),
    'url_authors' => array(t_admin('adm_url_prefix_authors', '作者列表页（默认 authors）'), 'authors'),
    'url_search'  => array(t_admin('adm_url_prefix_search', '搜索页（默认 search）'), 'search'),
    'url_user'    => array(t_admin('adm_url_prefix_user', '用户中心（默认 user）'), 'user'),
);

$tplRoutes = array_keys(UrlService::presets());
$sepRoutes = ['library', 'rank', 'novel', 'read', 'author', 'authors', 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('save_prefix', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        foreach ($prefixKeys as $key => $info) {
            $v = trim((string)($_POST[$key] ?? ''));
            $v = preg_replace('/[^a-zA-Z0-9_\-]/', '', $v);
            if ($v === '') $v = $info[1];
            SettingModel::set($key, $v);
        }
        foreach ($tplRoutes as $rt) {
            if (in_array($rt, $sepRoutes, true)) {
                $sep = (string)($_POST['url_sep_' . $rt] ?? '/');
                $sep = in_array($sep, ['-', '_'], true) ? $sep : '/';
                if ($rt === 'library') $sep = $sep === '-' ? '-' : '/';
                if ($sep === '/') {
                    SettingModel::delete(['key' => 'url_sep_' . $rt]);
                } else {
                    SettingModel::set('url_sep_' . $rt, $sep);
                }
            }
            $suffixOn = !empty($_POST['url_suffix_' . $rt]) ? '1' : '';
            SettingModel::set('url_suffix_' . $rt, $suffixOn);
        }
        admin_flash_redirect(t_admin('adm_url_saved', 'URL 设置已保存'), 'rewrite');
    }
}

$current = array();
foreach ($prefixKeys as $key => $info) {
    $v = SettingModel::get($key, $info[1]);
    $current[$key] = $v !== '' ? $v : $info[1];
}

$serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
$serverType = 'apache';
if (stripos($serverSoftware, 'nginx') !== false) {
    $serverType = 'nginx';
} elseif (stripos($serverSoftware, 'iis') !== false) {
    $serverType = 'iis';
}

$renderSample = function (string $tpl, array $sample, string $prefix): string {
    $map = array('{prefix}' => htmlspecialchars($prefix));
    foreach ($sample as $k => $v) {
        $map['{' . $k . '}'] = htmlspecialchars((string)$v);
    }
    return '/' . ltrim(str_replace(array_keys($map), array_values($map), $tpl), '/');
};

$sampleMap = [];
foreach (UrlService::presets() as $rt => $cfg) {
    $curPrefix = UrlService::prefix($rt);
    $sampleMap[$rt] = [
        'slash' => $renderSample(UrlService::templateWith($rt, '/'), $cfg['sample'], $curPrefix),
        'dash'  => $renderSample(UrlService::templateWith($rt, '-'), $cfg['sample'], $curPrefix),
        'underscore' => $renderSample(UrlService::templateWith($rt, '_'), $cfg['sample'], $curPrefix),
    ];
}
$libPrefix = UrlService::prefix('library');
$sampleMap['library'] = [
    'slash'      => '/' . $libPrefix . '/order_by/views/page/2',
    'dash'       => '/' . $libPrefix . '/order_by-views-page-2',
    'underscore' => '/' . $libPrefix . '/order_by_views_page_2',
];
?>
<form method="post" id="prefix-form">
    <?= Security::csrfField() ?>
    <div class="card">
        <h2><i class="fas fa-link"></i> <?= t_admin('adm_url_title', '自定义 URL 前缀') ?></h2>
        <div class="form-row-2">
            <?php foreach ($prefixKeys as $key => $info):
                $rt = $info[1];
                $curSep = UrlService::separator($rt);
                $sepDisabled = !in_array($rt, $sepRoutes, true);
                $underscoreDisabled = ($rt === 'library');
                $suffixDisabled = ($rt === 'search');
                $suffixOn = $suffixDisabled ? false : UrlService::suffixFor($rt);
                $sampleKey = $curSep === '-' ? 'dash' : ($curSep === '_' ? 'underscore' : 'slash');
                $curSample = $sampleMap[$rt][$sampleKey];
            ?>
            <div class="form-group">
                <label><?= htmlspecialchars($info[0]) ?></label>
                <div class="url-row">
                    <span class="url-label"><?= t_admin('adm_url_prefix_label', '自定义前缀') ?></span>
                    <input type="text" name="<?= htmlspecialchars($key) ?>" class="form-control"
                           value="<?= htmlspecialchars($current[$key]) ?>" placeholder="<?= htmlspecialchars($info[1]) ?>" autocomplete="off">
                    <span class="url-label"><?= t_admin('adm_url_sep_label', '参数链接符') ?></span>
                    <div class="seg sep-opts" data-route="<?= htmlspecialchars($rt) ?>">
                        <label class="seg-opt">
                            <input type="radio" name="url_sep_<?= htmlspecialchars($rt) ?>" value="/"
                                   <?= $curSep === '/' ? 'checked' : '' ?> <?= $sepDisabled ? 'disabled' : '' ?>>
                            <span>/</span>
                        </label>
                        <label class="seg-opt">
                            <input type="radio" name="url_sep_<?= htmlspecialchars($rt) ?>" value="-"
                                   <?= $curSep === '-' ? 'checked' : '' ?> <?= $sepDisabled ? 'disabled' : '' ?>>
                            <span>-</span>
                        </label>
                        <label class="seg-opt">
                            <input type="radio" name="url_sep_<?= htmlspecialchars($rt) ?>" value="_"
                                   <?= $curSep === '_' ? 'checked' : '' ?> <?= ($sepDisabled || $underscoreDisabled) ? 'disabled' : '' ?>>
                            <span>_</span>
                        </label>
                    </div>
                    <label class="inline-check suffix-check">
                        <input type="checkbox" name="url_suffix_<?= htmlspecialchars($rt) ?>" value="1"
                               <?= $suffixOn ? 'checked' : '' ?> <?= $suffixDisabled ? 'disabled' : '' ?>>
                        <span><?= t_admin('adm_url_suffix_item', 'URL 末尾加 .html') ?></span>
                    </label>
                </div>
                <div class="url-preview"><?= t_admin('adm_url_example', '示例：') ?><span class="tpl-sample"><?= $curSample ?></span></div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" name="save_prefix" class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_url_save', '保存设置') ?></button>
    </div>
</form>

<div class="card">
    <h2><i class="fas fa-server"></i> <?= t_admin('adm_url_server_rules', '服务器伪静态规则') ?></h2>
    <div class="tabs">
        <button type="button" class="tab-btn" data-tab="apache" <?= $serverType === 'apache' ? 'data-default' : '' ?>>Apache</button>
        <button type="button" class="tab-btn" data-tab="nginx" <?= $serverType === 'nginx' ? 'data-default' : '' ?>>Nginx</button>
        <button type="button" class="tab-btn" data-tab="iis" <?= $serverType === 'iis' ? 'data-default' : '' ?>>IIS</button>
    </div>

    <div class="tab-pane" data-pane="apache">
        <div style="margin:8px 0">
            <button type="button" class="btn btn-outline btn-sm" data-copy="rule-apache"><i class="fas fa-copy"></i> <?= t_admin('adm_rule_copy', '复制规则') ?></button>
        </div>
        <div class="rule-box"><pre id="rule-apache"><?= htmlspecialchars(enovel_rewrite_rules('apache'), ENT_QUOTES, 'UTF-8') ?></pre></div>
    </div>

    <div class="tab-pane" data-pane="nginx">
        <div style="margin:8px 0">
            <button type="button" class="btn btn-outline btn-sm" data-copy="rule-nginx"><i class="fas fa-copy"></i> <?= t_admin('adm_rule_copy', '复制规则') ?></button>
        </div>
        <p class="help-text"><?= t_admin('adm_rule_nginx_hint', 'fastcgi_intercept_errors off 让程序渲染的 404/500 页面原样透出，对外仍为标准状态码。') ?></p>
        <div class="rule-box"><pre id="rule-nginx"><?= htmlspecialchars(enovel_rewrite_rules('nginx'), ENT_QUOTES, 'UTF-8') ?></pre></div>
    </div>

    <div class="tab-pane" data-pane="iis">
        <div style="margin:8px 0">
            <button type="button" class="btn btn-outline btn-sm" data-copy="rule-iis"><i class="fas fa-copy"></i> <?= t_admin('adm_rule_copy', '复制规则') ?></button>
        </div>
        <div class="rule-box"><pre id="rule-iis"><?= htmlspecialchars(enovel_rewrite_rules('iis'), ENT_QUOTES, 'UTF-8') ?></pre></div>
    </div>
</div>

<script>
(function () {
    var SAMPLES = <?= json_encode($sampleMap, JSON_UNESCAPED_UNICODE) ?>;
    document.querySelectorAll('.sep-opts').forEach(function (box) {
        var rt = box.getAttribute('data-route');
        var sample = box.closest('.form-group').querySelector('.tpl-sample');
        function syncSample() {
            if (!sample) return;
            var s = SAMPLES[rt];
            var checked = box.querySelector('input[type=radio]:checked');
            var v = checked ? checked.value : '/';
            var key = v === '-' ? 'dash' : (v === '_' ? 'underscore' : 'slash');
            sample.textContent = s ? s[key] : '';
        }
        box.addEventListener('change', syncSample);
    });
    var copiedLabel = <?= json_encode(t_admin('adm_rule_copied', '已复制')) ?>;
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var pre = document.getElementById(btn.getAttribute('data-copy'));
            if (!pre) return;
            var text = pre.innerText;
            var label = btn.innerHTML;
            var done = function () {
                btn.innerHTML = '<i class="fas fa-check"></i> ' + copiedLabel;
                setTimeout(function () { btn.innerHTML = label; }, 1500);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done);
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch (e) {}
                document.body.removeChild(ta);
                done();
            }
        });
    });
})();
</script>
