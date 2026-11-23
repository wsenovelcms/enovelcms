<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_ac_title', '应用中心');

$ac = new AppCenterService($db);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && g('do', '', 'str') === 'order_status') {
    $orderNo = (string)g('order_no', '', 'raw');
    $st = ($ac->isBound() && $orderNo !== '') ? $ac->orderStatus($orderNo) : ['ok' => false, 'paid' => false, 'status' => 0];
    if (!empty($st['paid'])) {
        unset($_SESSION['ac_pending_order']);
        $ac->refresh();
    }
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => !empty($st['ok']), 'paid' => !empty($st['paid']), 'status' => (int)($st['status'] ?? 0)], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!Security::verifyCsrf()) {
        admin_flash_redirect(t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试'), 'appcenter');
    }
    $do = p('do', '', 'str');

    if ($do === 'bind_init') {
        $res = $ac->bindInit();
        if (!empty($res['ok'])) {
            $_SESSION['ac_bind_pending'] = [
                'bind_token' => $res['bind_token'],
                'login_url'  => $res['login_url'],
                'user_code'  => $res['user_code'],
                'expire_at'  => $res['expire_at'],
            ];
        }
        admin_flash_redirect($res['msg'] ?? ($res['ok'] ? t_admin('adm_ac_bind_init_ok', '请在打开的中心页面完成登录授权') : t_admin('adm_ac_bind_init_fail', '获取授权链接失败')), 'appcenter');
    }

    if ($do === 'connect_token') {
        $pending = $_SESSION['ac_bind_pending'] ?? [];
        $bindToken = (string)($pending['bind_token'] ?? p('bind_token', '', 'raw'));
        $res = $bindToken !== '' ? $ac->connectByToken($bindToken) : ['ok' => false, 'msg' => t_admin('adm_ac_bind_token_missing', '绑定令牌缺失，请重新获取')];
        unset($_SESSION['ac_bind_pending']);
        admin_flash_redirect($res['msg'], 'appcenter');
    }

    if ($do === 'refresh') {
        $res = $ac->refresh();
        admin_flash_redirect($res['msg'], 'appcenter');
    }

    if ($do === 'rename') {
        $res = $ac->renameSite((string)p('site_name', '', 'str'));
        admin_flash_redirect($res['msg'], 'appcenter');
    }

    if ($do === 'purchase') {
        $appKey = p('app_key', '', 'str');
        $backType = p('back_type', 'plugin', 'str');
        if ($appKey === '') {
            admin_flash_redirect(t_admin('adm_ac_app_key_missing', '缺少应用标识'), 'appcenter', ['type' => $backType]);
        }
        $res = $ac->purchase($appKey);
        if (!empty($res['paid'])) {
            $install = $ac->install($appKey);
            admin_flash_redirect($install['msg'], 'appcenter', ['type' => $backType]);
        }
        if (!empty($res['order_no'])) {
            $_SESSION['ac_pending_order'] = [
                'app_key'   => $appKey,
                'back_type' => $backType,
                'order_no'  => $res['order_no'],
                'pay'       => $res['pay'] ?? null,
                'created_at'=> time(),
            ];
        }
        admin_flash_redirect($res['msg'] ?: t_admin('adm_ac_pay_later', '请完成支付后再安装'), 'appcenter', ['type' => $backType]);
    }

    if ($do === 'pay') {
        $pending = $_SESSION['ac_pending_order'] ?? [];
        $orderNo = (string)($pending['order_no'] ?? p('order_no', '', 'raw'));
        if ($orderNo === '') admin_flash_redirect(t_admin('adm_ac_order_missing', '订单缺失，请重新获取授权'), 'appcenter');
        $res = $ac->payStart($orderNo);
        if ($res['ok'] && !empty($res['json']['pay'])) {
            $pending['pay'] = $res['json']['pay'];
            $pending['created_at'] = time();
            $_SESSION['ac_pending_order'] = $pending;
        }
        admin_flash_redirect($res['msg'] ?: t_admin('adm_ac_pay_tip', '请在打开的支付页面完成付款'), 'appcenter');
    }

    if ($do === 'cancel_order') {
        $pending = $_SESSION['ac_pending_order'] ?? [];
        $orderNo = (string)($pending['order_no'] ?? p('order_no', '', 'raw'));
        $backType = (string)($pending['back_type'] ?? 'plugin');
        $res = $ac->cancelOrder($orderNo);
        if (!empty($res['ok'])) unset($_SESSION['ac_pending_order']);
        admin_flash_redirect($res['msg'] ?: t_admin('adm_ac_order_canceled', '订单已取消'), 'appcenter', ['type' => $backType]);
    }

    if ($do === 'check_install') {
        $appKey = p('app_key', '', 'str');
        $backType = p('back_type', 'plugin', 'str');
        if ($appKey === '') admin_flash_redirect(t_admin('adm_ac_app_key_missing', '缺少应用标识'), 'appcenter', ['type' => $backType]);
        $res = $ac->install($appKey);
        if (!$res['ok'] && !empty($res['order_no'])) {
            $_SESSION['ac_pending_order'] = [
                'app_key'   => $appKey,
                'back_type' => $backType,
                'order_no'  => $res['order_no'],
                'pay'       => $res['pay'] ?? null,
                'created_at'=> time(),
            ];
        } else {
            unset($_SESSION['ac_pending_order']);
        }
        admin_flash_redirect($res['msg'], 'appcenter', ['type' => $backType]);
    }

    if ($do === 'install') {
        $appKey = p('app_key', '', 'str');
        $backType = p('back_type', 'plugin', 'str');
        if ($appKey === '') {
            admin_flash_redirect(t_admin('adm_ac_app_key_missing', '缺少应用标识'), 'appcenter', ['type' => $backType]);
        }
        $res = $ac->install($appKey);
        admin_flash_redirect($res['msg'], 'appcenter', ['type' => $backType]);
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $ticket = g('ticket', '', 'raw');
    if ($ticket !== '') {
        $consumed = $ac->consumeInstallTicket($ticket);
        if (!empty($consumed['ok'])) {
            $install = $ac->install((string)$consumed['app_key']);
            admin_flash_redirect($install['msg'], 'appcenter');
        }
        admin_flash_redirect($consumed['msg'] ?: t_admin('adm_ac_ticket_invalid', '安装工单无效或已失效'), 'appcenter');
    }
}

$bound   = $ac->isBound();
$syncError = '';
if ($bound) {
    $sync  = $ac->refresh();
    $bound = $ac->isBound();
    if (!$sync['ok']) $syncError = (string)$sync['msg'];
}
$bind    = $ac->getBind();
$pending = $_SESSION['ac_bind_pending'] ?? null;
if (is_array($pending) && !empty($pending['expire_at']) && strtotime((string)$pending['expire_at']) < time()) {
    unset($_SESSION['ac_bind_pending']);
    $pending = null;
}

$pendingOrder = $_SESSION['ac_pending_order'] ?? null;
if (is_array($pendingOrder) && time() - (int)($pendingOrder['created_at'] ?? 0) > 1800) {
    unset($_SESSION['ac_pending_order']);
    $pendingOrder = null;
}
if (is_array($pendingOrder) && $bound) {
    $st = $ac->orderStatus((string)($pendingOrder['order_no'] ?? ''));
    if (!empty($st['ok'])) {
        if (!empty($st['paid'])) {
            unset($_SESSION['ac_pending_order']);
            $pendingOrder = null;
            $ac->refresh();
            header('Location: ' . ($_SERVER['REQUEST_URI'] ?? admin_url('appcenter')));
            exit;
        }
        if (in_array((int)$st['status'], [2, 3], true)) {
            unset($_SESSION['ac_pending_order']);
            $pendingOrder = null;
        }
    }
}
if (!is_array($pendingOrder) && $bound) {
    $probe = $ac->pendingOrder();
    if ($probe['ok'] && is_array($probe['order'])) {
        $po = $probe['order'];
        if (!empty($po['order_no']) && !empty($po['app_key'])) {
            $pendingOrder = [
                'app_key'    => (string)$po['app_key'],
                'back_type'  => (string)($po['back_type'] ?? 'plugin'),
                'order_no'   => (string)$po['order_no'],
                'pay'        => null,
                'created_at' => time(),
            ];
            $_SESSION['ac_pending_order'] = $pendingOrder;
        }
    }
}

$env     = $ac->detectEnv();
$envText = $env ? ('PHP ' . $env['php'] . ' · ' . htmlspecialchars(strtoupper($env['os'])) . ' ' . htmlspecialchars($env['arch']) . ' · ' . strtoupper($env['ts'])) : '';
$loaderInstalled = $ac->loaderInstalled();

$localSiteName = $ac->displaySiteName();
$displaySiteName = $localSiteName !== '' ? $localSiteName : ($bind['site_name'] ?: $bind['site_url']);
$marketUrl = AppCenterService::centerUrl() . '/store/plugins';
$loginUrl = AppCenterService::centerUrl() . '/auth/login';

$view = g('view', 'market', 'str');
if (!in_array($view, ['market', 'mine'], true)) $view = 'market';

$type      = g('type', 'plugin', 'str');
if (!in_array($type, ['theme', 'plugin'], true)) $type = 'plugin';
$category  = (int)g('category', 0, 'int');
$priceType = g('price_type', '', 'str');
$keyword   = g('keyword', '', 'str');
$order     = g('order', 'newest', 'str');
if (!in_array($order, ['newest', 'downloads', 'rating'], true)) $order = 'newest';
$page      = max(1, g('page', 1, 'int'));

$result = $ac->catalog([
    'type'       => $type,
    'category'   => $category,
    'keyword'    => $keyword,
    'price_type' => $priceType,
    'order'      => $order,
    'page'       => $page,
    'per_page'   => 20,
]);
$offline = !empty($result['offline']);
$list    = $result['list'] ?? [];
$categories = $result['categories'] ?? [];
$installed = $ac->localInstalled();

$mine = $view === 'mine' ? $ac->mineList() : [];
$mineUpdateCount = count(array_filter($mine, fn($m) => !empty($m['has_update'])));

$anyNeedLoader = false;
foreach ($list as $_a) {
    if ((string)($_a['require_loader'] ?? '') === 'bolt') { $anyNeedLoader = true; break; }
}
$loaderMeta  = $anyNeedLoader ? $ac->loaders() : ['guide' => []];
$loaderGuide = $loaderMeta['guide'] ?? [];

$guideLoader = null;
foreach ($list as $_a) {
    $_chk = $_a['env_check'] ?? [];
    if (is_array($_chk['loader'] ?? null)) { $guideLoader = $_chk['loader']; break; }
}
$guideDlUrl  = $guideLoader ? Security::safeUrl($ac->loaderDownloadUrl((string)$guideLoader['file'])) : '';
$guideOsKey  = strtolower((string)($env['os'] ?? ''));
$guideOsKey  = str_contains($guideOsKey, 'win') ? 'windows' : (str_contains($guideOsKey, 'mac') || str_contains($guideOsKey, 'darwin') ? 'darwin' : 'linux');
$guideFileName = $guideLoader ? ((string)($guideLoader['os'] ?? '') === 'windows' ? 'php_enovelcms.dll' : 'enovelcms.so') : '';

$compatText = function (array $app): string {
    $phpList = $app['php_versions'] ?? [];
    if (!empty($phpList) && is_array($phpList)) {
        $php = implode(', ', $phpList);
    } else {
        $php = trim(($app['min_php'] ?: '') . '–' . ($app['max_php'] ?: ''), '–');
    }
    $cms = trim(cms_minor((string)($app['min_cms'] ?: '')) . '–' . cms_minor((string)($app['max_cms'] ?: '')), '–');
    $parts = [];
    $parts[] = 'PHP ' . ($php !== '' ? $php : t_admin('adm_ac_compat_any', '不限'));
    $parts[] = 'CMS ' . ($cms !== '' ? $cms : t_admin('adm_ac_compat_any', '不限'));
    return implode(' · ', $parts);
};

$loaderState = function (array $app) use ($loaderInstalled, $env): array {
    $need = (string)($app['require_loader'] ?? '') === 'bolt';
    $check = is_array($app['env_check'] ?? null) ? $app['env_check'] : [];
    $compatible = array_key_exists('compatible', $check) ? $check['compatible'] : null;
    $matched = is_array($check['loader'] ?? null) ? $check['loader'] : null;
    $reason = (string)($check['reason'] ?? '');
    $requiredExt = array_values(array_map('strval', (array)($check['required']['ext'] ?? [])));

    $haveExts = array_map('strtolower', (array)($env['exts'] ?? []));
    $missingExt = [];
    foreach ($requiredExt as $ext) {
        $e = strtolower(trim($ext));
        if ($e !== '' && !in_array($e, $haveExts, true)) $missingExt[] = trim($ext);
    }

    if ($compatible === false) {
        $status = 'deny';
    } elseif ($compatible === null) {
        $status = 'unknown';
    } elseif ($missingExt) {
        $status = 'deny';
        if ($reason === '') $reason = t_admin('adm_ac_env_missing_ext', '缺少必需 PHP 扩展：') . implode('、', $missingExt);
    } elseif ($need && !$loaderInstalled) {
        $status = $matched ? 'pending' : 'deny';
    } else {
        $status = 'ready';
    }

    return [
        'need'        => $need,
        'installed'   => $loaderInstalled,
        'matched'     => $matched,
        'compatible'  => $compatible,
        'reason'      => $reason,
        'requiredExt' => $requiredExt,
        'missingExt'  => $missingExt,
        'status'      => $status,
    ];
};

$anyDeny = false;
$denyReasons = array();
foreach ($list as $_a) {
    $_ls = $loaderState($_a);
    if ((string)($_ls['status'] ?? '') === 'deny') {
        $anyDeny = true;
        $_r = trim((string)($_ls['reason'] ?? ''));
        if ($_r !== '') $denyReasons[$_r] = true;
    }
}
if ($anyDeny && empty($loaderMeta['loaders'])) {
    $loaderMeta = $ac->loaders();
}
$loaderAll = is_array($loaderMeta['loaders'] ?? null) ? $loaderMeta['loaders'] : array();

$starsText = function ($rating): string {
    $r = (int)round((float)$rating);
    $r = max(0, min(5, $r));
    return str_repeat('★', $r) . str_repeat('☆', 5 - $r);
};

$coverHtml = function (array $app, string $size = 'card'): string {
    $name = (string)($app['name'] ?? $app['app_id'] ?? '?');
    $icon = trim((string)($app['icon'] ?? ''));
    if ($icon !== '') {
        $iconUrl = Security::safeUrl($icon);
        if ($iconUrl !== '') {
            return '<img class="ac-cover-img" src="' . htmlspecialchars($iconUrl) . '" alt="' . htmlspecialchars($name) . '" loading="lazy">';
        }
    }
    $letter = htmlspecialchars(mb_substr($name, 0, 1));
    return '<div class="ac-cover-ph"><span>' . $letter . '</span></div>';
};

$buildUrl = function (array $over = []) use ($type, $category, $priceType, $keyword, $order, $page, $view) {
    $q = array_merge(compact('type', 'category', 'priceType', 'keyword', 'order', 'page', 'view'), $over);
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    return admin_url('appcenter', $q);
};

$actionButtons = function (array $app, string $context = 'market') use ($ac, $installed, $loaderState, $loaderInstalled): string {
    $appKey  = (string)($app['app_id'] ?? '');
    $isInstalled = isset($installed[$appKey]);
    $hasLicense  = !empty($app['license']) || !empty($app['licensed'])
        || $ac->hasLocalLicense((int)($app['id'] ?? 0));
    $free        = !empty($app['price']['free']);
    $typeLabel   = $app['type'] === 'theme' ? 'theme' : 'plugin';
    $ls          = $loaderState($app);
    $status      = $ls['status'];
    $extMatched  = $ls['matched'];
    $out = '';

    if ($isInstalled) {
        return '<button type="button" class="btn btn-outline btn-sm" disabled><i class="fas fa-check"></i> ' . t_admin('adm_ac_installed', '已安装') . '</button>';
    }
    if ($status === 'deny') {
        $tip = $ls['reason'] !== '' ? $ls['reason'] : t_admin('adm_ac_env_no_match', '当前环境暂无可匹配的扩展');
        $out .= '<button type="button" class="btn btn-outline btn-sm ac-env-fix" onclick="openAcGuide()" style="border-color:#e0576b;color:#c0392b" title="' . htmlspecialchars($tip) . '"><i class="fas fa-triangle-exclamation"></i> ' . t_admin('adm_ac_env_fix', '环境不支持 · 安装说明') . '</button>';
        return $out;
    }
    if ($status === 'pending') {
        if ($extMatched) {
            $dlUrl = Security::safeUrl($ac->loaderDownloadUrl((string)$extMatched['file']));
            $matchLabel = htmlspecialchars($extMatched['label'] ?? t_admin('adm_ac_ext_fallback', 'EnovelCms 扩展'));
            $out .= '<a class="btn btn-outline btn-sm" href="' . htmlspecialchars($dlUrl) . '" title="' . $matchLabel . '"><i class="fas fa-download"></i> ' . t_admin('adm_ac_loader_download', '下载扩展') . '</a>';
        }
        if ($context === 'market') {
            $out .= '<button type="button" class="btn btn-outline btn-sm" onclick="openAcGuide()"><i class="fas fa-book-open"></i> ' . t_admin('adm_ac_loader_guide', '安装说明') . '</button>';
        }
        return $out;
    }
    if ($free || $hasLicense) {
        $out .= '<form method="post" action="' . htmlspecialchars(admin_url('appcenter')) . '" style="margin:0">'
            . Security::csrfField()
            . '<input type="hidden" name="do" value="install">'
            . '<input type="hidden" name="app_key" value="' . htmlspecialchars($appKey) . '">'
            . '<input type="hidden" name="back_type" value="' . htmlspecialchars($typeLabel) . '">'
            . '<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> ' . t_admin('adm_ac_install', '安装') . '</button></form>';
    } else {
        $out .= '<form method="post" action="' . htmlspecialchars(admin_url('appcenter')) . '" style="margin:0">'
            . Security::csrfField()
            . '<input type="hidden" name="do" value="purchase">'
            . '<input type="hidden" name="app_key" value="' . htmlspecialchars($appKey) . '">'
            . '<input type="hidden" name="back_type" value="' . htmlspecialchars($typeLabel) . '">'
            . '<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-cart-shopping"></i> ' . t_admin('adm_ac_get_license', '获取授权') . '</button></form>';
    }
    return $out;
};
?>

<?php if (!$bound): ?>
<div class="card">
    <div class="ac-conn-bar" style="align-items:flex-start;padding:0 0 16px;border-bottom:1px solid var(--border)">
        <div class="ac-conn-main">
            <h2 class="ac-conn-title"><i class="fas fa-link" style="color:var(--primary)"></i> <?= t_admin('adm_ac_connect', '连接应用中心') ?></h2>
            <p class="text-muted" style="font-size:12.5px;margin:6px 0 0;max-width:560px;line-height:1.7">
                <?= t_admin('adm_ac_not_connected_tip', '本站尚未连接应用中心。请先在官方应用中心注册并登录账号，再将本站绑定到该账号，即可安装与更新付费插件、主题。') ?>
            </p>
        </div>
        <div class="ac-conn-actions">
            <div class="ac-conn-btns">
                <a class="btn btn-primary" href="<?= htmlspecialchars(Security::safeUrl($loginUrl)) ?>" target="_blank" rel="noopener">
                    <i class="fas fa-external-link-alt"></i> <?= t_admin('adm_ac_go_center', '前往官方应用中心') ?>
                </a>
            </div>
            <span class="ac-conn-tip text-muted"><?= t_admin('adm_ac_go_center_tip', '新用户请先注册，已有账号直接登录') ?></span>
        </div>
    </div>

    <?php if (!$pending): ?>
        <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin-top:16px;max-width:460px">
            <?= Security::csrfField() ?>
            <input type="hidden" name="do" value="bind_init">
            <button type="submit" class="btn btn-outline"><i class="fas fa-key"></i> <?= t_admin('adm_ac_get_auth_link', '获取登录授权链接') ?></button>
        </form>
    <?php else: ?>
        <div class="alert alert-warn" style="margin-top:16px">
            <i class="fas fa-circle-info"></i>
            <strong><?= t_admin('adm_ac_complete_auth', '请完成中心授权') ?></strong>
            <p style="margin:6px 0 0">
                <?= t_admin('adm_ac_auth_step1', '1. 打开下面的链接，登录你的应用中心账号并确认绑定本站点；<br>') ?>
                <?= t_admin('adm_ac_auth_step2', '2. 授权成功后回到本页，点击「完成绑定」。链接 10 分钟内有效。') ?>
            </p>
            <p style="margin:10px 0 0">
                <a href="<?= htmlspecialchars(Security::safeUrl((string)$pending['login_url'])) ?>" target="_blank" rel="noopener" class="btn btn-outline">
                    <i class="fas fa-external-link-alt"></i> <?= t_admin('adm_ac_go_auth', '前往中心授权') ?>
                </a>
            </p>
            <p style="margin:12px 0 0;font-size:13px;">
                <?= t_admin('adm_ac_user_code_label', '用户码（中心确认时必填）：') ?>
                <code style="font-size:18px;letter-spacing:4px;padding:4px 10px;"><?= htmlspecialchars((string)($pending['user_code'] ?? '')) ?></code>
            </p>
        </div>
        <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin-top:12px;max-width:460px">
            <?= Security::csrfField() ?>
            <input type="hidden" name="do" value="connect_token">
            <input type="hidden" name="bind_token" value="<?= htmlspecialchars($pending['bind_token']) ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> <?= t_admin('adm_ac_done_bind', '我已完成授权，完成绑定') ?></button>
        </form>
        <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin-top:10px;max-width:460px">
            <?= Security::csrfField() ?>
            <input type="hidden" name="do" value="bind_init">
            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-rotate"></i> <?= t_admin('adm_ac_get_auth_link', '获取登录授权链接') ?></button>
        </form>
    <?php endif; ?>
</div>
<?php else: ?>

<?php if ($syncError !== ''): ?>
<div class="card ac-sync-warn"><?= htmlspecialchars($syncError) ?></div>
<?php endif; ?>

<div class="card ac-conn-bar">
    <div class="ac-conn-main">
        <h2 class="ac-conn-title"><i class="fas fa-circle-check" style="color:#10b981"></i>
            <?= t_admin('adm_ac_connected', '已连接应用中心') ?>
            <span class="text-muted" style="font-size:13px;font-weight:400">/ <?= htmlspecialchars((string)$bind['username'] ?? '') ?></span>
        </h2>
        <div class="ac-conn-meta text-muted">
            <span class="ac-site-line"><?= t_admin('adm_ac_site', '站点：') ?><span id="ac-site-name-text"><?= htmlspecialchars($displaySiteName) ?></span>
                <button type="button" class="ac-site-edit" id="ac-site-name-toggle"><?= t_admin('adm_ac_site_name_edit', '修改') ?></button>
            </span>
            <span><?= t_admin('adm_ac_site_url', '地址：') ?><?= htmlspecialchars((string)$bind['site_url']) ?></span>
            <span><?= t_admin('adm_ac_identity', '安装身份：') ?><code><?= htmlspecialchars($bind['client_identity'] ?? ($bind['identity_id'] ?: '—')) ?></code></span>
            <span><?= t_admin('adm_ac_env', '本机环境：%s', $envText ?: '—') ?></span>
        </div>
        <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" class="ac-site-form" id="ac-site-name-box" style="display:none">
            <?= Security::csrfField() ?>
            <input type="hidden" name="do" value="rename">
            <input type="text" name="site_name" class="form-control" maxlength="100" value="<?= htmlspecialchars($displaySiteName) ?>" placeholder="<?= t_admin('adm_ac_site_name_ph', '站点名称，最多 100 字') ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> <?= t_admin('adm_ac_site_name_save', '保存名称') ?></button>
        </form>
        <script>
        (function () {
            var toggle = document.getElementById('ac-site-name-toggle');
            var box = document.getElementById('ac-site-name-box');
            if (!toggle || !box) return;
            toggle.addEventListener('click', function () {
                var show = box.style.display === 'none';
                box.style.display = show ? 'flex' : 'none';
                if (show) {
                    var input = box.querySelector('input[name="site_name"]');
                    if (input) { input.focus(); input.select(); }
                }
            });
        })();
        </script>
    </div>
    <div class="ac-conn-actions">
        <div class="ac-conn-btns">
            <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin:0">
                <?= Security::csrfField() ?>
                <input type="hidden" name="do" value="refresh">
                <button type="submit" class="btn btn-outline"><i class="fas fa-rotate"></i> <?= t_admin('adm_ac_sync_auth', '同步授权') ?></button>
            </form>
            <a class="btn btn-outline" href="<?= htmlspecialchars(Security::safeUrl($marketUrl)) ?>" target="_blank" rel="noopener">
                <i class="fas fa-store"></i> <?= t_admin('adm_ac_go_market_center', '前往官方应用市场') ?>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="ac-view-tabs">
    <a class="ac-view-tab <?= $view === 'market' ? 'active' : '' ?>" href="<?= $buildUrl(['view' => 'market', 'page' => 1]) ?>">
        <i class="fas fa-store"></i> <?= t_admin('adm_ac_view_market', '市场浏览') ?>
    </a>
    <a class="ac-view-tab <?= $view === 'mine' ? 'active' : '' ?>" href="<?= $buildUrl(['view' => 'mine', 'page' => 1]) ?>">
        <i class="fas fa-boxes-stacked"></i> <?= t_admin('adm_ac_view_mine', '我的应用') ?>
        <?php if ($mineUpdateCount > 0): ?><span class="ac-tab-badge"><?= $mineUpdateCount ?></span><?php endif; ?>
    </a>
</div>

<?php if ($pendingOrder && $bound):
    $poPay   = $pendingOrder['pay'] ?? null;
    $payType = is_array($poPay) ? (string)($poPay['pay_type'] ?? '') : '';
    $payInfo = is_array($poPay) ? (string)($poPay['pay_info'] ?? '') : '';
    $payUrl  = Security::safeUrl($payInfo);
    $isJump  = $payUrl !== '' && ($payType === 'jump' || (bool)preg_match('#^https?://#i', $payInfo));
    $poBack  = $pendingOrder['back_type'] ?? 'plugin';
    $poQrUrl = Security::safeUrl($ac->payQrcodeUrl((string)$pendingOrder['order_no']));
?>
<div class="alert alert-warn ac-pending">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;align-items:center">
        <div>
            <strong><i class="fas fa-clock"></i> <?= t_admin('adm_ac_pending_order', '待支付订单') ?></strong>
            <span class="text-muted" style="font-size:12.5px;margin-left:6px">
                <?= t_admin('adm_ac_pending_app', '应用') ?> <code><?= htmlspecialchars((string)$pendingOrder['app_key']) ?></code> · <?= t_admin('adm_ac_pending_order_no', '单号') ?> <code><?= htmlspecialchars($pendingOrder['order_no']) ?></code>
            </span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <?php if ($isJump): ?>
            <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($payUrl) ?>" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt"></i> <?= t_admin('adm_ac_go_pay', '前往支付') ?>
            </a>
            <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin:0">
                <?= Security::csrfField() ?>
                <input type="hidden" name="do" value="pay">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-credit-card"></i> <?= t_admin('adm_ac_repay', '重新拉起支付') ?></button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin:0">
                <?= Security::csrfField() ?>
                <input type="hidden" name="do" value="check_install">
                <input type="hidden" name="app_key" value="<?= htmlspecialchars((string)$pendingOrder['app_key']) ?>">
                <input type="hidden" name="back_type" value="<?= htmlspecialchars($poBack) ?>">
                <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-circle-check"></i> <?= t_admin('adm_ac_check_install', '我已支付，检查并安装') ?></button>
            </form>
            <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin:0" onsubmit="return confirm(<?= json_encode(t_admin('adm_ac_cancel_confirm', '取消后该待支付订单将关闭且不可继续支付，确定取消？')) ?>)">
                <?= Security::csrfField() ?>
                <input type="hidden" name="do" value="cancel_order">
                <button type="submit" class="btn btn-outline btn-sm ac-cancel-btn"><i class="fas fa-xmark"></i> <?= t_admin('adm_ac_cancel_order', '取消订单') ?></button>
            </form>
        </div>
    </div>
    <?php if (!$isJump && is_array($poPay) && $poQrUrl !== ''): ?>
    <div class="ac-pay-qr">
        <img src="<?= htmlspecialchars($poQrUrl) ?>" alt="<?= t_admin('adm_ac_qrcode', '支付二维码') ?>" loading="lazy">
        <span class="text-muted" style="font-size:12px"><?= t_admin('adm_ac_qrcode_tip', '请使用手机扫码完成支付；若二维码未显示，请点「重新拉起支付」') ?></span>
    </div>
    <?php endif; ?>
    <script>
    (function () {
        var url = <?= json_encode(admin_url('appcenter', ['do' => 'order_status', 'order_no' => (string)$pendingOrder['order_no']])) ?>;
        var timer = setInterval(function () {
            fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); })
                .then(function (j) { if (j && j.paid) { clearInterval(timer); location.reload(); } })
                .catch(function () {});
        }, 10000);
    })();
    </script>
