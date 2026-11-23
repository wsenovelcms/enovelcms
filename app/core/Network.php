<?php

class Network
{

    private const UA_POOL = [
        'Mozilla/5.0 (compatible; Baiduspider-render/2.0; +http://www.baidu.com/search/spider.html)',
        'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        'Mozilla/5.0 (compatible; Bytespider; spider-feedback@bytedance.com)',
        'Mozilla/5.0 (compatible; 360Spider; http://so.com/help/help_3_2.html)',
        'Sogou web spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm#07)',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edg/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
    ];

    private const DEFAULTS = [
        'method'        => 'GET',
        'timeout'       => 10,
        'connectTimeout'=> 5,
        'maxBytes'      => 10485760,
        'follow'        => true,
        'sslVerify'     => false,
        'ua'            => '',
        'uaType'        => 'browser',
        'headers'       => [],
        'proxy'         => '',
        'allowHosts'    => [],
        'expectJson'    => false,
        'postData'      => null,
        'postIsJson'    => false,
        'referer'       => '',
        'saveTo'        => '',
    ];

    public static function defaultUaPool(): array
    {
        return self::UA_POOL;
    }

    public static function randomBrowserUa(): string
    {
        return self::UA_POOL[mt_rand(0, count(self::UA_POOL) - 1)];
    }

    public static function clientUa(): string
    {
        $phpVer = 'PHP/' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
        $os = function_exists('php_uname') ? php_uname('s') . ' ' . php_uname('r') : 'Unknown';
        $site = self::siteBaseUrl();
        $version = defined('ENOVELCMS_VERSION') ? ENOVELCMS_VERSION : '1.0.0';
        return 'EnovelCms-Client/' . $version . ' (' . $site . '; ' . $phpVer . '; ' . $os . ')';
    }

    public static function fillUaTemplate(string $template): string
    {
        $chrome = (118 + mt_rand(0, 6)) . '.0.' . mt_rand(4000, 6000) . '.' . mt_rand(0, 99);
        $map = [
            '{chrome}'    => $chrome,
            '{webkit}'    => '537.36',
            '{win}'       => 'Windows NT 10.0; Win64; x64',
            '{mac}'       => 'Macintosh; Intel Mac OS X 10_15_7',
            '{android}'   => 'Linux; Android 13; Pixel 7',
            '{iphone}'    => 'iPhone; CPU iPhone OS 17_0 like Mac OS X',
            '{random_ua}' => self::randomBrowserUa(),
        ];
        return str_replace(array_keys($map), array_values($map), $template);
    }

    private static function resolveUa(array $opt): string
    {
        $ua = trim((string)($opt['ua'] ?? ''));
        if ($ua !== '') return self::fillUaTemplate($ua);
        if (($opt['uaType'] ?? 'browser') === 'client') return self::clientUa();
        return self::randomBrowserUa();
    }

    public static function get(string $url, array $opts = []): array
    {
        $opts['method'] = 'GET';
        return self::request($url, $opts);
    }

    public static function post(string $url, $data = null, array $opts = []): array
    {
        $opts['method'] = 'POST';
        $opts['postData'] = $data;
        return self::request($url, $opts);
    }

    public static function json(string $url, array $opts = []): ?array
    {
        $opts['expectJson'] = true;
        $res = self::request($url, $opts);
        return $res['ok'] && is_array($res['json']) ? $res['json'] : null;
    }

    public static function download(string $url, string $savePath, array $opts = []): array
    {
        $opts['method'] = 'GET';
        $opts['saveTo'] = $savePath;
        $res = self::request($url, $opts);
        if (!$res['ok']) {
            return ['ok' => false, 'path' => '', 'bytes' => 0, 'headers' => $res['headers'], 'error' => $res['error']];
        }
        if (!is_file($savePath) || filesize($savePath) === 0) {
            return ['ok' => false, 'path' => '', 'bytes' => 0, 'headers' => $res['headers'], 'error' => '下载文件为空或未写入'];
        }
        return ['ok' => true, 'path' => $savePath, 'bytes' => (int)filesize($savePath), 'headers' => $res['headers'], 'error' => ''];
    }

