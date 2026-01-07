<?php
/**
 * 優惠券顯示管理類
 * 負責虛擬優惠券的創建、同步、顯示與樣式
 */
class NYB_CouponDisplay {

    /**
     * 活動代碼與優惠券代碼對應表
     */
    const ACTIVITY_COUPON_MAP = [
        'activity_1' => 'nyb_activity_1',
        'activity_2' => 'nyb_activity_2',
        'activity_3' => 'nyb_activity_3',
        'activity_4' => 'nyb_activity_4',
        'activity_5' => 'nyb_activity_5',
        'activity_6' => 'nyb_activity_6',
        'activity_7' => 'nyb_activity_7'
    ];

    /**
     * 優惠券顯示名稱對應表
     */
    const ACTIVITY_NAMES = [
        'nyb_activity_1' => '嗜睡床墊+催眠枕，送茸茸被',
        'nyb_activity_2' => '賴床墊送抱枕+眼罩',
        'nyb_activity_3' => '催眠枕任選2顆特價$8,888',
        'nyb_activity_4' => '買枕頭送枕套',
        'nyb_activity_5' => '嗜睡床墊+催眠枕*2+賴床墊，送天絲四件組床包',
        'nyb_activity_6' => '嗜睡床墊+床架，送側睡枕',
        'nyb_activity_7' => '嗜睡床墊+床架+催眠枕*2，送天絲四件組床包+茸茸被'
    ];

    /**
     * ActivityEngine 實例
     * @var NYB_ActivityEngine
     */
    private $engine;

    /**
     * 建構子
     * @param NYB_ActivityEngine $engine
     */
    public function __construct( $engine ) {
        $this->engine = $engine;
    }

