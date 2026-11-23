<?php

class AppCenterService
{

    const MAX_PACKAGE_ENTRIES = 2000;

    const MAX_EXTRACT_BYTES = 200 * 1024 * 1024;

    const DANGER_EXT = [
        'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'pht', 'phps',
        'htaccess', 'htpasswd',
        'so', 'dll', 'exe', 'bat', 'cmd', 'sh', 'cgi', 'pl', 'py', 'rb',
        'asp', 'aspx', 'jsp',
    ];

    private $db;

    private static ?string $activeCenter = null;

    public function __construct($db)
    {
        $this->db = $db;
        $this->migrateSecretToCipher();
        $this->cleanupTemp();
        try {
            $remembered = (string)SettingModel::get('ac_center_active', '');
            if ($remembered !== '' && in_array($remembered, self::centerUrls(), true)) {
                self::$activeCenter = $remembered;
            }
        } catch (\Throwable $e) {
            self::$activeCenter = null;
        }
    }

    public static function centerUrls(): array
    {
        $primary = rtrim(defined('APP_CENTER_URL') ? (string)APP_CENTER_URL : 'https://appcenter.enovelbbs.cn', '/');
        $backup = defined('APP_CENTER_URL_BACKUP') ? rtrim((string)APP_CENTER_URL_BACKUP, '/') : '';
        $list = [];
        foreach ([$primary, $backup] as $url) {
            if ($url !== '' && !in_array($url, $list, true)) {
                $list[] = $url;
            }
        }
        return $list;
    }

    public static function centerUrl(): string
    {
        $urls = self::centerUrls();
        if (self::$activeCenter !== null && in_array(self::$activeCenter, $urls, true)) {
            return self::$activeCenter;
        }
        return $urls[0] ?? '';
    }

    private function rememberCenter(string $url): void
    {
        self::$activeCenter = $url;
        try {
            if ((string)SettingModel::get('ac_center_active', '') !== $url) {
                SettingModel::set('ac_center_active', $url);
            }
        } catch (\Throwable $e) {
        }
    }

    private function centerEndpoint(): array
    {
        $p = parse_url(self::centerUrl());
        return [
            'scheme' => strtolower((string)($p['scheme'] ?? 'http')),
            'host'   => strtolower((string)($p['host'] ?? '')),
            'port'   => (int)($p['port'] ?? 0),
        ];
    }

