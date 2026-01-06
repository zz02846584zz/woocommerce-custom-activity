<?php

namespace CustomActivity\NewYearBundle\Domain\ValueObject;

/**
 * 活動狀態值物件
 * 不可變物件，代表活動的符合狀態
 */
final class ActivityStatus
{
    public const QUALIFIED = 'qualified';
    public const ALMOST = 'almost';
    public const NOT_QUALIFIED = 'not_qualified';

    private string $status;
    private array $missing;

    private function __construct(string $status, array $missing = [])
    {
        $this->validateStatus($status);
        $this->status = $status;
        $this->missing = $missing;
    }

    public static function qualified(): self
    {
        return new self(self::QUALIFIED, []);
    }

    public static function almost(array $missing): self
    {
        return new self(self::ALMOST, $missing);
    }

    public static function notQualified(array $missing): self
    {
        return new self(self::NOT_QUALIFIED, $missing);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMissing(): array
    {
        return $this->missing;
    }

    public function isQualified(): bool
    {
        return $this->status === self::QUALIFIED;
    }

    public function isAlmost(): bool
    {
        return $this->status === self::ALMOST;
    }

    public function isNotQualified(): bool
    {
        return $this->status === self::NOT_QUALIFIED;
    }

    private function validateStatus(string $status): void
    {
        $validStatuses = [self::QUALIFIED, self::ALMOST, self::NOT_QUALIFIED];

        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'missing' => $this->missing
        ];
    }
}