    public static function request(string $url, array $opts = []): array
    {
        $opt = array_merge(self::DEFAULTS, $opts);
        $fail = static fn(string $error, int $http = 0, array $headers = []) => [
            'ok' => false, 'http' => $http, 'body' => '', 'headers' => $headers,
            'error' => $error, 'json' => null,
        ];

        if (!extension_loaded('curl')) {
            return $fail('服务器未启用 cURL 扩展，无法发起网络请求');
        }
        $url = trim($url);
        if ($url === '') return $fail('请求地址为空');

        $safe = self::isSafeUrl($url, (array)$opt['allowHosts']);
        if (!$safe['ok']) return $fail($safe['error']);

        $saveTo = trim((string)$opt['saveTo']);
        $fp = null;
        $isStream = false;
        if ($saveTo !== '') {
            $dir = dirname($saveTo);
            if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
            $fp = fopen($saveTo, 'wb');
            if (!$fp) return $fail('本地文件写入失败');
            $isStream = true;
        }

        $ch = curl_init();
        $headers = self::buildHeaders($opt);
        $respHeaders = [];
        $optsArr = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => $fp === null,
            CURLOPT_FOLLOWLOCATION => (bool)$opt['follow'],
            CURLOPT_TIMEOUT        => max(1, (int)$opt['timeout']),
            CURLOPT_CONNECTTIMEOUT => max(1, (int)$opt['connectTimeout']),
            CURLOPT_SSL_VERIFYPEER => (bool)$opt['sslVerify'],
            CURLOPT_SSL_VERIFYHOST => (bool)$opt['sslVerify'] ? 2 : 0,
            CURLOPT_USERAGENT      => self::resolveUa($opt),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADERFUNCTION => function ($curl, $line) use (&$respHeaders) {
                $len = strlen($line);
                if (str_contains($line, ':')) {
                    [$hk, $hv] = explode(':', $line, 2);
                    $respHeaders[strtolower(trim($hk))] = trim($hv);
                }
                return $len;
            },
        ];
        if (defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $optsArr[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        $method = strtoupper((string)$opt['method']);
        if ($method === 'POST') {
            $optsArr[CURLOPT_POST] = true;
            $data = $opt['postData'];
            if ($opt['postIsJson']) {
                $optsArr[CURLOPT_POSTFIELDS] = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (string)$data;
                $headers[] = 'Content-Type: application/json; charset=utf-8';
                $optsArr[CURLOPT_HTTPHEADER] = $headers;
            } else {
                $optsArr[CURLOPT_POSTFIELDS] = is_array($data) ? http_build_query($data) : (string)$data;
            }
        }

        if ($opt['proxy'] !== '') {
            $optsArr[CURLOPT_PROXY] = $opt['proxy'];
        }
        if ($fp) {
            $optsArr[CURLOPT_FILE] = $fp;
        }

        $maxBytes = max(1024, (int)$opt['maxBytes']);
        $bytes = 0;
        $optsArr[CURLOPT_NOPROGRESS] = false;
        $optsArr[CURLOPT_PROGRESSFUNCTION] = function ($ch, $dlTotal, $dlNow) use ($maxBytes, &$bytes) {
            $bytes = (int)$dlNow;
            return $bytes > $maxBytes ? 1 : 0;
        };

        curl_setopt_array($ch, $optsArr);
        $body = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        if ($fp) { fclose($fp); $fp = null; }

        if ($body === false) {

            if ($errno === 42 || $bytes > $maxBytes) {
                if ($saveTo !== '' && is_file($saveTo)) { @unlink($saveTo); }
                return $fail('响应超过大小上限 ' . $maxBytes . ' 字节，已中止', $httpCode);
            }
            return $fail($err !== '' ? $err : ('HTTP ' . $httpCode), $httpCode);
        }

        if (!$isStream) {
            $body = (string)$body;
            if (strlen($body) > $maxBytes) {
                return $fail('响应超过大小上限 ' . $maxBytes . ' 字节，已拒绝', $httpCode);
            }
        } else {

            if ($saveTo !== '' && is_file($saveTo) && filesize($saveTo) > $maxBytes) {
                @unlink($saveTo);
                return $fail('响应超过大小上限 ' . $maxBytes . ' 字节，已拒绝', $httpCode);
            }
            $body = '';
        }

        $ok = $httpCode >= 200 && $httpCode < 300;
        $json = null;
        if ($opt['expectJson']) {
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                return [
                    'ok' => false, 'http' => $httpCode, 'body' => $body, 'headers' => $respHeaders,
                    'error' => '响应不是合法的 JSON 结构', 'json' => null,
                ];
            }
            $json = $decoded;
        }

        return [
            'ok'      => $ok,
            'http'    => $httpCode,
            'body'    => $body,
            'headers' => $respHeaders,
            'error'   => $ok ? '' : ('HTTP ' . $httpCode),
            'json'    => $json,
        ];
    }

