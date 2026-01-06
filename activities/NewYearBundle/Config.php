<?php
/**
 * 新年活動配置類
 *
 * 包裝原本的 define() 常數，提供類型安全的訪問介面
 * 遵循 YAGNI 原則：保持簡單，只做必要的封裝
 */

namespace NewYearBundle;

class Config
{
    // ==================== 活動期間 ====================

    public static function getCampaignStart(): string
    {
        return '2025-01-05 00:00:00';
    }

    public static function getCampaignEnd(): string
    {
        return '2026-02-28 23:59:59';
    }

    public static function isDebugMode(): bool
    {
        return true;
    }

    // ==================== 床墊相關 ====================

    /**
     * 賴床墊父層ID
     * @return int[]
     */
    public static function getLaiMattressParentIds(): array
    {
        return [3444];
    }

    /**
     * 嗜睡床墊父層ID
     * @return int[]
     */
    public static function getSpringMattressParentIds(): array
    {
        return [1324, 4370];
    }

    /**
     * 賴床墊變體ID
     * @return int[]
     */
    public static function getLaiMattressVars(): array
    {
        return [3446, 3445, 3447, 3448, 3695, 3696];
    }

    /**
     * 嗜睡床墊變體ID
     * @return int[]
     */
    public static function getSpringMattressVars(): array
    {
        return [
            2735, 2736, 2737, 2738, 2739,      // 嗜睡床墊(大地系列)
            4371, 4372, 4373, 4374, 4375       // 嗜睡床墊(海洋系列)
        ];
    }

    // ==================== Hash Maps (性能優化) ====================

    /**
     * @return array<int, int>
     */
    public static function getLaiMattressParentIdsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getLaiMattressParentIds());
        }
        return $map;
    }

    /**
     * @return array<int, int>
     */
    public static function getSpringMattressParentIdsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getSpringMattressParentIds());
        }
        return $map;
    }

    /**
     * @return array<int, int>
     */
    public static function getLaiMattressVarsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getLaiMattressVars());
        }
        return $map;
    }

    /**
     * @return array<int, int>
     */
    public static function getSpringMattressVarsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getSpringMattressVars());
        }
        return $map;
    }

    // ==================== 床墊尺寸對應天絲床包價值 ====================

    /**
     * @return array<int, int>
     */
    public static function getBeddingValueMap(): array
    {
        return [
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
    }

    // ==================== 催眠枕相關 ====================

    public static function getHypnoticPillowParent(): int
    {
        return 1307;
    }

    /**
     * @return int[]
     */
    public static function getHypnoticPillowVars(): array
    {
        return [2983, 2984, 3044];
    }

    /**
     * @return array<int, int>
     */
    public static function getHypnoticPillowVarsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getHypnoticPillowVars());
        }
        return $map;
    }

    // ==================== 床架相關 ====================

    public static function getBedFrameParent(): int
    {
        return 4421;
    }

    /**
     * @return int[]
     */
    public static function getBedFrameIds(): array
    {
        return [4930, 4929, 4422, 4423, 4424, 4425, 4426];
    }

    /**
     * @return array<int, int>
     */
    public static function getBedFrameIdsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getBedFrameIds());
        }
        return $map;
    }

    // ==================== 贈品相關 ====================

    public static function getGiftFleeceBlanket(): int
    {
        return 4180;
    }

    public static function getGiftHugPillow(): int
    {
        return 6346;
    }

    public static function getGiftEyeMask(): int
    {
        return 6300;
    }

    public static function getGiftSidePillowVar(): int
    {
        return 3044;
    }

    // ==================== 天絲枕套對應 ====================

    /**
     * 枕頭 -> 枕套對應
     * @return array<int, int>
     */
    public static function getPillowcaseMap(): array
    {
        return [
            2983 => 4439,
            2984 => 5663,
            3044 => 5662
        ];
    }

    // ==================== 活動相關 ====================

    public static function getComboSpecialPrice(): int
    {
        return 8888;
    }

    /**
     * 所有贈品ID集合（用於排除9折）
     * @return int[]
     */
    public static function getAllGiftIds(): array
    {
        return [
            self::getGiftFleeceBlanket(),
            self::getGiftHugPillow(),
            self::getGiftEyeMask(),
            self::getHypnoticPillowParent(), // 枕頭父層（BOGO贈品）
            4439, 5663, 5662                 // 天絲枕套
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function getAllGiftIdsMap(): array
    {
        static $map = null;
        if ($map === null) {
            $map = array_flip(self::getAllGiftIds());
        }
        return $map;
    }

    // ==================== 床墊尺寸名稱對應 ====================

    /**
     * @return array<int, string>
     */
    public static function getMattressSizeNameMap(): array
    {
        return [
            2735 => '單人',
            4371 => '單人',
            2736 => '單人加大',
            4372 => '單人加大',
            2737 => '雙人',
            4373 => '雙人',
            2738 => '雙人加大',
            4374 => '雙人加大',
            2739 => '雙人特大',
            4375 => '雙人特大',
        ];
    }

    // ==================== 活動名稱與描述 ====================

    /**
     * @return array<string, string>
     */
    public static function getActivityDescriptions(): array
    {
        return [
            'activity_1' => '嗜睡床墊任一張+催眠枕任一顆，再送茸茸被一件',
            'activity_2' => '買賴床墊，送抱枕+眼罩',
            'activity_3' => '枕頭任選2顆 $8888再加碼贈天絲枕套2個',
            'activity_4' => '（買一送一），買催眠枕送天絲枕套一件',
            'activity_5' => '床墊+催眠枕*2+賴床墊，送天絲床包四件組（1床包+2枕套+1被套）',
            'activity_6' => '床墊+床架送側睡枕',
            'activity_7' => '床墊+床架+枕頭*2，送天絲床包四件組（1床包+2枕套+1被套）+ 兩用茸茸被'
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getActivityNames(): array
    {
        return [
            'activity_1' => '嗜睡床墊任一張+催眠枕任一顆，送茸茸被',
            'activity_2' => '買賴床墊，送抱枕+眼罩',
            'activity_3' => '枕頭任選2顆 $8,888再加碼贈天絲枕套2個',
            'activity_4' => '買催眠枕送天絲枕套（買一送一）',
            'activity_5' => '床墊+催眠枕*2+賴床墊，送天絲床包四件組',
            'activity_6' => '床墊+床架送側睡枕',
            'activity_7' => '床墊+床架+枕頭*2，送天絲四件組床包+茸茸被'
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getActivityCouponCodes(): array
    {
        return [
            'activity_1' => 'nyb_activity_1',
            'activity_2' => 'nyb_activity_2',
            'activity_3' => 'nyb_activity_3',
            'activity_4' => 'nyb_activity_4',
            'activity_5' => 'nyb_activity_5',
            'activity_6' => 'nyb_activity_6',
            'activity_7' => 'nyb_activity_7'
        ];
    }
}

