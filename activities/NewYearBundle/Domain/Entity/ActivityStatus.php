<?php
/**
 * 活動狀態值對象
 *
 * 代表單一活動的符合狀態與缺少項目
 * PHP 8.0 相容版本（不使用 readonly）
 */

namespace NewYearBundle\Domain\Entity;

use NewYearBundle\Domain\Enum\ActivityStatusEnum;

class ActivityStatus
{
    public string $status;   // qualified/almost/not_qualified
    public array $missing;   // 缺少的項目列表

    public function __construct(string $status, array $missing = [])
    {
        if (!ActivityStatusEnum::isValid($status)) {
            throw new \InvalidArgumentException("Invalid activity status: {$status}");
        }

        $this->status = $status;
        $this->missing = $missing;
    }

    /**
     * 創建已符合資格的狀態
     */
    public static function qualified(): self
    {
        return new self(ActivityStatusEnum::QUALIFIED, []);
    }

    /**
     * 創建差一點符合的狀態
     */
    public static function almost(array $missing): self
    {
        return new self(ActivityStatusEnum::ALMOST, $missing);
    }

    /**
     * 創建不符合資格的狀態
     */
    public static function notQualified(array $missing): self
    {
        return new self(ActivityStatusEnum::NOT_QUALIFIED, $missing);
    }

    /**
     * 檢查是否已符合資格
     */
    public function isQualified(): bool
    {
        return $this->status === ActivityStatusEnum::QUALIFIED;
    }

    /**
     * 檢查是否差一點符合
     */
    public function isAlmost(): bool
    {
        return $this->status === ActivityStatusEnum::ALMOST;
    }

    /**
     * 檢查是否不符合資格
     */
    public function isNotQualified(): bool
    {
        return $this->status === ActivityStatusEnum::NOT_QUALIFIED;
    }

    /**
     * 轉換為陣列格式（向後兼容原格式）
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'missing' => $this->missing,
        ];
    }
}

