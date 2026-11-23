<?php

class Cache
{
    private static ?object $driver = null;
    private static string $driverType = 'file';

    public static function drivers(): array
    {
        return [
            'file'      => ['name' => '文件缓存',   'ext' => true,  'desc' => '无需额外服务，所有环境可用'],
            'memcached' => ['name' => 'Memcached',  'ext' => extension_loaded('memcached'), 'desc' => '高性能内存缓存，需安装 memcached 服务'],
            'redis'     => ['name' => 'Redis',      'ext' => extension_loaded('redis'),      'desc' => '高性能键值存储，需安装 Redis 服务'],
            'yac'       => ['name' => 'YAC',        'ext' => extension_loaded('yac') && self::yacUsable(), 'desc' => 'PHP 内置共享内存缓存，无需网络（CLI 需 yac.enable_cli=1）'],
        ];
    }

    private static function yacUsable(): bool
    {
        if (!extension_loaded('yac')) return false;
        if (PHP_SAPI === 'cli') return (bool)ini_get('yac.enable_cli');
        return (bool)ini_get('yac.enable');
    }

    public static function isAvailable(string $driver): bool
    {
        return self::drivers()[$driver]['ext'] ?? false;
    }

    public static function init(): void
    {
        if (!defined('CACHE_ENABLE') || !CACHE_ENABLE) return;
        if (self::$driver !== null) return;

        self::$driverType = defined('CACHE_DRIVER') ? CACHE_DRIVER : 'file';
        if (!self::isAvailable(self::$driverType)) {
            self::$driverType = 'file';
        }

        try {
            self::$driver = match(self::$driverType) {
                'memcached' => new CacheDriverMemcached(),
                'redis'     => new CacheDriverRedis(),
                'yac'       => new CacheDriverYac(),
                default     => new CacheDriverFile(),
            };
        } catch (\Throwable $e) {
            error_log('[cache] driver ' . self::$driverType . ' init failed, fallback to file: ' . $e->getMessage());
            self::$driverType = 'file';
            self::$driver = new CacheDriverFile();
        }
    }

    public static function driverType(): string
    {
        return self::$driverType;
    }

    public static function isEnabled(): bool
    {
        return self::$driver !== null;
    }

    private const MAX_KEY_LEN = 32;

    private static function normalizeKey(string $key): string
    {
        if (strlen($key) <= self::MAX_KEY_LEN) return $key;
        return 'h' . substr(md5($key), 0, 31);
    }

    public static function get(string $key)
    {
        if (!self::$driver) return null;
        return self::$driver->get(self::normalizeKey($key));
    }

    public static function set(string $key, $value, ?int $ttl = null): void
    {
        if (!self::$driver) return;
        self::$driver->set(self::normalizeKey($key), $value, $ttl ?? (defined('CACHE_TTL') ? CACHE_TTL : 300));
    }

    public static function delete(string $key): void
    {
        if (!self::$driver) return;
        self::$driver->delete(self::normalizeKey($key));
    }

    public static function flush(): void
    {
        if (!self::$driver) return;
        self::$driver->flush();
    }

    public static function remember(string $key, int $ttl, callable $callback)
    {
        $cached = self::get($key);
        if ($cached !== null) return $cached;
        $value = $callback();
        self::set($key, $value, $ttl);
        return $value;
    }
}

interface CacheDriverInterface
{
    public function get(string $key);
    public function set(string $key, $value, int $ttl): void;
    public function delete(string $key): void;
    public function flush(): void;
}

class CacheDriverFile implements CacheDriverInterface
{
    private const GUARD = "<?php exit; ?>\n";

    private string $dir;

    public function __construct()
    {

        $this->dir = ROOT_PATH . 'data/cache/';
        if (!is_dir($this->dir)) @mkdir($this->dir, 0755, true);
    }

    private function file(string $key): string
    {
        return $this->dir . md5($key) . '.cache.php';
    }

    public function get(string $key)
    {
        $file = $this->file($key);
        if (!file_exists($file)) return null;

        $raw = (string)@file_get_contents($file);
        if (strncmp($raw, self::GUARD, strlen(self::GUARD)) === 0) {
            $raw = substr($raw, strlen(self::GUARD));
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($data) || !isset($data['expire'])) return null;
        if ($data['expire'] > 0 && time() > $data['expire']) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    public function set(string $key, $value, int $ttl): void
    {
        $data = ['expire' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value];
        file_put_contents($this->file($key), self::GUARD . serialize($data), LOCK_EX);
    }

    public function delete(string $key): void
    {
        $file = $this->file($key);
        if (file_exists($file)) @unlink($file);
    }

    public function flush(): void
    {
        foreach (glob($this->dir . '*.cache.php') as $f) @unlink($f);
        foreach (glob($this->dir . '*.cache') as $f) @unlink($f);
    }
}

class CacheDriverMemcached implements CacheDriverInterface
{
    private \Memcached $mc;

    public function __construct()
    {
        $this->mc = new \Memcached();
        $host = defined('MEMCACHED_HOST') ? MEMCACHED_HOST : '127.0.0.1';
        $port = defined('MEMCACHED_PORT') ? MEMCACHED_PORT : 11211;
        $this->mc->addServer($host, $port);
    }

    public function get(string $key)
    {
        $v = $this->mc->get($key);
        return $v === false ? null : $v;
    }

    public function set(string $key, $value, int $ttl): void
    {
        $this->mc->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        $this->mc->delete($key);
    }

    public function flush(): void
    {
        $this->mc->flush();
    }
}

class CacheDriverRedis implements CacheDriverInterface
{
    private \Redis $redis;

    public function __construct()
    {
        $this->redis = new \Redis();
        $host = defined('REDIS_HOST') ? REDIS_HOST : '127.0.0.1';
        $port = defined('REDIS_PORT') ? REDIS_PORT : 6379;
        $this->redis->connect($host, $port, 2.0);
        if (defined('REDIS_PASSWORD') && REDIS_PASSWORD) {
            $this->redis->auth(REDIS_PASSWORD);
        }
    }

    public function get(string $key)
    {
        $v = $this->redis->get($key);

        return $v === false ? null : unserialize($v, ['allowed_classes' => false]);
    }

    public function set(string $key, $value, int $ttl): void
    {
        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, serialize($value));
            return;
        }
        $this->redis->set($key, serialize($value));
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }

    public function flush(): void
    {
        $this->redis->flushDb();
    }
}

class CacheDriverYac implements CacheDriverInterface
{
    private \Yac $yac;

    public function __construct()
    {
        $this->yac = new \Yac('enovel_');
    }

    private function acceptable(string $key): bool
    {
        if (strlen($key) <= 32) return true;
        error_log('[cache] yac key too long, skipped: ' . strlen($key) . ' bytes');
        return false;
    }

    public function get(string $key)
    {
        if (!$this->acceptable($key)) return null;
        $v = $this->yac->get($key);
        return $v === false ? null : $v;
    }

    public function set(string $key, $value, int $ttl): void
    {
        if (!$this->acceptable($key)) return;
        $this->yac->set($key, $value, $ttl);
    }

    public function delete(string $key): void
    {
        if (!$this->acceptable($key)) return;
        $this->yac->delete($key);
    }

    public function flush(): void
    {
        $this->yac->flush();
    }
}
