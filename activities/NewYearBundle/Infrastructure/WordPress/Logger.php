<?php
/**
 * 日誌包裝器
 *
 * 包裝 WordPress/WooCommerce 日誌系統
 * 提供統一的日誌介面
 */

namespace NewYearBundle\Infrastructure\WordPress;

use NewYearBundle\Config;

class Logger
{
    private const DEFAULT_LOG_FILE = 'newyear-bundle.log';
    private const SOURCE = 'newyear-bundle';

    private string $logFile;

    /**
     * @param string|null $logFile 日誌檔案名稱（相對於 WP_CONTENT_DIR）
     */
    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? self::DEFAULT_LOG_FILE;
    }

    /**
     * 記錄 info 級別日誌
     */
    public function info(string $message, array $context = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        $this->writeToFile($message, 'INFO', $context);

        // 同時使用 WooCommerce logger（如果可用）
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array_merge(['source' => self::SOURCE], $context));
        }
    }

    /**
     * 記錄 debug 級別日誌
     */
    public function debug(string $message, array $context = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        $this->writeToFile($message, 'DEBUG', $context);

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->debug($message, array_merge(['source' => self::SOURCE], $context));
        }
    }

    /**
     * 記錄 warning 級別日誌
     */
    public function warning(string $message, array $context = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }

        $this->writeToFile($message, 'WARNING', $context);

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->warning($message, array_merge(['source' => self::SOURCE], $context));
        }
    }

    /**
     * 記錄 error 級別日誌
     */
    public function error(string $message, array $context = []): void
    {
        $this->writeToFile($message, 'ERROR', $context);

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->error($message, array_merge(['source' => self::SOURCE], $context));
        }
    }

    /**
     * 檢查是否應該記錄日誌
     */
    private function shouldLog(): bool
    {
        return Config::isDebugMode() || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * 寫入檔案日誌
     */
    private function writeToFile(string $message, string $level, array $context): void
    {
        if (!defined('WP_CONTENT_DIR')) {
            return;
        }

        $logFilePath = WP_CONTENT_DIR . '/' . $this->logFile;
        $timestamp = current_time('Y-m-d H:i:s');

        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        error_log($logLine, 3, $logFilePath);
    }
}

