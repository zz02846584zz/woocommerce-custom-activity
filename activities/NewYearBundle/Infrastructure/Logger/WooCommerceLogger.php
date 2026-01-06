<?php

namespace CustomActivity\NewYearBundle\Infrastructure\Logger;

use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;
use CustomActivity\NewYearBundle\Config\CampaignConfig;

/**
 * WooCommerce 日誌記錄器
 * 使用 WooCommerce 內建的日誌系統
 */
final class WooCommerceLogger implements LoggerInterface
{
    private $logger;
    private array $context;

    public function __construct()
    {
        $this->logger = function_exists('wc_get_logger') ? wc_get_logger() : null;
        $this->context = ['source' => 'newyear-bundle'];
    }

    public function debug(string $message, array $context = []): void
    {
        if (!$this->isEnabled() || !$this->logger) {
            return;
        }

        $this->logger->debug($message, $this->mergeContext($context));
    }

    public function info(string $message, array $context = []): void
    {
        if (!$this->isEnabled() || !$this->logger) {
            return;
        }

        $this->logger->info($message, $this->mergeContext($context));
    }

    public function warning(string $message, array $context = []): void
    {
        if (!$this->isEnabled() || !$this->logger) {
            return;
        }

        $this->logger->warning($message, $this->mergeContext($context));
    }

    public function error(string $message, array $context = []): void
    {
        if (!$this->logger) {
            return;
        }

        // 錯誤訊息總是記錄
        $this->logger->error($message, $this->mergeContext($context));
    }

    public function isEnabled(): bool
    {
        return CampaignConfig::DEBUG_MODE || (defined('WP_DEBUG') && WP_DEBUG);
    }

    /**
     * 合併預設 context
     */
    private function mergeContext(array $context): array
    {
        return array_merge($this->context, $context);
    }
}