    /**
     * 初始化 Hook
     */
    public function init() {
        // 創建虛擬優惠券
        add_filter( 'woocommerce_get_shop_coupon_data', [ $this, 'create_virtual_coupon' ], 10, 2 );

        // 同步優惠券
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'sync_coupons' ], 99 );

        // 顯示優惠券（禁止移除）
        add_filter( 'woocommerce_cart_totals_coupon_html', [ $this, 'render_coupon_html' ], 10, 3 );

        // 禁止移除
        add_filter( 'woocommerce_remove_cart_discount', [ $this, 'prevent_removal' ], 10, 2 );

        // 隱藏成功訊息
        add_filter( 'woocommerce_coupon_message', [ $this, 'hide_success_message' ], 10, 3 );

        // 輸出 CSS 樣式
        add_action( 'wp_head', [ $this, 'output_styles' ], 20 );
    }

    /**
     * 創建虛擬優惠券（僅用於顯示，不實際折扣）
     * @param mixed $data
     * @param string $code
     * @return mixed
     */
    public function create_virtual_coupon( $data, $code ) {
        if ( ! $this->is_activity_coupon( $code ) ) {
            return $data;
        }

        return [
            'discount_type' => 'fixed_cart',
            'amount'        => 0
        ];
    }

    /**
     * 同步優惠券：根據活動狀態自動添加/移除優惠券
     * @param WC_Cart $cart
     */
    public function sync_coupons( $cart ) {
        // 防止無限循環和後台執行
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 3 ) {
            return;
        }

        $applied_coupons = $cart->get_applied_coupons();

        // 檢查是否有外部優惠券
        if ( $this->has_external_coupon( $applied_coupons ) ) {
            $this->remove_all_activity_coupons( $cart, $applied_coupons );
            return;
        }

        // 獲取符合條件的活動
        $activity_status = $this->engine->calculate_status();
        $qualified = array_filter( $activity_status, function( $status ) {
            return $status['status'] === 'qualified';
        });

        // 計算應該有的優惠券
        $should_have = [];
        foreach ( $qualified as $key => $data ) {
            if ( isset( self::ACTIVITY_COUPON_MAP[ $key ] ) ) {
                $should_have[] = self::ACTIVITY_COUPON_MAP[ $key ];
            }
        }

        // 添加缺少的優惠券
        foreach ( $should_have as $coupon_code ) {
            if ( ! $cart->has_discount( $coupon_code ) ) {
                $cart->apply_coupon( $coupon_code );
            }
        }

        // 移除不符合條件的優惠券
        foreach ( self::ACTIVITY_COUPON_MAP as $coupon_code ) {
            if ( ! in_array( $coupon_code, $should_have ) && $cart->has_discount( $coupon_code ) ) {
                $cart->remove_coupon( $coupon_code );
            }
        }
    }

    /**
     * 渲染優惠券 HTML（自訂樣式，隱藏移除按鈕）
     * @param string $html
     * @param WC_Coupon $coupon
     * @param string $discount_amount_html
     * @return string
     */
    public function render_coupon_html( $html, $coupon, $discount_amount_html ) {
        $code = $coupon->get_code();

        if ( ! $this->is_activity_coupon( $code ) ) {
            return $html;
        }

        $name = isset( self::ACTIVITY_NAMES[ $code ] ) ? self::ACTIVITY_NAMES[ $code ] : '新年優惠活動';

        // 返回自訂 HTML 結構
        return '<th colspan="2">' .
                   '<div class="nyb-activity-coupon-display">' .
                   '<span class="nyb-activity-icon">🎁</span>' .
                   '<span class="nyb-activity-name">' . esc_html( $name ) . '</span>' .
                   '<span class="nyb-activity-tag">已套用</span>' .
                   '</div>' .
                   '</th>';
    }

    /**
     * 禁止移除活動優惠券
     * @param bool $can_remove
     * @param string $code
     * @return bool
     */
    public function prevent_removal( $can_remove, $code ) {
        if ( $this->is_activity_coupon( $code ) ) {
            return false;
        }
        return $can_remove;
    }

    /**
     * 隱藏活動優惠券的成功訊息
     * @param string $message
     * @param int $message_code
     * @param WC_Coupon $coupon
     * @return string
     */
    public function hide_success_message( $message, $message_code, $coupon ) {
        if ( $coupon && $this->is_activity_coupon( $coupon->get_code() ) ) {
            if ( $message_code === WC_Coupon::WC_COUPON_SUCCESS ) {
                return ''; // 返回空字串，不顯示訊息
            }
        }
        return $message;
    }

    /**
     * 輸出 CSS 樣式
     */
    public function output_styles() {
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }

        ?>
        <style>
            /* 隱藏活動優惠券的左側標籤 */
            .cart-discount.coupon-nyb_activity_1 th:first-child,.cart-discount.coupon-nyb_activity_1 td:empty,
            .cart-discount.coupon-nyb_activity_2 th:first-child,.cart-discount.coupon-nyb_activity_2 td:empty,
            .cart-discount.coupon-nyb_activity_3 th:first-child,.cart-discount.coupon-nyb_activity_3 td:empty,
            .cart-discount.coupon-nyb_activity_4 th:first-child,.cart-discount.coupon-nyb_activity_4 td:empty,
            .cart-discount.coupon-nyb_activity_5 th:first-child,.cart-discount.coupon-nyb_activity_5 td:empty,
            .cart-discount.coupon-nyb_activity_6 th:first-child,.cart-discount.coupon-nyb_activity_6 td:empty,
            .cart-discount.coupon-nyb_activity_7 th:first-child,.cart-discount.coupon-nyb_activity_7 td:empty {
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
                color: #df565f;
            }

            .nyb-activity-tag {
                background: #df565f;
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
     * 檢查是否為活動優惠券
     * @param string $code
     * @return bool
     */
    private function is_activity_coupon( $code ) {
        return strpos( $code, 'nyb_activity_' ) === 0;
    }

    /**
     * 檢查是否有外部優惠券
     * @param array $applied_coupons
     * @return bool
     */
    private function has_external_coupon( $applied_coupons ) {
        foreach ( $applied_coupons as $coupon_code ) {
            if ( ! $this->is_activity_coupon( $coupon_code ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * 移除所有活動優惠券
     * @param WC_Cart $cart
     * @param array $applied_coupons
     */
    private function remove_all_activity_coupons( $cart, $applied_coupons ) {
        foreach ( $applied_coupons as $coupon_code ) {
            if ( $this->is_activity_coupon( $coupon_code ) ) {
                $cart->remove_coupon( $coupon_code );
            }
        }
    }
}

