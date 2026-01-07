<?php
/**
 * 活動3: 枕頭組合特價$8888（任意2個枕頭）
 */
class NYB_Activity3 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle3';
    }

    public function get_name() {
        return '催眠枕任選2顆特價$8,888';
    }

    public function get_description() {
        return '催眠枕任選2顆特價$8,888';
    }

    public function get_priority() {
        return 4;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['hypnotic_pillow'] >= 2;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'hypnotic_pillow', 2, $this->get_code() ) ) {
            return false;
        }

        // 收集所有購買的枕頭（排除贈品）
        $purchased_pillows = [];
        $maps = NYB_Constants::get_hash_maps();

        foreach ( $cart->get_cart() as $cart_item ) {
            $variation_id = $cart_item['variation_id'];

            // 排除贈品
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                continue;
            }

            // 排除活動4的免費贈品
            if ( $cart_item['data']->get_meta( '_is_free_gift' ) === 'yes' ) {
                continue;
            }

            // 只處理催眠枕
            if ( isset( $maps['hypnotic_pillow_vars'][ $variation_id ] ) ) {
                $price = $cart_item['data']->get_price();
                $quantity = $cart_item['quantity'];

                // 將每個枕頭單獨加入陣列（考慮數量）
                for ( $i = 0; $i < $quantity; $i++ ) {
                    $purchased_pillows[] = [
                        'variation_id' => $variation_id,
                        'price' => $price,
                        'name' => $cart_item['data']->get_name()
                    ];
                }
            }
        }

        // 如果少於2個枕頭，不套用活動
        if ( count( $purchased_pillows ) < 2 ) {
            return false;
        }

        // 按價格降序排序
        usort( $purchased_pillows, function( $a, $b ) {
            return $b['price'] - $a['price'];
        });

        // 取最高價的兩個枕頭
        $top_two = array_slice( $purchased_pillows, 0, 2 );
        $top_two_total = $top_two[0]['price'] + $top_two[1]['price'];

        // 計算需要的折扣金額
        $discount_needed = $top_two_total - NYB_Constants::COMBO_SPECIAL_PRICE;

        if ( $discount_needed > 0 ) {
            // 移除之前的折扣（如果有）
            foreach ( $cart->get_fees() as $fee_key => $fee ) {
                if ( $fee->name === '枕頭組合特價優惠' ) {
                    $cart->remove_fee( $fee->name );
                }
            }

            // 套用新折扣
            $cart->add_fee( '枕頭組合特價優惠', -$discount_needed );
        }

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 剩餘: 催眠枕:%d",
            $this->get_code(),
            $stats['available']['hypnotic_pillow']
        ), $context );

        return true;
    }
}

