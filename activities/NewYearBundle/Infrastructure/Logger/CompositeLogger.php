<?php

namespace CustomActivity\NewYearBundle\Infrastructure\Logger;

use CustomActivity\NewYearBundle\Domain\Service\LoggerInterface;

/**
 * 組合日誌記錄器
 * 可同時使用多個 Logger
 */
final class CompositeLogger implements LoggerInterface
{
    /**
     * @var LoggerInterface[]
     */
    private array $loggers = [];

    /**
     * @param LoggerInterface[] $loggers
     */
    public function __construct(array $loggers)
    {
        $this->loggers = $loggers;
    }

    public function debug(string $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->debug($message, $context);
        }
    }

    public function info(string $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->info($message, $context);
        }
    }

    public function warning(string $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->warning($message, $context);
        }
    }

    public function error(string $message, array $context = []): void
    {
        foreach ($this->loggers as $logger) {
            $logger->error($message, $context);
        }
    }

    public function isEnabled(): bool
    {
        foreach ($this->loggers as $logger) {
            if ($logger->isEnabled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 新增 Logger
     */
    public function addLogger(LoggerInterface $logger): void
    {
        $this->loggers[] = $logger;
    }
}

