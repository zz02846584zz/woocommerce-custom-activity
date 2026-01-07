<?php
/**
 * 新年活動自動載入器
 * 負責載入所有必要的類別文件
 */

// 基礎目錄
$base_dir = __DIR__;

// 載入配置
require_once $base_dir . '/config/Constants.php';

// 載入引擎
require_once $base_dir . '/engine/CartAnalyzer.php';
require_once $base_dir . '/engine/ActivityEngine.php';

// 載入活動介面和基類
require_once $base_dir . '/activities/ActivityInterface.php';

// 載入所有活動類別
require_once $base_dir . '/activities/Activity1.php';
require_once $base_dir . '/activities/Activity2.php';
require_once $base_dir . '/activities/Activity3.php';
require_once $base_dir . '/activities/Activity4.php';
require_once $base_dir . '/activities/Activity5.php';
require_once $base_dir . '/activities/Activity6.php';
require_once $base_dir . '/activities/Activity7.php';

// 載入輔助類別
require_once $base_dir . '/gift/GiftManager.php';
require_once $base_dir . '/discount/SiteWideDiscount.php';
require_once $base_dir . '/display/CouponDisplay.php';

// 檢查活動期間
if ( ! NYB_Constants::is_campaign_active() ) {
    NYB_Constants::log( sprintf(
        "[新年活動期間檢查] 活動未啟用 | 當前時間: %s | 活動期間: %s ~ %s",
        current_time( 'mysql' ),
        NYB_Constants::CAMPAIGN_START,
        NYB_Constants::CAMPAIGN_END
    ) );
    return; // 停用所有功能
}

// 初始化全館9折
NYB_SiteWideDiscount::init();

// 初始化贈品管理
NYB_GiftManager::init();

// 初始化活動引擎
$nyb_engine = new NYB_ActivityEngine();

// 初始化優惠券顯示（需要傳入引擎實例）
$nyb_coupon_display = new NYB_CouponDisplay( $nyb_engine );
$nyb_coupon_display->init();

// 綁定活動檢測
add_action( 'woocommerce_before_calculate_totals', function( $cart ) use ( $nyb_engine ) {
    $nyb_engine->execute( $cart );
}, 10 );

/**
 * 向後兼容：提供舊的函數接口
 */

// 計算活動狀態
function nyb_calculate_activity_status( $product_id = 0 ) {
    global $nyb_engine;

    if ( $nyb_engine && method_exists( $nyb_engine, 'calculate_status' ) ) {
        return $nyb_engine->calculate_status( $product_id );
    }

    return [];
}

// 獲取相關活動
function nyb_get_related_activities( $product_id, $variation_id = 0 ) {
    global $nyb_engine;

    if ( $nyb_engine && method_exists( $nyb_engine, 'get_related_activities' ) ) {
        return $nyb_engine->get_related_activities( $product_id, $variation_id );
    }

    return [];
}

// 獲取活動描述
function nyb_get_activity_description( $activity_key ) {
    global $nyb_engine;

    if ( $nyb_engine ) {
        $activity = $nyb_engine->get_activity_by_code( $activity_key );
        if ( $activity ) {
            return $activity->get_description();
        }
    }

    return '';
}

// 獲取活動名稱
function nyb_get_activity_name( $activity_key ) {
    global $nyb_engine;

    if ( $nyb_engine ) {
        $activity = $nyb_engine->get_activity_by_code( $activity_key );
        if ( $activity ) {
            return $activity->get_name();
        }
    }

    return '新年優惠活動';
}

// 日誌函數
function nyb_log( $message, $context = [] ) {
    NYB_Constants::log( $message, $context );
}

