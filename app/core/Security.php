<?php

class Security
{

    public static function h(?string $str): string
    {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::csrfToken() . '">';
    }

    public static function verifyCsrf(): bool
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $token = $_POST['_csrf'] ?? $_GET['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return !empty($token) && hash_equals($_SESSION['_csrf_token'] ?? '', $token);
    }

    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function generateCaptcha(?int $length = null, bool $forceNew = true): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $code = $forceNew ? null : self::activeCaptcha();
        if ($code === null) {
            $cfg = self::captchaConfig();
            $length = $length ?? (int)$cfg['length'];
            if ($length < 3) $length = 3;
            if ($length > 8) $length = 8;

            $charsetMap = [
                'digits' => '23456789',
                'letters' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz',
                'alnum'  => '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz',
            ];
            $chars = $charsetMap[$cfg['charset']] ?? $charsetMap['alnum'];

            if (!$cfg['case_sensitive']) {
                $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            }

            $max = strlen($chars) - 1;
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, $max)];
            }
            $_SESSION['captcha_code'] = $code;
            $_SESSION['captcha_case_sensitive'] = $cfg['case_sensitive'] ? 1 : 0;
            $_SESSION['captcha_time'] = time();
        }

        self::drawCaptchaImage($code);
    }

    private static function drawCaptchaImage(string $code): void
    {
        $width = 120;
        $height = 40;
        $image = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($image, 245, 245, 245);
        $textColor = imagecolorallocate($image, 50, 50, 50);
        $lineColor = imagecolorallocate($image, 150, 150, 150);
        $pixelColor = imagecolorallocate($image, 100, 100, 100);
        imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

        for ($i = 0; $i < 5; $i++) {
            imageline($image, random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height), $lineColor);
        }
        for ($i = 0; $i < 100; $i++) {
            imagesetpixel($image, random_int(0, $width), random_int(0, $height), $pixelColor);
        }

        $font = 5;
        $fontWidth = imagefontwidth($font);
        $fontHeight = imagefontheight($font);
        $x = (int)(($width - $fontWidth * strlen($code)) / 2);
        $y = (int)(($height - $fontHeight) / 2);
        imagestring($image, $font, $x, $y, $code, $textColor);

        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        imagepng($image);
    }

    public static function activeCaptcha(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $code = isset($_SESSION['captcha_code']) ? (string)$_SESSION['captcha_code'] : '';
        $created = (int)($_SESSION['captcha_time'] ?? 0);
        if ($code === '' || $created === 0 || time() - $created > (int)self::captchaConfig()['ttl']) {
            return null;
        }
        return $code;
    }

    public static function captchaConfig(): array
    {
        $get = function (string $k, string $d): string {
            try { return (string)SettingModel::get($k, $d); }
            catch (\Throwable $e) { return $d; }
        };
        $ttl = (int)$get('captcha_ttl', '600');
        if ($ttl < 60) $ttl = 60;
        if ($ttl > 3600) $ttl = 3600;
        return [
            'enabled'         => $get('captcha_enabled', '0') === '1',
            'length'          => (int)$get('captcha_length', '5'),
            'charset'         => in_array($get('captcha_charset', 'alnum'), ['digits','letters','alnum'], true)
                                  ? $get('captcha_charset', 'alnum') : 'alnum',
            'case_sensitive'  => $get('captcha_case_sensitive', '0') === '1',
            'ttl'             => $ttl,
        ];
    }

    public static function captchaEnabled(): bool
    {
        return self::captchaConfig()['enabled'];
    }

    public static function requireCaptcha(?string $input = null): ?string
    {
        if (!self::captchaEnabled()) return null;
        if ($input === null) {
            $input = $_POST['captcha'] ?? p('_captcha', '', 'str');
        }
        return self::verifyCaptchaReason($input);
    }

    public static function verifyCaptcha($input): bool
    {
        return self::verifyCaptchaReason($input) === null;
    }

    public static function verifyCaptchaReason($input): ?string
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (!self::captchaEnabled()) {
            return null;
        }

        $pluginResult = Hook::applyFilters('captcha_verify', null, $input);
        if ($pluginResult !== null) {
            $ok = (bool)$pluginResult;
            if ($ok) self::clearCaptcha();
            return $ok ? null : Language::get('captcha_reenter', '验证码错误，请重新输入');
        }

        $input = trim((string)$input);
        if ($input === '') {
            return Language::get('captcha_empty', '请输入验证码');
        }
        $code = $_SESSION['captcha_code'] ?? '';
        $created = (int)($_SESSION['captcha_time'] ?? 0);
        if ($code === '' || $created === 0) {
            return Language::get('captcha_expired', '验证码已过期，请点击图片刷新后再输入');
        }
        if (time() - $created > (int)self::captchaConfig()['ttl']) {
            self::clearCaptcha();
            return Language::get('captcha_expired', '验证码已过期，请点击图片刷新后再输入');
        }
        $sensitive = !empty($_SESSION['captcha_case_sensitive']);
        if ($sensitive) {
            $ok = strcmp($input, (string)$code) === 0;
        } else {
            $ok = strtolower($input) === strtolower((string)$code);
        }
        if ($ok) {
            self::clearCaptcha();
            return null;
        }
        return Language::get('captcha_reenter', '验证码错误，请重新输入');
    }

    public static function captchaHtml(string $name = 'captcha', string $placeholder = ''): string
    {
        if (!self::captchaEnabled()) {
            return '';
        }
        if ($placeholder === '') {
            $placeholder = Language::get('captcha_field_placeholder', '请输入验证码');
        }
        $captchaAlt = Language::get('captcha', '验证码');
        $refreshTitle = Language::get('captcha_refresh_title', '点击刷新');
        $refreshHint = Language::get('captcha_refresh_hint', '看不清？点击图片刷新');
        $src = self::activeCaptcha() !== null ? '/api/captcha?reuse=1' : '/api/captcha?' . time();
        $default = '<div class="captcha-wrap">'
            . '<div class="captcha-row" style="display:flex;gap:8px;align-items:center;">'
            . '<input type="text" class="captcha-input" name="' . htmlspecialchars($name) . '" placeholder="' . htmlspecialchars($placeholder) . '" required maxlength="8" autocomplete="off" style="flex:1;height:42px;box-sizing:border-box;">'
            . '<img src="' . htmlspecialchars($src) . '" class="captcha-img" title="' . htmlspecialchars($refreshTitle) . '" alt="' . htmlspecialchars($captchaAlt) . '" style="height:42px;border:1px solid #ddd;border-radius:4px;cursor:pointer;">'
            . '</div>'
            . '<span class="captcha-refresh-hint" style="display:block;margin-top:6px;font-size:12px;color:#888;cursor:pointer;">' . htmlspecialchars($refreshHint) . '</span>'
            . '</div>';
        return (string)Hook::applyFilters('captcha_render_html', $default, $name, $placeholder);
    }

    public static function clearCaptcha(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        unset($_SESSION['captcha_code'], $_SESSION['captcha_time'], $_SESSION['captcha_case_sensitive']);
    }

    public static function input(string $key, $default = null, string $type = 'str')
    {
        $val = $_REQUEST[$key] ?? $default;
        switch ($type) {
            case 'int':    return (int)$val;
            case 'float':  return (float)$val;
            case 'bool':   return (bool)$val;
            case 'str':    return is_string($val) ? trim($val) : (string)$val;
            case 'raw':    return $val;
            case 'array':  return is_array($val) ? $val : [];
            default:       return is_string($val) ? trim($val) : $val;
        }
    }

    public static function get(string $key, $default = null, string $type = 'str')
    {
        $val = $_GET[$key] ?? $default;
        return self::filterValue($val, $type);
    }

    public static function post(string $key, $default = null, string $type = 'str')
    {
        $val = $_POST[$key] ?? $default;
        return self::filterValue($val, $type);
    }

    private static function filterValue($val, string $type)
    {
        switch ($type) {
            case 'int':   return (int)$val;
            case 'float': return (float)$val;
            case 'bool':  return (bool)$val;
            case 'str':   return is_string($val) ? trim($val) : (string)$val;
            case 'raw':   return $val;
            case 'array': return is_array($val) ? $val : [];
            default:      return is_string($val) ? trim($val) : $val;
        }
    }

    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        if ($https) {
            try {
                $hsts = SettingModel::get('enable_hsts', '0');
            } catch (\Throwable $e) {
                $hsts = '0';
            }
            if ((string)$hsts === '1') {
                header('Strict-Transport-Security: max-age=15778463; includeSubDomains');
            }
        }
    }

    public static function safeFilename(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $name);
        return $name;
    }

    public static function safeUrl(?string $url): string
    {
        $url = trim((string)$url);
        if ($url === '') return '';

        if (!str_contains($url, '://')) {
            if (str_starts_with($url, '//')) return '';
            if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $url)) return '';
            return $url;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    public static function validPluginId(string $id): bool
    {
        return (bool)preg_match('/^[a-z][a-z0-9_]{2,30}$/', $id);
    }

    public static function verifyImageUpload(array $file, int $maxBytes = 5 * 1024 * 1024): ?array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return null;
        if (($file['size'] ?? 0) > $maxBytes) return null;
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;
        $type = (int)$info[2];
        $map = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        if (!isset($map[$type])) return null;
        return ['ext' => $map[$type], 'type' => $type];
    }

    public static function verifyZipUpload(array $file): bool
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return false;
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return false;
        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext !== 'zip') return false;
        $fp = @fopen($file['tmp_name'], 'rb');
        if (!$fp) return false;
        $magic = fread($fp, 4);
        fclose($fp);
        return $magic === "PK\x03\x04" || $magic === "PK\x05\x06" || $magic === "PK\x07\x08";
    }

    public static function validateLanguagePack(string $content): array
    {
        if ($content === '' || strpos($content, '<?php') === false) {
            return ['ok' => false, 'reason' => 'missing_open_tag'];
        }
        if (strpos($content, '?>') !== false) {
            return ['ok' => false, 'reason' => 'closing_tag'];
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $tokens = @token_get_all($content);
        if ($tokens === false) {
            return ['ok' => false, 'reason' => 'tokenize_fail'];
        }

        $allow = [
            T_OPEN_TAG, T_RETURN, T_ARRAY, T_DOUBLE_ARROW,
            T_CONSTANT_ENCAPSED_STRING,
            T_LNUMBER, T_DNUMBER,
            T_WHITESPACE, T_COMMENT, T_DOC_COMMENT,
        ];
        $allowChars = ['[', ']', ',', ';'];
        $blockedTokens = [
            T_VARIABLE                => 'variable',
            T_STRING                  => 'identifier',
            T_INLINE_HTML             => 'inline_html',
            T_OPEN_TAG_WITH_ECHO      => 'echo_tag',
            T_EVAL                    => 'eval',
            T_INCLUDE                 => 'include',
            T_REQUIRE                 => 'require',
            T_INCLUDE_ONCE            => 'include_once',
            T_REQUIRE_ONCE            => 'require_once',
            T_OBJECT_OPERATOR         => 'object_op',
            T_DOUBLE_COLON            => 'double_colon',
            T_PAAMAYIM_NEKUDOTAYIM    => 'double_colon',
            T_CURLY_OPEN              => 'curly_open',
            T_DOLLAR_OPEN_CURLY_BRACES=> 'dollar_curly',
            T_ENCAPSED_AND_WHITESPACE => 'interpolated',
            T_BAD_CHARACTER           => 'bad_char',
        ];

        foreach ($tokens as $tok) {
            if (is_array($tok)) {
                $id = $tok[0];
                if (in_array($id, $allow, true)) continue;
                $reason = $blockedTokens[$id] ?? ('token:' . token_name($id));
                return ['ok' => false, 'reason' => $reason];
            }
            if (in_array($tok, $allowChars, true)) continue;
            return ['ok' => false, 'reason' => 'char:' . $tok];
        }

        return ['ok' => true, 'reason' => ''];
    }

    public static function randomToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }
}
