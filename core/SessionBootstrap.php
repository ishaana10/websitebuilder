<?php
declare(strict_types=1);

if (!defined('NU_ROOT')) {
    define('NU_ROOT', __DIR__ . '/..');
}

/**
 * Returns application configuration array with Redis settings
 */
function getAppConfig(): array {
    global $nuConfig;
    if (!empty($nuConfig) && is_array($nuConfig)) {
        return $nuConfig;
    }

    $configLocal = NU_ROOT . '/config.local.php';
    if (file_exists($configLocal)) {
        $cfg = @include $configLocal;
        if (is_array($cfg)) return $cfg;
    }

    return [
        'redis' => [
            'enabled' => false,
            'scheme' => 'unix',
            'path' => '/home/ictfjcom/.redis/redis.sock',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
            'prefix' => 'nuvis:',
            'default_ttl' => 300,
            'timeout' => 2.5
        ],
        'sessionTimeout' => 1800
    ];
}

if (session_status() === PHP_SESSION_NONE) {
    // Unique session ID / cookie name as requested
    session_name('nuviswebdesignxssid');

    // Configure secure session cookie attributes before session start
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    $appConfig = getAppConfig();
    $nuConfig = $GLOBALS['nuConfig'] ?? $appConfig;

    if (!empty($nuConfig['redis']['enabled']) && extension_loaded('redis')) {
        try {
            require_once NU_ROOT . '/core/Cache.php';
            require_once NU_ROOT . '/core/RedisSessionHandler.php';

            $cache = \NuCache::getInstance();
            if ($cache->isEnabled() && $cache->client()) {
                $handler = new \NuRedisSessionHandler(
                    $cache->client(),
                    ($nuConfig['redis']['prefix'] ?? 'nuvis:') . 'sess:',
                    (int)($nuConfig['sessionTimeout'] ?? 1800)
                );
                session_set_save_handler($handler, true);
            }
        } catch (\Throwable $e) {
            error_log('[SessionBootstrap] Redis sessions failed: ' . $e->getMessage());
        }
    }

    session_start();
}