    public function siteUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . $host;
    }

    public function clientUa(): string
    {
        $phpVer = 'PHP/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        $os = function_exists('php_uname') ? php_uname('s') . ' ' . php_uname('r') : 'Unknown';
        return 'EnovelCms-Client/' . ENOVELCMS_VERSION
             . ' (' . $this->siteUrl() . '; ' . $phpVer . '; ' . $os . ')';
    }

    public function detectEnv(): array
    {
        $os = defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : (function_exists('php_uname') ? php_uname('s') : '');
        $arch = function_exists('php_uname') ? php_uname('m') : '';
        $ts = defined('ZEND_THREAD_SAFE') ? (ZEND_THREAD_SAFE ? 'ts' : 'nts') : 'any';
        $exts = function_exists('get_loaded_extensions') ? get_loaded_extensions() : [];
        return [
            'php'   => PHP_VERSION,
            'os'    => (string)$os,
            'arch'  => strtolower((string)$arch),
            'ts'    => $ts,
            'exts'  => array_values(array_map('strtolower', $exts ?: [])),
        ];
    }

    public function loaderInstalled(): bool
    {
        if (extension_loaded('bolt')) return true;
        if (extension_loaded('phpbolt')) return true;
        $exts = function_exists('get_loaded_extensions') ? get_loaded_extensions() : [];
        foreach ($exts as $ext) {
            if (str_contains(strtolower($ext), 'bolt')) return true;
        }
        return false;
    }

    public function getBind(): ?array
    {
        $bind = $this->db->find('ac_bind', ['id' => 1]);
        if ($bind !== null && array_key_exists('client_secret', $bind)) {

            $bind['client_secret'] = $this->decryptSecret((string)$bind['client_secret']);
        }
        return $bind;
    }

    public function isBound(): bool
    {
        $bind = $this->getBind();
        return $bind && (int)($bind['bound'] ?? 1) === 1
            && $bind['client_token'] !== '' && $bind['client_secret'] !== '';
    }

    private function saveBind(array $data): void
    {
        if (array_key_exists('client_secret', $data)) {

            $data['client_secret'] = $this->encryptSecret((string)$data['client_secret']);
        }
        $existing = $this->db->find('ac_bind', ['id' => 1]);
        if ($existing) {
            $this->db->update('ac_bind', $data, ['id' => 1]);
        } else {
            $data['id'] = 1;
            $this->db->insert('ac_bind', $data);
        }
    }

    public function licenses(): array
    {
        return $this->db->select('ac_license', [], 'id DESC');
    }

    private function syncLicenses(array $list): void
    {
        $this->db->delete('ac_license', []);
        foreach ($list as $l) {
            $this->db->insert('ac_license', [
                'license_key'      => (string)($l['license_key'] ?? ''),
                'center_app_id'    => (int)($l['app_id'] ?? 0),
                'app_key'          => (string)($l['app_key'] ?? ''),
                'app_type'         => (string)($l['app_type'] ?? ''),
                'name'             => (string)($l['name'] ?? ''),
                'version'          => (string)($l['version'] ?? ''),
                'expire_at'        => $l['expire_at'] ?? null,
                'status'           => (int)($l['status'] ?? 1),
                'remote_updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function hasLocalLicense(int $centerAppId): bool
    {
        if ($centerAppId <= 0) return false;
        return $this->db->count('ac_license', ['center_app_id' => $centerAppId, 'status' => 1]) > 0;
    }

    private function sign(array $params, string $secret): string
    {
        unset($params['sign']);

        $scalar = [];
        foreach ($params as $k => $v) {
            if (is_scalar($v)) $scalar[$k] = (string)$v;
        }
        ksort($scalar);
        return hash_hmac('sha256', http_build_query($scalar), $secret);
    }

    private function signedParams(array $params): array
    {
        $bind = $this->getBind();
        $params['site_url'] = $this->siteUrl();
        $params['client_token'] = (string)($bind['client_token'] ?? '');
        $params['timestamp'] = time();
        $params['nonce'] = bin2hex(random_bytes(8));
        $params['sign'] = $this->sign($params, (string)($bind['client_secret'] ?? ''));
        return $params;
    }

    private function sslVerify(): bool
    {
        return defined('AC_VERIFY_SSL') && AC_VERIFY_SSL === true;
    }

    private function secretCipherKey(): string
    {
        $base = defined('SECRET_KEY') ? (string)SECRET_KEY : '';
        return hash('sha256', $base . '|ac-bind-secret-v1', true);
    }

    private function encryptSecret(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') return '';
        if (str_starts_with($plain, 'v1:')) return $plain;
        if (!extension_loaded('openssl') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            return $plain;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->secretCipherKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) return $plain;
        return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
    }

    private function decryptSecret(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') return '';
        if (!str_starts_with($stored, 'v1:')) return $stored;
        if (!extension_loaded('openssl')) return '';
        $parts = explode(':', $stored);
        if (count($parts) !== 4) return '';
        [, $ivB64, $tagB64, $cipherB64] = $parts;
        $iv = base64_decode($ivB64, true);
        $tag = base64_decode($tagB64, true);
        $cipher = base64_decode($cipherB64, true);
        if ($iv === false || $tag === false || $cipher === false || strlen($iv) !== 12) return '';
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->secretCipherKey(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    private function migrateSecretToCipher(): void
    {
        $row = $this->db->find('ac_bind', ['id' => 1], 'id, client_secret');
        if (!$row) return;
        $stored = (string)($row['client_secret'] ?? '');
        if ($stored === '' || str_starts_with($stored, 'v1:')) return;
        $enc = $this->encryptSecret($stored);
        if ($enc !== '' && $enc !== $stored) {
            $this->db->update('ac_bind', ['client_secret' => $enc], ['id' => 1]);
        }
    }

    private function verifySignatureEnabled(): bool
    {
        return !defined('AC_VERIFY_SIGNATURE') || AC_VERIFY_SIGNATURE !== false;
    }

    private function signPublicKey(): string
    {
        return defined('AC_SIGN_PUBLIC_KEY') ? (string)AC_SIGN_PUBLIC_KEY : '';
    }

    private function verifyPackageSignature(string $hash, string $sigHeader): array
    {
        if (!$this->verifySignatureEnabled()) return ['ok' => true, 'error' => ''];
        $pubPem = $this->signPublicKey();
        if ($pubPem === '') return ['ok' => true, 'error' => ''];
        if (!extension_loaded('openssl')) {
            return ['ok' => false, 'error' => t_admin('adm_ac_svc_no_openssl', '当前 PHP 未启用 openssl 扩展，无法校验安装包签名')];
        }
        $sig = base64_decode(trim($sigHeader), true);
        if ($hash === '' || $sig === false || $sig === '') {
            return ['ok' => false, 'error' => t_admin('adm_ac_svc_no_sign', '安装包缺少中心签名，已拒绝安装')];
        }
        $pubkey = openssl_pkey_get_public($pubPem);
        if (!$pubkey) return ['ok' => false, 'error' => t_admin('adm_ac_svc_bad_pubkey', '签名公钥配置无效')];
        $result = openssl_verify($hash, $sig, $pubkey, OPENSSL_ALGO_SHA256);
        if ($result !== 1) {
            return ['ok' => false, 'error' => t_admin('adm_ac_svc_bad_sign', '安装包签名校验失败（包体或签名来源不可信），已拒绝安装')];
        }
        return ['ok' => true, 'error' => ''];
    }

    private function transport(string $endpoint, array $params = [], string $method = 'GET', bool $signed = true, array $timeouts = []): array
    {
        $connectTimeout = (int)($timeouts['connect'] ?? 5);
        $totalTimeout   = (int)($timeouts['total'] ?? 8);
        $sslVerify      = $this->sslVerify();
        $method         = strtoupper($method);

        $params = array_merge($this->detectEnv(), $params);
        if ($signed) {
            $params = $this->signedParams($params);
        }

        $fail = ['body' => false, 'httpCode' => 0, 'headers' => [], 'err' => '', 'offline' => true];
        foreach (self::centerUrls() as $base) {
            $centerHost = strtolower((string)parse_url($base, PHP_URL_HOST));
            $url = $base . '/api/' . ltrim($endpoint, '/');
            $safe = Network::isSafeUrl($url, $centerHost !== '' ? [$centerHost] : []);
            if (!$safe['ok']) {
                $fail['err'] = (string)$safe['error'];
                continue;
            }
            $opts = [
                'method'        => $method,
                'uaType'        => 'client',
                'sslVerify'     => $sslVerify,
                'timeout'       => $totalTimeout,
                'connectTimeout'=> $connectTimeout,
                'allowHosts'    => $centerHost !== '' ? [$centerHost] : [],
            ];
            if ($method === 'POST') {
                $opts['postData'] = $params;
            } else {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }

            $res = Network::request($url, $opts);
            if ((int)$res['http'] > 0) {
                $this->rememberCenter($base);
                $offline = (!$res['ok'] && (int)$res['http'] >= 500);
                return [
                    'body'      => $res['body'],
                    'httpCode'  => (int)$res['http'],
                    'headers'   => $res['headers'],
                    'err'       => $res['error'],
                    'offline'   => $offline,
                ];
            }
            $fail['err'] = (string)$res['error'];
            $fail['headers'] = $res['headers'];
        }
        return $fail;
    }

    private function request(string $endpoint, array $params = [], string $method = 'GET', bool $signed = true, array $timeouts = []): array
    {
        $t = $this->transport($endpoint, $params, $method, $signed, $timeouts);
        if ($t['offline']) {
            return ['ok' => false, 'json' => null, 'headers' => $t['headers'], 'offline' => true, 'error' => t_admin('adm_ac_svc_offline', '应用中心暂时无法连接：%s', $t['err'] ?: 'HTTP ' . $t['httpCode'])];
        }
        $json = json_decode($t['body'], true);
        if (!is_array($json)) {
            return ['ok' => false, 'json' => null, 'headers' => $t['headers'], 'offline' => false, 'error' => t_admin('adm_ac_svc_bad_format', '中心返回数据格式错误')];
        }
        if (isset($json['ok']) && $json['ok'] === false) {
            $unbound = $this->isUnboundResponse($json);
            if ($unbound) {
                $this->resetBindLocal();
            }
            return ['ok' => false, 'json' => $json, 'headers' => $t['headers'], 'offline' => false, 'unbound' => $unbound, 'error' => $unbound ? t_admin('adm_ac_disconnected', '已断开与应用中心的连接，本地授权缓存已清空') : (string)($json['error'] ?? $json['msg'] ?? t_admin('adm_ac_svc_request_fail', '请求失败'))];
        }
        return ['ok' => true, 'json' => $json, 'headers' => $t['headers'], 'offline' => false, 'error' => ''];
    }

    private function isUnboundResponse(array $json): bool
    {
        $code = (string)($json['code'] ?? '');
        $err  = (string)($json['error'] ?? $json['msg'] ?? '');
        return $code === 'CLIENT_UNBOUND' || $err === '客户端凭证无效或已停用' || str_contains($err, '已解除绑定');
    }

    private function resetBindLocal(): void
    {
        $this->db->update('ac_bind', [
            'bound'         => 0,
            'client_token'  => '',
            'client_secret' => '',
        ], ['id' => 1]);
        $this->db->delete('ac_license', []);
    }

    public function identity(): string
    {
        $bind = $this->getBind();
        $id = (string)($bind['client_identity'] ?? '');
        if ($id !== '' && preg_match('/^[a-f0-9]{32}$/', $id)) return $id;
        $seed = $this->siteUrl() . '|' . ROOT_PATH . '|' . time();
        $id = md5($seed);
        $this->saveBind(['client_identity' => $id]);
        return $id;
    }

    public function displaySiteName(): string
    {
        $custom = trim((string)SettingModel::get('ac_site_name', ''));
        if ($custom !== '') return $custom;
        return trim((string)SettingModel::get('site_name', ''));
    }

    public function renameSite(string $name): array
    {
        $name = trim((string)preg_replace('/\s+/u', ' ', $name));
        if ($name === '') return ['ok' => false, 'msg' => t_admin('adm_ac_site_name_empty', '站点名称不能为空')];
        $name = mb_substr($name, 0, 100);
        SettingModel::set('ac_site_name', $name);
        $this->saveBind(['site_name' => $name]);
        if (!$this->isBound()) return ['ok' => true, 'msg' => t_admin('adm_ac_site_name_saved', '站点名称已保存')];
        $resp = $this->request('rename', ['site_name' => $name], 'POST', true);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error']];
        return ['ok' => true, 'msg' => t_admin('adm_ac_site_name_synced', '站点名称已保存并同步到中心')];
    }

    public function bindInit(): array
    {
        $identity = $this->identity();
        $siteUrl = $this->siteUrl();

        $challenge = $this->request('bind_challenge', [
            'client_identity' => $identity,
            'site_url'        => $siteUrl,
        ], 'POST', false);
        if (!$challenge['ok'] && in_array((string)($challenge['json']['code'] ?? ''), ['BAD_IDENTITY', 'TOO_MANY_REQUESTS'], true)) {
            return ['ok' => false, 'msg' => $challenge['error']];
        }
        $challengeValue = (string)($challenge['json']['challenge'] ?? '');
        $verifyWritten = $challengeValue === '' ? true : (@file_put_contents(ROOT_PATH . 'ac_verify.txt', $challengeValue) !== false);

        $resp = $this->request('bind_init', [
            'client_identity' => $identity,
            'site_url'        => $siteUrl,
            'site_name'       => $this->displaySiteName(),
            'cms_version'     => ENOVELCMS_VERSION,
        ], 'POST', false);
        if (!$resp['ok']) {
            if ((string)($resp['json']['code'] ?? '') === 'SITE_NOT_VERIFIED') {
                $msg = (string)$resp['error'];
                if (!$verifyWritten) {
                    $msg = t_admin('adm_ac_svc_verify_unwritable', '站点根目录不可写，无法生成所有权校验文件 ac_verify.txt') . '；' . $msg;
                }
                if ($challengeValue !== '') {
                    $msg .= '。' . t_admin(
                        'adm_ac_svc_verify_hint',
                        '可在域名 %s 下添加 DNS TXT 记录：主机记录 _enovelcms-verify，记录值 %s，保存后重新连接',
                        (string)parse_url($siteUrl, PHP_URL_HOST),
                        $challengeValue
                    );
                }
                return ['ok' => false, 'msg' => $msg];
            }
            return ['ok' => false, 'msg' => $resp['error']];
        }
        return [
            'ok'        => true,
            'bind_token' => (string)($resp['json']['bind_token'] ?? ''),
            'login_url'  => (string)($resp['json']['login_url'] ?? ''),
            'user_code'  => (string)($resp['json']['user_code'] ?? ''),
            'expire_at'  => (string)($resp['json']['expire_at'] ?? ''),
        ];
    }

    public function connectByToken(string $bindToken): array
    {
        $resp = $this->request('handshake', [
            'bind_token'  => $bindToken,
            'client_identity' => $this->identity(),
            'site_url'    => $this->siteUrl(),
            'cms_version' => ENOVELCMS_VERSION,
        ], 'POST', false);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error']];
        $client = $resp['json']['client'] ?? [];
        $user = $resp['json']['user'] ?? [];
        if (empty($client['client_token'])) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_no_credential', '中心未返回有效凭证')];

        $this->saveBind([
            'center_user_id' => (int)($user['id'] ?? 0),
            'identity_id'    => (string)($user['identity_id'] ?? ''),
            'username'       => (string)($user['username'] ?? ''),
            'email'          => (string)($user['email'] ?? ''),
            'client_token'   => (string)$client['client_token'],
            'client_secret'  => (string)$client['client_secret'],
            'client_identity' => (string)($client['identity'] ?? $this->identity()),
            'site_url'       => (string)$client['site_url'],
            'site_name'      => (string)$client['site_name'],
            'cms_version'    => ENOVELCMS_VERSION,
            'ua'             => $this->clientUa(),
            'bound'          => 1,
            'last_active_at' => date('Y-m-d H:i:s'),
        ]);
        $this->syncLicenses($resp['json']['licenses'] ?? []);
        @unlink(ROOT_PATH . 'ac_verify.txt');
        return ['ok' => true, 'msg' => t_admin('adm_ac_svc_connected', '已成功连接应用中心')];
    }

    public function refresh(): array
    {
        $bind = $this->getBind();
        $resp = $this->request('handshake', [
            'center_user_id' => (int)($bind['center_user_id'] ?? 0),
        ], 'GET', true);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error']];
        $user = $resp['json']['user'] ?? [];

        if (!empty($user['id'])) {
            $this->saveBind([
                'center_user_id' => (int)$user['id'],
                'identity_id'    => (string)($user['identity_id'] ?? ''),
                'username'       => (string)($user['username'] ?? ''),
                'email'          => (string)($user['email'] ?? ''),
                'bound'          => 1,
            ]);
        }
        $this->syncLicenses($resp['json']['licenses'] ?? []);
        return ['ok' => true, 'msg' => t_admin('adm_ac_svc_licenses_synced', '授权列表已同步')];
    }

    public function catalog(array $filters): array
    {
        $filters['cms_version'] = ENOVELCMS_VERSION;
        $resp = $this->request('catalog', $filters, 'GET', $this->isBound());
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error'], 'offline' => !empty($resp['offline']), 'list' => [], 'total' => 0];
        return [
            'ok'         => true,
            'offline'    => false,
            'list'       => $resp['json']['list'] ?? [],
            'categories' => $resp['json']['categories'] ?? [],
            'total'      => (int)($resp['json']['total'] ?? 0),
            'page'       => (int)($resp['json']['page'] ?? 1),
            'perPage'    => (int)($resp['json']['perPage'] ?? 20),
            'totalPages' => (int)($resp['json']['totalPages'] ?? 1),
        ];
    }

    public function detail(string $appKey): array
    {
        $resp = $this->request('detail', ['app_id' => $appKey], 'GET', $this->isBound());
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error'], 'offline' => !empty($resp['offline'])];
        return ['ok' => true, 'offline' => false, 'app' => $resp['json']['app'] ?? []];
    }

    public function loaders(): array
    {
        $resp = $this->request('loaders', [], 'GET', true);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error'], 'offline' => !empty($resp['offline']), 'loaders' => []];
        return [
            'ok'       => true,
            'offline'  => false,
            'provider' => (string)($resp['json']['provider'] ?? ''),
            'version'  => (string)($resp['json']['version'] ?? ''),
            'loaders'  => $resp['json']['loaders'] ?? [],
            'guide'    => $resp['json']['guide'] ?? [],
        ];
    }

    public function loaderDownloadUrl(string $file): string
    {
        $params = $this->signedParams(['file' => $file]);
        return self::centerUrl() . '/api/loader_download?' . http_build_query($params);
    }

    public function purchase(string $appKey): array
    {
        if (!$this->isBound()) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_not_bound', '尚未连接应用中心'), 'paid' => false];

        $detail = $this->detail($appKey);
        if (!$detail['ok']) return ['ok' => false, 'msg' => $detail['msg'], 'paid' => false];
        $app = $detail['app'];

        $envCheck = $app['env_check'] ?? null;
        if (is_array($envCheck) && ($envCheck['compatible'] === false)) {
            return [
                'ok'       => false,
                'msg'      => (string)($envCheck['reason'] ?: t_admin('adm_ac_svc_env_incompatible', '当前环境与该应用不兼容，请升级 PHP 或安装必需扩展后再试')),
                'env_check'=> $envCheck,
                'paid'     => false,
            ];
        }

        $check = $this->request('license_check', ['app_id' => $appKey], 'GET', true);
        if ((bool)($check['json']['has_license'] ?? false)) {
            return ['ok' => true, 'msg' => t_admin('adm_ac_svc_already_licensed', '已持有授权'), 'paid' => true, 'order_no' => '', 'pay' => null];
        }

        $purchase = $this->request('purchase', ['app_id' => $appKey], 'POST', true);
        if (!$purchase['ok']) return ['ok' => false, 'msg' => $purchase['error'], 'paid' => false];

        $paid = !empty($purchase['json']['license_id']);
        return [
            'ok'       => true,
            'msg'      => (string)($purchase['json']['msg'] ?? ''),
            'paid'     => $paid,
            'order_no' => (string)($purchase['json']['order_no'] ?? ''),
            'pay'      => $purchase['json']['pay'] ?? null,
        ];
    }

    public function payStart(string $orderNo): array
    {
        if (!$this->isBound()) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_not_bound', '尚未连接应用中心')];
        return $this->request('pay_start', ['order_no' => $orderNo], 'POST', true);
    }

    public function cancelOrder(string $orderNo): array
    {
        if (!$this->isBound()) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_not_bound', '尚未连接应用中心')];
        if ($orderNo === '') return ['ok' => false, 'msg' => t_admin('adm_ac_order_missing', '订单缺失，请重新获取授权')];
        return $this->request('order_cancel', ['order_no' => $orderNo], 'POST', true);
    }

    public function payQrcodeUrl(string $orderNo): string
    {
        $params = $this->signedParams(['order_no' => $orderNo]);
        return self::centerUrl() . '/api/pay_qrcode?' . http_build_query($params);
    }

    public function pendingOrder(): array
    {
        if (!$this->isBound()) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_not_bound', '尚未连接应用中心'), 'order' => null];
        $resp = $this->request('pending_order', [], 'GET', true);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error'], 'order' => null, 'offline' => !empty($resp['offline'])];
        $order = $resp['json']['order'] ?? null;
        return ['ok' => true, 'msg' => '', 'order' => is_array($order) ? $order : null];
    }

    public function orderStatus(string $orderNo): array
    {
        if (!$this->isBound()) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_not_bound', '尚未连接应用中心'), 'status' => 0, 'paid' => false];
        if ($orderNo === '') return ['ok' => false, 'msg' => t_admin('adm_ac_order_missing', '订单缺失，请重新获取授权'), 'status' => 0, 'paid' => false];
        $resp = $this->request('order_status', ['order_no' => $orderNo], 'GET', true);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error'], 'status' => 0, 'paid' => false];
        return [
            'ok'     => true,
            'msg'    => '',
            'status' => (int)($resp['json']['status'] ?? 0),
            'paid'   => !empty($resp['json']['paid']),
        ];
    }

    public function consumeInstallTicket(string $ticket): array
    {
        if (!$this->isBound()) return ['ok' => false, 'msg' => t_admin('adm_ac_svc_not_bound', '尚未连接应用中心')];
        if ($ticket === '') return ['ok' => false, 'msg' => t_admin('adm_ac_svc_ticket_missing', '安装工单缺失')];
        $resp = $this->request('install_ticket', ['ticket' => $ticket], 'POST', true);
        if (!$resp['ok']) return ['ok' => false, 'msg' => $resp['error']];
        $appKey = (string)($resp['json']['app_key'] ?? '');
        if ($appKey === '') return ['ok' => false, 'msg' => t_admin('adm_ac_svc_ticket_no_app', '工单未返回有效应用标识')];
        return ['ok' => true, 'msg' => t_admin('adm_ac_svc_ticket_confirmed', '工单已确认'), 'app_key' => $appKey];
    }

    public function install(string $appKey): array
    {
        $purchase = $this->purchase($appKey);
        if (!$purchase['ok']) return $purchase;
        if (!$purchase['paid']) {
            return [
                'ok'       => false,
                'msg'      => t_admin('adm_ac_svc_pay_first', '付费应用需先完成支付，支付成功后授权自动发放'),
                'order_no' => $purchase['order_no'],
                'pay'      => $purchase['pay'],
            ];
        }
        return $this->downloadAndInstall($appKey);
    }

    private function downloadAndInstall(string $appKey): array
    {
        $detail = $this->detail($appKey);
        if (!$detail['ok']) return ['ok' => false, 'msg' => $detail['msg']];
        $app = $detail['app'];
        $type = $app['type'] === 'theme' ? 'theme' : 'plugin';

        $zipFile = $this->downloadPackage($appKey);
        if (!$zipFile['ok']) return ['ok' => false, 'msg' => $zipFile['error']];

        $expected = strtolower((string)($zipFile['headers']['x-package-hash'] ?? ''));
        $signature = (string)($zipFile['headers']['x-package-signature'] ?? '');
        $signatureRequired = $this->verifySignatureEnabled() && $this->signPublicKey() !== '';
        if ($signatureRequired && $expected === '') {
            @unlink($zipFile['path']);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_no_sign', '安装包缺少中心签名，已拒绝安装')];
        }
        if ($expected !== '') {
            $sigCheck = $this->verifyPackageSignature($expected, $signature);
            if (!$sigCheck['ok']) {
                @unlink($zipFile['path']);
                return ['ok' => false, 'msg' => $sigCheck['error']];
            }
            $actual = hash_file('sha256', $zipFile['path']);
            if (!hash_equals($expected, $actual)) {
                @unlink($zipFile['path']);
                return ['ok' => false, 'msg' => t_admin('adm_ac_svc_integrity_fail', '安装包完整性校验失败（SHA256 不匹配），已拒绝安装')];
            }
        }

        $install = $this->installPackage($zipFile['path'], $type, $appKey);
        @unlink($zipFile['path']);
        if (!$install['ok']) return $install;

        $this->refresh();
        return ['ok' => true, 'msg' => ($type === 'theme'
            ? t_admin('adm_ac_svc_installed_theme', '主题安装成功：%s', $appKey)
            : t_admin('adm_ac_svc_installed_plugin', '插件安装成功：%s', $appKey))];
    }

    private function downloadPackage(string $appKey): array
    {
        $tempDir = ROOT_PATH . 'data/temp';
        if (!is_dir($tempDir)) @mkdir($tempDir, 0755, true);
        $zipFile = $tempDir . '/ac_' . preg_replace('/[^a-z0-9_]/i', '', $appKey) . '_' . uniqid() . '.zip';

        $params = $this->signedParams(array_merge($this->detectEnv(), ['app_id' => $appKey]));
        $url = self::centerUrl() . '/api/download?' . http_build_query($params);

        $centerHost = $this->centerEndpoint()['host'];
        $safe = Network::isSafeUrl($url, $centerHost !== '' ? [$centerHost] : []);
        if (!$safe['ok']) {
            return ['ok' => false, 'error' => $safe['error']];
        }

        $maxBytes = 100 * 1024 * 1024;
        $dl = Network::download($url, $zipFile, [
            'uaType'        => 'client',
            'sslVerify'     => $this->sslVerify(),
            'timeout'       => 300,
            'connectTimeout'=> 15,
            'maxBytes'      => $maxBytes,
            'allowHosts'    => $centerHost !== '' ? [$centerHost] : [],
        ]);
        $headers = $dl['headers'];
        if (!$dl['ok']) {
            @unlink($zipFile);
            return ['ok' => false, 'error' => t_admin('adm_ac_svc_download_fail', '安装包下载失败：%s', $dl['error'])];
        }
        if ((int)@filesize($zipFile) > $maxBytes) {
            @unlink($zipFile);
            return ['ok' => false, 'error' => t_admin('adm_ac_svc_size_exceeded', '安装包体积超出允许上限，已拒绝接收')];
        }

        $check = fopen($zipFile, 'rb');
        $head = $check ? fread($check, 4) : '';
        if ($check) fclose($check);
        if ($head !== "PK\x03\x04" && $head !== "PK\x05\x06" && $head !== "PK\x07\x08") {
            @unlink($zipFile);
            return ['ok' => false, 'error' => t_admin('adm_ac_svc_not_zip', '安装包不是有效的 ZIP 文件（文件头校验失败）')];
        }
        return ['ok' => true, 'path' => $zipFile, 'headers' => $headers];
    }

    private function installPackage(string $zipPath, string $type, string $appKey): array
    {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_no_zip_ext', '当前 PHP 未启用 zip 扩展，无法解压安装包')];
        }
        $idOk = $type === 'theme' ? Theme::validThemeId($appKey) : Security::validPluginId($appKey);
        if (!$idOk) {
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_id_invalid', '应用唯一ID不合法')];
        }

        $extractDir = ROOT_PATH . 'data/temp/ac_install_' . uniqid();
        if (!@mkdir($extractDir, 0755, true)) {
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_temp_dir_fail', '无法创建临时解压目录')];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->removeTree($extractDir);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_open_zip', '无法打开安装包 ZIP')];
        }

        if ($zip->numFiles > self::MAX_PACKAGE_ENTRIES) {
            $zip->close();
            $this->removeTree($extractDir);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_entries_exceeded', '安装包条目数超出允许上限，已拒绝安装')];
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) continue;
            $norm = str_replace('\\', '/', $name);
            if ($norm === '' || strpos($norm, '..') !== false
                || strpos($norm, '/') === 0
                || preg_match('#^[a-zA-Z]:#', $norm)) {
                $zip->close();
                $this->removeTree($extractDir);
                return ['ok' => false, 'msg' => t_admin('adm_ac_svc_bad_path', '安装包含非法路径（目录穿越）：%s', $name)];
            }
            $ext = strtolower((string)pathinfo($norm, PATHINFO_EXTENSION));
            if (in_array($ext, self::DANGER_EXT, true)) {
                $zip->close();
                $this->removeTree($extractDir);
                return ['ok' => false, 'msg' => t_admin('adm_ac_svc_danger_ext', '安装包含不允许的文件类型（.%s），已拒绝安装', $ext)];
            }
            $stat = $zip->statIndex($i);
            $attr = (int)($stat['external_attr'] ?? 0);

            if ((($attr >> 16) & 0xF000) === 0xA000) {
                $zip->close();
                $this->removeTree($extractDir);
                return ['ok' => false, 'msg' => t_admin('adm_ac_svc_symlink', '安装包含符号链接条目，已拒绝安装')];
            }
        }
        $zip->extractTo($extractDir);
        $zip->close();

        $extractedBytes = $this->directorySize($extractDir);
        if ($extractedBytes > self::MAX_EXTRACT_BYTES) {
            $this->removeTree($extractDir);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_extract_size_exceeded', '安装包解压后体积超出允许上限，已拒绝安装')];
        }

        $rootDir = '';
        foreach (scandir($extractDir) as $item) {
            if ($item === '.' || $item === '..') continue;
            if (is_dir($extractDir . '/' . $item)) { $rootDir = $item; break; }
        }
        if ($rootDir !== $appKey) {
            $this->removeTree($extractDir);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_top_mismatch', '压缩包顶层目录名必须与应用唯一ID一致（%s）', $appKey)];
        }

        $source = $extractDir . '/' . $rootDir;
        if ($type === 'theme') {
            $metaFile = $source . '/theme.xml';
            if (!file_exists($metaFile)) {
                $this->removeTree($extractDir);
                return ['ok' => false, 'msg' => t_admin('adm_ac_svc_theme_no_meta', '主题包缺少 theme.xml 元信息文件')];
            }
            $target = ROOT_PATH . 'templates/' . $rootDir;
        } else {
            $metaFile = $source . '/plugin.xml';
            if (!file_exists($metaFile) || !file_exists($source . '/main.php')) {
                $this->removeTree($extractDir);
                return ['ok' => false, 'msg' => t_admin('adm_ac_svc_plugin_no_meta', '插件包缺少 plugin.xml 或 main.php')];
            }
            $target = ROOT_PATH . 'plugins/' . $rootDir;
        }

        if (count(glob($source . '/*', GLOB_NOSORT)) === 0) {
            $this->removeTree($extractDir);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_empty_extract', '安装包解压后为空，已拒绝安装')];
        }

        if (!$this->overwriteInstall($source, $target)) {
            $this->removeTree($extractDir);
            return ['ok' => false, 'msg' => t_admin('adm_ac_svc_move_fail', '移动应用目录失败，旧版本已保留，请检查目录权限')];
        }
        $this->removeTree($extractDir);

        if ($type === 'plugin' && class_exists('Plugin')) {
            Plugin::maybeUpgrade($appKey, $this->db);
        } elseif ($type === 'theme' && class_exists('Theme')) {
            Theme::maybeUpgrade($appKey, $this->db);
        }

        return ['ok' => true, 'msg' => t_admin('adm_ac_svc_installed', '安装完成')];
    }

    private function overwriteInstall(string $source, string $target): bool
    {
        if (!is_dir($target)) {
            if (@rename($source, $target)) return true;
        }
        if (!$this->copyDir($source, $target)) return false;
        $this->pruneRemoved($target, $source);
        return true;
    }

    private function copyDir(string $source, string $target): bool
    {
        if (!@mkdir($target, 0755, true) && !is_dir($target)) return false;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($source));
            $dest = $target . $rel;
            if ($item->isDir()) {
                @mkdir($dest, 0755, true);
            } else {
                if (!@copy($item->getPathname(), $dest)) return false;
            }
        }
        return true;
    }

    private function pruneRemoved(string $target, string $source): void
    {
        if (!is_dir($target)) return;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $rel = substr($item->getPathname(), strlen($target));
            if (!file_exists($source . $rel)) {
                if ($item->isDir()) @rmdir($item->getRealPath());
                else @unlink($item->getRealPath());
            }
        }
    }

    public function localInstalled(): array
    {
        $list = [];
        $targets = ['plugins' => ['type' => 'plugin', 'meta' => 'plugin.xml'], 'templates' => ['type' => 'theme', 'meta' => 'theme.xml']];
        foreach ($targets as $dir => $cfg) {
            $base = ROOT_PATH . $dir;
            if (!is_dir($base)) continue;
            foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $d) {
                $key = basename($d);

                if ($key === '' || str_starts_with($key, '.') || str_contains($key, '.bak_')) continue;
                $info = ['key' => $key, 'type' => $cfg['type'], 'dir' => $d];
                $metaFile = $d . '/' . $cfg['meta'];
                if (file_exists($metaFile)) {
                    $xml = @simplexml_load_file($metaFile);
                    if ($xml !== false) {
                        foreach (array('name', 'description', 'version', 'category', 'gateway', 'cms_adapted', 'type', 'icon') as $xk) {
                            if (isset($xml->$xk)) $info[$xk] = trim((string)$xml->$xk);
                        }
                    }
                }
                $localIcon = trim((string)($info['icon'] ?? ''));
                if ($localIcon === '' && file_exists($d . '/logo.png')) {
                    $localIcon = '/' . $dir . '/' . $key . '/logo.png';
                } elseif ($localIcon !== '' && strpos($localIcon, 'http') !== 0 && strpos($localIcon, '/') !== 0) {
                    $localIcon = '/' . $dir . '/' . $key . '/' . $localIcon;
                }
                $info['icon'] = $localIcon;
                if ($cfg['type'] === 'plugin') {

                    $reg = $this->db->find('plugin_registry', ['plugin_id' => $key]);
                    $info['registered'] = $reg ? true : false;
                    $info['enabled'] = $reg ? (int)$reg['status'] === 1 : false;
                    if ($reg && !empty($reg['version'])) $info['version'] = (string)$reg['version'];
                } else {
                    $reg = $this->db->find('theme_registry', ['theme_id' => $key]);
                    $info['registered'] = $reg ? true : false;
                    $info['enabled'] = $reg ? (int)$reg['status'] === 1 : false;
                    if ($reg && !empty($reg['version'])) $info['version'] = (string)$reg['version'];
                }
                $list[$key] = $info;
            }
        }
        return $list;
    }

    public function mineList(): array
    {
        $installed = $this->localInstalled();
        $local = [];
        foreach ($installed as $key => $info) {
            $local[$key] = [
                'version' => (string)($info['version'] ?? '1.0.0'),
                'type'    => (string)($info['type'] ?? 'plugin'),
            ];
        }
        $updates = $this->checkUpdates($local);

        $licenseByKey = [];
        foreach ($this->licenses() as $lic) {
            $k = (string)($lic['app_key'] ?? '');
            if ($k !== '') $licenseByKey[$k] = $lic;
        }

        $out = [];
        foreach ($installed as $key => $info) {
            $up = $updates[$key] ?? [];
            $out[] = [
                'key'        => $key,
                'name'       => (string)($info['name'] ?? $key),
                'type'       => (string)($info['type'] ?? 'plugin'),
                'version'    => (string)($info['version'] ?? '1.0.0'),
                'latest'     => (string)($up['latest'] ?? ''),
                'has_update' => !empty($up['has_update']),
                'dir'        => (string)($info['dir'] ?? ''),
                'icon'       => (string)($info['icon'] ?? ''),
                'license'    => $licenseByKey[$key] ?? null,
            ];
        }
        usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    const UPDATE_CHECK_TTL = 1800;

    public function checkUpdates(array $local, bool $force = false): array
    {
        $cache = $this->readUpdateCache();
        $fresh = !$force && ($cache['checked_at'] ?? 0) + self::UPDATE_CHECK_TTL > time();
        $apps = $fresh ? ($cache['apps'] ?? []) : null;

        if ($apps === null) {
            $keys = array_keys($local);
            $resp = $this->request('versions_check', ['keys' => implode(',', $keys)], 'GET', $this->isBound());
            if ($resp['ok'] && is_array($resp['json']['apps'] ?? null)) {
                $apps = $resp['json']['apps'];
                $this->writeUpdateCache($apps);
            } else {

                $apps = $cache['apps'] ?? [];
            }
        }

        $out = [];
        foreach ($local as $appKey => $info) {
            $localVer = (string)($info['version'] ?? '');
            $remote = $apps[$appKey] ?? null;
            $latest = is_array($remote) ? (string)($remote['version'] ?? '') : '';
            $hasUpdate = $latest !== '' && $localVer !== '' && version_compare($latest, $localVer, '>');
            $out[$appKey] = [
                'local'      => $localVer,
                'latest'     => $latest,
                'name'       => is_array($remote) ? (string)($remote['name'] ?? '') : '',
                'type'       => is_array($remote) ? (string)($remote['type'] ?? '') : (string)($info['type'] ?? ''),
                'has_update' => $hasUpdate,
            ];
        }
        return $out;
    }

    private function readUpdateCache(): array
    {
        $row = $this->db->find('settings', ['key' => 'ac_update_check']);
        if (!$row || empty($row['value'])) return ['checked_at' => 0, 'apps' => []];
        $data = json_decode((string)$row['value'], true);
        return is_array($data)
            ? ['checked_at' => (int)($data['checked_at'] ?? 0), 'apps' => $data['apps'] ?? []]
            : ['checked_at' => 0, 'apps' => []];
    }

    private function writeUpdateCache(array $apps): void
    {
        $val = json_encode(['checked_at' => time(), 'apps' => $apps]);
        $existing = $this->db->find('settings', ['key' => 'ac_update_check']);
        if ($existing) {
            $this->db->update('settings', ['value' => $val], ['key' => 'ac_update_check']);
        } else {
            $this->db->insert('settings', ['key' => 'ac_update_check', 'value' => $val]);
        }
    }

    private function directorySize(string $dir): int
    {
        if (!is_dir($dir)) return 0;
        $total = 0;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $item) {
                if ($item->isFile()) $total += (int)$item->getSize();
            }
        } catch (\Throwable $e) {
            return 0;
        }
        return $total;
    }

    private function removeTree(string $dir): bool
    {
        if (!is_dir($dir)) return false;
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
            }
        } catch (\Throwable $e) {
            return false;
        }
        return @rmdir($dir);
    }

    private function cleanupTemp(): void
    {
        $tempDir = ROOT_PATH . 'data/temp';
        if (!is_dir($tempDir)) return;
        foreach (glob($tempDir . '/ac_install_*') ?: [] as $d) {
            if (is_dir($d)) $this->removeTree($d);
        }
        foreach (glob($tempDir . '/ac_*.zip') ?: [] as $z) {
            if (is_file($z)) @unlink($z);
        }
    }
}
