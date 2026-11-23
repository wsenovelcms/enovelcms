<?php

define('ADMIN_BOOT', 1);

if (!defined('ADMIN_PROXIED')) {
    $configFile = dirname(__DIR__) . '/config/config.php';
    if (!file_exists($configFile)) { header('Location: ../install/install.php'); exit; }
    require_once $configFile;
    define('ROOT_PATH', dirname(__DIR__) . '/');
    require_once ROOT_PATH . 'app/core/functions.php';
    define('BASE_URL', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    if (!defined('PRIVATE_PATH')) { define('PRIVATE_PATH', ROOT_PATH . 'data/'); }
    if (!defined('APP_CENTER_URL')) { define('APP_CENTER_URL', 'https://appcenter.enovelbbs.cn'); }
    if (!defined('AC_VERIFY_SIGNATURE')) { define('AC_VERIFY_SIGNATURE', true); }
    if (!defined('AC_SIGN_PUBLIC_KEY')) { define('AC_SIGN_PUBLIC_KEY', ''); }

    spl_autoload_register(function ($class) {
        foreach (array('app/core/', 'app/models/', 'app/services/') as $dir) {
            $f = ROOT_PATH . $dir . $class . '.php';
            if (file_exists($f)) { require $f; return; }
        }
    });

    ErrorHandler::register();

    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        session_name(SESSION_NAME);
        session_set_cookie_params(array(
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ));
        session_start();
    }
    Security::sendSecurityHeaders();
    Cache::init();
    $db = new Database();
    Model::setDb($db);
    try {
        $row = $db->query("SELECT `value` FROM " . tb('settings') . " WHERE `key`='debug_mode'")->fetch();
        if ($row && (string)$row['value'] === '1') ErrorHandler::setDebug(true);
    } catch (\Throwable $e) {}
} else {

    $db = new Database();
    Model::setDb($db);
}

$adminDir = admin_dir();
$reqPath  = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$reqSeg   = strtolower(trim(explode('/', $reqPath)[1] ?? ''));
if ($adminDir !== 'admin' && $reqSeg === 'admin') {
    $_SERVER['REQUEST_URI'] = '/__not_found__';
    $_GET = array();
    $_POST = array();
    $_REQUEST = array();
    require ROOT_PATH . 'index.php';
    exit;
}

Extension::setDb($db);
Plugin::setDb($db);
PluginForm::setDb($db);
PluginDB::setDb($db);
PluginConfig::setDb($db);
AdminLanguage::init();
Plugin::init($db);
Theme::setDb($db);
Theme::init($db);
Theme::loadActiveIncludes();
Ui::registerAdmin();
require_once ROOT_PATH . 'admin/includes/helpers.php';

$action = preg_replace('/[^a-z_]/', '', g('action', 'dashboard', 'str'));
$loggedIn = !empty($_SESSION['admin_id']);
$flash = isset($_SESSION['admin_flash']) ? $_SESSION['admin_flash'] : '';
unset($_SESSION['admin_flash']);

$adminEntry = admin_url('');

if ($loggedIn && $action === 'login') {
    header('Location: ' . $adminEntry);
    exit;
}

if ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCsrf()) {
        $_SESSION = array();
        session_destroy();
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
    }
    header('Location: ' . $adminEntry);
    exit;
}

if ($loggedIn && $action === 'lang') {
    AdminLanguage::setLang((string)g('lang', '', 'str'));
    header('Location: ' . $adminEntry);
    exit;
}

