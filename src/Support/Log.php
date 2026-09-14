<?php

declare(strict_types=1);

namespace App\Support;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Thin Monolog facade — channels: app, payments, security.
 * PHP 7.4 compatible.
 */
final class Log
{
    /** @var array<string, Logger> */
    private static $loggers = [];

    public static function app(): LoggerInterface
    {
        return self::channel('app');
    }

    public static function payments(): LoggerInterface
    {
        return self::channel('payments');
    }

    public static function security(): LoggerInterface
    {
        return self::channel('security');
    }

    public static function channel(string $name): LoggerInterface
    {
        if (!isset(self::$loggers[$name])) {
            self::$loggers[$name] = self::create($name);
        }
        return self::$loggers[$name];
    }

    private static function create(string $name): Logger
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $levelName = strtolower((string) (getenv('LOG_LEVEL') ?: ($_ENV['LOG_LEVEL'] ?? '')));
        if ($levelName === '') {
            $env = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local')));
            $levelName = ($env === 'local' || $env === 'development') ? 'debug' : 'warning';
        }

        $levels = [
            'debug' => Logger::DEBUG,
            'info' => Logger::INFO,
            'notice' => Logger::NOTICE,
            'warning' => Logger::WARNING,
            'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL,
            'alert' => Logger::ALERT,
            'emergency' => Logger::EMERGENCY,
        ];
        $level = $levels[$levelName] ?? Logger::WARNING;
        $logger = new Logger($name);
        $handler = new RotatingFileHandler(
            $dir . DIRECTORY_SEPARATOR . $name . '.log',
            14,
            $level
        );
        $logger->pushHandler($handler);
        return $logger;
    }
}
