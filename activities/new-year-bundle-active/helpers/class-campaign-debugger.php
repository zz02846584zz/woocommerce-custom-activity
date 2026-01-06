<?php
/**
 * 活動除錯工具
 * 用於測試和診斷活動規則
 */
class NYB_Campaign_Debugger {

    /**
     * 初始化除錯工具
     */
    public static function init() {
        // if ( ! NYB_DEBUG_MODE ) {
        //     return;
        // }

        // 在購物車頁面顯示除錯資訊
        add_action( 'woocommerce_after_cart', [ __CLASS__, 'display_debug_info' ] );

        // 添加 AJAX 端點測試規則
        add_action( 'wp_ajax_nyb_test_rule', [ __CLASS__, 'ajax_test_rule' ] );
    }

    /**
     * 顯示除錯資訊
     */
    public static function display_debug_info() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $cart = WC()->cart;
        if ( ! $cart || $cart->is_empty() ) {
            return;
        }

        $cart_items = $cart->get_cart();
        $analysis = self::analyze_cart_debug( $cart_items );
        $matched_rules = WC()->session->get( 'nyb_matched_rules', [] );

        ?>
        <div class="nyb-debug-panel" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; margin-top: 30px; border-radius: 8px; font-family: 'Courier New', monospace; font-size: 13px;">
            <h3 style="color: #4ec9b0; margin-top: 0;">🔧 活動除錯面板（僅管理員可見）</h3>

            <!-- 購物車分析 -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #dcdcaa; border-bottom: 1px solid #3e3e3e; padding-bottom: 5px;">📊 購物車分析</h4>
                <table style="width: 100%; color: #d4d4d4; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px; width: 200px;">嗜睡床墊數量：</td>
                        <td style="padding: 5px; color: #4fc1ff;"><?php echo $analysis['spring_mattress_count']; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">賴床墊數量：</td>
                        <td style="padding: 5px; color: #4fc1ff;"><?php echo $analysis['lai_mattress_count']; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">催眠枕數量：</td>
                        <td style="padding: 5px; color: #4fc1ff;"><?php echo $analysis['hypnotic_pillow_count']; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">床架數量：</td>
                        <td style="padding: 5px; color: #4fc1ff;"><?php echo $analysis['bed_frame_count']; ?></td>
                    </tr>
                </table>
            </div>

