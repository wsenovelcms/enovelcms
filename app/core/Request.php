<?php

class Request
{
    public string $method;
    public string $path;
    public array $get;
    public array $post;
    public array $server;
    public array $files;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->get    = $_GET;
        $this->post   = $_POST;
        $this->server = $_SERVER;
        $this->files  = $_FILES;

        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path = parse_url($uri, PHP_URL_PATH) ?: '/';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public function input(string $key, $default = null, string $type = 'str')
    {
        return Security::input($key, $default, $type);
    }

    public function get(string $key, $default = null, string $type = 'str')
    {
        return Security::get($key, $default, $type);
    }

    public function post(string $key, $default = null, string $type = 'str')
    {
        return Security::post($key, $default, $type);
    }

    public function isLoggedIn(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public function userId(): int
    {
        return (int)($_SESSION['user_id'] ?? 0);
    }
}
