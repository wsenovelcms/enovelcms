<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_settings', '系统设置');

$logoDir = '/assets/uploads/logo/';
$defaultLogo = '/assets/uploads/logo/default_logo.png';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
    if (Security::verifyCsrf()) {
        $img = Security::verifyImageUpload($_FILES['site_logo']);
        if ($img) {
            $targetDir = ROOT_PATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'logo';
            if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
            $fname = 'logo_' . time() . '.' . $img['ext'];
            if (move_uploaded_file($_FILES['site_logo']['tmp_name'], $targetDir . DIRECTORY_SEPARATOR . $fname)) {
                $old = SettingModel::get('site_logo', '');
                if ($old && strpos($old, $logoDir) === 0) {
                    $oldPath = ROOT_PATH . ltrim($old, '/\\');
                    if (is_file($oldPath)) { @unlink($oldPath); }
                }
                SettingModel::set('site_logo', $logoDir . $fname);
            }
        }
        admin_flash_redirect(t_admin('adm_set_logo_updated', 'Logo 已更新'), 'settings');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('delete_site_logo', null, 'raw') !== null) {
    if (Security::verifyCsrf()) {
        $old = SettingModel::get('site_logo', '');
        if ($old && strpos($old, $logoDir) === 0) {
            $oldPath = ROOT_PATH . ltrim($old, '/\\');
            if (is_file($oldPath)) { @unlink($oldPath); }
            $row = SettingModel::find(array('key' => 'site_logo'));
            if ($row) { SettingModel::delete(array('key' => 'site_logo')); }
        }
        admin_flash_redirect(t_admin('adm_set_logo_deleted', 'Logo 已删除'), 'settings');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && p('test_mail', null, 'raw') !== null) {
    if (!Security::verifyCsrf()) {
        admin_flash_redirect(t_admin('adm_csrf_failed_short', 'CSRF验证失败'), 'settings');
    } else {
        SettingModel::set('smtp_host', trim((string)(p('smtp_host', '', 'str'))));
        SettingModel::set('smtp_port', (string)(int)($_POST['smtp_port'] ?? 25));
        SettingModel::set('smtp_secure', (string)(p('smtp_secure', '', 'str')));
        SettingModel::set('smtp_user', trim((string)(p('smtp_user', '', 'str'))));
        SettingModel::set('smtp_pass', (string)(p('smtp_pass', '', 'str')));
        SettingModel::set('smtp_from', trim((string)(p('smtp_from', '', 'str'))));
        SettingModel::set('smtp_fromname', trim((string)(p('smtp_fromname', '', 'str'))));
        $testTo = trim((string)(p('test_email', '', 'str')));
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            admin_flash_redirect(t_admin('adm_set_mail_bad', '测试邮箱格式不正确，请检查后重试'), 'settings');
        } else {
            $mailer = new MailService();
            $r = $mailer->send($testTo, t_admin('adm_set_mail_title', 'EnovelCms 测试邮件'),
                t_admin('adm_set_mail_body', '<p>这是一封来自 <strong>%s</strong> 的测试邮件。</p><p>收到此邮件说明 SMTP 配置已生效。</p>', htmlspecialchars(SettingModel::get('site_name', 'EnovelCms'))));
            admin_flash_redirect($r['ok'] ? t_admin('adm_set_mail_sent', '测试邮件已发送至 %s', $testTo) : t_admin('adm_set_mail_fail', '测试邮件发送失败，请检查 SMTP 服务器/账号/端口/加密方式'), 'settings');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !p('delete_site_logo', null, 'raw') !== null && empty($_FILES['site_logo']['name'])) {
    if (Security::verifyCsrf()) {
        $textKeys = array(
            'site_name', 'site_keywords', 'site_description', 'register_mode',
            'point_name', 'currency_name', 'currency_symbol', 'analytics_code',
            'smtp_host', 'smtp_user', 'smtp_pass',
            'smtp_from', 'smtp_fromname', 'smtp_secure', 'signed_deny_msg',
            'sg_blacklist_ip', 'sg_blacklist_ua',
        );
        $numKeys = array(
            'allow_guest_read', 'library_per_page', 'sign_base_gold', 'sign_continue_percent',
            'sign_min_continue_bonus', 'sign_max_continue_bonus', 'vip_price_per_month',
            'vip_gold_per_month', 'vip_default_price_per_300', 'withdraw_fee_rate',
            'vip_min_withdraw_amount', 'recharge_exchange_rate', 'smtp_port', 'debug_mode',
            'enable_hsts', 'vip_preview_words',
            'captcha_enabled', 'captcha_length', 'captcha_case_sensitive',
            'signed_allow_edit_info', 'signed_allow_manage_volume', 'signed_allow_manage_chapter',
            'sg_enabled', 'sg_check_blacklist', 'sg_block_bad_ua',
            'sg_rate_limit', 'sg_rate_window', 'sg_rate_max',
            'sg_rate_block', 'sg_wechat_block', 'sg_log_enabled', 'sg_log_days',
        );
        $boolKeys = array(
            'allow_guest_read', 'debug_mode', 'enable_hsts', 'captcha_enabled',
            'captcha_case_sensitive',
            'signed_allow_edit_info', 'signed_allow_manage_volume', 'signed_allow_manage_chapter',
            'sg_enabled', 'sg_check_blacklist', 'sg_block_bad_ua',
            'sg_rate_limit', 'sg_wechat_block', 'sg_log_enabled',
        );
        foreach ($textKeys as $k) {
            if (array_key_exists($k, $_POST)) {
                SettingModel::set($k, p($k, '', 'text'));
            }
        }
        foreach ($numKeys as $k) {
            if (array_key_exists($k, $_POST)) {
                $v = p($k, 0, 'float');
                if ($k === 'sign_continue_percent' || $k === 'withdraw_fee_rate') { $v = min(100, max(0, $v)); }
                if ($k === 'sg_rate_window') { $v = min(3600, max(10, $v)); }
                if ($k === 'sg_rate_max')     { $v = min(10000, max(1, $v)); }
                if ($k === 'sg_rate_block')   { $v = min(3600, max(0, $v)); }
                if ($k === 'sg_log_days')     { $v = min(3650, max(0, $v)); }
                if (in_array($k, $boolKeys, true)) { $v = $v ? 1 : 0; }
                SettingModel::set($k, (string)$v);
            } elseif (in_array($k, array('allow_guest_read', 'debug_mode', 'enable_hsts', 'captcha_enabled', 'captcha_case_sensitive', 'sg_enabled', 'sg_check_blacklist', 'sg_block_bad_ua', 'sg_rate_limit', 'sg_wechat_block', 'sg_log_enabled'), true)) {
                SettingModel::set($k, '0');
            }
        }
        if (array_key_exists('captcha_charset', $_POST)) {
            $cs = p('captcha_charset', '', 'text');
            if (!in_array($cs, array('digits','letters','alnum'), true)) $cs = 'alnum';
            SettingModel::set('captcha_charset', $cs);
        }
        $smtpFrom = trim((string)SettingModel::get('smtp_from', ''));
        if ($smtpFrom !== '' && !filter_var($smtpFrom, FILTER_VALIDATE_EMAIL)) {
            SettingModel::set('smtp_from', '');
        }
        $smtpFromName = str_replace(["\r", "\n"], '', (string)SettingModel::get('smtp_fromname', ''));
        SettingModel::set('smtp_fromname', trim($smtpFromName));

        if (array_key_exists('admin_dir', $_POST)) {
            $adminDir = trim((string)p('admin_dir', '', 'str'));
            $reserved = ['admin', 'install', 'api', 'pay', 'user', 'plugin', 'lang',
                'search', 'library', 'rank', 'novel', 'read', 'author', 'authors',
                'assets', 'templates', 'data', 'config', 'app'];
            if ($adminDir !== '' && (!preg_match('/^[A-Za-z0-9_-]{3,32}$/', $adminDir) || in_array(strtolower($adminDir), $reserved, true))) {
                $adminDir = '';
            }
            SettingModel::set('admin_dir', $adminDir);
            SettingModel::set('admin_entry', '');
        }

        SettingModel::clearCache();
        admin_dir(true);
        Cache::flush();
        admin_flash_redirect(t_admin('adm_set_saved', '系统设置已保存，缓存已更新'), 'settings');
    }
}

$g = function ($k, $d = '') {
    return SettingModel::get($k, $d);
};
$adminDirCurrent = admin_dir();
$adminDirValue = $adminDirCurrent === 'admin' ? '' : $adminDirCurrent;
$currentLogo = $g('site_logo', '');
$isCustom = $currentLogo && strpos($currentLogo, $logoDir) === 0;
$displayLogo = $isCustom ? $currentLogo : $defaultLogo;
?>
<form method="post" enctype="multipart/form-data">
    <?= Security::csrfField() ?>

    <div class="card">
        <div class="tabs">
            <button type="button" class="tab-btn" data-tab="basic"><i class="fas fa-sliders-h"></i> <?= t_admin('adm_set_tab_basic', '基本设置') ?></button>
            <button type="button" class="tab-btn" data-tab="signin"><i class="fas fa-calendar-check"></i> <?= t_admin('adm_set_tab_signin', '积分签到') ?></button>
            <button type="button" class="tab-btn" data-tab="vip"><i class="fas fa-crown"></i> <?= t_admin('adm_set_tab_vip', 'VIP 与章节') ?></button>
            <button type="button" class="tab-btn" data-tab="pay"><i class="fas fa-coins"></i> <?= t_admin('adm_set_tab_pay', '充值提现') ?></button>
            <button type="button" class="tab-btn" data-tab="smtp"><i class="fas fa-envelope"></i> <?= t_admin('adm_set_tab_smtp', '邮件 SMTP') ?></button>
            <button type="button" class="tab-btn" data-tab="system"><i class="fas fa-shield-halved"></i> <?= t_admin('adm_set_tab_system', '系统安全') ?></button>
            <button type="button" class="tab-btn" data-tab="signed"><i class="fas fa-lock"></i> <?= t_admin('adm_set_tab_signed', '签约作品权限') ?></button>
        </div>

        <div class="tab-pane" data-pane="basic">
            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_set_site_name', '网站名称') ?></label><input type="text" name="site_name" class="form-control" value="<?= htmlspecialchars($g('site_name')) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_point_name', '积分名称') ?></label><input type="text" name="point_name" class="form-control" value="<?= htmlspecialchars($g('point_name', t_admin('adm_set_point_name_default', '金币'))) ?>"></div>
            </div>
            <div class="form-group"><label><?= t_admin('adm_set_keywords', '网站关键词') ?></label><input type="text" name="site_keywords" class="form-control" value="<?= htmlspecialchars($g('site_keywords')) ?>" placeholder="<?= t_admin('adm_set_keywords_ph', '多个用英文逗号分隔') ?>"></div>
            <div class="form-group"><label><?= t_admin('adm_set_description', '网站描述') ?></label><textarea name="site_description" class="form-control"><?= htmlspecialchars($g('site_description')) ?></textarea></div>
            <div class="form-row-2">
                <div class="form-group">
                    <label><?= t_admin('adm_set_register_mode', '注册登录模式') ?></label>
                    <select name="register_mode" class="form-control">
                        <option value="normal" <?= $g('register_mode', 'normal') === 'normal' ? 'selected' : '' ?>><?= t_admin('adm_set_register_normal', '普通模式（用户名密码）') ?></option>
                        <option value="email" <?= $g('register_mode') === 'email' ? 'selected' : '' ?>><?= t_admin('adm_set_register_email', '邮箱模式（需邮箱验证）') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_guest_read', '游客阅读') ?></label>
                    <select name="allow_guest_read" class="form-control">
                        <option value="1" <?= (string)$g('allow_guest_read', '1') === '1' ? 'selected' : '' ?>><?= t_admin('adm_set_guest_full', '允许游客阅读免费章节全文') ?></option>
                        <option value="0" <?= (string)$g('allow_guest_read') === '0' ? 'selected' : '' ?>><?= t_admin('adm_set_guest_preview', '仅显示前300字预览') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label><?= t_admin('adm_set_library_per_page', '书库每页数量') ?></label><input type="number" name="library_per_page" class="form-control input-medium" min="1" max="100" value="<?= htmlspecialchars($g('library_per_page', 20)) ?>"></div>
            <div class="form-group"><label><?= t_admin('adm_set_analytics', '统计代码') ?></label><textarea name="analytics_code" class="form-control" placeholder="<?= t_admin('adm_set_analytics_ph', '百度统计/CNZZ/GA 等 HTML/JS 代码') ?>"><?= htmlspecialchars($g('analytics_code')) ?></textarea></div>
            <div class="form-group">
                <label><?= t_admin('adm_set_logo', '网站 Logo') ?></label>
                <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
                    <img src="<?= htmlspecialchars($displayLogo) ?>" alt="Logo" style="max-height:56px;max-width:220px;background:#f8fafc;border:1px dashed var(--border);padding:8px;border-radius:8px">
                    <input type="file" name="site_logo" accept="image/*">
                    <?php if ($isCustom): ?>
                    <button type="submit" name="delete_site_logo" value="1" class="btn btn-outline btn-sm" data-confirm="<?= t_admin('adm_set_logo_delete_confirm', '确认删除 Logo？') ?>"><i class="fas fa-trash"></i> <?= t_admin('adm_delete', '删除') ?></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane" data-pane="signin">
            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_set_sign_base', '基础签到积分') ?></label><input type="number" name="sign_base_gold" class="form-control" value="<?= htmlspecialchars($g('sign_base_gold', 10)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_sign_percent', '连续奖励百分比(%)') ?></label><input type="number" name="sign_continue_percent" class="form-control" value="<?= htmlspecialchars($g('sign_continue_percent', 5)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_sign_min', '连续奖励最小') ?></label><input type="number" name="sign_min_continue_bonus" class="form-control" value="<?= htmlspecialchars($g('sign_min_continue_bonus', 1)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_sign_max', '连续奖励最大') ?></label><input type="number" name="sign_max_continue_bonus" class="form-control" value="<?= htmlspecialchars($g('sign_max_continue_bonus', 100)) ?>"></div>
            </div>
        </div>

        <div class="tab-pane" data-pane="vip">
            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_set_vip_price', 'VIP 人民币价格（元/月）') ?></label><input type="number" step="0.01" name="vip_price_per_month" class="form-control" value="<?= htmlspecialchars($g('vip_price_per_month', 30)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_vip_gold', 'VIP 金币价格（金币/月）') ?></label><input type="number" name="vip_gold_per_month" class="form-control" value="<?= htmlspecialchars($g('vip_gold_per_month', 3000)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_vip_price_300', '每300字默认价（书币）') ?></label><input type="number" name="vip_default_price_per_300" class="form-control" value="<?= htmlspecialchars($g('vip_default_price_per_300', 1)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_vip_preview_words', 'VIP章节免费预览字数') ?></label><input type="number" name="vip_preview_words" class="form-control" value="<?= htmlspecialchars($g('vip_preview_words', 1000)) ?>"></div>
            </div>
        </div>

        <div class="tab-pane" data-pane="pay">
            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_set_recharge_rate', '充值汇率（1元 = ? 积分）') ?></label><input type="number" name="recharge_exchange_rate" class="form-control" min="1" value="<?= htmlspecialchars($g('recharge_exchange_rate', 10)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_withdraw_fee', '提现手续费(%)') ?></label><input type="number" step="0.1" name="withdraw_fee_rate" class="form-control" value="<?= htmlspecialchars($g('withdraw_fee_rate', 5)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_withdraw_min', '最低提现金额（书币）') ?></label><input type="number" name="vip_min_withdraw_amount" class="form-control" value="<?= htmlspecialchars($g('vip_min_withdraw_amount', 10)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_currency_name', '币种名称') ?></label><input type="text" name="currency_name" class="form-control" value="<?= htmlspecialchars($g('currency_name', '元')) ?>" placeholder="元"></div>
                <div class="form-group"><label><?= t_admin('adm_set_currency_symbol', '币种符号') ?></label><input type="text" name="currency_symbol" class="form-control" value="<?= htmlspecialchars($g('currency_symbol', '¥')) ?>" placeholder="¥"></div>
            </div>
        </div>

        <div class="tab-pane" data-pane="smtp">
            <div class="form-row-2">
                <div class="form-group"><label><?= t_admin('adm_set_smtp_host', 'SMTP 服务器') ?></label><input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($g('smtp_host')) ?>" placeholder="smtp.qq.com"></div>
                <div class="form-group"><label><?= t_admin('adm_set_smtp_port', 'SMTP 端口') ?></label><input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($g('smtp_port', 25)) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_smtp_user', 'SMTP 账号') ?></label><input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($g('smtp_user')) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_smtp_pass', 'SMTP 密码/授权码') ?></label><input type="password" name="smtp_pass" class="form-control" value="<?= htmlspecialchars($g('smtp_pass')) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_smtp_from', '发件邮箱') ?></label><input type="email" name="smtp_from" class="form-control" value="<?= htmlspecialchars($g('smtp_from')) ?>"></div>
                <div class="form-group"><label><?= t_admin('adm_set_smtp_fromname', '发件人名称') ?></label><input type="text" name="smtp_fromname" class="form-control" value="<?= htmlspecialchars($g('smtp_fromname', 'EnovelCms')) ?>"></div>
            </div>
            <div class="form-group" style="max-width:560px">
                <label><?= t_admin('adm_set_test_mail', '发送测试邮件') ?></label>
                <div style="display:flex;gap:8px">
                    <input type="email" name="test_email" class="form-control" placeholder="<?= t_admin('adm_set_test_mail_ph', '输入接收测试的邮箱') ?>" style="max-width:320px">
                    <button type="submit" name="test_mail" value="1" class="btn btn-outline" style="white-space:nowrap"><i class="fas fa-paper-plane"></i> <?= t_admin('adm_set_test_mail_btn', '发送测试') ?></button>
                </div>
            </div>
        </div>

        <div class="tab-pane" data-pane="system">
            <div class="form-group">
                <label><?= t_admin('adm_set_admin_entry', '后台目录名') ?></label>
                <input type="text" name="admin_dir" class="form-control" value="<?= htmlspecialchars($adminDirValue) ?>" placeholder="<?= t_admin('adm_set_admin_entry_ph', '留空则使用默认 admin；填写后后台入口为 域名/目录名/') ?>" autocomplete="off">
                <span class="help-text"><?= t_admin('adm_set_admin_entry_tip', '仅字母、数字、短横线、下划线，3-32 位；修改后需在伪静态配置中同步目录映射。') ?></span>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">
            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="debug_mode" value="1" <?= (string)$g('debug_mode') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_debug', '开启调试模式') ?>
                </label>
            </div>
            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="enable_hsts" value="1" <?= (string)$g('enable_hsts') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_hsts', '强制 HTTPS（HSTS）') ?>
                </label>
                <span class="help-text"><?= t_admin('adm_set_hsts_tip', '仅 HTTPS 站点开启，HTTP 站点勿开。') ?></span>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">
            <h3 style="margin:0 0 14px;font-size:15px;font-weight:600"><i class="fas fa-shield-halved"></i> <?= t_admin('adm_set_captcha_title', '图形验证码') ?></h3>
            <p style="margin:0 0 12px;font-size:12px;color:var(--text-muted);line-height:1.7"><?= t_admin('adm_set_captcha_route_hint', '验证码图片经由伪静态路由 /api/captcha 输出，站点未配置伪静态（或反向代理未转发该路径）时图片无法显示，请保持关闭。') ?></p>

            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="captcha_enabled" value="1" <?= (string)$g('captcha_enabled', '0') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_captcha_enable', '启用图形验证码') ?>
                </label>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label><?= t_admin('adm_set_captcha_length', '验证码长度') ?></label>
                    <input type="number" name="captcha_length" class="form-control input-medium" min="3" max="8" value="<?= htmlspecialchars($g('captcha_length', 5)) ?>">
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_captcha_charset', '字符组合') ?></label>
                    <select name="captcha_charset" class="form-control">
                        <option value="alnum"  <?= $g('captcha_charset','alnum') === 'alnum' ? 'selected' : '' ?>><?= t_admin('adm_set_captcha_alnum', '字母 + 数字（推荐）') ?></option>
                        <option value="digits" <?= $g('captcha_charset') === 'digits' ? 'selected' : '' ?>><?= t_admin('adm_set_captcha_digits', '纯数字') ?></option>
                        <option value="letters"<?= $g('captcha_charset') === 'letters' ? 'selected' : '' ?>><?= t_admin('adm_set_captcha_letters', '纯字母') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="captcha_case_sensitive" value="1" <?= (string)$g('captcha_case_sensitive', '0') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_captcha_case', '区分大小写') ?>
                </label>
            </div>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">
            <h3 style="margin:0 0 6px;font-size:15px;font-weight:600"><i class="fas fa-shield-halved"></i> <?= t_admin('adm_set_guard_title', '访问防护（系统级）') ?></h3>

            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="sg_enabled" value="1" <?= (string)$g('sg_enabled', '0') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_guard_enable', '启用访问防护（总开关）') ?>
                </label>
            </div>

            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="sg_check_blacklist" value="1" <?= (string)$g('sg_check_blacklist', '1') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_guard_blacklist', '启用 IP / UA 黑名单') ?>
                </label>
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_set_guard_ip_blacklist', 'IP 黑名单（每行一个，支持网段前缀）') ?></label>
                <textarea name="sg_blacklist_ip" class="form-control" rows="3" placeholder="123.45.67.89&#10;192.168.1."><?= htmlspecialchars($g('sg_blacklist_ip', '')) ?></textarea>
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_set_guard_ua_blacklist', 'UA 黑名单关键词（每行一个，忽略大小写）') ?></label>
                <textarea name="sg_blacklist_ua" class="form-control" rows="3" placeholder="BadBot&#10;scan-tool"><?= htmlspecialchars($g('sg_blacklist_ua', '')) ?></textarea>
            </div>

            <hr style="border:none;border-top:1px dashed var(--border);margin:18px 0">
            <h3 style="margin:0 0 12px;font-size:14px;font-weight:600"><?= t_admin('adm_set_guard_bad_ua_title', '异常请求特征') ?></h3>
            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="sg_block_bad_ua" value="1" <?= (string)$g('sg_block_bad_ua', '1') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_guard_block_scanner', '拦截扫描器 / 非浏览器客户端') ?>
                </label>
            </div>

            <hr style="border:none;border-top:1px dashed var(--border);margin:18px 0">
            <h3 style="margin:0 0 12px;font-size:14px;font-weight:600"><?= t_admin('adm_set_guard_rate_title', '频率限制（防高频刷请求）') ?></h3>
            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="sg_rate_limit" value="1" <?= (string)$g('sg_rate_limit', '1') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_guard_rate_enable', '按 IP 启用频率限制') ?>
                </label>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label><?= t_admin('adm_set_guard_rate_window', '统计窗口（秒）') ?></label>
                    <input type="number" name="sg_rate_window" class="form-control input-medium" min="10" max="3600" value="<?= htmlspecialchars($g('sg_rate_window', '60')) ?>">
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_guard_rate_max', '窗口内最大请求数') ?></label>
                    <input type="number" name="sg_rate_max" class="form-control input-medium" min="1" max="10000" value="<?= htmlspecialchars($g('sg_rate_max', '60')) ?>">
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_guard_rate_block', '超限封锁时长（秒，0=按窗口）') ?></label>
                    <input type="number" name="sg_rate_block" class="form-control input-medium" min="0" max="3600" value="<?= htmlspecialchars($g('sg_rate_block', '300')) ?>">
                </div>
            </div>

            <hr style="border:none;border-top:1px dashed var(--border);margin:18px 0">
            <h3 style="margin:0 0 12px;font-size:14px;font-weight:600"><?= t_admin('adm_set_guard_browser_title', '内置浏览器与日志') ?></h3>
            <div class="form-group">
                <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                    <input type="checkbox" name="sg_wechat_block" value="1" <?= (string)$g('sg_wechat_block', '0') === '1' ? 'checked' : '' ?>>
                    <?= t_admin('adm_set_guard_wechat', '微信 / QQ 内置浏览器引导') ?>
                </label>
            </div>
            <div class="form-row-2">
                <div class="form-group">
                    <label style="display:inline-flex;gap:8px;align-items:center;font-weight:400;cursor:pointer">
                        <input type="checkbox" name="sg_log_enabled" value="1" <?= (string)$g('sg_log_enabled', '1') === '1' ? 'checked' : '' ?>>
                        <?= t_admin('adm_set_guard_log', '记录拦截日志') ?>
                    </label>
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_guard_log_days', '日志保留天数（0=不自动清理）') ?></label>
                    <input type="number" name="sg_log_days" class="form-control input-medium" min="0" max="3650" value="<?= htmlspecialchars($g('sg_log_days', '30')) ?>">
                </div>
            </div>
        </div>

        <div class="tab-pane" data-pane="signed">
            <div class="form-row-2">
                <div class="form-group">
                    <label><?= t_admin('adm_set_signed_edit_info', '编辑作品信息') ?></label>
                    <select name="signed_allow_edit_info" class="form-control">
                        <option value="0" <?= (string)$g('signed_allow_edit_info', '0') === '0' ? 'selected' : '' ?>><?= t_admin('adm_set_signed_forbid_info', '禁止（书名、状态、简介等锁定）') ?></option>
                        <option value="1" <?= (string)$g('signed_allow_edit_info') === '1' ? 'selected' : '' ?>><?= t_admin('adm_set_signed_allow', '允许') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_signed_manage_volume', '管理分卷') ?></label>
                    <select name="signed_allow_manage_volume" class="form-control">
                        <option value="0" <?= (string)$g('signed_allow_manage_volume', '0') === '0' ? 'selected' : '' ?>><?= t_admin('adm_set_signed_forbid_volume', '禁止（无法新建、编辑分卷）') ?></option>
                        <option value="1" <?= (string)$g('signed_allow_manage_volume') === '1' ? 'selected' : '' ?>><?= t_admin('adm_set_signed_allow', '允许') ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= t_admin('adm_set_signed_manage_chapter', '管理章节') ?></label>
                    <select name="signed_allow_manage_chapter" class="form-control">
                        <option value="0" <?= (string)$g('signed_allow_manage_chapter', '0') === '0' ? 'selected' : '' ?>><?= t_admin('adm_set_signed_forbid_chapter', '禁止（无法新建、编辑、删除章节）') ?></option>
                        <option value="1" <?= (string)$g('signed_allow_manage_chapter') === '1' ? 'selected' : '' ?>><?= t_admin('adm_set_signed_allow', '允许') ?></option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label><?= t_admin('adm_set_signed_deny_msg', '拒绝提示词') ?></label>
                <textarea name="signed_deny_msg" class="form-control" placeholder="<?= t_admin('adm_set_signed_deny_ph', '操作被拦截时向作者展示的提示文案') ?>"><?= htmlspecialchars($g('signed_deny_msg', t_admin('adm_set_signed_deny_default', '该作品已签约，相关内容已锁定，如需调整请联系网站管理员'))) ?></textarea>
            </div>
        </div>

        <div style="border-top:1px solid var(--border);padding-top:18px">
            <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_set_save_all', '保存所有设置') ?></button>
        </div>
    </div>
    <?php Hook::doAction('admin_settings_after'); ?>
</form>
