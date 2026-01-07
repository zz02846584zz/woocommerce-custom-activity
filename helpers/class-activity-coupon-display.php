<?php
// helpers/class-activity-coupon-display.php

/**
 * 活動優惠券顯示類別
 *
 * 職責：
 * - 創建虛擬優惠券
 * - 同步購物車中的活動優惠券
 * - 管理優惠券顯示樣式
 * - 防止手動移除活動優惠券
 */
class NYB_Activity_Coupon_Display {

    /**
     * 優惠券前綴常數
     */
    const COUPON_PREFIX = 'nyb_activity_';

    /**
     * 初始化鉤子
     */
    public static function init() {
        add_filter( 'woocommerce_get_shop_coupon_data', [ __CLASS__, 'create_virtual_coupon' ], 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'sync_coupons' ], 99 );
        add_filter( 'woocommerce_cart_totals_coupon_html', [ __CLASS__, 'prevent_removal' ], 10, 3 );
        add_filter( 'woocommerce_remove_cart_discount', [ __CLASS__, 'block_removal' ], 10, 2 );
        add_filter( 'woocommerce_coupon_message', [ __CLASS__, 'hide_success_message' ], 10, 3 );
        add_action( 'wp_head', [ __CLASS__, 'render_styles' ], 20 );
    }

    /**
     * 檢查是否為活動優惠券
     * @param string $code 優惠券代碼
     * @return bool
     */
    private static function is_activity_coupon( $code ) {
        return strpos( $code, self::COUPON_PREFIX ) === 0;
    }

    /**
     * 獲取所有活動優惠券映射
     * @return array [activity_key => coupon_code]
     */
    private static function get_activity_coupon_map() {
        if ( ! defined( 'NYB_ACTIVITY_MAP' ) ) {
            return [];
        }

        $map = [];
        foreach ( NYB_ACTIVITY_MAP as $key => $data ) {
            if ( isset( $data['coupon_code'] ) ) {
                $map[ $key ] = $data['coupon_code'];
            }
        }
        return $map;
    }

    /**
     * 創建虛擬優惠券數據
     * @param mixed $data 優惠券數據
     * @param string $code 優惠券代碼
     * @return array|mixed
     */
    public static function create_virtual_coupon( $data, $code ) {
        if ( ! self::is_activity_coupon( $code ) ) {
            return $data;
        }

        return [
            'discount_type' => 'fixed_cart',
            'amount'        => 0
        ];
    }

    /**
     * 同步購物車中的活動優惠券
     * 根據實際應用的活動自動添加或移除優惠券
     *
     * @param WC_Cart $cart 購物車對象
     */
    public static function sync_coupons( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 3 ) {
            return;
        }

        $applied_coupons = $cart->get_applied_coupons();

        // 檢查是否有外部優惠券（非活動優惠券）
        $has_external_coupon = false;
        foreach ( $applied_coupons as $coupon_code ) {
            if ( ! self::is_activity_coupon( $coupon_code ) ) {
                $has_external_coupon = true;
                break;
            }
        }

        // 如果有外部優惠券，移除所有活動優惠券
        if ( $has_external_coupon ) {
            foreach ( $applied_coupons as $coupon_code ) {
                if ( self::is_activity_coupon( $coupon_code ) ) {
                    $cart->remove_coupon( $coupon_code );
                }
            }
            return;
        }

        // 獲取實際應用的活動（使用與贈品應用相同的邏輯）
        if ( ! function_exists( 'nyb_get_actually_applied_activities' ) ) {
            return;
        }

        $applied_activities = nyb_get_actually_applied_activities();
        $activity_coupon_map = self::get_activity_coupon_map();

        // 計算應該有的優惠券
        $should_have_coupons = [];
        foreach ( $applied_activities as $activity_key ) {
            if ( isset( $activity_coupon_map[ $activity_key ] ) ) {
                $should_have_coupons[] = $activity_coupon_map[ $activity_key ];
            }
        }

        // 添加缺少的優惠券
        foreach ( $should_have_coupons as $coupon_code ) {
            if ( ! $cart->has_discount( $coupon_code ) ) {
                $cart->apply_coupon( $coupon_code );
            }
        }

        // 移除多餘的優惠券
        foreach ( $activity_coupon_map as $coupon_code ) {
            if ( ! in_array( $coupon_code, $should_have_coupons, true )
                && $cart->has_discount( $coupon_code ) ) {
                $cart->remove_coupon( $coupon_code );
            }
        }
    }

    /**
     * 自訂優惠券顯示 HTML（防止移除按鈕出現）
     * @param string $html 原始 HTML
     * @param WC_Coupon $coupon 優惠券對象
     * @param string $discount_amount_html 折扣金額 HTML
     * @return string
     */
    public static function prevent_removal( $html, $coupon, $discount_amount_html ) {
        $code = $coupon->get_code();

        if ( ! self::is_activity_coupon( $code ) ) {
            return $html;
        }

        // 根據優惠券代碼獲取活動名稱
        $name = self::get_coupon_display_name( $code );

        // 返回自訂 HTML 結構（包裹在 th 標籤中，隱藏移除按鈕）
        return '<th colspan="2">' .
                 '<div class="nyb-activity-coupon-display">' .
                 '<span class="nyb-activity-icon">🎁</span>' .
                 '<span class="nyb-activity-name">' . esc_html( $name ) . '</span>' .
                 '<span class="nyb-activity-tag">已套用</span>' .
                 '</div>' .
                 '</th>';
    }

    /**
     * 獲取優惠券顯示名稱
     * @param string $coupon_code 優惠券代碼
     * @return string
     */
    private static function get_coupon_display_name( $coupon_code ) {
        if ( ! function_exists( 'nyb_get_activity_key_by_coupon' ) ||
             ! function_exists( 'nyb_get_activity_name' ) ) {
            return '新年優惠活動';
        }

        $activity_key = nyb_get_activity_key_by_coupon( $coupon_code );
        if ( ! $activity_key ) {
            return '新年優惠活動';
        }

        return nyb_get_activity_name( $activity_key, 'full' );
    }

    /**
     * 阻止手動移除活動優惠券
     * @param bool $can_remove 是否可移除
     * @param string $code 優惠券代碼
     * @return bool
     */
    public static function block_removal( $can_remove, $code ) {
        if ( self::is_activity_coupon( $code ) ) {
            return false;
        }
        return $can_remove;
    }

    /**
     * 隱藏活動優惠券的成功訊息
     * @param string $message 訊息內容
     * @param int $message_code 訊息代碼
     * @param WC_Coupon|null $coupon 優惠券對象
     * @return string
     */
    public static function hide_success_message( $message, $message_code, $coupon ) {
        // 檢查是否為活動優惠券
        if ( $coupon && self::is_activity_coupon( $coupon->get_code() ) ) {
            // 如果是套用成功的訊息 (WC_Coupon::WC_COUPON_SUCCESS)
            if ( $message_code === WC_Coupon::WC_COUPON_SUCCESS ) {
                return ''; // 返回空字串，不顯示訊息
            }
        }
        return $message;
    }

    /**
     * 輸出活動優惠券顯示樣式
     */
    public static function render_styles() {
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }

        $coupon_selectors = self::generate_coupon_selectors();

        ?>
        <style>
            /* 隱藏活動優惠券的左側標籤 */
            <?php echo $coupon_selectors; ?> {
                display: none;
            }

            /* 活動優惠券顯示樣式 */
            .nyb-activity-coupon-display {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 0;
                margin: -16px 0 -8px;
            }

            .woocommerce-checkout .nyb-activity-coupon-display {
                margin: -20px 0 -20px -0.7em;
            }

            .nyb-activity-icon {
                font-size: 20px;
            }

            .nyb-activity-name {
                flex: 1;
                font-weight: bold;
                color: #4a9d6f;
            }

            .nyb-activity-tag {
                background: #83bd9a;
                color: white;
                padding: 3px 10px;
                border-radius: 15px;
                font-size: 11px;
                font-weight: bold;
            }

            /* 手機版適配 */
            @media (max-width: 768px) {
                .nyb-activity-coupon-display {
                    gap: 8px;
                }

                .nyb-activity-name {
                    font-size: 13px;
                }

                .nyb-activity-tag {
                    font-size: 10px;
                    padding: 2px 8px;
                }
            }
        </style>
        <?php
    }

    /**
     * 動態生成優惠券選擇器（根據活動映射）
     * @return string CSS 選擇器
     */
    private static function generate_coupon_selectors() {
        $activity_coupon_map = self::get_activity_coupon_map();
        $selectors = [];

        foreach ( $activity_coupon_map as $coupon_code ) {
            $selectors[] = ".cart-discount.coupon-{$coupon_code} th:first-child";
            $selectors[] = ".cart-discount.coupon-{$coupon_code} td:empty";
        }

        return implode( ',', $selectors );
    }
}