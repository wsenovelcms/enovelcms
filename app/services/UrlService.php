<?php

class UrlService
{

    private static array $defaultPrefixes = [
        'library' => 'library',
        'rank'    => 'rank',
        'novel'   => 'novel',
        'read'    => 'read',
        'author'  => 'author',
        'authors' => 'authors',
        'search'  => 'search',
        'user'    => 'user',
    ];

    private static ?array $prefixCache = null;
    private static ?bool $suffixCache = null;
    private static ?array $templateCache = null;

    private static array $routeSegments = [
        'library' => ['prefix'],
        'rank'    => ['prefix', 'type'],
        'novel'   => ['prefix', 'id', 'page'],
        'read'    => ['prefix', 'novel', 'chapter'],
        'author'  => ['prefix', 'id'],
        'authors' => ['prefix', 'page'],
        'search'  => ['prefix'],
        'user'    => ['prefix', 'action'],
    ];

    private static array $optionalSegments = ['type', 'page', 'action'];

    private static array $patternGroups = [
        '{id}'      => '(\d+)',
        '{novel}'   => '(\d+)',
        '{chapter}' => '(\d+)',
        '{page}'    => '(\d+)',
        '{type}'    => '(\w+)',
        '{action}'  => '(\w+)',
    ];

    public static function presets(): array
    {
        return [
            'library' => ['sample' => []],
            'rank'    => ['sample' => ['type' => 'views']],
            'novel'   => ['sample' => ['id' => 5, 'page' => 2]],
            'read'    => ['sample' => ['novel' => 12, 'chapter' => 34]],
            'author'  => ['sample' => ['id' => 1]],
            'authors' => ['sample' => ['page' => 2]],
            'search'  => ['sample' => []],
            'user'    => ['sample' => ['action' => 'bookshelf']],
        ];
    }

    public static function separator(string $route): string
    {
        $v = (string)SettingModel::get('url_sep_' . $route, '/');
        return in_array($v, ['-', '_'], true) ? $v : '/';
    }

    public static function template(string $route): string
    {
        if (self::$templateCache === null) self::$templateCache = [];
        if (!isset(self::$templateCache[$route])) {
            self::$templateCache[$route] = self::templateWith($route, self::separator($route));
        }
        return self::$templateCache[$route];
    }

    public static function templateWith(string $route, string $sep): string
    {
        $segs = self::$routeSegments[$route] ?? ['prefix'];
        $sep = in_array($sep, ['-', '_'], true) ? $sep : '/';
        $out = '/{prefix}';
        for ($i = 1; $i < count($segs); $i++) {
            $out .= $sep . '{' . $segs[$i] . '}';
        }
        return $out;
    }

    private static function renderTemplate(string $route, array $vars): string
    {
        $tpl = self::template($route);
        $map = ['{prefix}' => self::prefix($route)];
        foreach (['novel', 'chapter', 'id'] as $k) {
            if (isset($vars[$k]) && $vars[$k] !== '' && $vars[$k] !== null) {
                $map['{' . $k . '}'] = (string)(int)$vars[$k];
            }
        }
        foreach (['type', 'action', 'page'] as $k) {
            if (isset($vars[$k]) && $vars[$k] !== '' && $vars[$k] !== null) {
                $map['{' . $k . '}'] = (string)$vars[$k];
            }
        }
        $rendered = str_replace(array_keys($map), array_values($map), $tpl);
        $rendered = preg_replace('/\{[a-z]+\}/', '', $rendered);
        $kept = [];
        foreach (explode('/', trim($rendered, '/')) as $seg) {
            $seg = rtrim($seg, '-_.');
            if ($seg === '') continue;
            $kept[] = $seg;
        }
        return '/' . implode('/', $kept);
    }

    public static function templatePattern(string $route): string
    {
        $tpl = self::template($route);
        $map = [preg_quote('{prefix}', '#') => preg_quote(self::prefix($route), '#')];
        foreach (self::$patternGroups as $ph => $group) {
            $map[preg_quote($ph, '#')] = $group;
        }
        $regex = str_replace(array_keys($map), array_values($map), preg_quote($tpl, '#'));
        return '#^' . trim($regex, '/') . '$#';
    }

    public static function prefixes(): array
    {
        if (self::$prefixCache === null) {
            $map = [];
            foreach (self::$defaultPrefixes as $name => $default) {
                $raw = SettingModel::get('url_' . $name, $default);
                $clean = self::sanitizeSegment((string)$raw);
                $map[$name] = $clean !== '' ? $clean : $default;
            }
            self::$prefixCache = $map;
        }
        return self::$prefixCache;
    }

    public static function prefix(string $name): string
    {
        $all = self::prefixes();
        return $all[$name] ?? $name;
    }

    public static function htmlSuffix(): bool
    {
        if (self::$suffixCache === null) {
            self::$suffixCache = ((string)SettingModel::get('url_html_suffix', '') === '1');
        }
        return self::$suffixCache;
    }

    public static function suffixFor(string $route): bool
    {
        $value = SettingModel::get('url_suffix_' . $route, null);
        if ($value === null) return self::htmlSuffix();
        return (string)$value === '1';
    }

    public static function sanitizeSegment(string $value): string
    {
        return (string)preg_replace('/[^a-zA-Z0-9_\-]/', '', $value);
    }