if ($loggedIn && $action === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $_SESSION['admin_flash'] = t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试');
    } else {
        $oldPassword = p('old_password', '', 'raw');
        $newPassword = p('new_password', '', 'raw');
        $confirm     = p('confirm_password', '', 'raw');
        if ($oldPassword === '' || $newPassword === '' || $confirm === '') {
            $_SESSION['admin_flash'] = t_admin('adm_pwd_incomplete', '请填写完整的密码信息');
        } elseif (!AdminModel::verifyCurrentPassword((int)$_SESSION['admin_id'], $oldPassword)) {
            $_SESSION['admin_flash'] = t_admin('adm_pwd_wrong_current', '当前密码不正确');
        } elseif (strlen($newPassword) < 6) {
            $_SESSION['admin_flash'] = t_admin('adm_pwd_too_short', '新密码长度至少 6 位');
        } elseif ($newPassword !== $confirm) {
            $_SESSION['admin_flash'] = t_admin('adm_pwd_mismatch', '两次输入的新密码不一致');
        } else {
            AdminModel::changePassword((int)$_SESSION['admin_id'], $newPassword);
            $_SESSION['admin_flash'] = t_admin('adm_pwd_changed', '密码修改成功');
        }
    }
    header('Location: ' . $adminEntry);
    exit;
}

if (!$loggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $throttle = new LoginThrottleService();
    if (!Security::verifyCsrf()) {
        $flash = t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试');
    } elseif ($throttle->isLocked()) {
        $mins = (int)ceil($throttle->secondsUntilUnlock() / 60);
        $flash = t_admin('adm_login_locked', '登录已锁定，请 %d 分钟后再试', $mins);
    } else {
        $username = p('username', '', 'str');
        $password = p('password', '', 'raw');
        $captcha  = p('captcha', '', 'str');
        $captchaReason = Security::verifyCaptchaReason($captcha);
        if ($captchaReason !== null) {
            $throttle->recordAttempt(false);
            $flash = $captchaReason;
        } else {
            $admin = AdminModel::login($username, $password);
            if ($admin) {
                $throttle->recordAttempt(true);
                session_regenerate_id(true);
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['username'];
                $redirectTo = (string)($_POST['redirect'] ?? $_GET['redirect'] ?? '');
                $internal = $redirectTo !== ''
                    && !str_contains($redirectTo, '://')
                    && !preg_match('#^//#', $redirectTo)
                    && !preg_match('#^\\\\#', $redirectTo);
                header('Location: ' . ($internal ? $redirectTo : $adminEntry));
                exit;
            }
            $throttle->recordAttempt(false);
            $flash = t_admin('adm_login_failed', '用户名或密码错误');
        }
    }
}

if (!$loggedIn) { $action = 'login'; }

if ($loggedIn && $action === 'id_card') {
    require ROOT_PATH . 'admin/includes/id_card.php';
}

if ($loggedIn && $action === 'qr_code') {
    require ROOT_PATH . 'admin/includes/qr_code.php';
}

$pluginPages = Hook::applyFilters('admin_plugin_pages', array());
$pageFile = ROOT_PATH . 'admin/pages/' . $action . '.php';

if ($loggedIn && $action === 'plugin') {
    $pid = preg_replace('/[^a-z0-9_]/', '', (string)(g('pid', '', 'str')));
    if ($pid !== '' && !Plugin::isEnabled($pid)) {
        $pluginName = Plugin::all()[$pid]['name'] ?? $pid;
        $pageContent = '<div class="card">'
            . '<div class="card-header"><h2><i class="fas fa-plug"></i> ' . htmlspecialchars($pluginName) . '</h2></div>'
            . '<div class="card-body" style="text-align:center;padding:48px 32px;">'
            . '<div style="font-size:48px;color:#b8860b;margin-bottom:16px;"><i class="fas fa-circle-info"></i></div>'
            . '<h3 style="margin-bottom:12px;">' . t_admin('adm_plugin_disabled', '插件未启用') . '</h3>'
            . '<p style="color:#666;margin-bottom:24px;">' . t_admin('adm_plugin_disabled_tip', '请先在插件列表中启用该插件，再进行设置。') . '</p>'
            . '<a href="' . htmlspecialchars(admin_url('plugins', ['filter' => 'enabled'])) . '" class="btn btn-primary" style="margin-right:8px;"><i class="fas fa-arrow-left"></i> ' . t_admin('adm_back_plugin_list', '返回插件列表') . '</a>'
            . '</div></div>';
        $pageFile = null;
        $pageTitle = $pluginName;
    } else {
        $adminFile = ROOT_PATH . 'plugins/' . $pid . '/admin.php';
        if ($pid !== '' && is_file($adminFile)) {
            $pageFile = $adminFile;
            $pageTitle = Plugin::all()[$pid]['name'] ?? (t_admin('adm_plugin_label_prefix', '插件：') . $pid);
        } elseif ($pid !== '' && PluginConfig::fields($pid)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!Security::verifyCsrf()) {
                    admin_flash_redirect(t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试'), 'plugin', ['pid' => $pid]);
                }
                PluginConfig::save($pid);
                admin_flash_redirect(t_admin('adm_plugin_setting_saved', '插件设置已保存'), 'plugin', ['pid' => $pid]);
            }
            $pageTitle = (Plugin::all()[$pid]['name'] ?? $pid) . t_admin('adm_plugin_setting_suffix', ' 设置');
            $pageFile = '__plugin_config__';
        }
    }
}