</div>
<?php endif; ?>

<?php if ($view === 'mine'): ?>
<div class="card">
    <div class="ac-mine-head">
        <div>
            <h2 style="margin:0"><i class="fas fa-boxes-stacked"></i> <?= t_admin('adm_ac_view_mine', '我的应用') ?></h2>
        </div>
        <div class="ac-mine-stats">
            <span class="stat-chip"><?= t_admin('adm_ac_mine_installed', '已装 <strong>%s</strong>', count($mine)) ?></span>
            <span class="stat-chip <?= $mineUpdateCount > 0 ? 'warn' : '' ?>"><?= t_admin('adm_ac_mine_upgradeable', '可升级 <strong>%s</strong>', $mineUpdateCount) ?></span>
        </div>
    </div>

    <?php if (empty($mine)): ?>
        <div class="empty">
            <i class="fas fa-box-open"></i>
            <p><?= t_admin('adm_ac_mine_empty', '本机还没有安装任何应用') ?></p>
            <a class="btn btn-outline btn-sm" href="<?= $buildUrl(['view' => 'market']) ?>"><i class="fas fa-store"></i> <?= t_admin('adm_ac_go_market', '去市场看看') ?></a>
        </div>
    <?php else: ?>
    <div class="ac-mine-list">
        <?php foreach ($mine as $m):
            $mType = $m['type'] === 'theme' ? t_admin('adm_ac_type_theme', '主题') : t_admin('adm_ac_type_plugin', '插件');
            $mAdminUrl = $m['type'] === 'theme'
                ? admin_url('themes')
                : admin_url('plugins', ['filter' => 'enabled']);
        ?>
        <div class="ac-mine-item">
            <div class="ac-mine-main">
                <?php
                    $mIcon = trim((string)($m['icon'] ?? ''));
                    $mIconUrl = $mIcon !== '' ? ((strpos($mIcon, 'http') === 0) ? $mIcon : BASE_URL . $mIcon) : '';
                    if ($mIconUrl !== '') {
                        echo '<img class="ac-mine-icon" style="object-fit:cover" src="' . htmlspecialchars($mIconUrl) . '" alt="">';
                    } else {
                        echo '<div class="ac-mine-icon' . ($m['type'] === 'theme' ? ' is-theme' : '') . '">' . htmlspecialchars(mb_substr((string)$m['name'], 0, 1)) . '</div>';
                    }
                ?>
                <div style="min-width:0">
                    <div class="ac-mine-name">
                        <?= htmlspecialchars((string)$m['name']) ?>
                        <span class="badge badge-gray"><?= $mType ?></span>
                </div>
                <div class="ac-mine-meta">
                    <code><?= htmlspecialchars($m['key']) ?></code>
                    <?= t_admin('adm_ac_mine_current', '· 当前 v%s', htmlspecialchars((string)$m['version'])) ?>
                    <?php if ($m['has_update']): ?> <?= t_admin('adm_ac_mine_latest', '· 最新 v%s', htmlspecialchars($m['latest'])) ?><?php endif; ?>
                </div>
                </div>
            </div>
            <div class="ac-mine-actions">
                <?php if ($m['has_update']): ?>
                    <form method="post" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" style="margin:0" onsubmit="return confirm(<?= json_encode(t_admin('adm_ac_mine_upgrade_confirm', '将从应用中心下载最新版覆盖安装该应用，确定升级？')) ?>)">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="do" value="install">
                        <input type="hidden" name="app_key" value="<?= htmlspecialchars($m['key']) ?>">
                        <input type="hidden" name="back_type" value="<?= htmlspecialchars((string)$m['type']) ?>">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-arrow-up"></i> <?= t_admin('adm_ac_mine_upgrade_btn', '升级') ?></button>
                    </form>
                <?php endif; ?>
                <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars($mAdminUrl) ?>"><i class="fas fa-gear"></i> <?= t_admin('adm_ac_mine_manage', '管理') ?></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php ?>
