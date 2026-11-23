<?php

class Router
{
    private array $routes = [];
    private array $params = [];

    public function add(string $pattern, string $handler, string $method = 'GET'): void
    {
        $this->routes[] = ['pattern' => $pattern, 'handler' => $handler, 'method' => $method];
    }

    public function dispatch(string $path): array
    {
        $path = trim($path, '/');
        $path = preg_replace('/\.html$/', '', $path);

        $p = [
            'library' => preg_quote(UrlService::prefix('library'), '#'),
            'rank'    => preg_quote(UrlService::prefix('rank'), '#'),
            'novel'   => preg_quote(UrlService::prefix('novel'), '#'),
            'read'    => preg_quote(UrlService::prefix('read'), '#'),
            'author'  => preg_quote(UrlService::prefix('author'), '#'),
            'authors' => preg_quote(UrlService::prefix('authors'), '#'),
            'search'  => preg_quote(UrlService::prefix('search'), '#'),
            'user'    => preg_quote(UrlService::prefix('user'), '#'),
        ];

        $libSep = UrlService::separator('library');
        $libSepEsc = $libSep === '-' ? '\-' : ($libSep === '_' ? '_' : '\/');
        $libVal = '[^' . $libSepEsc . ']+';
        $libPair = '[a-z_]+' . $libSepEsc . $libVal;
        $esc = function (string $rt): string {
            $s = UrlService::separator($rt);
            return $s === '-' ? '\-' : ($s === '_' ? '_' : '\/');
        };
        $rankSep = $esc('rank'); $novelSep = $esc('novel'); $userSep = $esc('user');

        $defaultRoutes = [
            ['pattern' => '#^$#',                                          'handler' => 'HomeController@index'],
            ['pattern' => '#^' . $p['library'] . '(/' . $libPair . '(?:' . $libSepEsc . $libPair . ')*)?$#', 'handler' => 'LibraryController@index'],
            ['pattern' => '#^' . $p['rank'] . '(?:'.$rankSep.'(\w+))?$#',            'handler' => 'RankController@index'],
            ['pattern' => '#^' . $p['novel'] . $novelSep.'(\d+)(?:'.$novelSep.'(\d+))?$#', 'handler' => 'NovelController@detail'],
            ['pattern' => UrlService::templatePattern('read'),          'handler' => 'ReadController@index'],
            ['pattern' => '#^' . $p['search'] . '(?:/([^/]+))?$#',         'handler' => 'SearchController@index'],
            ['pattern' => UrlService::templatePattern('authors'),       'handler' => 'AuthorController@index'],
            ['pattern' => '#^' . $p['authors'] . '$#',                    'handler' => 'AuthorController@index'],
            ['pattern' => UrlService::templatePattern('author'),        'handler' => 'AuthorController@detail'],
            ['pattern' => '#^' . $p['user'] . '(?:'.$userSep.'(\w+))?$#',           'handler' => 'UserController@handle'],
            ['pattern' => '#^lang$#',                                      'handler' => 'SiteController@switchLang'],
            ['pattern' => '#^authorcenter(?:/(\w+))?$#',                  'handler' => 'AuthorCenterController@handle'],
            ['pattern' => '#^pay/(notify|return)$#',                      'handler' => 'PayController@handle'],
            ['pattern' => '#^api/(\w+)$#',                                'handler' => 'ApiController@handle'],
            ['pattern' => '#^plugin/(\w+)(?:/(\w+))?$#',                  'handler' => 'PluginPageController@handle'],
        ];

        $this->routes = array_merge($defaultRoutes, $this->routes);

        $this->routes = Hook::applyFilters('router_routes', $this->routes);

        foreach ($this->routes as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches);
                $this->params = $matches;

                if ($route['handler'] === 'NovelController@detail' && count($matches) >= 2 && ctype_digit($matches[1])) {
                    $_GET['chapter_page'] = (int)$matches[1];
                    $route['params'] = [$matches[0]];
                    $matches = [$matches[0]];
                }

                if ($route['handler'] === 'AuthorController@index' && count($matches) >= 1 && ctype_digit($matches[0])) {
                    $_GET['page'] = (int)$matches[0];
                    $route['params'] = [];
                    $matches = [];
                }

                if ($route['handler'] === 'LibraryController@index') {
                    if (!empty($matches[0])) {
                        $segments = preg_split('/' . $libSepEsc . '/', trim($matches[0], '/' . $libSep));
                        while (count($segments) >= 2) {
                            $k = array_shift($segments);
                            $v = array_shift($segments);
                            if (preg_match('/^[a-z_]+$/', $k)) {
                                $_GET[$k] = $v;
                            }
                        }

                        if (count($segments) === 1 && ctype_digit($segments[0])) {
                            $_GET['category'] = $segments[0];
                        }
                    }

                    $route['params'] = [];
                    $matches = [];
                }

                if ($route['handler'] === 'SearchController@index' && !empty($matches[0])) {
                    $_GET['keyword'] = urldecode($matches[0]);
                }

                return ['handler' => $route['handler'], 'params' => $matches];
            }
        }

        return ['handler' => 'NotFoundController@index', 'params' => []];
    }

    public function execute(array $route, object $db, object $request): void
    {
        [$controller, $action] = explode('@', $route['handler']);

        if (!class_exists($controller)) {
            throw new \RuntimeException("路由控制器不存在: {$controller}");
        }

        $instance = new $controller($db, $request);

        if (!method_exists($instance, $action)) {
            throw new \RuntimeException("路由动作不存在: {$controller}@{$action}");
        }

        Hook::doAction('core_before_controller', $controller, $action);

        call_user_func_array([$instance, $action], $route['params']);

        Hook::doAction('core_after_controller', $controller, $action);
    }
}
