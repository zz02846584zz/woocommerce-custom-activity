<?php
/**
 * 活動狀態枚舉
 */

namespace NewYearBundle\Domain\Enum;

class ActivityStatusEnum
{
    public const QUALIFIED = 'qualified';         // 已符合資格
    public const ALMOST = 'almost';               // 差一點符合
    public const NOT_QUALIFIED = 'not_qualified'; // 不符合資格

    /**
     * 檢查是否為有效狀態
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, [
            self::QUALIFIED,
            self::ALMOST,
            self::NOT_QUALIFIED,
        ], true);
    }
}