    private static function buildHeaders(array $opt): array
    {
        $headers = ['Accept-Language: zh-CN,zh;q=0.9,en;q=0.8'];
        if ($opt['referer'] !== '') {
            $headers[] = 'Referer: ' . $opt['referer'];
        }
        foreach ((array)$opt['headers'] as $k => $v) {
            if (is_string($k) && $v !== '') $headers[] = $k . ': ' . $v;
        }
        return $headers;
    }

    public static function isSafeUrl(string $url, array $allowHosts = []): array
    {
        if ($url === '') return ['ok' => false, 'error' => '请求地址为空'];
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['ok' => false, 'error' => '仅允许 HTTP/HTTPS 出站请求'];
        }
        $host = (string)parse_url($url, PHP_URL_HOST);
        if ($host === '') return ['ok' => false, 'error' => '请求地址缺少主机名'];
        $host = strtolower(trim($host, '[]'));

        if ($host === 'localhost') return ['ok' => false, 'error' => '禁止访问本机地址'];

        if (!empty($allowHosts)) {
            $allowHosts = array_map(static fn($h) => strtolower(trim((string)$h)), $allowHosts);
            if (!in_array($host, $allowHosts, true)) {
                return ['ok' => false, 'error' => '目标主机不在允许名单内：' . $host];
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (self::isPrivateIpv4($host)) return ['ok' => false, 'error' => '禁止访问内网/保留 IPv4 地址'];
            return ['ok' => true, 'error' => ''];
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if (self::isPrivateIpv6($host)) return ['ok' => false, 'error' => '禁止访问内网/保留 IPv6 地址'];
            return ['ok' => true, 'error' => ''];
        }

        if (preg_match('/(^|\.)(localhost|local|internal|intranet|lan|home|test|example\.local|corp)$/i', $host)) {
            return ['ok' => false, 'error' => '禁止访问内网保留域名'];
        }
        return ['ok' => true, 'error' => ''];
    }

    private static function isPrivateIpv4(string $ip): bool
    {
        $long = ip2long($ip);
        if ($long === false) return false;
        $long = sprintf('%u', $long);
        $ranges = [
            ['0.0.0.0', '0.255.255.255'],
            ['10.0.0.0', '10.255.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['100.64.0.0', '100.127.255.255'],
        ];
        foreach ($ranges as $r) {
            $lo = sprintf('%u', ip2long($r[0]));
            $hi = sprintf('%u', ip2long($r[1]));
            if ($long >= $lo && $long <= $hi) return true;
        }
        return false;
    }

    private static function isPrivateIpv6(string $ip): bool
    {
        $ip = strtolower(trim($ip, '[]'));
        if ($ip === '::1' || $ip === '::') return true;
        if (strpos($ip, '::ffff:') === 0) {
            $v4 = substr($ip, 7);
            if (filter_var($v4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return self::isPrivateIpv4($v4);
            }
        }
        $bin = @inet_pton($ip);
        if ($bin === false) return false;
        $first = ord($bin[0]);
        if (($first & 0xfe) === 0xfc) return true;
        if (($first & 0xff) === 0xfe) return true;
        return false;
    }

    private static function siteBaseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
