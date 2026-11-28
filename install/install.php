<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

$lang = isset($_REQUEST['lang']) ? strtolower(trim((string)$_REQUEST['lang'])) : '';
if (!in_array($lang, array('zh', 'en'), true)) {
    $lang = isset($_SESSION['install_lang']) ? $_SESSION['install_lang'] : 'zh';
}
if (!in_array($lang, array('zh', 'en'), true)) {
    $lang = 'zh';
}

$root = dirname(__DIR__) . '/';

if (file_exists($root . 'data/install.lock')) {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    $adminDir = 'admin';
    $configFile = $root . 'config/config.php';
    if (is_file($configFile)) {
        require_once $configFile;
        if (defined('ADMIN_DIR') && ADMIN_DIR !== '') {
            $adminDir = (string)ADMIN_DIR;
        }
    }
    $baseDir = rtrim(str_replace('\\', '/', dirname(dirname((string)(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/install/install.php')))), '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . (string)(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost') . $baseDir;
    $frontUrl = $base . '/';
    $adminUrl = $base . '/' . $adminDir . '/';
    $isEn = $lang === 'en';
    $txt = $isEn ? array(
        'title'  => 'EnovelCms is already installed',
        'lead'   => 'The installer is locked for security. Your site is running normally — use the entries below.',
        'front'  => 'Front site',
        'admin'  => 'Admin panel',
        'open'   => 'Open',
        'warn_t' => 'Delete the install directory now',
        'warn_b' => 'Leaving install/install.php on the server lets anyone re-run the installer and overwrite your site. Delete the whole install directory; your site keeps working.',
        'note'   => 'Remove data/install.lock only when you really intend to reinstall.',
    ) : array(
        'title'  => 'EnovelCms 已安装完成',
        'lead'   => '安装程序已锁定。你的站点正在正常运行，可以从下面进入。',
        'front'  => '前台首页',
        'admin'  => '后台管理',
        'open'   => '打开',
        'warn_t' => '请立即删除 install 目录',
        'warn_b' => 'install/install.php 留在服务器上，任何人都能重跑安装程序覆盖你的站点。删除整个 install 目录即可，站点不受影响。',
        'note'   => '只有确认要重装时，才需要删除 data/install.lock。',
    );
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    ?><!DOCTYPE html>
<html lang="<?php echo $isEn ? 'en' : 'zh-CN'; ?>">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex,nofollow">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $e($txt['title']); ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;padding:48px 16px;background:#f5f6f7;font:14px/1.7 -apple-system,"Segoe UI","Microsoft YaHei",sans-serif;color:#23262b}
.wrap{max-width:720px;margin:0 auto}
.card{background:#fff;border:1px solid #e2e5e9;border-radius:8px;padding:32px}
.head{text-align:center;padding-bottom:22px;border-bottom:1px solid #f0f2f4}
.dot{width:44px;height:44px;line-height:44px;margin:0 auto 12px;border-radius:50%;background:#eef4ea;color:#3f6b2a;font-size:22px;font-weight:700}
h1{font-size:19px;margin:0 0 6px}
.lead{color:#7a828c;font-size:13px;margin:0}
.entries{display:flex;flex-wrap:wrap;gap:14px;margin:22px 0 24px}
.entry{flex:1 1 260px;border:1px solid #e2e5e9;border-radius:6px;padding:16px}
.label{font-size:13px;font-weight:600;color:#3a4048;margin-bottom:6px}
.url{font-family:ui-monospace,Consolas,monospace;font-size:12.5px;color:#6b7480;word-break:break-all;margin-bottom:12px;min-height:34px}
.btn{display:block;text-align:center;padding:9px 14px;border-radius:4px;text-decoration:none;font-size:13px;font-weight:600;background:#2f3438;color:#fff}
.btn:hover{background:#1f2328}
.warn{background:#fbecea;border-left:3px solid #a62b1f;border-radius:4px;padding:14px 16px}
.warn h3{margin:0 0 6px;font-size:13.5px;color:#a62b1f}
.warn p{margin:0;font-size:12.5px;color:#5d3b36}
.note{color:#8a919b;font-size:12.5px;margin:16px 0 0}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<div class="head">
<div class="dot">✓</div>
<h1><?php echo $e($txt['title']); ?></h1>
<p class="lead"><?php echo $e($txt['lead']); ?></p>
</div>
<div class="entries">
<div class="entry">
<div class="label"><?php echo $e($txt['front']); ?></div>
<div class="url"><?php echo $e($frontUrl); ?></div>
<a class="btn" href="<?php echo $e($frontUrl); ?>"><?php echo $e($txt['open']); ?></a>
</div>
<div class="entry">
<div class="label"><?php echo $e($txt['admin']); ?></div>
<div class="url"><?php echo $e($adminUrl); ?></div>
<a class="btn" href="<?php echo $e($adminUrl); ?>"><?php echo $e($txt['open']); ?></a>
</div>
</div>
<div class="warn">
<h3><?php echo $e($txt['warn_t']); ?></h3>
<p><?php echo $e($txt['warn_b']); ?></p>
</div>
<p class="note"><?php echo $e($txt['note']); ?></p>
</div>
</div>
</body>
</html><?php
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['install_lang'] = $lang;

$LANG = array(
    'title'         => array('zh' => 'EnovelCms 3.0 安装向导', 'en' => 'EnovelCms 3.0 Installer'),
    'subtitle'      => array('zh' => '全新 MVC 架构 · 真实 Smarty 模板引擎 · 主题与插件化设计', 'en' => 'Native MVC · Real Smarty engine · Theme and plugin architecture'),
    'lang_switch'       => array('zh' => '语言', 'en' => 'Language'),
    'step1'             => array('zh' => '使用协议', 'en' => 'License'),
    'step2'             => array('zh' => '环境检测', 'en' => 'Requirements'),
    'step3'             => array('zh' => '伪静态', 'en' => 'Rewrite'),
    'step4'             => array('zh' => '数据库与管理员', 'en' => 'Database'),
    'step5'             => array('zh' => '安装完成', 'en' => 'Finish'),
    'agree_title'       => array('zh' => 'EnovelCms 3.0 使用声明与用户协议', 'en' => 'EnovelCms 3.0 License and Terms of Use'),
    'agree_intro'       => array('zh' => '感谢您选择 EnovelCms 小说内容管理系统（以下简称“本软件”）。在安装或使用本软件前，请仔细阅读以下条款。一旦安装、复制或以其他方式使用本软件，即表示您同意接受本声明约束。', 'en' => 'Thank you for choosing EnovelCms, a novel content management system (the "Software"). Please read the following terms carefully before installing or using the Software. Installing, copying or otherwise using the Software means you accept these terms.'),
    'agree_s1'          => array('zh' => '1. 授权使用', 'en' => '1. License Grant'),
    'agree_b1'          => array('zh' => '您可以在遵守本协议的前提下，在单个网站域名下自由使用本软件，允许基于本程序进行免费商用与二次开发。', 'en' => 'Subject to these terms, you may use the Software on a single website domain, including free commercial use and secondary development based on this program.'),
    'agree_s2'          => array('zh' => '2. 知识产权', 'en' => '2. Intellectual Property'),
    'agree_b2'          => array('zh' => '请保留本软件的版权信息，不得对非开源部分进行反向工程、反编译或试图提取源代码。', 'en' => 'Keep the copyright notices intact. Do not reverse engineer, decompile or attempt to extract source code from any non-open-source part.'),
    'agree_s3'          => array('zh' => '3. 免责声明', 'en' => '3. Disclaimer'),
    'agree_b3'          => array('zh' => '本软件按“现状”提供，作者不对因使用本软件造成的任何直接或间接损失（包括数据丢失、业务中断等）承担责任。请在使用前自行做好数据备份。', 'en' => 'The Software is provided "as is". The author is not liable for any direct or indirect loss caused by using it, including data loss or service interruption. Back up your data before use.'),
    'agree_s4'          => array('zh' => '4. 法律与争议', 'en' => '4. Law and Disputes'),
    'agree_b4'          => array('zh' => '本协议受中华人民共和国法律管辖。作者保留随时更新本协议的权利，更新后继续使用即视为接受修改。若您不同意以上条款，请停止安装并删除相关文件。', 'en' => 'These terms are governed by the laws of the People\'s Republic of China. The author may update them at any time; continued use after an update means you accept the change. If you disagree, stop the installation and delete the files.'),
    'agree_check'       => array('zh' => '我已阅读并同意上述使用声明与用户协议，愿意遵守相关条款', 'en' => 'I have read and agree to the License and Terms of Use above'),
    'btn_agree'         => array('zh' => '我同意，下一步', 'en' => 'I agree, continue'),
    'btn_prev'          => array('zh' => '上一步', 'en' => 'Back'),
    'btn_retest'        => array('zh' => '重新检测并下一步', 'en' => 'Re-check and continue'),
    'btn_verify'        => array('zh' => '验证伪静态规则', 'en' => 'Verify rewrite rules'),
    'btn_reverify'      => array('zh' => '重新验证', 'en' => 'Verify again'),
    'btn_copy'          => array('zh' => '复制规则', 'en' => 'Copy rules'),
    'btn_manual_ok'     => array('zh' => '我已用浏览器确认通过，继续', 'en' => 'I confirmed in a browser, continue'),
    'btn_install'       => array('zh' => '开始安装', 'en' => 'Install now'),
    'env_item'          => array('zh' => '检测项', 'en' => 'Item'),
    'env_required'      => array('zh' => '要求', 'en' => 'Required'),
    'env_current'       => array('zh' => '当前', 'en' => 'Current'),
    'env_result'        => array('zh' => '结果', 'en' => 'Result'),
    'badge_optional'    => array('zh' => '可选', 'en' => 'Optional'),
    'badge_pass'        => array('zh' => '通过', 'en' => 'Pass'),
    'badge_fail'        => array('zh' => '未通过', 'en' => 'Fail'),
    'env_php'           => array('zh' => 'PHP 版本', 'en' => 'PHP version'),
    'env_php_desc'      => array('zh' => 'EnovelCms 3.0 要求 PHP 8.0 及以上（兼容 8.1/8.2/8.3/8.4）', 'en' => 'EnovelCms 3.0 requires PHP 8.0 or newer (8.1/8.2/8.3/8.4 supported)'),
    'env_ext'           => array('zh' => '扩展 %s', 'en' => 'Extension %s'),
    'env_fn'            => array('zh' => '函数 %s', 'en' => 'Function %s'),
    'env_fn_desc'       => array('zh' => '安全随机数与密码哈希所需', 'en' => 'Required for secure randomness and password hashing'),
    'env_sql'           => array('zh' => 'install/install.sql', 'en' => 'install/install.sql'),
    'env_sql_desc'      => array('zh' => '安装所需的数据库结构文件', 'en' => 'Database schema file used by the installer'),
    'req_enabled'       => array('zh' => '已启用', 'en' => 'Enabled'),
    'req_available'     => array('zh' => '可用', 'en' => 'Available'),
    'req_writable'      => array('zh' => '存在且可写', 'en' => 'Exists and writable'),
    'req_file'          => array('zh' => '文件存在', 'en' => 'File exists'),
    'req_suggest'       => array('zh' => '建议启用', 'en' => 'Recommended'),
    'st_enabled'        => array('zh' => '已启用', 'en' => 'Enabled'),
    'st_disabled'       => array('zh' => '未启用', 'en' => 'Disabled'),
    'st_available'      => array('zh' => '可用', 'en' => 'Available'),
    'st_unavailable'    => array('zh' => '不可用', 'en' => 'Unavailable'),
    'st_mkdir_failed'   => array('zh' => '创建失败', 'en' => 'Create failed'),
    'st_auto_created'   => array('zh' => '已自动创建', 'en' => 'Created automatically'),
    'st_no_write'       => array('zh' => '存在但不可写', 'en' => 'Exists, not writable'),
    'st_writable'       => array('zh' => '已存在可写', 'en' => 'Exists and writable'),
    'st_exists'         => array('zh' => '存在', 'en' => 'Exists'),
    'st_missing'        => array('zh' => '缺失', 'en' => 'Missing'),
    'dir_config'        => array('zh' => '配置目录（写入 config.php）', 'en' => 'Config directory (writes config.php)'),
    'dir_data'          => array('zh' => '数据主目录', 'en' => 'Main data directory'),
    'dir_admin'         => array('zh' => '后台登录安全日志', 'en' => 'Admin login security logs'),
    'dir_cache'         => array('zh' => '缓存目录', 'en' => 'Cache directory'),
    'dir_tpl_cache'     => array('zh' => 'Smarty 模板编译目录', 'en' => 'Smarty compile directory'),
    'dir_smarty'        => array('zh' => 'Smarty 缓存目录', 'en' => 'Smarty cache directory'),
    'dir_chapters'      => array('zh' => '章节文本存储', 'en' => 'Chapter text storage'),
    'dir_covers'        => array('zh' => '小说封面存储', 'en' => 'Novel cover storage'),
    'dir_logs'          => array('zh' => '日志目录', 'en' => 'Log directory'),
    'dir_rate'          => array('zh' => '限流计数', 'en' => 'Rate limit counters'),
    'dir_backup'        => array('zh' => '升级备份', 'en' => 'Upgrade backups'),
    'dir_temp'          => array('zh' => '临时文件目录', 'en' => 'Temp files'),
    'dir_uploads'       => array('zh' => '用户上传根目录', 'en' => 'User uploads root'),
    'dir_idcard'        => array('zh' => '身份证上传', 'en' => 'ID card uploads'),
    'dir_runtime'       => array('zh' => '运行目录', 'en' => 'Runtime directory'),
    'ext_pdo'           => array('zh' => 'MySQL 数据库 PDO 驱动', 'en' => 'PDO driver for MySQL'),
    'ext_mbstring'      => array('zh' => '多字节字符串处理（中文字数统计等）', 'en' => 'Multibyte string handling (word counts)'),
    'ext_json'          => array('zh' => 'JSON 数据解析', 'en' => 'JSON parsing'),
    'ext_openssl'       => array('zh' => '加密与安全随机数', 'en' => 'Encryption and secure randomness'),
    'ext_curl'          => array('zh' => '网络请求（支付、在线升级、伪静态自检）', 'en' => 'HTTP requests (payment, upgrade, rewrite check)'),
    'ext_gd'            => array('zh' => '图形处理（登录验证码生成）', 'en' => 'Image processing (login captcha)'),
    'ext_zip'           => array('zh' => 'ZIP 解压（主题/插件包安装）', 'en' => 'ZIP extraction (theme/plugin packages)'),
    'rw_title'          => array('zh' => '伪静态规则', 'en' => 'Rewrite rules'),
    'rw_server'         => array('zh' => '当前服务器：%s', 'en' => 'Detected server: %s'),
    'rw_ok'             => array('zh' => '伪静态验证通过，请求已被正确重写。', 'en' => 'Rewrite verified: the request was rewritten correctly.'),
    'rw_fail'           => array('zh' => '验证未通过，规则还没生效。保存配置并重载服务器后再点一次验证。', 'en' => 'Verification failed: the rule is not active yet. Save the config, reload the server, then verify again.'),
    'rw_fail_unreach'   => array('zh' => '服务器访问不到自己的域名，无法自动验证。确认首页能正常打开后，点下面的按钮继续。', 'en' => 'The server cannot reach its own domain, so it cannot self-verify. Once the homepage opens normally, use the button below to continue.'),
    'rw_root_bad'       => array('zh' => '站点根目录不可写，无法写入验证文件，请检查根目录权限与所有者。', 'en' => 'The site root is not writable, so the probe file cannot be created. Check the root directory permissions and owner.'),
    'rw_type_nginx'     => array('zh' => 'Nginx', 'en' => 'Nginx'),
    'rw_type_apache'    => array('zh' => 'Apache', 'en' => 'Apache'),
    'rw_type_iis'       => array('zh' => 'IIS', 'en' => 'IIS'),
    'rw_type_unknown'   => array('zh' => '未知', 'en' => 'Unknown'),
    'fld_db_host'       => array('zh' => '数据库主机', 'en' => 'Database host'),
    'fld_db_port'       => array('zh' => '数据库端口', 'en' => 'Database port'),
    'fld_db_name'       => array('zh' => '数据库名', 'en' => 'Database name'),
    'fld_db_user'       => array('zh' => '数据库用户名', 'en' => 'Database user'),
    'fld_db_pass'       => array('zh' => '数据库密码', 'en' => 'Database password'),
    'fld_db_prefix'     => array('zh' => '数据表前缀', 'en' => 'Table prefix'),
    'fld_admin_user'    => array('zh' => '管理员用户名', 'en' => 'Admin username'),
    'fld_admin_pass'    => array('zh' => '管理员密码', 'en' => 'Admin password'),
    'fld_admin_pass2'   => array('zh' => '再次输入管理员密码', 'en' => 'Repeat admin password'),
    'ph_db_name'        => array('zh' => 'enovelcms', 'en' => 'enovelcms'),
    'ph_pass_min'       => array('zh' => '至少 6 位', 'en' => 'At least 6 characters'),
    'done_title'        => array('zh' => '安装完成', 'en' => 'Installation complete'),
    'done_text'         => array('zh' => 'EnovelCms 3.0 已安装完成并写入配置。', 'en' => 'EnovelCms 3.0 is installed and configured.'),
    'done_delete_title'=> array('zh' => '立即删除 install 目录', 'en' => 'Delete the install directory now'),
    'done_delete_body' => array('zh' => '整个 install 目录（含 install.php 与 install.sql）必须从服务器删除，否则任何人都能重跑安装程序覆盖你的站点。', 'en' => 'Delete the whole install directory (install.php and install.sql included), otherwise anyone can re-run the installer and overwrite your site.'),
    'done_paths'        => array('zh' => '站点地址', 'en' => 'Site addresses'),
    'done_front'        => array('zh' => '前台', 'en' => 'Front site'),
    'done_admin'        => array('zh' => '后台', 'en' => 'Admin panel'),
    'done_open'         => array('zh' => '打开', 'en' => 'Open'),
    'done_account'      => array('zh' => '管理员账号', 'en' => 'Administrator'),
    'done_next'         => array('zh' => '建议随后到后台修改后台目录名、开启登录通知并核对支付配置。', 'en' => 'Then rename the admin directory in settings, enable login notices and check payment configuration.'),
    'err_env'           => array('zh' => '环境检测未通过，请按提示修复后重新检测', 'en' => 'Requirements check failed. Fix the items above and re-check.'),
    'err_fields'        => array('zh' => '请填写完整的数据库与管理员信息', 'en' => 'Please fill in all database and administrator fields.'),
    'err_pass2'         => array('zh' => '两次输入的管理员密码不一致', 'en' => 'The two administrator passwords do not match.'),
    'err_pass_short'    => array('zh' => '管理员密码至少 6 位', 'en' => 'The administrator password must be at least 6 characters.'),
    'err_admin_name'    => array('zh' => '管理员用户名需 3-20 位，只能是字母、数字、下划线或中文', 'en' => 'Admin username must be 3-20 characters: letters, digits, underscore or Chinese only.'),
    'err_dbname'        => array('zh' => '数据库名只能包含字母、数字、下划线与连字符', 'en' => 'Database name may only contain letters, digits, underscore and hyphen.'),
    'err_sql_missing'   => array('zh' => '安装数据文件缺失：%s', 'en' => 'Install SQL file missing: %s'),
    'err_db'            => array('zh' => '数据库错误：%s', 'en' => 'Database error: %s'),
    'err_rw_first'      => array('zh' => '请先完成伪静态验证', 'en' => 'Complete the rewrite verification first.'),
    'meta'              => array('zh' => 'EnovelCms 3.0.6 · PHP %s', 'en' => 'EnovelCms 3.0.6 · PHP %s'),
    'conflict_title'    => array('zh' => '检测到同前缀的已有数据表', 'en' => 'Existing tables with the same prefix detected'),
    'conflict_body'     => array('zh' => '数据库中已存在使用该前缀的数据表，为避免覆盖原数据，安装已暂停。请选择处理方式：清除数据后重新安装，或返回修改表前缀。', 'en' => 'Tables using this prefix already exist. To protect your data the installation is paused. Either wipe the existing data and reinstall, or go back and change the table prefix.'),
    'conflict_tables'   => array('zh' => '检测到的已有数据表（共 %s 张）', 'en' => 'Existing tables found (%s)'),
    'conflict_wipe'     => array('zh' => '清除数据并重新安装', 'en' => 'Wipe data and reinstall'),
    'conflict_wipe_tip' => array('zh' => '将永久删除下列全部数据表，此操作不可恢复，请确认已备份。', 'en' => 'All tables listed below will be permanently deleted. This cannot be undone; make sure you have a backup.'),
    'conflict_prefix'   => array('zh' => '修改表前缀', 'en' => 'Change table prefix'),
    'conflict_prefix_tip' => array('zh' => '返回表单，更换一个未被占用的表前缀后继续安装。', 'en' => 'Return to the form and use a different, unused table prefix.'),
    'conflict_prefix_notice' => array('zh' => '原前缀已存在数据，请修改表前缀后再安装。', 'en' => 'The previous prefix already has data. Change the table prefix before installing.'),
);

function t($key)
{
    global $LANG, $lang;
    if (isset($LANG[$key][$lang])) {
        return $LANG[$key][$lang];
    }
    return isset($LANG[$key]['zh']) ? $LANG[$key]['zh'] : $key;
}

function tf($key)
{
    $args = func_get_args();
    array_shift($args);
    return vsprintf(t($key), $args);
}

function splitSql($sql)
{
    $statements = array();
    $buffer = '';
    $len = strlen($sql);
    $i = 0;
    $quote = '';
    while ($i < $len) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';
        if ($quote !== '') {
            $buffer .= $ch;
            if ($ch === '\\' && $quote !== '`') {
                if ($i + 1 < $len) { $buffer .= $sql[$i + 1]; $i += 2; continue; }
            }
            if ($ch === $quote) { $quote = ''; }
            $i++;
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buffer .= $ch;
            $i++;
            continue;
        }
        if (($ch === '-' && $next === '-') || $ch === '#') {
            while ($i < $len && $sql[$i] !== "\n" && $sql[$i] !== "\r") { $i++; }
            continue;
        }
        if ($ch === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = ($end === false) ? $len : $end + 2;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') { $statements[] = $stmt; }
            $buffer = '';
            $i++;
            continue;
        }
        $buffer .= $ch;
        $i++;
    }
    $tail = trim($buffer);
    if ($tail !== '') { $statements[] = $tail; }
    return $statements;
}

function phpQuote($value)
{
    return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string)$value) . "'";
}

function existingTables(PDO $pdo, string $prefix): array
{
    if ($prefix === '') return array();
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute(array($prefix . '%'));
    $needle = strtolower($prefix);
    $matched = array();
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (str_starts_with(strtolower($table), $needle)) $matched[] = $table;
    }
    return $matched;
}

function dropTablesByPrefix(PDO $pdo, string $prefix): void
{
    foreach (existingTables($pdo, $prefix) as $table) {
        $safe = str_replace('`', '', $table);
        $pdo->exec('DROP TABLE IF EXISTS `' . $safe . '`');
    }
}

function runEnvironmentCheck($root)
{
    $items = array();
    $passed = true;

    $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
    $items[] = array(
        'type' => 'required', 'label' => t('env_php'),
        'required' => '>= 8.0.0', 'current' => PHP_VERSION,
        'ok' => $phpOk, 'desc' => t('env_php_desc'),
    );
    if (!$phpOk) { $passed = false; }

    $requiredExt = array(
        'pdo_mysql' => t('ext_pdo'),
        'mbstring'  => t('ext_mbstring'),
        'json'      => t('ext_json'),
        'openssl'   => t('ext_openssl'),
        'curl'      => t('ext_curl'),
    );
    foreach ($requiredExt as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $items[] = array(
            'type' => 'required', 'label' => tf('env_ext', $ext),
            'required' => t('req_enabled'), 'current' => $loaded ? t('st_enabled') : t('st_disabled'),
            'ok' => $loaded, 'desc' => $desc,
        );
        if (!$loaded) { $passed = false; }
    }

    $optionalExt = array(
        'gd'  => t('ext_gd'),
        'zip' => t('ext_zip'),
    );
    foreach ($optionalExt as $ext => $desc) {
        $loaded = extension_loaded($ext);
        $items[] = array(
            'type' => 'optional', 'label' => tf('env_ext', $ext),
            'required' => t('req_suggest'), 'current' => $loaded ? t('st_enabled') : t('st_disabled'),
            'ok' => true, 'desc' => $desc,
        );
    }

    $functions = array('random_int', 'password_hash', 'password_verify');
    foreach ($functions as $fn) {
        $exists = function_exists($fn);
        $items[] = array(
            'type' => 'required', 'label' => tf('env_fn', $fn),
            'required' => t('req_available'), 'current' => $exists ? t('st_available') : t('st_unavailable'),
            'ok' => $exists, 'desc' => t('env_fn_desc'),
        );
        if (!$exists) { $passed = false; }
    }

    $knownDesc = array(
        'admin'                => t('dir_admin'),
        'cache'                => t('dir_cache'),
        'cache/templates'      => t('dir_tpl_cache'),
        'cache/smarty'         => t('dir_smarty'),
        'chapters'             => t('dir_chapters'),
        'covers'               => t('dir_covers'),
        'logs'                 => t('dir_logs'),
        'security/rate'        => t('dir_rate'),
        'temp'                 => t('dir_temp'),
        'uploads'              => t('dir_uploads'),
        'uploads/idcards'      => t('dir_idcard'),
    );
    $dirs = array('config' => t('dir_config'), 'data' => t('dir_data'));
    foreach (require $root . 'app/core/runtime_dirs.php' as $sub) {
        $dirs['data/' . $sub] = isset($knownDesc[$sub]) ? $knownDesc[$sub] : t('dir_runtime');
    }
    foreach ($dirs as $dir => $desc) {
        $full = $root . $dir;
        $autoCreated = false;
        if (!is_dir($full)) {
            if (@mkdir($full, 0755, true)) {
                $autoCreated = true;
            }
        }
        $exists = is_dir($full);
        $writable = $exists && is_writable($full);
        if (!$exists) {
            $current = t('st_mkdir_failed');
        } elseif ($autoCreated) {
            $current = t('st_auto_created');
        } elseif (!$writable) {
            $current = t('st_no_write');
        } else {
            $current = t('st_writable');
        }
        $items[] = array(
            'type' => 'required', 'label' => $dir,
            'required' => t('req_writable'), 'current' => $current,
            'ok' => $writable, 'desc' => $desc,
        );
        if (!$writable) { $passed = false; }
    }

    $sqlFile = __DIR__ . '/install.sql';
    $sqlExists = is_file($sqlFile);
    $items[] = array(
        'type' => 'required', 'label' => t('env_sql'),
        'required' => t('req_file'), 'current' => $sqlExists ? t('st_exists') : t('st_missing'),
        'ok' => $sqlExists, 'desc' => t('env_sql_desc'),
    );
    if (!$sqlExists) { $passed = false; }

    return array('items' => $items, 'passed' => $passed);
}

function detectServerType()
{
    $sw = strtolower(isset($_SERVER['SERVER_SOFTWARE']) ? (string)$_SERVER['SERVER_SOFTWARE'] : '');
    if (strpos($sw, 'nginx') !== false) { return 'nginx'; }
    if (strpos($sw, 'iis') !== false || strpos($sw, 'microsoft') !== false) { return 'iis'; }
    if (strpos($sw, 'apache') !== false) { return 'apache'; }
    return 'unknown';
}

require_once $root . 'app/core/rewrite_rules.php';

function rewriteRules($type)
{
    if (!in_array($type, array('nginx', 'apache', 'iis'), true)) {
        return enovel_rewrite_rules('nginx') . "\n\n" . enovel_rewrite_rules('apache') . "\n\n" . enovel_rewrite_rules('iis');
    }
    return enovel_rewrite_rules($type);
}

function siteBase()
{
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : 'localhost';
    $dir = str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '/install/install.php'));
    $base = rtrim(preg_replace('#/install$#', '', $dir), '/');
    return array('scheme' => $scheme, 'host' => $host, 'base' => $base,
        'front' => $scheme . '://' . $host . $base . '/',
        'admin' => $scheme . '://' . $host . $base . '/admin/');
}

function probeFetch($url, $marker = '')
{
    $result = array('ok' => false, 'code' => 0, 'body' => '', 'msg' => '', 'url' => $url);
    if (!function_exists('curl_init')) {
        $result['msg'] = 'curl unavailable';
        return $result;
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_USERAGENT      => 'EnovelCms-Installer/3.0',
    ));
    $body = curl_exec($ch);
    $result['code'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        $result['msg'] = curl_error($ch);
        return $result;
    }
    $result['body'] = (string)$body;
    if ($marker !== '') {
        $result['ok'] = $result['code'] === 200 && strpos($result['body'], $marker) !== false;
    } else {
        $result['ok'] = $result['code'] === 200;
    }
    return $result;
}

function looksSitePage($body)
{
    $looksSite = strpos($body, 'install.php') !== false
        || strpos($body, '/assets/') !== false
        || stripos($body, 'EnovelCms') !== false;
    $looksDefault = preg_match('/Welcome to nginx|Apache2? .*Default Page|The requested URL was not found|IIS Windows Server|<title>404/i', $body) === 1;
    return $looksSite && !$looksDefault;
}

$base = siteBase();

$step = isset($_REQUEST['step']) ? (int)$_REQUEST['step'] : 1;
if ($step < 1 || $step > 5) {
    $step = 1;
}
$error = '';
$rwResult = null;
$conflict = null;
$formValues = null;

if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agree'])) {
    $_SESSION['install_agreed'] = true;
    header('Location: install.php?step=2&lang=' . $lang);
    exit;
}

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $env = runEnvironmentCheck($root);
    if ($env['passed']) {
        $_SESSION['env_passed'] = true;
        header('Location: install.php?step=3&lang=' . $lang);
        exit;
    }
    $error = t('err_env');
}

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['manual_ok'])) {
        $_SESSION['rewrite_ok'] = true;
        header('Location: install.php?step=4&lang=' . $lang);
        exit;
    }
    $token = bin2hex(random_bytes(5));
    $probeFile = $root . '__rw_' . $token . '.php';
    $written = @file_put_contents($probeFile, "<?php echo '__RW_" . $token . "';") !== false;
    if ($written) {
        $phpProbe = probeFetch($base['front'] . '__rw_' . $token . '.php', '__RW_' . $token);
        $rwProbe = probeFetch($base['front'] . '__rw_' . $token . '.html');
        @unlink($probeFile);
        if (looksSitePage($rwProbe['body'])) {
            $_SESSION['rewrite_ok'] = true;
            header('Location: install.php?step=4&lang=' . $lang);
            exit;
        }
        if (!$phpProbe['ok'] || ($rwProbe['code'] === 0 && $rwProbe['msg'] !== '')) {
            $error = t('rw_fail_unreach');
        } else {
            $error = t('rw_fail');
        }
    } else {
        $error = t('rw_root_bad');
    }
}

if ($step === 4 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['rewrite_ok'])) {
        $error = t('err_rw_first');
    } else {
        $dbHost = preg_replace('/[^a-zA-Z0-9_\.\-\:]/', '', (string)(isset($_POST['db_host']) ? $_POST['db_host'] : 'localhost'));
        $dbPort = max(1, min(65535, (int)(isset($_POST['db_port']) ? $_POST['db_port'] : 3306)));
        $dbNameRaw = trim((string)(isset($_POST['db_name']) ? $_POST['db_name'] : ''));
        $dbName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $dbNameRaw);
        $dbUser = preg_replace('/[^a-zA-Z0-9_\.\-\@]/', '', (string)(isset($_POST['db_user']) ? $_POST['db_user'] : 'root'));
        $dbPass = (string)(isset($_POST['db_pass']) ? $_POST['db_pass'] : '');
        $dbPrefix = preg_replace('/[^a-zA-Z0-9_]/', '', (string)(isset($_POST['db_prefix']) ? $_POST['db_prefix'] : 'En_'));
        $adminUser = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fa5}]/u', '', (string)(isset($_POST['admin_user']) ? $_POST['admin_user'] : 'admin'));
        $adminPass = (string)(isset($_POST['admin_pass']) ? $_POST['admin_pass'] : '');
        $adminPass2 = (string)(isset($_POST['admin_pass2']) ? $_POST['admin_pass2'] : '');

        if ($dbHost === '') $dbHost = 'localhost';
        if ($dbPrefix !== '' && !str_ends_with($dbPrefix, '_')) $dbPrefix .= '_';

        if ($dbName === '' || $adminUser === '' || $adminPass === '') {
            $error = t('err_fields');
        } elseif ($dbNameRaw !== $dbName) {
            $error = t('err_dbname');
        } elseif (mb_strlen($adminUser) < 3 || mb_strlen($adminUser) > 20) {
            $error = t('err_admin_name');
        } elseif (strlen($adminPass) < 6) {
            $error = t('err_pass_short');
        } elseif ($adminPass !== $adminPass2) {
            $error = t('err_pass2');
        } else {
            try {
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $pdo = new PDO($dsn, $dbUser, $dbPass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

                $forceWipe = isset($_POST['force_wipe']) && (string)$_POST['force_wipe'] === '1';
                $choosePrefix = isset($_POST['choose_prefix']) && (string)$_POST['choose_prefix'] === '1';
                $existing = existingTables($pdo, $dbPrefix);
                $conflict = ($existing && !$forceWipe && !$choosePrefix) ? array('tables' => $existing) : null;
                $formValues = $choosePrefix ? $_POST : null;
                if ($conflict === null && $formValues === null) {
                    if ($existing && $forceWipe) { dropTablesByPrefix($pdo, $dbPrefix); }

                $sqlFile = __DIR__ . '/install.sql';
                if (!is_file($sqlFile)) {
                    throw new Exception(tf('err_sql_missing', $sqlFile));
                }
                $sql = file_get_contents($sqlFile);
                if ($dbPrefix !== '') {
                    $sql = preg_replace(
                        '/(CREATE TABLE IF NOT EXISTS|INSERT IGNORE INTO) `(\w+)`/',
                        '$1 `' . $dbPrefix . '$2`',
                        $sql
                    );
                }
                foreach (splitSql($sql) as $stmt) {
                    $pdo->exec($stmt);
                }

                $hash = password_hash($adminPass, PASSWORD_BCRYPT);
                $adminsTable = $dbPrefix . 'admins';
                $check = $pdo->prepare("SELECT id FROM `{$adminsTable}` WHERE username = ?");
                $check->execute(array($adminUser));
                $adminId = $check->fetchColumn();
                if ($adminId) {
                    $pdo->prepare("UPDATE `{$adminsTable}` SET password = ? WHERE id = ?")->execute(array($hash, $adminId));
                } else {
                    $pdo->prepare("INSERT INTO `{$adminsTable}` (username, password) VALUES (?, ?)")->execute(array($adminUser, $hash));
                }

                $secretKey = bin2hex(random_bytes(16));
                $acPublicKey = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy7UOWnWv0jlZw9Ai+HzH\ni2Z5IRnRZy+DoMv3zUxE8shMg7GcIffYUyLpBV7V0XP0x3IEsT47sRTMIbQ9PSLa\n+vPfrbtBu+PCTPsa1z6XKWf+mkpqGeayngX/RpOBUVSSlSzjT/ORw5kZQxc66U6s\ni6JHDUwAVaTc7++PQzICC3cTz726pKBo9BmRDNQIAqzEXP7JoEzxJ9MEbnzaXDdt\nmqeqdnbQdMDlh5vnI++WgEXMmqK0F88ekD3lscZeycvHY7QBbrPoVwkfjtySTPx0\nTpzJEENLm1G2PUYKre2jPhhf6MoY4I24qrJrPp8DyGndGsO02i6KOn/ddfhSvPdz\nXQIDAQAB\n-----END PUBLIC KEY-----\n";
                $config = '<?php' . "\n"
                    . "define('PRIVATE_PATH', dirname(__DIR__) . '/data/');\n"
                    . "define('DB_HOST', " . phpQuote($dbHost) . ");\n"
                    . "define('DB_PORT', {$dbPort});\n"
                    . "define('DB_NAME', " . phpQuote($dbName) . ");\n"
                    . "define('DB_USER', " . phpQuote($dbUser) . ");\n"
                    . "define('DB_PASS', " . phpQuote($dbPass) . ");\n"
                    . "define('DB_CHARSET', 'utf8mb4');\n"
                    . "define('DB_PREFIX', " . phpQuote($dbPrefix) . ");\n"
                    . "define('ADMIN_DIR', 'admin');\n"
                    . "define('DEBUG', false);\n"
                    . "define('CACHE_ENABLE', true);\n"
                    . "define('CACHE_DRIVER', 'file');\n"
                    . "define('CACHE_TTL', 300);\n"
                    . "define('SESSION_NAME', 'ENOVEL3');\n"
                    . "define('SESSION_LIFETIME', 7200);\n"
                    . "define('SECRET_KEY', " . phpQuote($secretKey) . ");\n"
                    . "define('ENOVELCMS_VERSION', '3.0.6');\n"
                    . "define('ENOVELCMS_BUILD', '20261122');\n"
                    . "define('APP_CENTER_URL', 'https://appcenter.enovelbbs.cn');\n"
                    . "define('APP_CENTER_URL_BACKUP', 'https://appcenter.enovelcms.cn');\n"
                    . "define('AC_VERIFY_SIGNATURE', true);\n"
                    . "define('AC_SIGN_PUBLIC_KEY', " . phpQuote($acPublicKey) . ");\n"
                    . "define('SITE_LANG', " . phpQuote($lang) . ");\n";
                if (@file_put_contents($root . 'config/config.php', $config, LOCK_EX) === false) {
                    throw new Exception('无法写入 config/config.php，请检查 config 目录权限');
                }

                @file_put_contents($root . 'data/install.lock', date('Y-m-d H:i:s'), LOCK_EX);

                $_SESSION['install_done'] = true;
                $_SESSION['install_admin'] = $adminUser;
                header('Location: install.php?step=5&lang=' . $lang);
                exit;
                }
            } catch (PDOException $e) {
                $error = tf('err_db', $e->getMessage());
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    }
}

if ($step >= 2 && empty($_SESSION['install_agreed'])) {
    header('Location: install.php?step=1&lang=' . $lang);
    exit;
}
if ($step >= 3 && empty($_SESSION['env_passed'])) {
    header('Location: install.php?step=2&lang=' . $lang);
    exit;
}
if ($step >= 4 && $step < 5 && empty($_SESSION['rewrite_ok'])) {
    header('Location: install.php?step=3&lang=' . $lang);
    exit;
}
if ($step === 5 && empty($_SESSION['install_done'])) {
    header('Location: install.php?step=1&lang=' . $lang);
    exit;
}

$env = runEnvironmentCheck($root);
$serverType = detectServerType();
$serverLabel = t('rw_type_' . $serverType);
$rwText = rewriteRules($serverType);
$stepKeys = array(1 => 'step1', 2 => 'step2', 3 => 'step3', 4 => 'step4', 5 => 'step5');
?><!DOCTYPE html>
<html lang="<?php echo $lang === 'en' ? 'en' : 'zh-CN'; ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo t('title'); ?></title>
<style>
* { box-sizing: border-box; }
body { font-family: <?php echo $lang === 'en' ? '"Segoe UI", -apple-system, Roboto, "Helvetica Neue", Arial, sans-serif' : '"Microsoft YaHei", "PingFang SC", sans-serif'; ?>; background: #f0f2f5; margin: 0; padding: 40px 16px; color: #2c3e50; }
.wrap { max-width: 780px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 6px 28px rgba(0,0,0,.08); overflow: hidden; }
.head { padding: 28px 40px 20px; border-bottom: 1px solid #eef0f3; position: relative; }
.head h1 { margin: 0; font-size: 22px; padding-right: 150px; }
.head .sub { color: #8a94a6; font-size: 13px; margin-top: 6px; padding-right: 150px; }
.lang-switch { position: absolute; top: 28px; right: 32px; display: flex; align-items: center; gap: 6px; font-size: 12.5px; }
.lang-switch span { color: #a6adba; }
.lang-switch a { padding: 4px 10px; border-radius: 5px; text-decoration: none; color: #5b6574; border: 1px solid #e3e7ee; }
.lang-switch a.on { background: #1d6fe0; color: #fff; border-color: #1d6fe0; }
.steps { display: flex; padding: 22px 40px; gap: 6px; }
.step { flex: 1; display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: #a6adba; min-width: 0; }
.step .num { width: 24px; height: 24px; border-radius: 50%; background: #eef0f3; color: #8a94a6; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }
.step.active { color: #1d6fe0; font-weight: 700; }
.step.active .num { background: #1d6fe0; color: #fff; }
.step.done { color: #2faa6e; }
.step.done .num { background: #2faa6e; color: #fff; }
.body { padding: 8px 40px 36px; }
table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid #f0f2f5; }
th { color: #8a94a6; font-weight: 600; font-size: 12.5px; }
.badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.badge.ok { background: #e6f7ee; color: #2faa6e; }
.badge.no { background: #fdecec; color: #e5484d; }
.badge.opt { background: #eef4ff; color: #1d6fe0; }
.desc { color: #a6adba; font-size: 12px; }
.agreement { max-height: 320px; overflow-y: auto; border: 1px solid #eef0f3; border-radius: 8px; padding: 18px 22px; font-size: 13.5px; line-height: 1.8; background: #fafbfc; }
.agreement h2 { font-size: 15px; margin: 0 0 14px; }
.agreement h3 { font-size: 13.5px; margin: 16px 0 4px; }
.agreement p { margin: 0; color: #4a5361; }
.agree-row { display: flex; align-items: center; gap: 10px; margin: 20px 0 8px; font-size: 14px; }
label.fld { display: block; margin-top: 14px; font-size: 13px; color: #5b6574; }
input.inp { width: 100%; padding: 11px 13px; border: 1px solid #dce0e6; border-radius: 8px; font-size: 14px; margin-top: 6px; }
input.inp:focus { outline: none; border-color: #1d6fe0; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
.btn { display: inline-block; padding: 12px 28px; background: #1d6fe0; color: #fff; border: none; border-radius: 8px; font-size: 15px; cursor: pointer; text-decoration: none; }
.btn:disabled { background: #b9cdf0; cursor: not-allowed; }
.btn.ghost { background: #eef0f3; color: #5b6574; }
.actions { display: flex; justify-content: space-between; margin-top: 28px; gap: 12px; }
.error { background: #fdecec; border: 1px solid #f6c5c6; color: #c0353a; padding: 11px 14px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; word-break: break-word; }
.ok-box { background: #e6f7ee; border: 1px solid #bfe9d3; color: #1f7a51; padding: 11px 14px; border-radius: 8px; font-size: 13.5px; margin-bottom: 16px; }
.server-tag { display: inline-block; padding: 4px 12px; border-radius: 6px; background: #eef4ff; color: #1d6fe0; font-size: 13px; font-weight: 700; margin-bottom: 12px; }
.rules { width: 100%; min-height: 340px; font-family: Consolas, Monaco, "Courier New", monospace; font-size: 12.5px; line-height: 1.6; padding: 14px; border: 1px solid #dce0e6; border-radius: 8px; background: #fafbfc; color: #2c3e50; resize: vertical; }
.hint { color: #5b6574; font-size: 13px; line-height: 1.7; margin: 12px 0; }
.entry-grid { display: flex; flex-wrap: wrap; gap: 14px; margin: 24px 0 6px; text-align: left; }
.entry-card { flex: 1 1 250px; border: 1px solid #e6eaef; border-radius: 10px; padding: 16px 18px 18px; background: #fbfcfd; }
.entry-label { font-size: 13px; font-weight: 700; color: #37404e; margin-bottom: 8px; }
.entry-label::before { content: ""; display: inline-block; width: 7px; height: 7px; border-radius: 2px; background: #22a06b; margin-right: 8px; vertical-align: 1px; }
.entry-card:nth-child(2) .entry-label::before { background: #333f4d; }
.entry-url { font-family: Consolas, "Courier New", monospace; font-size: 12.5px; color: #6b7585; word-break: break-all; line-height: 1.5; min-height: 38px; margin-bottom: 14px; }
.entry-btn { display: block; text-align: center; padding: 10px 16px; border-radius: 8px; background: #22a06b; color: #fff !important; text-decoration: none; font-size: 13.5px; font-weight: 600; transition: background .15s; }
.entry-btn:hover { background: #1b8a5c; }
.entry-btn.dark { background: #333f4d; }
.entry-btn.dark:hover { background: #25303b; }
.success-box { text-align: center; padding: 18px 0 4px; }
.success-box .tick { width: 78px; height: 78px; border-radius: 50%; background: #e6f7ee; color: #2faa6e; font-size: 42px; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
.warn-box { border: 2px solid #f0b429; background: #fffaf0; border-radius: 10px; padding: 18px 22px; margin: 22px 0 6px; text-align: left; }
.warn-box h3 { margin: 0 0 8px; font-size: 15px; color: #b7791f; }
.warn-box p { margin: 0; font-size: 13.5px; line-height: 1.75; color: #7c5a12; }
.paths { margin: 20px 0 4px; border: 1px solid #eef0f3; border-radius: 10px; overflow: hidden; text-align: left; }
.paths div { display: flex; justify-content: space-between; gap: 16px; padding: 12px 18px; font-size: 13.5px; border-bottom: 1px solid #f5f7fa; }
.paths div:last-child { border-bottom: 0; }
.paths b { color: #8a94a6; font-weight: 600; }
.paths a { color: #1d6fe0; text-decoration: none; word-break: break-all; }
.meta { text-align: center; color: #b6bdc9; font-size: 12px; padding: 16px; }
@media (max-width: 640px) { .grid2 { grid-template-columns: 1fr; } .head h1, .head .sub { padding-right: 0; } .lang-switch { position: static; margin-top: 12px; } .head { padding: 24px 22px 18px; } .steps { padding: 18px 22px; flex-wrap: wrap; } .body { padding: 8px 22px 30px; } }
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1><?php echo t('title'); ?></h1>
        <div class="sub"><?php echo t('subtitle'); ?></div>
        <div class="lang-switch">
            <span><?php echo t('lang_switch'); ?></span>
            <a href="install.php?step=<?php echo $step; ?>&amp;lang=zh" class="<?php echo $lang === 'zh' ? 'on' : ''; ?>">中文</a>
            <a href="install.php?step=<?php echo $step; ?>&amp;lang=en" class="<?php echo $lang === 'en' ? 'on' : ''; ?>">English</a>
        </div>
    </div>
    <div class="steps">
        <?php foreach ($stepKeys as $n => $key): ?>
        <div class="step <?php echo $n == $step ? 'active' : ($n < $step ? 'done' : ''); ?>">
            <span class="num"><?php echo $n < $step ? '✓' : $n; ?></span>
            <span><?php echo t($key); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="body">
    <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
        <div class="agreement">
            <h2><?php echo t('agree_title'); ?></h2>
            <p><?php echo t('agree_intro'); ?></p>
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <h3><?php echo t('agree_s' . $i); ?></h3>
            <p><?php echo t('agree_b' . $i); ?></p>
            <?php endfor; ?>
        </div>
        <form method="post" action="install.php?step=1&amp;lang=<?php echo $lang; ?>">
            <label class="agree-row">
                <input type="checkbox" id="agreeCheck" required onchange="document.getElementById('nextBtn').disabled=!this.checked;">
                <span><?php echo t('agree_check'); ?></span>
            </label>
            <div class="actions">
                <span></span>
                <button type="submit" id="nextBtn" name="agree" value="1" class="btn" disabled><?php echo t('btn_agree'); ?></button>
            </div>
        </form>

    <?php elseif ($step === 2): ?>
        <table>
            <thead><tr>
                <th><?php echo t('env_item'); ?></th>
                <th><?php echo t('env_required'); ?></th>
                <th><?php echo t('env_current'); ?></th>
                <th><?php echo t('env_result'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($env['items'] as $it): ?>
                <tr>
                    <td><?php echo htmlspecialchars($it['label'], ENT_QUOTES, 'UTF-8'); ?>
                        <div class="desc"><?php echo htmlspecialchars($it['desc'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($it['required'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($it['current'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php
                        if ($it['type'] === 'optional') {
                            echo '<span class="badge opt">' . t('badge_optional') . '</span>';
                        } else {
                            echo $it['ok'] ? '<span class="badge ok">' . t('badge_pass') . '</span>' : '<span class="badge no">' . t('badge_fail') . '</span>';
                        }
                    ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" action="install.php?step=2&amp;lang=<?php echo $lang; ?>">
            <div class="actions">
                <a class="btn ghost" href="install.php?step=1&amp;lang=<?php echo $lang; ?>"><?php echo t('btn_prev'); ?></a>
                <button type="submit" class="btn" <?php echo $env['passed'] ? '' : 'disabled'; ?>><?php echo t('btn_retest'); ?></button>
            </div>
        </form>

    <?php elseif ($step === 3): ?>
        <div class="server-tag"><?php echo tf('rw_server', $serverLabel); ?></div>
        <textarea class="rules" id="rwRules" readonly><?php echo htmlspecialchars($rwText, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <div class="actions" style="margin-top:12px;">
            <button type="button" class="btn ghost" onclick="var t=document.getElementById('rwRules');t.select();document.execCommand('copy');this.textContent='<?php echo $lang === 'en' ? 'Copied' : '已复制'; ?>';"><?php echo t('btn_copy'); ?></button>
            <span></span>
        </div>
        <form method="post" action="install.php?step=3&amp;lang=<?php echo $lang; ?>">
            <div class="actions">
                <a class="btn ghost" href="install.php?step=2&amp;lang=<?php echo $lang; ?>"><?php echo t('btn_prev'); ?></a>
                <button type="submit" class="btn"><?php echo t('btn_verify'); ?></button>
            </div>
        </form>
        <?php if ($error): ?>
        <form method="post" action="install.php?step=3&amp;lang=<?php echo $lang; ?>" style="margin-top:16px;">
            <input type="hidden" name="manual_ok" value="1">
            <div class="actions" style="justify-content:flex-end;margin-top:0;">
                <button type="submit" class="btn ghost"><?php echo t('btn_manual_ok'); ?></button>
            </div>
        </form>
        <?php endif; ?>

    <?php elseif ($step === 4): ?>
        <?php if ($conflict): ?>
            <div class="ok-box" style="background:#fff7e6;border:1px solid #f0d9a8;color:#9a6b1f;">
                <h3 style="margin:0 0 8px;"><?php echo t('conflict_title'); ?></h3>
                <p style="margin:0;line-height:1.7;"><?php echo t('conflict_body'); ?></p>
            </div>
            <p style="font-weight:600;margin:18px 0 8px;"><?php echo tf('conflict_tables', count($conflict['tables'])); ?></p>
            <div style="max-height:200px;overflow:auto;border:1px solid #e2e5e9;border-radius:8px;padding:10px 14px;font-family:Consolas,monospace;font-size:12.5px;">
                <?php foreach ($conflict['tables'] as $ct): ?>
                    <div><?php echo htmlspecialchars($ct, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
            <?php
                $hiddenKeys = array('db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'db_prefix', 'admin_user', 'admin_pass', 'admin_pass2');
                $hiddenInputs = function ($extra) use ($hiddenKeys) {
                    $out = '';
                    foreach ($hiddenKeys as $hk) {
                        $val = isset($_POST[$hk]) ? (string)$_POST[$hk] : '';
                        $out .= '<input type="hidden" name="' . $hk . '" value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '">';
                    }
                    foreach ($extra as $ek => $ev) {
                        $out .= '<input type="hidden" name="' . $ek . '" value="' . htmlspecialchars((string)$ev, ENT_QUOTES, 'UTF-8') . '">';
                    }
                    return $out;
                };
            ?>
            <form method="post" action="install.php?step=4&amp;lang=<?php echo $lang; ?>" style="margin-top:18px;">
                <?php echo $hiddenInputs(array('force_wipe' => '1')); ?>
                <p style="color:#c0353a;font-size:12.5px;margin:0 0 10px;"><?php echo t('conflict_wipe_tip'); ?></p>
                <button type="submit" class="btn" style="background:#c0392b;"><?php echo t('conflict_wipe'); ?></button>
            </form>
            <form method="post" action="install.php?step=4&amp;lang=<?php echo $lang; ?>" style="margin-top:14px;">
                <?php echo $hiddenInputs(array('choose_prefix' => '1')); ?>
                <p style="color:#5b6574;font-size:12.5px;margin:0 0 10px;"><?php echo t('conflict_prefix_tip'); ?></p>
                <button type="submit" class="btn ghost"><?php echo t('conflict_prefix'); ?></button>
            </form>
        <?php else: ?>
        <?php if ($formValues): ?>
            <div class="ok-box" style="background:#fff7e6;border:1px solid #f0d9a8;color:#9a6b1f;margin-bottom:16px;"><?php echo t('conflict_prefix_notice'); ?></div>
        <?php endif; ?>
        <?php
            $fv = function ($key, $default = '') use ($formValues) {
                $val = is_array($formValues) && isset($formValues[$key]) ? (string)$formValues[$key] : $default;
                return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
            };
        ?>
        <form method="post" action="install.php?step=4&amp;lang=<?php echo $lang; ?>" onsubmit="if(document.querySelector('[name=admin_pass]').value!==document.querySelector('[name=admin_pass2]').value){alert('<?php echo t('err_pass2'); ?>');return false;}">
            <div class="grid2">
                <label class="fld"><?php echo t('fld_db_host'); ?>
                    <input class="inp" name="db_host" value="<?php echo $fv('db_host', 'localhost'); ?>" required>
                </label>
                <label class="fld"><?php echo t('fld_db_port'); ?>
                    <input class="inp" name="db_port" value="<?php echo $fv('db_port', '3306'); ?>" required>
                </label>
                <label class="fld"><?php echo t('fld_db_name'); ?>
                    <input class="inp" name="db_name" value="<?php echo $fv('db_name'); ?>" placeholder="<?php echo t('ph_db_name'); ?>" required>
                </label>
                <label class="fld"><?php echo t('fld_db_user'); ?>
                    <input class="inp" name="db_user" value="<?php echo $fv('db_user', 'root'); ?>" required>
                </label>
                <label class="fld"><?php echo t('fld_db_pass'); ?>
                    <input class="inp" name="db_pass" type="password" value="<?php echo $fv('db_pass'); ?>">
                </label>
                <label class="fld"><?php echo t('fld_db_prefix'); ?>
                    <input class="inp" name="db_prefix" value="<?php echo $fv('db_prefix', 'En_'); ?>" required>
                </label>
                <label class="fld"><?php echo t('fld_admin_user'); ?>
                    <input class="inp" name="admin_user" value="<?php echo $fv('admin_user', 'admin'); ?>" required minlength="3" maxlength="20">
                </label>
                <label class="fld"><?php echo t('fld_admin_pass'); ?>
                    <input class="inp" name="admin_pass" type="password" value="<?php echo $fv('admin_pass'); ?>" required minlength="6" placeholder="<?php echo t('ph_pass_min'); ?>">
                </label>
                <label class="fld" style="grid-column:1/-1;"><?php echo t('fld_admin_pass2'); ?>
                    <input class="inp" name="admin_pass2" type="password" value="<?php echo $fv('admin_pass2'); ?>" required minlength="6" placeholder="<?php echo t('ph_pass_min'); ?>">
                </label>
            </div>
            <div class="actions">
                <a class="btn ghost" href="install.php?step=3&amp;lang=<?php echo $lang; ?>"><?php echo t('btn_prev'); ?></a>
                <button type="submit" class="btn"><?php echo t('btn_install'); ?></button>
            </div>
        </form>
        <?php endif; ?>

    <?php elseif ($step === 5): ?>
        <div class="success-box">
            <div class="tick">✓</div>
            <h2><?php echo t('done_title'); ?></h2>
            <p style="color:#5b6574;"><?php echo t('done_text'); ?></p>
        </div>
        <div class="entry-grid">
            <div class="entry-card">
                <div class="entry-label"><?php echo t('done_front'); ?></div>
                <div class="entry-url"><?php echo htmlspecialchars($base['front'], ENT_QUOTES, 'UTF-8'); ?></div>
                <a class="entry-btn" href="<?php echo htmlspecialchars($base['front'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo t('done_open'); ?></a>
            </div>
            <div class="entry-card">
                <div class="entry-label"><?php echo t('done_admin'); ?></div>
                <div class="entry-url"><?php echo htmlspecialchars($base['admin'], ENT_QUOTES, 'UTF-8'); ?></div>
                <a class="entry-btn dark" href="<?php echo htmlspecialchars($base['admin'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo t('done_open'); ?></a>
            </div>
        </div>
        <div class="paths">
            <div><b><?php echo t('done_account'); ?></b><span><?php echo htmlspecialchars((string)(isset($_SESSION['install_admin']) ? $_SESSION['install_admin'] : ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <div class="warn-box">
            <h3><?php echo t('done_delete_title'); ?></h3>
            <p><?php echo t('done_delete_body'); ?></p>
        </div>
        <p class="hint"><?php echo t('done_next'); ?></p>
    <?php endif; ?>
    </div>
</div>
<div class="meta"><?php echo tf('meta', PHP_VERSION); ?></div>
</body>
</html>
