<?php
/**
 * 購物車快照值對象
 *
 * Immutable Value Object - 代表購物車在某一時刻的狀態
 * PHP 8.0 相容版本（不使用 readonly）
 */

namespace NewYearBundle\Domain\Entity;

class CartSnapshot
{
    public int $springMattressCount;      // 嗜睡床墊數量
    public int $laiMattressCount;         // 賴床墊數量
    public int $hypnoticPillowCount;      // 催眠枕總數
    public int $hypnoticPillowCountOther; // 其他催眠枕數量
    public int $hypnoticPillowCountHigh;  // 高枕數量
    public int $bedFrameCount;            // 床架數量
    public array $hypnoticPillowVars;     // 催眠枕變體統計 [var_id => count]
    public array $mattressVars;           // 床墊變體統計

    public function __construct(
        int $springMattressCount,
        int $laiMattressCount,
        int $hypnoticPillowCount,
        int $hypnoticPillowCountOther,
        int $hypnoticPillowCountHigh,
        int $bedFrameCount,
        array $hypnoticPillowVars,
        array $mattressVars
    ) {
        $this->springMattressCount = $springMattressCount;
        $this->laiMattressCount = $laiMattressCount;
        $this->hypnoticPillowCount = $hypnoticPillowCount;
        $this->hypnoticPillowCountOther = $hypnoticPillowCountOther;
        $this->hypnoticPillowCountHigh = $hypnoticPillowCountHigh;
        $this->bedFrameCount = $bedFrameCount;
        $this->hypnoticPillowVars = $hypnoticPillowVars;
        $this->mattressVars = $mattressVars;
    }

    /**
     * 創建空快照
     */
    public static function empty(): self
    {
        return new self(0, 0, 0, 0, 0, 0, [], []);
    }

    /**
     * 檢查是否為空購物車
     */
    public function isEmpty(): bool
    {
        return $this->springMattressCount === 0
            && $this->laiMattressCount === 0
            && $this->hypnoticPillowCount === 0
            && $this->bedFrameCount === 0;
    }

    /**
     * 獲取總床墊數量
     */
    public function getTotalMattressCount(): int
    {
        return $this->springMattressCount + $this->laiMattressCount;
    }

    /**
     * 轉換為陣列（用於日誌或調試）
     */
    public function toArray(): array
    {
        return [
            'spring_mattress_count' => $this->springMattressCount,
            'lai_mattress_count' => $this->laiMattressCount,
            'hypnotic_pillow_count' => $this->hypnoticPillowCount,
            'hypnotic_pillow_count_other' => $this->hypnoticPillowCountOther,
            'hypnotic_pillow_count_high' => $this->hypnoticPillowCountHigh,
            'bed_frame_count' => $this->bedFrameCount,
            'hypnotic_pillow_vars' => $this->hypnoticPillowVars,
            'mattress_vars' => $this->mattressVars,
        ];
    }
}

