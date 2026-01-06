<?php
/**
 * 活動優惠顯示類
 * 職責：在購物車/結帳頁面顯示活動標籤（整合規則引擎）
 */
class NYB_Activity_Coupon_Display {

    public static function init() {
        // 在購物車總計表格中顯示活動標籤
        add_action( 'woocommerce_cart_totals_after_order_total', [ __CLASS__, 'display_activity_badges_in_cart' ] );

        // 在結帳頁面顯示活動標籤
        add_action( 'woocommerce_review_order_after_order_total', [ __CLASS__, 'display_activity_badges_in_checkout' ] );
    }

    /**
     * 在購物車頁面顯示活動標籤
     */
    public static function display_activity_badges_in_cart() {
        self::display_activity_badges( 'cart' );
    }

    /**
     * 在結帳頁面顯示活動標籤
     */
    public static function display_activity_badges_in_checkout() {
        self::display_activity_badges( 'checkout' );
    }

    /**
     * 顯示活動標籤（統一邏輯）
     */
    private static function display_activity_badges( $context = 'cart' ) {
        // 檢查是否有 WooCommerce Session
        if ( ! WC()->session ) {
            return;
        }

        // 從 Session 讀取符合的規則（由規則引擎設定）
        $matched_rules = WC()->session->get( 'nyb_matched_rules', [] );

        if ( empty( $matched_rules ) ) {
            return;
        }

        // 規則名稱對應
        $rule_names = [
            'rule_1' => '嗜睡床墊+催眠枕，贈兩用茸茸被',
            'rule_2' => '枕頭任選2顆 $8888，贈天絲枕套2個',
            'rule_3' => '買催眠枕送天絲枕套',
            'rule_4' => '買賴床墊送抱枕+眼罩',
            'rule_5' => '嗜睡床墊+床架，贈側睡枕',
            'rule_6' => '嗜睡床墊+床架+枕*2，贈天絲床包+茸茸被',
            'rule_7' => '嗜睡床墊+枕*2+賴床墊，贈天絲床包+茸茸被',
        ];

        // 顯示每個符合的規則
        foreach ( $matched_rules as $rule ) {
            $rule_key = $rule['rule_name'] ?? '';
            $display_name = $rule_names[ $rule_key ] ?? $rule['description'] ?? '新年優惠活動';

            ?>
            <tr class="nyb-activity-badge-row cart-discount coupon-<?php echo esc_attr( $rule_key ); ?>">
                <th colspan="2">
                    <div class="nyb-activity-coupon-display">
                        <span class="nyb-activity-icon">🎁</span>
                        <span class="nyb-activity-name"><?php echo esc_html( $display_name ); ?></span>
                        <span class="nyb-activity-tag">已套用</span>
                    </div>
                </th>
            </tr>
            <?php
        }
    }

    /**
     * 取得規則顯示名稱（支援舊版相容）
     */
    private static function get_rule_display_name( $rule_key ) {
        $rule_names = [
            'rule_1' => '嗜睡床墊+催眠枕，贈兩用茸茸被',
            'rule_2' => '枕頭任選2顆 $8888，贈天絲枕套2個',
            'rule_3' => '買催眠枕送天絲枕套',
            'rule_4' => '買賴床墊送抱枕+眼罩',
            'rule_5' => '嗜睡床墊+床架，贈側睡枕',
            'rule_6' => '嗜睡床墊+床架+枕*2，贈天絲床包+茸茸被',
            'rule_7' => '嗜睡床墊+枕*2+賴床墊，贈天絲床包+茸茸被',
        ];

        return $rule_names[ $rule_key ] ?? '新年優惠活動';
    }
}

/**
 * 添加活動標籤樣式
 */
add_action( 'wp_head', function() {
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }
    ?>
    <style>
        /* 活動標籤行樣式 */
        .nyb-activity-badge-row {
            background: #fff9f0 !important;
            border-top: 1px solid #ffecd1 !important;
            border-bottom: 1px solid #ffecd1 !important;
        }

        /* 活動優惠顯示容器 */
        .nyb-activity-coupon-display {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 0;
        }

        /* 結帳頁面微調 */
        .woocommerce-checkout .nyb-activity-coupon-display {
            padding: 10px 0;
        }

        /* 活動圖示 */
        .nyb-activity-icon {
            font-size: 20px;
            line-height: 1;
        }

        /* 活動名稱 */
        .nyb-activity-name {
            flex: 1;
            font-weight: bold;
            color: #df565f;
            font-size: 14px;
        }

        /* 活動標籤 */
        .nyb-activity-tag {
            background: #df565f;
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        /* 手機版適配 */
        @media (max-width: 768px) {
            .nyb-activity-coupon-display {
                gap: 8px;
                padding: 10px 0;
            }

            .nyb-activity-icon {
                font-size: 18px;
            }

            .nyb-activity-name {
                font-size: 13px;
            }

            .nyb-activity-tag {
                font-size: 10px;
                padding: 3px 10px;
            }
        }

        /* 購物車表格整體調整 */
        .woocommerce-cart-form__contents .nyb-activity-badge-row th {
            padding: 0 12px;
        }

        .woocommerce-checkout .nyb-activity-badge-row th {
            padding: 0;
        }
    </style>
    <?php
}, 20 );