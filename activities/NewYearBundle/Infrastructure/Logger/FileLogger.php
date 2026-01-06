<?php

namespace CustomActivity\NewYearBundle\Infrastructure\Logger;

use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;
use CustomActivity\NewYearBundle\Config\CampaignConfig;

/**
 * 檔案日誌記錄器
 * 將日誌寫入檔案
 */
final class FileLogger implements LoggerInterface
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? WP_CONTENT_DIR . '/newyear-bundle.log';
    }

    public function debug(string $message, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->write('DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        // 錯誤訊息總是記錄
        $this->write('ERROR', $message, $context);
    }

    public function isEnabled(): bool
    {
        return CampaignConfig::DEBUG_MODE || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * 寫入日誌到檔案
     */
    private function write(string $level, string $message, array $context): void
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        error_log($logMessage, 3, $this->logFile);
    }
}