if ($loggedIn && $action === 'plugin_page') {
    $pid  = preg_replace('/[^a-z0-9_]/', '', (string)(g('pid', '', 'str')));
    $page = preg_replace('/[^a-z0-9_]/', '', (string)(g('page', '', 'str')));
    if ($pid !== '' && $page !== '') {
        $candidate = ROOT_PATH . 'plugins/' . $pid . '/pages/' . $page . '.php';
        if (is_file($candidate)) {
            $pageFile = $candidate;
            $pageTitle = Plugin::all()[$pid]['name'] ?? $pid;
        }
    }
}

if ($loggedIn && $action === 'theme_admin') {
    $tname = preg_replace('/[^a-z0-9_]/', '', (string)(g('name', '', 'str')));
    if ($tname !== '') {
        ThemeConfig::setCurrent($tname);
        Theme::loadInclude($tname);

        $id   = $tname;
        $path = ROOT_PATH . 'templates/' . $tname . '/';
        $url  = BASE_URL . '/templates/' . $tname . '/';
        $themeAdmin = ROOT_PATH . 'templates/' . $tname . '/admin.php';
        if (is_file($themeAdmin)) {
            $pageFile = $themeAdmin;
            $pageTitle = t_admin('adm_theme_setting_prefix', '主题设置：') . $tname;
        } elseif (ThemeConfig::fields($tname) || Theme::configFields($tname)) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!Security::verifyCsrf()) {
                    admin_flash_redirect(t_admin('adm_csrf_failed', 'CSRF 验证失败，请刷新页面重试'), 'theme_admin', ['name' => $tname]);
                }
                ThemeConfig::save($tname);
                admin_flash_redirect(t_admin('adm_theme_config_saved', '主题配置已保存'), 'theme_admin', ['name' => $tname]);
            }
            $pageTitle = $tname . t_admin('adm_plugin_setting_suffix', ' 设置');
            $pageFile = '__theme_config__';
        }
    }
}

