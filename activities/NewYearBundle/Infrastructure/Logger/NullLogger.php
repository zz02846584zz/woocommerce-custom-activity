<?php

namespace CustomActivity\NewYearBundle\Infrastructure\Logger;

use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;

/**
 * 空日誌記錄器
 * 不執行任何操作，用於測試或停用日誌
 */
final class NullLogger implements LoggerInterface
{
    public function debug(string $message, array $context = []): void
    {
        // 不執行任何操作
    }

    public function info(string $message, array $context = []): void
    {
        // 不執行任何操作
    }

    public function warning(string $message, array $context = []): void
    {
        // 不執行任何操作
    }

    public function error(string $message, array $context = []): void
    {
        // 不執行任何操作
    }

    public function isEnabled(): bool
    {
        return false;
    }
}