            <!-- 符合的規則 -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #dcdcaa; border-bottom: 1px solid #3e3e3e; padding-bottom: 5px;">✅ 符合的規則</h4>
                <?php if ( empty( $matched_rules ) ) : ?>
                    <p style="color: #808080;">無符合的規則</p>
                <?php else : ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ( $matched_rules as $rule ) : ?>
                            <li style="padding: 8px; background: #2d2d2d; margin-bottom: 5px; border-left: 3px solid #4ec9b0;">
                                <strong style="color: #4ec9b0;"><?php echo esc_html( $rule['rule_name'] ); ?></strong>
                                <span style="color: #808080;"> (優先級: <?php echo $rule['priority']; ?>)</span><br>
                                <span style="color: #ce9178;"><?php echo esc_html( $rule['description'] ); ?></span>
                                <?php if ( isset( $rule['gifts'] ) ) : ?>
                                    <br><span style="color: #9cdcfe;">贈品數量: <?php echo count( $rule['gifts'] ); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- 購物車項目詳情 -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: #dcdcaa; border-bottom: 1px solid #3e3e3e; padding-bottom: 5px;">🛒 購物車項目</h4>
                <table style="width: 100%; color: #d4d4d4; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #2d2d2d;">
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e;">商品ID</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e;">變體ID</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e;">數量</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e;">類型</th>
                            <th style="padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e;">贈品</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $cart_items as $cart_item_key => $cart_item ) : ?>
                            <?php
                            $product_id = $cart_item['product_id'];
                            $variation_id = $cart_item['variation_id'] ?? 0;
                            $quantity = $cart_item['quantity'];
                            $type = self::get_product_type( $product_id, $variation_id );
                            $is_gift = ( isset( $cart_item['nyb_is_gift'] ) && $cart_item['nyb_is_gift'] ) ||
                                       ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] );
                            ?>
                            <tr style="border-bottom: 1px solid #3e3e3e;">
                                <td style="padding: 8px; color: #4fc1ff;"><?php echo $product_id; ?></td>
                                <td style="padding: 8px; color: #4fc1ff;"><?php echo $variation_id ?: '-'; ?></td>
                                <td style="padding: 8px; color: #b5cea8;"><?php echo $quantity; ?></td>
                                <td style="padding: 8px; color: #dcdcaa;"><?php echo $type; ?></td>
                                <td style="padding: 8px; color: <?php echo $is_gift ? '#4ec9b0' : '#808080'; ?>">
                                    <?php echo $is_gift ? '✓' : '✗'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- 活動狀態 -->
            <div>
                <h4 style="color: #dcdcaa; border-bottom: 1px solid #3e3e3e; padding-bottom: 5px;">⏰ 活動狀態</h4>
                <table style="width: 100%; color: #d4d4d4; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px; width: 200px;">活動開始時間：</td>
                        <td style="padding: 5px; color: #ce9178;"><?php echo NYB_CAMPAIGN_START; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">活動結束時間：</td>
                        <td style="padding: 5px; color: #ce9178;"><?php echo NYB_CAMPAIGN_END; ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">當前時間：</td>
                        <td style="padding: 5px; color: #ce9178;"><?php echo current_time( 'Y-m-d H:i:s' ); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 5px;">活動狀態：</td>
                        <td style="padding: 5px;">
                            <?php
                            $is_active = self::is_campaign_active();
                            $status_color = $is_active ? '#4ec9b0' : '#f48771';
                            $status_text = $is_active ? '✓ 進行中' : '✗ 未開始/已結束';
                            ?>
                            <span style="color: <?php echo $status_color; ?>; font-weight: bold;"><?php echo $status_text; ?></span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- 快速測試按鈕 -->
            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #3e3e3e;">
                <button onclick="nyb_clear_gifts()" style="background: #f48771; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;">
                    清除所有贈品
                </button>
                <button onclick="nyb_revalidate_cart()" style="background: #4ec9b0; color: #1e1e1e; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    重新驗證購物車
                </button>
            </div>

            <script>
            function nyb_clear_gifts() {
                if (confirm('確定要清除所有贈品嗎？')) {
                    jQuery.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                        action: 'nyb_clear_gifts'
                    }, function() {
                        location.reload();
                    });
                }
            }

            function nyb_revalidate_cart() {
                jQuery('body').trigger('update_checkout');
                location.reload();
            }
            </script>
        </div>
        <?php
    }

    /**
     * 分析購物車（除錯用）
     */
    private static function analyze_cart_debug( $cart_items ) {
        $analysis = [
            'spring_mattress_count' => 0,
            'lai_mattress_count'    => 0,
            'hypnotic_pillow_count' => 0,
            'bed_frame_count'       => 0,
        ];

        foreach ( $cart_items as $cart_item ) {
            $product_id   = $cart_item['product_id'];
            $variation_id = $cart_item['variation_id'] ?? 0;
            $quantity     = $cart_item['quantity'];

            if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $analysis['spring_mattress_count'] += $quantity;
            }
            if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
                $analysis['lai_mattress_count'] += $quantity;
            }
            if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
                $analysis['hypnotic_pillow_count'] += $quantity;
            }
            if ( isset( NYB_BED_FRAME_IDS_MAP[ $variation_id ] ) ) {
                $analysis['bed_frame_count'] += $quantity;
            }
        }

        return $analysis;
    }

    /**
     * 取得商品類型
     */
    private static function get_product_type( $product_id, $variation_id ) {
        if ( isset( NYB_SPRING_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            return '嗜睡床墊';
        }
        if ( isset( NYB_LAI_MATTRESS_VARS_MAP[ $variation_id ] ) ) {
            return '賴床墊';
        }
        if ( isset( NYB_HYPNOTIC_PILLOW_VARS_MAP[ $variation_id ] ) ) {
            return '催眠枕';
        }
        if ( isset( NYB_BED_FRAME_IDS_MAP[ $variation_id ] ) ) {
            return '床架';
        }
        return '其他';
    }

    /**
     * 檢查活動是否進行中
     */
    private static function is_campaign_active() {
        $now = current_time( 'timestamp' );
        $start = strtotime( NYB_CAMPAIGN_START );
        $end = strtotime( NYB_CAMPAIGN_END );
        return ( $now >= $start && $now <= $end );
    }

    /**
     * AJAX: 清除所有贈品
     */
    public static function ajax_clear_gifts() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Permission denied' );
        }

        $cart = WC()->cart;
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $is_gift = ( isset( $cart_item['nyb_is_gift'] ) && $cart_item['nyb_is_gift'] ) ||
                       ( isset( $cart_item['_nyb_virtual_bedding'] ) && $cart_item['_nyb_virtual_bedding'] );

            if ( $is_gift ) {
                $cart->remove_cart_item( $cart_item_key );
            }
        }

        wp_send_json_success();
    }
}

// 初始化除錯工具
// if ( NYB_DEBUG_MODE ) {
    NYB_Campaign_Debugger::init();
    add_action( 'wp_ajax_nyb_clear_gifts', [ 'NYB_Campaign_Debugger', 'ajax_clear_gifts' ] );
// }

