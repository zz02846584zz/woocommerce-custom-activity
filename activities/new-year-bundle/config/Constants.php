<?php
/**
 * 新年活動常數定義
 * 單一職責：管理所有活動相關的常數
 */
class NYB_Constants {

    // 活動期間
    const CAMPAIGN_START = '2025-01-05 00:00:00';
    const CAMPAIGN_END = '2026-02-28 23:59:59';

    // 日誌開關
    const DEBUG_MODE = true;

    // 床墊相關
    const LAI_MATTRESS_PARENT_IDS = [3444];
    const SPRING_MATTRESS_PARENT_IDS = [1324, 4370];

    const LAI_MATTRESS_VARS = [3446, 3445, 3447, 3448, 3695, 3696];
    const SPRING_MATTRESS_VARS = [
        2735, 2736, 2737, 2738, 2739,      // 嗜睡床墊(大地系列)
        4371, 4372, 4373, 4374, 4375       // 嗜睡床墊(海洋系列)
    ];

    // 床墊尺寸對應天絲床包價值
    const BEDDING_VALUE_MAP = [
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
    const HYPNOTIC_PILLOW_PARENT = 1307;
    const HYPNOTIC_PILLOW_VARS = [2983, 2984, 3044];

    // 床架
    const BED_FRAME_PARENT = 4421;
    const BED_FRAME_IDS = [4930, 4929, 4422, 4423, 4424, 4425, 4426];

    // 贈品
    const GIFT_FLEECE_BLANKET = 4180;
    const GIFT_HUG_PILLOW = 6346;
    const GIFT_EYE_MASK = 6300;
    const GIFT_SIDE_PILLOW_VAR = 3044;

    // 天絲枕套對應 (枕頭 -> 枕套)
    const PILLOWCASE_MAP = [
        2983 => 4439,
        2984 => 5663,
        3044 => 5662
    ];

    // 活動3特價組合價格
    const COMBO_SPECIAL_PRICE = 8888;

    // 所有贈品ID集合
    const ALL_GIFT_IDS = [
        self::GIFT_FLEECE_BLANKET,
        self::GIFT_HUG_PILLOW,
        self::GIFT_EYE_MASK,
        self::HYPNOTIC_PILLOW_PARENT,
        4439, 5663, 5662  // 天絲枕套
    ];

    // Hash Map 快取（靜態屬性）
    private static $hash_maps = null;

    /**
     * 獲取 Hash Map（O(1) 查詢）
     */
    public static function get_hash_maps() {
        if ( self::$hash_maps === null ) {
            self::$hash_maps = [
                'lai_mattress_parent' => array_flip( self::LAI_MATTRESS_PARENT_IDS ),
                'spring_mattress_parent' => array_flip( self::SPRING_MATTRESS_PARENT_IDS ),
                'lai_mattress_vars' => array_flip( self::LAI_MATTRESS_VARS ),
                'spring_mattress_vars' => array_flip( self::SPRING_MATTRESS_VARS ),
                'hypnotic_pillow_vars' => array_flip( self::HYPNOTIC_PILLOW_VARS ),
                'bed_frame_ids' => array_flip( self::BED_FRAME_IDS ),
                'all_gift_ids' => array_flip( self::ALL_GIFT_IDS ),
            ];
        }
        return self::$hash_maps;
    }

    /**
     * 檢查是否在活動期間
     */
    public static function is_campaign_active() {
        $current_time = current_time( 'mysql' );
        return $current_time >= self::CAMPAIGN_START && $current_time <= self::CAMPAIGN_END;
    }

    /**
     * 日誌記錄函數
     */
    public static function log( $message, $context = [] ) {
        if ( ! self::DEBUG_MODE && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
            return;
        }

        $log_file = WP_CONTENT_DIR . '/newyear-bundle.log';
        $timestamp = current_time('Y-m-d H:i:s');
        error_log("[{$timestamp}] {$message}\n", 3, $log_file);
    }
}