<?php else: ?>
<div class="card">
    <?php if ($offline): ?>
        <div class="alert alert-warn ac-state">
            <i class="fas fa-cloud-slash"></i>
            <div>
                <strong><?= t_admin('adm_ac_offline_title', '应用中心暂时无法连接') ?></strong>
                <p style="margin:6px 0 0"><?= t_admin('adm_ac_offline_desc', '市场列表暂不可用；本站已安装的应用不受影响，仍可正常使用。网络恢复后刷新本页即可浏览市场。') ?></p>
            </div>
        </div>
    <?php elseif (!$result['ok']): ?>
        <div class="alert alert-err"><?= htmlspecialchars($result['msg'] ?? t_admin('adm_ac_market_fail', '拉取市场列表失败')) ?></div>
    <?php else: ?>

        <div class="ac-toolbar">
          <div class="ac-toolbar-row">
            <div class="ac-type-seg">
                <?php foreach (['plugin' => t_admin('adm_ac_type_plugin', '插件'), 'theme' => t_admin('adm_ac_type_theme', '主题')] as $tk => $tlabel): ?>
                    <a class="ac-type-tab <?= $type === $tk ? 'active' : '' ?>" href="<?= $buildUrl(['type' => $tk, 'page' => 1]) ?>"><?= $tlabel ?></a>
                <?php endforeach; ?>
            </div>

            <form method="get" action="<?= htmlspecialchars(admin_url('appcenter')) ?>" class="ac-filter">
            <input type="hidden" name="action" value="appcenter">
            <input type="hidden" name="view" value="market">
            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
            <input class="form-control" type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="<?= t_admin('adm_ac_search_ph', '搜索名称 / 标识') ?>">
            <?php if (!empty($categories)): ?>
            <select class="form-control" name="category">
                <option value="0"><?= t_admin('adm_ac_category_all', '全部分类') ?></option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>" <?= $category === (int)$cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)$cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <select class="form-control" name="price_type">
                <option value=""><?= t_admin('adm_ac_price_all', '全部价格') ?></option>
                <option value="free" <?= $priceType === 'free' ? 'selected' : '' ?>><?= t_admin('adm_ac_price_free', '免费') ?></option>
                <option value="paid" <?= $priceType === 'paid' ? 'selected' : '' ?>><?= t_admin('adm_ac_price_paid', '付费') ?></option>
            </select>
            <select class="form-control" name="order">
                <option value="newest" <?= $order === 'newest' ? 'selected' : '' ?>><?= t_admin('adm_ac_order_newest', '最新发布') ?></option>
                <option value="downloads" <?= $order === 'downloads' ? 'selected' : '' ?>><?= t_admin('adm_ac_order_downloads', '下载最多') ?></option>
                <option value="rating" <?= $order === 'rating' ? 'selected' : '' ?>><?= t_admin('adm_ac_order_rating', '评分最高') ?></option>
            </select>
                <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-filter"></i> <?= t_admin('adm_ac_filter', '筛选') ?></button>
            </form>
          </div>
        </div>

        <div class="ac-grid">
            <?php if (empty($list)): ?>
                <div class="empty" style="grid-column:1/-1">
                    <i class="fas fa-magnifying-glass-minus"></i>
                    <p><?= t_admin('adm_ac_empty', '当前筛选条件下暂无应用') ?></p>
                </div>
            <?php else: foreach ($list as $app):
                $appKey      = (string)($app['app_id'] ?? '');
                $centerAppUrl = AppCenterService::centerUrl() . '/store/app/' . (int)($app['id'] ?? 0);
                $isInstalled = isset($installed[$appKey]);
                $hasLicense  = !empty($app['license']) || !empty($app['licensed'])
                    || $ac->hasLocalLicense((int)($app['id'] ?? 0));
                $free        = !empty($app['price']['free']);
                $priceLabel  = htmlspecialchars($app['price']['label'] ?? '—');
                $priceBase   = $free || empty($app['price']['promo']) ? '' : htmlspecialchars($app['price']['base_price'] ?? '');
                $typeLabel   = $app['type'] === 'theme' ? t_admin('adm_ac_type_theme', '主题') : t_admin('adm_ac_type_plugin', '插件');
                $ls          = $loaderState($app);
                $envStatus   = $ls['status'];
                $extMatched  = $ls['matched'];
                $summary     = mb_substr((string)($app['description'] ?? ''), 0, 48);
                $authorName  = (string)($app['author_name'] ?? '');
                $catName     = (string)($app['category_name'] ?? '');
                $updatedDate = $app['updated_at'] ? mb_substr((string)$app['updated_at'], 0, 10) : '';
                $version     = trim((string)($app['version'] ?? ''));
                $compat      = $compatText($app);
                $reqExt      = array_values(array_filter(array_map('strval', (array)($app['require_ext'] ?? []))));

                if ($isInstalled) {
                    $status = '<span class="badge badge-green">' . t_admin('adm_ac_installed', '已安装') . '</span>';
                } elseif ($hasLicense) {
                    $status = '<span class="badge badge-blue">' . t_admin('adm_ac_authorized', '已授权') . '</span>';
                } elseif ($free) {
                    $status = '<span class="badge badge-green">' . t_admin('adm_ac_free_badge', '免费') . '</span>';
                } else {
                    $status = '<span class="badge badge-orange">' . t_admin('adm_ac_pending_auth', '待授权') . '</span>';
                }
            ?>
            <div class="ac-card">
                <?php if ($version !== ''): ?><span class="ac-card-ver">v<?= htmlspecialchars($version) ?></span><?php endif; ?>
                <div class="ac-card-head-row">
                    <a class="ac-card-cover" href="<?= htmlspecialchars(Security::safeUrl($centerAppUrl)) ?>" target="_blank" rel="noopener">
                        <?= $coverHtml($app) ?>
                        <span class="ac-status"><?= $status ?></span>
                    </a>
                    <div class="ac-card-head-info">
                        <div class="ac-card-title">
                            <a class="ac-card-name" href="<?= htmlspecialchars(Security::safeUrl($centerAppUrl)) ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string)($app['name'] ?? $appKey)) ?></a>
                        </div>
                        <div class="ac-card-author">
                            <?php if ($authorName !== ''): ?><i class="fas fa-user"></i> <?= htmlspecialchars($authorName) ?><?php else: ?><code><?= htmlspecialchars($appKey) ?></code><?php endif; ?>
                        </div>
                        <?php if ($catName !== '' || $typeLabel !== ''): ?>
                        <div class="ac-card-cat">
                            <span class="ac-req-type"><?= $typeLabel ?><?php if ($catName !== ''): ?> · <?= htmlspecialchars($catName) ?><?php endif; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ac-card-divider"></div>
                <div class="ac-card-body">
                    <?php if ($summary !== ''): ?>
                    <div class="ac-card-desc"><?= htmlspecialchars($summary) ?><?= mb_strlen((string)($app['description'] ?? '')) > 48 ? '…' : '' ?></div>
                    <?php endif; ?>
                    <div class="ac-card-req">
                        <span class="ac-req-item" title="<?= htmlspecialchars($compat) ?>"><i class="fas fa-microchip"></i> <?= htmlspecialchars($compat) ?></span>
                    </div>
                    <?php if ($reqExt): ?>
                    <div class="ac-req-ext-row">
                        <?php foreach ($reqExt as $ext):
                            $extOk = extension_loaded(strtolower(trim($ext)));
                        ?><span class="ac-req-ext <?= $extOk ? 'is-ok' : 'is-miss' ?>" title="<?= $extOk ? t_admin('adm_ac_ext_loaded', '本机已安装') : t_admin('adm_ac_ext_missing', '本机未安装') ?>"><?= htmlspecialchars($ext) ?></span><?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!$isInstalled && $envStatus !== 'unknown'): ?>
                        <?php if ($envStatus === 'ready'): ?>
                            <?php if ($ls['need'] && $ls['installed']): ?>
                                <span class="ac-ext ac-ext-ok"><i class="fas fa-circle-check"></i><?= t_admin('adm_ac_loader_installed', 'EnovelCms 扩展已安装') ?></span>
                            <?php else: ?>
                                <span class="ac-ext ac-ext-ok"><i class="fas fa-circle-check"></i><?= t_admin('adm_ac_env_ready', '环境就绪') ?></span>
                            <?php endif; ?>
                        <?php elseif ($envStatus === 'pending'): ?>
                            <button type="button" class="ac-ext ac-ext-pending" onclick="openAcGuide()">
                                <i class="fas fa-triangle-exclamation"></i><?= t_admin('adm_ac_loader_pending', 'EnovelCms 扩展待安装 · 查看说明') ?>
                            </button>
                        <?php else: ?>
                            <span class="ac-ext ac-ext-deny" onclick="openAcGuide()" role="button" tabindex="0" style="cursor:pointer" title="<?= htmlspecialchars((string)$ls['reason']) ?>"><i class="fas fa-circle-xmark"></i><?= htmlspecialchars($ls['reason'] ?: t_admin('adm_ac_loader_unsupported', '当前环境不支持该扩展')) ?> · <?= t_admin('adm_ac_env_view_guide', '查看安装说明') ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <div class="ac-card-foot">
                    <div class="ac-card-meta">
                        <div class="ac-meta-row">
                            <span class="ac-price <?= $free ? 'free' : '' ?>"><?= $priceLabel ?><?php if ($priceBase): ?><del><?= $priceBase ?></del><?php endif; ?></span>
                            <span><i class="fas fa-star"></i> <?= $starsText($app['rating'] ?? 0) ?></span>
                        </div>
                        <div class="ac-meta-row">
                            <?php if ($updatedDate !== ''): ?><span><i class="fas fa-clock"></i> <?= htmlspecialchars($updatedDate) ?></span><?php endif; ?>
                            <span><i class="fas fa-download"></i> <?= (int)($app['downloads'] ?? 0) ?></span>
                        </div>
                    </div>
                    <div class="ac-card-actions">
                        <a class="btn btn-outline btn-sm" href="<?= htmlspecialchars(Security::safeUrl($centerAppUrl)) ?>" target="_blank" rel="noopener"><i class="fas fa-circle-info"></i> <?= t_admin('adm_ac_detail', '详情') ?></a>
                        <?= $actionButtons($app) ?>
                    </div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <?php $totalPages = max(1, (int)($result['totalPages'] ?? 1)); ?>
        <?php if ($totalPages > 1): ?>
            <div class="pager">
                <?php if ($page > 1): ?>
                    <a class="btn btn-outline btn-sm" href="<?= $buildUrl(['page' => $page - 1]) ?>"><?= t_admin('adm_pg_prev', '上一页') ?></a>
                <?php endif; ?>
                <span class="text-muted" style="font-size:12.5px"><?= t_admin('adm_ac_page_line', '第 %d / %d 页 · 共 %s 个', $page, $totalPages, (int)($result['total'] ?? 0)) ?></span>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-outline btn-sm" href="<?= $buildUrl(['page' => $page + 1]) ?>"><?= t_admin('adm_pg_next', '下一页') ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php
            $osTips = $loaderGuide['by_os'][$guideOsKey] ?? [];
            $sceneLabel = $guideOsKey === 'windows' ? 'windows' : 'unix';
            if ($guideLoader) {
                $parts = [];
                if (!empty($guideLoader['php'])) $parts[] = 'PHP ' . $guideLoader['php'];
                if (!empty($guideLoader['os'])) $parts[] = strtoupper($guideLoader['os']);
                if (!empty($guideLoader['arch'])) $parts[] = $guideLoader['arch'];
                if (!empty($guideLoader['ts']) && $guideLoader['ts'] !== 'any') $parts[] = strtoupper($guideLoader['ts']);
                $envLabel = implode(' · ', $parts);
            } else {
                $envLabel = '';
            }
        ?>
        <div class="modal-mask ac-guide-modal" id="acGuideModal">
            <div class="modal ac-guide-box">
                <div class="modal-head">
                    <h3><i class="fas fa-puzzle-piece"></i> <?= t_admin('adm_ac_loader_title', 'EnovelCms 扩展安装说明') ?></h3>
                    <button type="button" class="modal-close" aria-label="<?= t_admin('adm_ac_loader_close', '关闭') ?>" onclick="closeAcGuide()">&times;</button>
                </div>
                <div class="modal-body">
                    <?php if ($anyDeny): ?>
                    <div class="alert alert-err" style="margin-bottom:12px">
                        <strong><i class="fas fa-circle-xmark"></i> <?= t_admin('adm_ac_env_modal_now', '当前环境没有可用的扩展包') ?></strong>
                        <div style="margin-top:6px;font-size:12.5px;line-height:1.7">
                            <?= htmlspecialchars(strtoupper((string)$env['os'])) ?> / <?= htmlspecialchars((string)$env['arch']) ?> / PHP <?= htmlspecialchars((string)$env['php']) ?> / <?= strtoupper((string)$env['ts']) === 'TS' ? 'TS' : 'NTS' ?>
                        </div>
                        <?php if (!empty($denyReasons)): ?>
                        <div style="margin-top:6px;font-size:12.5px;line-height:1.7"><?= htmlspecialchars(implode('；', array_keys($denyReasons))) ?></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($loaderAll): ?>
                    <div class="ac-guide-sub"><?= t_admin('adm_ac_env_modal_pkgs', '应用中心已提供以下扩展包（按环境选择）') ?></div>
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;margin-bottom:12px">
                        <thead>
                            <tr style="background:var(--bg-muted,#f6f6f6)">
                                <th style="text-align:left;padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= t_admin('adm_ac_env_th_sys', '系统') ?></th>
                                <th style="text-align:left;padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= t_admin('adm_ac_env_th_arch', '架构') ?></th>
                                <th style="text-align:left;padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= t_admin('adm_ac_env_th_php', 'PHP') ?></th>
                                <th style="text-align:left;padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= t_admin('adm_ac_env_th_ts', '线程安全') ?></th>
                                <th style="text-align:left;padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= t_admin('adm_ac_env_th_file', '文件') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($loaderAll as $_ld):
                            $_file = (string)($_ld['file'] ?? '');
                            $_dl = $_file !== '' ? Security::safeUrl($ac->loaderDownloadUrl($_file)) : '';
                        ?>
                            <tr>
                                <td style="padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= htmlspecialchars((string)($_ld['os'] ?? '')) ?></td>
                                <td style="padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= htmlspecialchars((string)($_ld['arch'] ?? '')) ?></td>
                                <td style="padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= htmlspecialchars((string)($_ld['php'] ?? '')) ?></td>
                                <td style="padding:6px 8px;border:1px solid var(--border,#e5e5e5)"><?= htmlspecialchars(strtoupper((string)($_ld['ts'] ?? ''))) ?></td>
                                <td style="padding:6px 8px;border:1px solid var(--border,#e5e5e5)">
                                    <?php if ($_dl !== ''): ?>
                                        <a href="<?= htmlspecialchars($_dl) ?>" class="btn btn-outline btn-sm"><i class="fas fa-download"></i> <?= t_admin('adm_ac_loader_download', '下载扩展') ?></a>
                                    <?php else: ?><?= htmlspecialchars($_file) ?><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>

                    <details class="ac-scene" open>
                        <summary><i class="fas fa-right-left"></i> <?= t_admin('adm_ac_env_modal_switch', '切换到有扩展包的 PHP 版本（推荐，最省事）') ?></summary>
                        <div class="ac-scene-body">
                            <ol>
                                <li><?= t_admin('adm_ac_env_sw_1', '宝塔（Linux）：软件商店 → PHP → 确认已安装 8.0 / 8.1 / 8.2 / 8.3 中任一版本；网站 → 设置 → PHP 版本，切到该版本并保存，面板会自动重载 php-fpm。') ?></li>
                                <li><?= t_admin('adm_ac_env_sw_2', '宝塔（Windows）：软件商店 → PHP → 安装对应版本（nginx / php-cgi 选 NTS，Apache 模块模式选 TS）；网站设置里切换 PHP 版本。') ?></li>
                                <li><?= t_admin('adm_ac_env_sw_3', '独立环境：修改站点 nginx 配置中的 fastcgi_pass 指向新版本的 socket 或端口，再执行 systemctl restart php-fpm。') ?></li>
                                <li><?= t_admin('adm_ac_env_sw_4', '切换完成后回到应用中心刷新页面，红色提示会变成「下载扩展」；下载后按下方步骤把文件放进扩展目录，并在 php.ini 中启用。') ?></li>
                            </ol>
                            <p style="margin:8px 0 0;font-size:12px;color:var(--text-muted)"><?= t_admin('adm_ac_env_sw_note', '新版本 PHP（如 8.4）暂时没有对应扩展包时不必勉强，切到 8.2 / 8.3 更省事。') ?></p>
                        </div>
                    </details>
                    <?php endif; ?>

                    <?php if ($guideDlUrl): ?>
                    <div class="ac-guide-dl ac-guide-dl-matched">
                        <div>
                            <div class="ac-guide-dl-title">
                                <i class="fas fa-circle-check"></i> <?= t_admin('adm_ac_loader_matched', '已适配当前机器') ?>
                                <span class="ac-guide-dl-badge"><?= t_admin('adm_ac_loader_recommend', '推荐下载') ?></span>
                            </div>
                            <div class="text-muted" style="font-size:12px;margin-top:4px">
                                <?= $envLabel !== '' ? htmlspecialchars($envLabel) : t_admin('adm_ac_loader_matched_desc', '已按本机环境匹配') ?> · <?= htmlspecialchars($guideFileName) ?>
                            </div>
                        </div>
                        <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($guideDlUrl) ?>"><i class="fas fa-download"></i> <?= t_admin('adm_ac_loader_download_btn', '下载') ?></a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-err" style="margin-bottom:12px"><?= t_admin('adm_ac_loader_no_match_file', '当前环境暂未匹配到可下载的扩展文件，请确认 PHP 版本与系统后联系站长。') ?></div>
                    <?php endif; ?>

                    <div class="ac-guide-sub"><?= t_admin('adm_ac_loader_principle', '安装总则') ?></div>
                    <ol class="ac-guide-steps">
                        <?php foreach (($loaderGuide['steps'] ?? []) as $step): ?>
                            <li><?= htmlspecialchars($step) ?></li>
                        <?php endforeach; ?>
                    </ol>

                    <div class="ac-guide-sub"><?= t_admin('adm_ac_loader_scene', '按你的安装方式选择其中一种') ?></div>

                    <details class="ac-scene" <?= $sceneLabel === 'unix' ? 'open' : '' ?>>
                        <summary><i class="fas fa-server"></i> <?= t_admin('adm_ac_loader_bt_linux', '宝塔面板（Linux 服务器）') ?></summary>
                        <div class="ac-scene-body">
                            <ol>
                                <li><?= t_admin('adm_ac_lbt_1') ?></li>
                                <li><?= t_admin('adm_ac_lbt_2') ?></li>
                                <li><?= t_admin('adm_ac_lbt_3') ?></li>
                                <li><?= t_admin('adm_ac_lbt_4') ?></li>
                            </ol>
                        </div>
                    </details>

                    <details class="ac-scene" <?= $sceneLabel === 'windows' ? 'open' : '' ?>>
                        <summary><i class="fas fa-server"></i> <?= t_admin('adm_ac_loader_bt_win', '宝塔面板（Windows 服务器）') ?></summary>
                        <div class="ac-scene-body">
                            <ol>
                                <li><?= t_admin('adm_ac_wbt_1') ?></li>
                                <li><?= t_admin('adm_ac_wbt_2') ?></li>
                                <li><?= t_admin('adm_ac_wbt_3') ?></li>
                                <li><?= t_admin('adm_ac_wbt_4') ?></li>
                            </ol>
                        </div>
                    </details>

                    <details class="ac-scene" <?= $sceneLabel === 'unix' ? 'open' : '' ?>>
                        <summary><i class="fas fa-terminal"></i> <?= t_admin('adm_ac_loader_cli', 'Linux / macOS 命令行（非宝塔）') ?></summary>
                        <div class="ac-scene-body">
                            <ol>
                                <li><?= t_admin('adm_ac_cli_1') ?></li>
                                <li><?= t_admin('adm_ac_cli_2') ?></li>
                                <li><?= t_admin('adm_ac_cli_3') ?></li>
                            </ol>
                        </div>
                    </details>

                    <details class="ac-scene" <?= $sceneLabel === 'windows' ? 'open' : '' ?>>
                        <summary><i class="fas fa-windows"></i> <?= t_admin('adm_ac_loader_win_standalone', 'Windows 独立环境（非宝塔）') ?></summary>
                        <div class="ac-scene-body">
                            <ol>
                                <li><?= t_admin('adm_ac_win_1') ?></li>
                                <li><?= t_admin('adm_ac_win_2') ?></li>
                                <li><?= t_admin('adm_ac_win_3') ?></li>
                            </ol>
                        </div>
                    </details>

                    <?php if ($osTips): ?>
                        <div class="alert alert-warn" style="font-size:12.5px;margin:12px 0 0">
                            <strong><?= t_admin('adm_ac_loader_os_tips', '本机（%s）注意事项', strtoupper($guideOsKey)) ?></strong>
                            <ul style="margin:6px 0 0;padding-left:18px">
                                <?php foreach ($osTips as $tip): ?><li><?= htmlspecialchars($tip) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn btn-outline" onclick="closeAcGuide()"><?= t_admin('adm_ac_loader_close', '关闭') ?></button>
                </div>
            </div>
        </div>
        <script>
            function openAcGuide(){ var m=document.getElementById('acGuideModal'); if(m){ m.classList.add('show'); } }
            function closeAcGuide(){ var m=document.getElementById('acGuideModal'); if(m){ m.classList.remove('show'); } }
            document.addEventListener('click', function(e){ var m=document.getElementById('acGuideModal'); if(m && e.target===m){ closeAcGuide(); } });
        </script>

    <?php endif; ?>
</div>
<?php endif; ?>
