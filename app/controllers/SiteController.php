<?php

class SiteController extends Controller
{

    public function switchLang(): void
    {
        $code = (string)g('lang', '', 'str');
        if (!Language::isLangAvailable($code)) {
            $code = 'zh-cn';
        }
        $_SESSION['lang'] = $code;
        setcookie('lang', $code, time() + 86400 * 365, '/');

        $this->redirectInternal();
    }

    private function redirectInternal(): void
    {
        $fallback = url_full('home');
        $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref === '') {
            $this->redirect($fallback);
        }

        $parts = parse_url($ref);
        if ($parts === false || !isset($parts['path'])) {
            $this->redirect($fallback);
        }

        if (isset($parts['host']) && $parts['host'] !== (string)($_SERVER['HTTP_HOST'] ?? '')) {
            $this->redirect($fallback);
        }

        $path = $parts['path'];
        if (strpos($path, '//') === 0
            || strpos($path, '\\') !== false
            || strpos($path, '..') !== false
            || preg_match('#^[a-zA-Z]:#', $path)) {
            $this->redirect($fallback);
        }

        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
        $this->redirect($path . $query . $fragment);
    }
}