if ($loggedIn && isset($pluginPages[$action]) && is_string($pluginPages[$action]) && file_exists($pluginPages[$action])) {
    $pageFile = $pluginPages[$action];
}
if ($loggedIn && $pageFile === '__plugin_config__') {
    ob_start();
    ?>
<div class="card">
    <a href="<?= htmlspecialchars(admin_url('plugins', ['filter' => 'enabled'])) ?>" class="text-muted" style="font-size:12.5px"><i class="fas fa-arrow-left"></i> <?= t_admin('adm_back_plugin_list', '返回插件列表') ?></a>
    <form method="post" action="<?= htmlspecialchars(admin_url('plugin', ['pid' => $pid])) ?>">
        <?= Security::csrfField() ?>
        <?= PluginConfig::render($pid) ?>
    </form>
</div>
    <?php
    $pageContent = ob_get_clean();
} elseif ($loggedIn && $pageFile === '__theme_config__') {
    ob_start();
    $fields = ThemeConfig::fields($tname);
    if (!$fields) $fields = Theme::configFields($tname);
    ?>
<div class="card">
    <a href="<?= htmlspecialchars(admin_url('themes')) ?>" class="text-muted" style="font-size:12.5px"><i class="fas fa-arrow-left"></i> <?= t_admin('adm_back_theme_list', '返回主题列表') ?></a>
    <h2><i class="fas fa-palette"></i> <?= htmlspecialchars($tname) ?> <?= t_admin('adm_theme_config_title', '配置') ?></h2>
    <form method="post" action="<?= htmlspecialchars(admin_url('theme_admin', ['name' => $tname])) ?>">
        <?= Security::csrfField() ?>
        <?php foreach ($fields as $f):
            $name = $f['name'] ?? '';
            if (!$name) continue;
            $val = Theme::config($name, $f['default'] ?? '', $tname);
            $type = $f['type'] ?? 'text';
        ?>
        <div class="form-group">
            <label><?= htmlspecialchars($f['label'] ?? $name) ?></label>
            <?php if ($type === 'switch'): ?>
                <label class="switch"><input type="checkbox" name="tcfg[<?= htmlspecialchars($name) ?>]" value="1" <?= $val ? 'checked' : '' ?>><span class="slider"></span></label>
            <?php elseif ($type === 'select' && !empty($f['options'])): ?>
                <select class="form-control" name="tcfg[<?= htmlspecialchars($name) ?>]">
                    <?php foreach ($f['options'] as $ov => $ol):
                        $sel = ((string)$ov === (string)$val) ? 'selected' : ''; ?>
                        <option value="<?= htmlspecialchars($ov) ?>" <?= $sel ?>><?= htmlspecialchars($ol) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($type === 'textarea'): ?>
                <textarea class="form-control" name="tcfg[<?= htmlspecialchars($name) ?>]" rows="3"><?= htmlspecialchars((string)$val) ?></textarea>
            <?php else: ?>
                <input class="form-control" type="<?= $type === 'number' ? 'number' : 'text' ?>" name="tcfg[<?= htmlspecialchars($name) ?>]" value="<?= htmlspecialchars((string)$val) ?>">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save', '保存') ?></button>
    </form>
</div>
    <?php
    $pageContent = ob_get_clean();
} elseif ($loggedIn && empty($pageContent) && !file_exists($pageFile)) {
    http_response_code(404);
    $pageContent = '<div class="card"><p class="empty">' . t_admin('adm_page_not_exist', '该管理页面不存在：') . htmlspecialchars($action) . '</p></div>';
} elseif ($loggedIn && empty($pageContent)) {
    $baseLevel = ob_get_level();
    ob_start();
    try {
        require $pageFile;
    } catch (\Throwable $e) {
        while (ob_get_level() > $baseLevel) {
            ob_end_clean();
        }
        $errMsg = $e->getMessage();
        if (str_contains($errMsg, "doesn't exist") || str_contains($errMsg, 'Base table or view not found')) {
            $pluginName = Plugin::all()[$pid]['name'] ?? ($pid ?? t_admin('adm_plugin_unknown', '该插件'));
            $pageContent = '<div class="card">'
                . '<div class="card-header"><h2><i class="fas fa-plug"></i> ' . htmlspecialchars($pluginName) . '</h2></div>'
                . '<div class="card-body" style="text-align:center;padding:48px 32px;">'
                . '<div style="font-size:48px;color:#b8860b;margin-bottom:16px;"><i class="fas fa-circle-info"></i></div>'
                . '<h3 style="margin-bottom:12px;">' . t_admin('adm_plugin_not_installed', '插件尚未安装') . '</h3>'
                . '<p style="color:#666;margin-bottom:24px;">' . t_admin('adm_plugin_not_installed_tip', '数据库表尚未创建，请先启用插件以完成安装。') . '</p>'
                . '<a href="' . htmlspecialchars(admin_url('plugins', ['filter' => 'enabled'])) . '" class="btn btn-primary" style="margin-right:8px;"><i class="fas fa-arrow-left"></i> ' . t_admin('adm_back_plugin_list', '返回插件列表') . '</a>'
                . '</div></div>';
        } else {
            throw $e;
        }
    }
    if (ob_get_level() > $baseLevel) {
        $pageContent = ob_get_clean();
    }
}
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars(AdminLanguage::current()) ?>">
<head>
<meta charset="UTF-8">
<script>window.admLang = <?= json_encode(AdminLanguage::jsStrings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EnovelCms 3.0 <?= t_admin('adm_admin_home', '管理后台') ?> - Powered by EnovelCms</title>
<link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= htmlspecialchars(admin_asset('admin.css')) ?>?v=<?= filemtime(__DIR__ . '/assets/admin.css') ?>">
<?php Hook::doAction('admin_head'); ?>
</head>
<body>
<?php if ($action === 'login'): ?>
<div class="login-wrap">
    <div class="login-card">
        <h1><i class="fas fa-book-open"></i> EnovelCms</h1>
        <p class="sub">3.0 <?= t_admin('adm_admin_home', '管理后台') ?></p>
        <?php if ($flash): ?><div class="alert alert-err"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
        <form method="post" action="<?= htmlspecialchars(admin_url('login')) ?>">
            <?= Security::csrfField() ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars((string)($_SERVER['REQUEST_URI'] ?? '')) ?>">
            <div class="form-group"><input type="text" name="username" placeholder="<?= t_admin('adm_username', '管理员用户名') ?>" required autofocus></div>
            <div class="form-group"><input type="password" name="password" placeholder="<?= t_admin('adm_password', '密码') ?>" required></div>
            <?php if (Security::captchaEnabled()): ?>
            <div class="form-group captcha-group">
                <?= Security::captchaHtml('captcha', t_admin('adm_captcha', '验证码')) ?>
            </div>
            <?php endif; ?>
            <button type="submit" class="btn" style="width:100%;justify-content:center"><i class="fas fa-right-to-bracket"></i> <?= t_admin('adm_login', '登 录') ?></button>
        </form>
        <?php Hook::doAction('admin_login_after_form', $action); ?>
    </div>
    <?php Hook::doAction('admin_login_footer', $action); ?>
</div>
<?php else: ?>
<div class="admin-layout" id="adminLayout">
    <?php require ROOT_PATH . 'admin/includes/sidebar.php'; ?>
    <div class="main-wrap">
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="icon-btn" id="sidebarToggle" title="<?= t_admin('adm_toggle_sidebar', '折叠 / 展开菜单') ?>" aria-label="toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
        <h1 class="page-title"><?= htmlspecialchars(Hook::applyFilters('admin_page_title', $pageTitle ?? t_admin('adm_admin_home', '管理后台'), $action)) ?></h1>
            </div>
            <div class="topbar-right">
                <a class="version-badge" id="versionBadge" href="<?= htmlspecialchars(admin_url('upgrade')) ?>" data-check-url="<?= htmlspecialchars(admin_url('upgrade', ['do' => 'latest'])) ?>" title="<?= t_admin('adm_version_tip', '当前版本，点击检查更新') ?>">
                    <i class="fas fa-code-branch"></i> v<?= htmlspecialchars(ENOVELCMS_VERSION) ?>
                </a>
                <select class="admin-lang-select" onchange="if(this.value) location.href=this.value" aria-label="<?= t_admin('adm_language', '语言') ?>">
                    <?php foreach (AdminLanguage::getAll() as $langCode => $langName): ?>
                    <option value="<?= htmlspecialchars(admin_url('lang', ['lang' => $langCode])) ?>" <?= AdminLanguage::current() === $langCode ? 'selected' : '' ?>><?= htmlspecialchars($langName) ?></option>
                    <?php endforeach; ?>
                </select>
                <a class="topbar-link" href="<?= htmlspecialchars(admin_url('dashboard')) ?>"><i class="fas fa-gauge"></i> <?= t_admin('adm_dashboard', '后台首页') ?></a>
                <a class="topbar-link" href="<?= htmlspecialchars(BASE_URL) ?>/" target="_blank"><i class="fas fa-arrow-up-right-from-square"></i> <?= t_admin('adm_visit_frontend', '访问前台') ?></a>
                <a class="topbar-link" href="<?= htmlspecialchars(UpgradeService::OFFICIAL_API) ?>" target="_blank"><i class="fas fa-globe"></i> <?= t_admin('adm_official_site', '官网') ?></a>
                <div class="user-menu" id="userMenu">
                    <?php $adminName = $_SESSION['admin_name'] ?? 'admin'; $initial = strtoupper(mb_substr($adminName, 0, 1, 'UTF-8')); ?>
                    <button type="button" class="user-trigger" id="userTrigger">
                        <span class="avatar"><?= htmlspecialchars($initial) ?></span>
                        <span class="user-name"><?= htmlspecialchars($adminName) ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown">
                        <div class="dropdown-head">
                            <span class="avatar"><?= htmlspecialchars($initial) ?></span>
                            <div>
                                <div class="d-name"><?= htmlspecialchars($adminName) ?></div>
                                <div class="d-role"><?= t_admin('adm_super_admin', '超级管理员') ?></div>
                            </div>
                        </div>
                        <a href="<?= htmlspecialchars(admin_url('settings')) ?>"><i class="fas fa-gear"></i> <?= t_admin('adm_settings', '系统设置') ?></a>
                        <button type="button" class="dropdown-item-btn" id="openPwdModal"><i class="fas fa-key"></i> <?= t_admin('adm_change_password', '修改密码') ?></button>
                        <form method="post" action="<?= htmlspecialchars(admin_url('logout')) ?>" style="margin:0;padding:0;background:none;border:none;">
                            <?= Security::csrfField() ?>
                            <button type="submit" class="dropdown-item-btn"><i class="fas fa-right-from-bracket"></i> <?= t_admin('adm_logout', '退出登录') ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <div class="content">
            <?php Hook::doAction('admin_content_top', $action); ?>
            <?php if ($flash): ?>
            <div class="modal-mask" id="flashModal" style="display:flex;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:9999;align-items:center;justify-content:center;">
                <div style="background:#fff;border-radius:10px;width:380px;max-width:90vw;padding:26px 24px 20px;box-shadow:0 12px 40px rgba(0,0,0,.25);">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <i class="fas fa-check-circle" style="color:#10b981;font-size:22px;"></i>
                        <h3 style="margin:0;font-size:16px;color:#1e293b;"><?= t_admin('adm_flash_tip', '提示') ?></h3>
                    </div>
                    <p style="margin:0 0 18px;font-size:14px;color:#475569;line-height:1.6;"><?= htmlspecialchars($flash) ?></p>
                    <button type="button" onclick="document.getElementById('flashModal').remove();location.reload();" style="width:100%;padding:9px 0;background:#2563eb;color:#fff;border:0;border-radius:6px;font-size:14px;cursor:pointer;"><?= t_admin('adm_confirm', '确 定') ?></button>
                </div>
            </div>
            <?php endif; ?>
            <?= $pageContent ?>
            <?php Hook::doAction('admin_content_bottom', $action); ?>
        </div>
    </div>
</div>

<div class="modal-mask" id="pwdModal">
    <div class="modal">
        <div class="modal-head">
            <h3><i class="fas fa-key"></i> <?= t_admin('adm_change_password', '修改密码') ?></h3>
            <button type="button" class="modal-close" id="pwdModalClose" aria-label="close">&times;</button>
        </div>
        <form method="post" action="<?= htmlspecialchars(admin_url('password')) ?>">
            <?= Security::csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label><?= t_admin('adm_old_password', '当前密码') ?></label>
                    <input type="password" name="old_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_new_password', '新密码（至少 6 位）') ?></label>
                    <input type="password" name="new_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_confirm_password', '确认新密码') ?></label>
                    <input type="password" name="confirm_password" class="form-control" required minlength="6" autocomplete="new-password">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-outline" id="pwdCancel"><?= t_admin('adm_cancel', '取消') ?></button>
                <button type="submit" class="btn"><i class="fas fa-check"></i> <?= t_admin('adm_pwd_submit', '确认修改') ?></button>
            </div>
        </form>
    </div>
</div>
<script src="<?= htmlspecialchars(admin_asset('admin.js')) ?>?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>"></script>
<?php Hook::doAction('admin_footer'); ?>
<?php endif; ?>
</body>
</html>
