<?php
/**
 * 購物車活動監聽器
 * 職責：監聽購物車變動、觸發規則驗證、自動添加/移除贈品
 */
class NYB_Cart_Campaign_Listener {

    private static $instance = null;

    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // 購物車更新時觸發
        add_action( 'woocommerce_cart_updated', [ $this, 'on_cart_updated' ] );

        // 計算購物車總額前處理
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_campaign_pricing' ], 999 );

        // 結帳頁面顯示活動資訊
        add_action( 'woocommerce_review_order_before_payment', [ $this, 'display_campaign_info' ] );

        // 購物車頁面顯示活動提示
        add_action( 'woocommerce_before_cart', [ $this, 'display_campaign_suggestions' ] );

        // 🔒 問題3：禁止移除贈品
        add_filter( 'woocommerce_cart_item_remove_link', [ $this, 'disable_gift_removal' ], 10, 2 );

        // 🔒 問題3：禁止修改贈品數量
        add_filter( 'woocommerce_cart_item_quantity', [ $this, 'disable_gift_quantity_change' ], 10, 3 );

        // ⚡ 問題1：優惠券套用時檢查互斥
        add_action( 'woocommerce_applied_coupon', [ $this, 'check_coupon_mutex' ] );

        // ⚡ 問題1：購物車計算前移除互斥優惠券
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'enforce_discount_mutex' ], 1 );
    }

    /**
     * 購物車更新時的處理邏輯
     */
    public function on_cart_updated() {
        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            return;
        }

        // ⚠️ 問題D：活動期間外清理贈品
        if ( ! $this->is_campaign_active() ) {
            $this->remove_all_gifts();
            WC()->session->set( 'nyb_matched_rules', [] );
            return;
        }

        // 驗證規則
        $matched_rules = NYB_Campaign_Rule_Engine::validate_cart( $cart->get_cart() );

        // 儲存到 session
        WC()->session->set( 'nyb_matched_rules', $matched_rules );

        // 自動添加贈品
        $this->sync_gifts( $matched_rules );

        $this->log( 'Cart updated, matched rules:', $matched_rules );
    }

    /**
     * 套用活動價格（規則2的特價）
     */
    public function apply_campaign_pricing( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! $this->is_campaign_active() ) {
            return;
        }

        $matched_rules = WC()->session->get( 'nyb_matched_rules', [] );

        foreach ( $matched_rules as $rule ) {
            if ( isset( $rule['price_override'] ) ) {
                $this->apply_price_override( $cart, $rule['price_override'] );
            }
        }
    }

    /**
     * 套用價格覆寫（規則2：枕頭2顆$8888）
     */
    private function apply_price_override( $cart, $override_config ) {
        if ( $override_config['target'] !== 'hypnotic_pillow' ) {
            return;
        }

        $pillow_items = [];
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $variation_id = $cart_item['variation_id'] ?? 0;
            if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
                $pillow_items[] = [
                    'key'  => $cart_item_key,
                    'item' => $cart_item,
                ];
            }
        }

        // 計算前2顆的平均價格
        $total_qty = 0;
        foreach ( $pillow_items as $pillow ) {
            $total_qty += $pillow['item']['quantity'];
        }

        if ( $total_qty >= 2 ) {
            $avg_price = $override_config['price'] / 2;
            $remaining_qty = 2;

            foreach ( $pillow_items as $pillow ) {
                $qty = min( $pillow['item']['quantity'], $remaining_qty );
                if ( $qty > 0 ) {
                    $cart->cart_contents[ $pillow['key'] ]['data']->set_price( $avg_price );
                    $remaining_qty -= $qty;
                }
                if ( $remaining_qty <= 0 ) break;
            }
        }
    }

    /**
     * 同步贈品（添加/移除）
     */
    private function sync_gifts( $matched_rules ) {
        $cart = WC()->cart;
        $current_gifts = $this->get_current_gift_items();
        $expected_gifts = [];

        // 收集所有應該存在的贈品
        foreach ( $matched_rules as $rule ) {
            if ( isset( $rule['gifts'] ) ) {
                foreach ( $rule['gifts'] as $gift ) {
                    $key = $this->get_gift_key( $gift );
                    $expected_gifts[ $key ] = $gift;
                }
            }
        }

        // 移除多餘的贈品
        foreach ( $current_gifts as $gift_key => $cart_item_key ) {
            if ( ! isset( $expected_gifts[ $gift_key ] ) ) {
                $cart->remove_cart_item( $cart_item_key );
                $this->log( 'Removed gift:', $gift_key );
            }
        }

        // 添加缺少的贈品
        foreach ( $expected_gifts as $gift_key => $gift ) {
            if ( ! isset( $current_gifts[ $gift_key ] ) ) {
                $this->add_gift_to_cart( $gift );
                $this->log( 'Added gift:', $gift_key );
            }
        }
    }

    /**
     * 取得購物車中現有的贈品項目
     */
    private function get_current_gift_items() {
        $gifts = [];
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            // 統一檢查兩種贈品標記
            $is_gift = ( isset( $cart_item['nyb_is_gift'] ) && $cart_item['nyb_is_gift'] ) ||
                       ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] );

            if ( $is_gift ) {
                $gift_key = $cart_item['nyb_gift_key'] ?? $cart_item['_nyb_activity_type'] ?? '';
                if ( $gift_key ) {
                    $gifts[ $gift_key ] = $cart_item_key;
                }
            }
        }
        return $gifts;
    }

    /**
     * 生成贈品唯一鍵
     */
    private function get_gift_key( $gift ) {
        if ( isset( $gift['product_id'] ) ) {
            return 'product_' . $gift['product_id'];
        }
        if ( isset( $gift['variation_id'] ) ) {
            return 'variation_' . $gift['variation_id'];
        }
        // if ( isset( $gift['virtual_product'] ) ) {
        //     return 'virtual_' . $gift['virtual_product']['sku'];
        // }
        return '';
    }

    /**
     * 添加贈品到購物車
     */
    private function add_gift_to_cart( $gift ) {
        $cart_item_data = [
            'nyb_is_gift'   => true,
            'nyb_gift_key'  => $this->get_gift_key( $gift ),
        ];

        // 🎁 問題2：虛擬商品（天絲床包）- 修正方法簽名
        if ( isset( $gift['virtual_product'] ) ) {
            $virtual_product = $gift['virtual_product'];
            $variation_id = (int) str_replace( 'GIFT-BEDDING-', '', $virtual_product['sku'] );

            NYB_Virtual_Bedding_Product::add_to_cart(
                WC()->cart,
                $variation_id,
                'bundle_' . ( $gift['rule_name'] ?? 'auto' )
            );
            return;
        }

        // 實體商品
        $product_id = $gift['product_id'] ?? 0;
        $variation_id = $gift['variation_id'] ?? 0;
        $quantity = $gift['quantity'] ?? 1;

        if ( $variation_id > 0 ) {
            $parent_id = wc_get_product( $variation_id )->get_parent_id();
            WC()->cart->add_to_cart(
                $parent_id,
                $quantity,
                $variation_id,
                [],
                $cart_item_data
            );
        } elseif ( $product_id > 0 ) {
            WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );
        }
    }

    /**
     * 在結帳頁面顯示活動資訊
     */
    public function display_campaign_info() {
        $matched_rules = WC()->session->get( 'nyb_matched_rules', [] );
        if ( empty( $matched_rules ) ) {
            return;
        }

        echo '<div class="nyb-campaign-notice woocommerce-info" style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin-bottom: 20px;">';
        echo '<h3 style="margin-top: 0; color: #1e40af;">🎁 您已享有以下優惠</h3>';
        echo '<ul style="margin: 10px 0 0 20px; line-height: 1.8;">';
        foreach ( $matched_rules as $rule ) {
            echo '<li style="color: #1f2937;">' . esc_html( $rule['description'] ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * 在購物車頁面顯示活動建議（差一點就能享受優惠）
     */
    public function display_campaign_suggestions() {
        if ( ! $this->is_campaign_active() ) {
            return;
        }

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            return;
        }

        $analysis = $this->analyze_cart_for_suggestions();
        $suggestions = $this->generate_suggestions( $analysis );

        if ( empty( $suggestions ) ) {
            return;
        }

        echo '<div class="nyb-campaign-suggestions woocommerce-info" style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin-bottom: 20px;">';
        echo '<h3 style="margin-top: 0; color: #92400e;">💡 再加購以下商品即可享受優惠</h3>';
        echo '<ul style="margin: 10px 0 0 20px; line-height: 1.8;">';
        foreach ( $suggestions as $suggestion ) {
            echo '<li style="color: #78350f;"><strong>' . esc_html( $suggestion['title'] ) . '</strong><br>';
            echo '<span style="font-size: 0.9em; color: #a16207;">' . esc_html( $suggestion['hint'] ) . '</span></li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    /**
     * 分析購物車並生成活動建議
     */
    private function analyze_cart_for_suggestions() {
        $cart_items = WC()->cart->get_cart();
        $analysis = [
            'has_spring_mattress' => false,
            'has_lai_mattress'    => false,
            'hypnotic_pillow_count' => 0,
            'has_bed_frame'       => false,
        ];

        foreach ( $cart_items as $cart_item ) {
            $product_id   = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;

            if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $analysis['has_spring_mattress'] = true;
            }
            if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $analysis['has_lai_mattress'] = true;
            }
            if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
                $analysis['hypnotic_pillow_count'] += $cart_item['quantity'];
            }
            if ( isset( NYB_BED_FRAME_IDS_MAP[ $product_id ] ) ) {
                $analysis['has_bed_frame'] = true;
            }
        }

        return $analysis;
    }

    /**
     * 根據購物車狀態生成建議
     */
    private function generate_suggestions( $analysis ) {
        $suggestions = [];

        // 建議規則7：嗜睡+枕*2+賴 → 床包+茸茸被
        if ( $analysis['has_spring_mattress'] && $analysis['hypnotic_pillow_count'] >= 2 && ! $analysis['has_lai_mattress'] ) {
            $suggestions[] = [
                'title' => '加購賴床墊，贈天絲床包+茸茸被',
                'hint'  => '您已有嗜睡床墊和枕頭，再加購賴床墊即可享受',
            ];
        }

        // 建議規則6：嗜睡+床架+枕*2 → 床包+茸茸被
        if ( $analysis['has_spring_mattress'] && $analysis['hypnotic_pillow_count'] >= 2 && ! $analysis['has_bed_frame'] ) {
            $suggestions[] = [
                'title' => '加購床架，贈天絲床包+茸茸被',
                'hint'  => '您已有嗜睡床墊和枕頭，再加購床架即可享受',
            ];
        }

        // 建議規則5：嗜睡+床架 → 側睡枕
        if ( $analysis['has_spring_mattress'] && ! $analysis['has_bed_frame'] && $analysis['hypnotic_pillow_count'] < 2 ) {
            $suggestions[] = [
                'title' => '加購床架，贈側睡枕',
                'hint'  => '您已有嗜睡床墊，再加購床架即可享受',
            ];
        }

        // 建議規則2：枕*2 → $8888+枕套*2
        if ( $analysis['hypnotic_pillow_count'] === 1 ) {
            $suggestions[] = [
                'title' => '再加購1顆枕頭，2顆只要 $8888 並贈枕套2個',
                'hint'  => '您已有1顆枕頭，再加購1顆即可享受超值優惠',
            ];
        }

        // 建議規則1：嗜睡+枕 → 茸茸被
        if ( $analysis['has_spring_mattress'] && $analysis['hypnotic_pillow_count'] === 0 ) {
            $suggestions[] = [
                'title' => '加購催眠枕，贈兩用茸茸被',
                'hint'  => '您已有嗜睡床墊，再加購枕頭即可享受',
            ];
        }

        // 只顯示前3個建議，避免過於雜亂
        return array_slice( $suggestions, 0, 3 );
    }

    /**
     * 檢查活動是否進行中
     */
    private function is_campaign_active() {
        $now = current_time( 'timestamp' );
        $start = strtotime( NYB_CAMPAIGN_START );
        $end = strtotime( NYB_CAMPAIGN_END );
        return ( $now >= $start && $now <= $end );
    }

    /**
     * 🔒 問題3：禁止移除贈品
     */
    public function disable_gift_removal( $link, $cart_item_key ) {
        $cart_item = WC()->cart->get_cart()[ $cart_item_key ] ?? null;
        if ( ! $cart_item ) {
            return $link;
        }

        $is_gift = ( isset( $cart_item['nyb_is_gift'] ) && $cart_item['nyb_is_gift'] ) ||
                   ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] );

        if ( $is_gift ) {
            return '<span class="nyb-gift-locked" style="color: #999; font-size: 12px;">🎁 活動贈品</span>';
        }

        return $link;
    }

    /**
     * 🔒 問題3：禁止修改贈品數量
     */
    public function disable_gift_quantity_change( $product_quantity, $cart_item_key, $cart_item ) {
        $is_gift = ( isset( $cart_item['nyb_is_gift'] ) && $cart_item['nyb_is_gift'] ) ||
                   ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] );

        if ( $is_gift ) {
            $quantity = $cart_item['quantity'];
            return sprintf(
                '<span class="quantity nyb-gift-qty" style="color: #666;">%d <small style="color: #999;">(贈品)</small></span>',
                $quantity
            );
        }

        return $product_quantity;
    }

    /**
     * ⚡ 問題1：檢查優惠券與活動互斥
     */
    public function check_coupon_mutex( $coupon_code ) {
        if ( ! $this->is_campaign_active() ) {
            return;
        }

        $matched_rules = WC()->session->get( 'nyb_matched_rules', [] );

        // 如果有活動規則生效，檢查是否為全館折扣券
        if ( ! empty( $matched_rules ) && $this->is_global_discount_coupon( $coupon_code ) ) {
            WC()->cart->remove_coupon( $coupon_code );
            wc_add_notice(
                '活動組合優惠與全館折扣不可共用，已自動移除折扣券',
                'notice'
            );
            $this->log( 'Removed global discount coupon due to campaign rules', [ 'coupon' => $coupon_code ] );
        }
    }

    /**
     * ⚡ 問題1：強制執行折扣互斥（購物車計算前）
     */
    public function enforce_discount_mutex( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! $this->is_campaign_active() ) {
            return;
        }

        $matched_rules = WC()->session->get( 'nyb_matched_rules', [] );
        if ( empty( $matched_rules ) ) {
            return;
        }

        // 移除所有全館折扣券
        $applied_coupons = $cart->get_applied_coupons();
        foreach ( $applied_coupons as $coupon_code ) {
            if ( $this->is_global_discount_coupon( $coupon_code ) ) {
                $cart->remove_coupon( $coupon_code );
                $this->log( 'Auto-removed global discount coupon', [ 'coupon' => $coupon_code ] );
            }
        }
    }

    /**
     * 判斷是否為全館折扣券
     */
    private function is_global_discount_coupon( $coupon_code ) {
        // 從常數讀取全館折扣券列表
        $global_discount_coupons = defined( 'NYB_GLOBAL_DISCOUNT_COUPONS' )
            ? NYB_GLOBAL_DISCOUNT_COUPONS
            : [];

        // 允許外部過濾器修改
        $global_discount_coupons = apply_filters( 'nyb_global_discount_coupons', $global_discount_coupons );

        // 1. 先檢查代碼是否在白名單中
        if ( in_array( strtoupper( $coupon_code ), array_map( 'strtoupper', $global_discount_coupons ) ) ) {
            return true;
        }

        // 2. 智能檢查：百分比折扣且無商品限制
        $coupon = new WC_Coupon( $coupon_code );
        if ( $coupon->get_id() ) {
            $is_percentage = ( $coupon->get_discount_type() === 'percent' );
            $no_product_restriction = empty( $coupon->get_product_ids() ) && empty( $coupon->get_product_categories() );

            if ( $is_percentage && $no_product_restriction ) {
                $this->log( 'Detected global discount coupon by type', [
                    'coupon' => $coupon_code,
                    'type'   => $coupon->get_discount_type(),
                ] );
                return true;
            }
        }

        return false;
    }

    /**
     * 移除所有活動贈品（活動結束時調用）
     */
    private function remove_all_gifts() {
        $cart = WC()->cart;
        $removed_count = 0;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $is_gift = ( isset( $cart_item['nyb_is_gift'] ) && $cart_item['nyb_is_gift'] ) ||
                       ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] );

            if ( $is_gift ) {
                $cart->remove_cart_item( $cart_item_key );
                $removed_count++;
            }
        }

        if ( $removed_count > 0 ) {
            $this->log( 'Campaign ended, removed all gifts', [ 'count' => $removed_count ] );
            wc_add_notice( '活動已結束，已自動移除活動贈品', 'notice' );
        }
    }

    /**
     * 記錄日誌
     */
    private function log( $message, $data = [] ) {
        if ( NYB_DEBUG_MODE ) {
            error_log( '[NYB Cart Listener] ' . $message . ' ' . print_r( $data, true ) );
        }
    }
}

