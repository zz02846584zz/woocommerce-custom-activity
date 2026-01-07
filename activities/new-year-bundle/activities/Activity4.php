<?php
/**
 * 活動4: 買枕頭送枕套（買一送一）
 */
class NYB_Activity4 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle4';
    }

    public function get_name() {
        return '買催眠枕送天絲枕套（買一送一）';
    }

    public function get_description() {
        return '買催眠枕送配對天絲枕套';
    }

    public function get_priority() {
        return 5;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['hypnotic_pillow'] >= 1;
    }

    public function apply( $cart, &$stats, $context ) {
        // 收集購物車中所有購買的催眠枕
        $purchased_pillows = [];
        $pillow_count_for_activity4 = 0;
        $maps = NYB_Constants::get_hash_maps();

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            $variation_id = $cart_item['variation_id'];

            // 排除贈品
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                continue;
            }

            // 只處理催眠枕
            if ( isset( $maps['hypnotic_pillow_vars'][ $variation_id ] ) ) {
                if ( ! isset( $purchased_pillows[ $variation_id ] ) ) {
                    $purchased_pillows[ $variation_id ] = [
                        'quantity' => 0,
                        'name' => $cart_item['data']->get_name(),
                        'cart_item_key' => $cart_item_key
                    ];
                }
                $purchased_pillows[ $variation_id ]['quantity'] += $cart_item['quantity'];
                $pillow_count_for_activity4 += $cart_item['quantity'];
            }
        }

        // 如果沒有購買任何催眠枕，清空 session 並移除贈品
        if ( empty( $purchased_pillows ) ) {
            WC()->session->__unset( 'nyb_bundle4_pillow_gifts' );
            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === $this->get_code() ) {
                    $cart->remove_cart_item( $cart_item_key );
                }
            }
            return false;
        }

        // 計算被活動4使用的枕頭數量（從 stats 的 usage 中取得）
        if ( isset( $stats['usage'][$this->get_code()]['hypnotic_pillow'] ) ) {
            $pillow_count_for_activity4 = $stats['usage'][$this->get_code()]['hypnotic_pillow'];
        }

        NYB_Constants::log( sprintf( "[%s] 被活動使用的枕頭數量: %d", $this->get_code(), $pillow_count_for_activity4 ), $context );

        // 為每個被使用的枕頭添加對應的枕套贈品
        $pillowcases_to_add = [];

        foreach ( $purchased_pillows as $var_id => $pillow_data ) {
            if ( isset( NYB_Constants::PILLOWCASE_MAP[ $var_id ] ) ) {
                $quantity_to_gift = min( $pillow_data['quantity'], $pillow_count_for_activity4 );

                if ( $quantity_to_gift > 0 ) {
                    $pillowcase_id = NYB_Constants::PILLOWCASE_MAP[ $var_id ];

                    if ( ! isset( $pillowcases_to_add[ $pillowcase_id ] ) ) {
                        $pillowcases_to_add[ $pillowcase_id ] = 0;
                    }

                    $pillowcases_to_add[ $pillowcase_id ] += $quantity_to_gift;
                    $pillow_count_for_activity4 -= $quantity_to_gift;
                }
            }

            if ( $pillow_count_for_activity4 <= 0 ) {
                break;
            }
        }

        // 儲存到 session
        WC()->session->set( 'nyb_bundle4_pillow_gifts', $pillowcases_to_add );

        // 移除舊的活動4贈品
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['_nyb_auto_gift'] ) && $cart_item['_nyb_auto_gift'] === $this->get_code() ) {
                $cart->remove_cart_item( $cart_item_key );
            }
        }

        // 添加枕套贈品
        foreach ( $pillowcases_to_add as $pillowcase_id => $quantity ) {
            $this->add_gift(
                $cart,
                NYB_Constants::HYPNOTIC_PILLOW_PARENT,
                $quantity,
                $pillowcase_id,
                $this->get_code(),
                [ '_nyb_gift_type' => 'pillowcase' ]
            );
        }

        // 將贈品價格設為 0
        $this->set_gifts_free( $cart, $this->get_code() );

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 使用了 %d 個催眠枕",
            $this->get_code(),
            isset( $stats['usage'][$this->get_code()]['hypnotic_pillow'] ) ? $stats['usage'][$this->get_code()]['hypnotic_pillow'] : 0
        ), $context );

        return true;
    }
}

