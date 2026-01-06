<?php

namespace CustomActivity\NewYearBundle\Domain\Service;

/**
 * Logger 介面
 * 定義日誌記錄的契約
 */
interface LoggerInterface
{
    /**
     * 記錄除錯訊息
     */
    public function debug(string $message, array $context = []): void;

    /**
     * 記錄資訊訊息
     */
    public function info(string $message, array $context = []): void;

    /**
     * 記錄警告訊息
     */
    public function warning(string $message, array $context = []): void;

    /**
     * 記錄錯誤訊息
     */
    public function error(string $message, array $context = []): void;

    /**
     * 檢查是否啟用日誌
     */
    public function isEnabled(): bool;
}

