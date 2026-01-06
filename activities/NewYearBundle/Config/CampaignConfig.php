<?php

namespace CustomActivity\NewYearBundle\Config;

/**
 * 活動配置類別
 * 集中管理所有活動相關的常數與配置
 */
final class CampaignConfig
{
    // 活動期間
    public const CAMPAIGN_START = '2025-01-05 00:00:00';
    public const CAMPAIGN_END = '2026-02-28 23:59:59';

    // 除錯模式
    public const DEBUG_MODE = true;

    // 床墊相關 ID
    public const LAI_MATTRESS_PARENT_IDS = [3444];
    public const LAI_MATTRESS_VARS = [3446, 3445, 3447, 3448, 3695, 3696];

    public const SPRING_MATTRESS_PARENT_IDS = [1324, 4370];
    public const SPRING_MATTRESS_VARS = [
        2735, 2736, 2737, 2738, 2739,      // 嗜睡床墊(大地系列)
        4371, 4372, 4373, 4374, 4375       // 嗜睡床墊(海洋系列)
    ];

    // 床墊尺寸對應天絲床包價值
    public const BEDDING_VALUE_MAP = [
        2735 => 3680,  // 單人
        4371 => 3680,
        2736 => 3880,  // 單人加大
        4372 => 3880,
        2737 => 4580,  // 雙人
        4373 => 4580,
        2738 => 4780,  // 雙人加大
        4374 => 4780,
        2739 => 4980,  // 雙人特大
        4375 => 4980,
    ];

    // 催眠枕
    public const HYPNOTIC_PILLOW_PARENT = 1307;
    public const HYPNOTIC_PILLOW_VARS = [2983, 2984, 3044];

    // 床架
    public const BED_FRAME_PARENT = 4421;
    public const BED_FRAME_IDS = [4930, 4929, 4422, 4423, 4424, 4425, 4426];

    // 贈品
    public const GIFT_FLEECE_BLANKET = 4180;  // 茸茸被
    public const GIFT_HUG_PILLOW = 6346;      // 抱枕
    public const GIFT_EYE_MASK = 6300;        // 眼罩
    public const GIFT_SIDE_PILLOW_VAR = 3044; // 側睡枕variation

    // 天絲枕套對應 (枕頭 -> 枕套)
    public const PILLOWCASE_MAP = [
        2983 => 4439,
        2984 => 5663,
        3044 => 5662
    ];

    // 活動3特價組合價格
    public const COMBO_SPECIAL_PRICE = 8888;

    // 所有贈品ID集合
    public const ALL_GIFT_IDS = [
        self::GIFT_FLEECE_BLANKET,
        self::GIFT_HUG_PILLOW,
        self::GIFT_EYE_MASK,
        self::HYPNOTIC_PILLOW_PARENT,
        4439, 5663, 5662  // 天絲枕套
    ];

    /**
     * 取得 Hash Map（用於 O(1) 查詢）
     */
    private static ?array $hashMaps = null;

    public static function getHashMap(string $key): array
    {
        if (self::$hashMaps === null) {
            self::$hashMaps = [
                'lai_mattress_parent' => array_flip(self::LAI_MATTRESS_PARENT_IDS),
                'lai_mattress_vars' => array_flip(self::LAI_MATTRESS_VARS),
                'spring_mattress_parent' => array_flip(self::SPRING_MATTRESS_PARENT_IDS),
                'spring_mattress_vars' => array_flip(self::SPRING_MATTRESS_VARS),
                'hypnotic_pillow_vars' => array_flip(self::HYPNOTIC_PILLOW_VARS),
                'bed_frame_ids' => array_flip(self::BED_FRAME_IDS),
                'all_gift_ids' => array_flip(self::ALL_GIFT_IDS),
            ];
        }

        return self::$hashMaps[$key] ?? [];
    }

    /**
     * 檢查是否在活動期間
     */
    public static function isActivePeriod(): bool
    {
        $current_time = current_time('mysql');
        return $current_time >= self::CAMPAIGN_START && $current_time <= self::CAMPAIGN_END;
    }
}