    private static function isPlaceholder($value): bool
    {
        return is_string($value) && preg_match('/^@@[\w]+@@$/', $value) === 1;
    }

    public static function build(string $route, array $params = []): string
    {
        $suffix = self::suffixFor($route) ? '.html' : '';
        $query = self::buildQuery($params['query'] ?? null);

        switch ($route) {
            case 'home':
                $path = '/';
                $suffix = '';
                break;

            case 'library':
                $path = self::renderTemplate('library', []);
                $segments = [];

                $keys = ['order_by' => 'order_by', 'page' => 'page', 'category' => 'category',
                         'vip_filter' => 'vip_filter', 'status' => 'status',
                         'min_words' => 'min_words', 'max_words' => 'max_words',
                         'tag_id' => 'tag_id',
                         'keyword' => 'keyword'];
                foreach ($keys as $k => $src) {
                    $v = $params[$src] ?? null;
                    if ($v === null || $v === '' || $v === 0 || $v === 'all') continue;
                    if ($src === 'category' && (int)$v <= 0) continue;
                    $segments[] = $k;
                    $segments[] = rawurlencode((string)$v);
                }
                if ($segments) $path .= '/' . implode(self::separator('library'), $segments);
                break;

            case 'rank':
                $rawType = $params['type'] ?? '';
                if (self::isPlaceholder($rawType)) {
                    $path = '/' . self::prefix('rank') . '/' . $rawType;
                } else {
                    $type = self::sanitizeSegment((string)$rawType);
                    $path = self::renderTemplate('rank', $type !== '' ? ['type' => $type] : []);
                }
                break;

            case 'novel':
                $novelId = (int)($params['id'] ?? 0);
                $novelPage = $params['page'] ?? 0;
                $vars = ['id' => $novelId];
                if (is_string($novelPage) && $novelPage !== '') {
                    $vars['page'] = $novelPage;
                } elseif ((int)$novelPage > 1) {
                    $vars['page'] = (int)$novelPage;
                }
                $path = self::renderTemplate('novel', $vars);
                break;

            case 'read':
                $path = self::renderTemplate('read', [
                    'novel'   => (int)($params['novel'] ?? 0),
                    'chapter' => (int)($params['chapter'] ?? 0),
                ]);
                break;

            case 'authors':
                $authorPage = $params['page'] ?? 0;
                if (is_string($authorPage) && $authorPage !== '') {
                    $path = self::renderTemplate('authors', ['page' => $authorPage]);
                } elseif ((int)$authorPage > 1) {
                    $path = self::renderTemplate('authors', ['page' => (int)$authorPage]);
                } else {
                    $path = '/' . self::prefix('authors');
                }
                break;

            case 'author':
                $path = self::renderTemplate('author', ['id' => (int)($params['id'] ?? 0)]);
                break;

            case 'search':
                $path = self::renderTemplate('search', []);
                $suffix = '';
                break;

            case 'user':
                $action = self::sanitizeSegment((string)($params['action'] ?? ''));
                $path = self::renderTemplate('user', $action !== '' ? ['action' => $action] : []);
                break;

            case 'authorcenter':
                $action = self::sanitizeSegment((string)($params['action'] ?? ''));
                $path = '/authorcenter' . ($action !== '' ? '/' . $action : '');
                $suffix = '';
                break;

            case 'pay':
                $action = self::sanitizeSegment((string)($params['action'] ?? 'notify'));
                $path = '/pay/' . ($action !== '' ? $action : 'notify');
                $suffix = '';
                break;

            case 'api':
                $action = self::sanitizeSegment((string)($params['action'] ?? ''));
                $path = '/api' . ($action !== '' ? '/' . $action : '');
                $suffix = '';
                break;

            case 'lang':
                $path = '/lang';
                $suffix = '';
                $query = self::buildQuery(array('lang' => $params['lang'] ?? ''));
                break;

            default:
                $path = '/';
                $suffix = '';
                break;
        }

        return $path . $suffix . $query;
    }

    private static function buildQuery($query): string
    {
        if ($query === null || $query === '' || $query === false) return '';
        if (is_array($query)) {
            $query = http_build_query($query);
        }
        return '?' . ltrim((string)$query, '?');
    }

    public static function home(): string
    {
        return BASE_URL . '/';
    }

    public static function library(int $catId = 0): string
    {
        return BASE_URL . self::build('library', $catId > 0 ? ['cat' => $catId] : []);
    }

    public static function rank(string $type = ''): string
    {
        return BASE_URL . self::build('rank', $type !== '' ? ['type' => $type] : []);
    }

    public static function novel(int $id): string
    {
        return BASE_URL . self::build('novel', ['id' => $id]);
    }

    public static function read(int $novelId, int $chapterId): string
    {
        return BASE_URL . self::build('read', ['novel' => $novelId, 'chapter' => $chapterId]);
    }

    public static function authors(): string
    {
        return BASE_URL . self::build('authors');
    }

    public static function author(int $id): string
    {
        return BASE_URL . self::build('author', ['id' => $id]);
    }

    public static function search(): string
    {
        return BASE_URL . self::build('search');
    }

    public static function user(string $action = ''): string
    {
        return BASE_URL . self::build('user', $action !== '' ? ['action' => $action] : []);
    }
}
