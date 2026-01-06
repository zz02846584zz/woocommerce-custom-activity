<?php
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'activities/new-year-bundle-active/helpers/class-activity-coupon-display.php';
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'activities/new-year-bundle-active/helpers/class-virtual-bedding-product.php';
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'activities/new-year-bundle-active/helpers/class-campaign-rule-engine.php';
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'activities/new-year-bundle-active/helpers/class-cart-campaign-listener.php';
require_once CUSTOM_ACTIVITY_PLUGIN_DIR . 'activities/new-year-bundle-active/helpers/class-campaign-debugger.php';

NYB_Activity_Coupon_Display::init();
NYB_Virtual_Bedding_Product::init();
NYB_Cart_Campaign_Listener::init();

// 活動期間
define( 'NYB_CAMPAIGN_START', '2025-01-05 00:00:00' );
define( 'NYB_CAMPAIGN_END', '2026-02-28 23:59:59' );

// 日誌開關（生產環境建議設為 false）
define( 'NYB_DEBUG_MODE', true );

// 床墊相關
define( 'NYB_LAI_MATTRESS_PARENT_IDS', [3444] ); // 所有賴床墊父層ID
define( 'NYB_SPRING_MATTRESS_PARENT_IDS', [1324, 4370] ); // 所有嗜睡床墊父層ID

define( 'NYB_LAI_MATTRESS_VARS', [3446, 3445, 3447, 3448, 3695, 3696] ); // 賴床墊

define( 'NYB_SPRING_MATTRESS_VARS', [
    2735, 2736, 2737, 2738, 2739,      // 嗜睡床墊(大地系列)
    4371, 4372, 4373, 4374, 4375       // 嗜睡床墊(海洋系列)
] );

// ⚡ 性能優化：Hash Map (O(1) 查詢速度)
define( 'NYB_LAI_MATTRESS_PARENT_IDS_MAP', array_flip( NYB_LAI_MATTRESS_PARENT_IDS ) );
define( 'NYB_SPRING_MATTRESS_PARENT_IDS_MAP', array_flip( NYB_SPRING_MATTRESS_PARENT_IDS ) );
define( 'NYB_LAI_MATTRESS_VARS_MAP', array_flip( NYB_LAI_MATTRESS_VARS ) );
define( 'NYB_SPRING_MATTRESS_VARS_MAP', array_flip( NYB_SPRING_MATTRESS_VARS ) );

// 嗜睡床墊尺寸對應天絲床包價值
define( 'NYB_BEDDING_VALUE_MAP', [
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
] );

// 催眠枕
define( 'NYB_HYPNOTIC_PILLOW_PARENT', 1307 );
define( 'NYB_HYPNOTIC_PILLOW_VARS', [2983, 2984, 3044] );
define( 'NYB_HYPNOTIC_PILLOW_VARS_MAP', array_flip( NYB_HYPNOTIC_PILLOW_VARS ) );

// 床架
define( 'NYB_BED_FRAME_PARENT', 4421 );
define( 'NYB_BED_FRAME_IDS', [4930, 4929, 4422, 4423, 4424, 4425, 4426] );
define( 'NYB_BED_FRAME_IDS_MAP', array_flip( NYB_BED_FRAME_IDS ) );

// 贈品
define( 'NYB_GIFT_FLEECE_BLANKET', 4180 );  // 兩用茸茸被
define( 'NYB_GIFT_HUG_PILLOW', 6346 );      // 抱枕
define( 'NYB_GIFT_EYE_MASK', 6300 );        // 眼罩
define( 'NYB_GIFT_SIDE_PILLOW_VAR', 3044 ); // 側睡枕variation

// 天絲枕套對應 (枕頭 -> 枕套)
define( 'NYB_PILLOWCASE_MAP', [
    2983 => 4439,
    2984 => 5663,
    3044 => 5662
] );

// 活動3特價組合價格
define( 'NYB_COMBO_SPECIAL_PRICE', 8888 );

// 全館折扣券代碼（與活動互斥）
define( 'NYB_GLOBAL_DISCOUNT_COUPONS', [
    'GLOBAL10',      // 全館9折
    'SITEWIDE10',    // 全站折扣
    'ALL10OFF',      // 全館優惠
] );