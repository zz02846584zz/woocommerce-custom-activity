<?php
/**
 * 活動5: 大禮包送天絲四件組
 */
class NYB_Activity5 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle5';
    }

    public function get_name() {
        return '嗜睡床墊+催眠枕*2+賴床墊，送天絲四件組床包';
    }

    public function get_description() {
        return '嗜睡床墊+催眠枕×2+賴床墊，贈天絲四件組床包';
    }

    public function get_priority() {
        return 3;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['spring_mattress'] >= 1 &&
               $stats['available']['hypnotic_pillow'] >= 2 &&
               $stats['available']['lai_mattress'] >= 1;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'spring_mattress', 1, $this->get_code() ) ||
             ! NYB_CartAnalyzer::consume_item( $stats, 'hypnotic_pillow', 2, $this->get_code() ) ||
             ! NYB_CartAnalyzer::consume_item( $stats, 'lai_mattress', 1, $this->get_code() ) ) {
            return false;
        }

        // 找出嗜睡床墊的尺寸（用於確定床包價值）
        $mattress_var_id = null;
        $maps = NYB_Constants::get_hash_maps();

        foreach ( $cart->get_cart() as $cart_item ) {
            $variation_id = $cart_item['variation_id'];

            // 排除贈品
            if ( isset( $cart_item['_nyb_auto_gift'] ) ) {
                continue;
            }

            if ( isset( $maps['spring_mattress_vars'][ $variation_id ] ) ) {
                $mattress_var_id = $variation_id;
                break;
            }
        }

        if ( $mattress_var_id && isset( NYB_Constants::BEDDING_VALUE_MAP[ $mattress_var_id ] ) ) {
            // 添加虛擬床包商品到購物車
            $result = NYB_Virtual_Bedding_Product::add_to_cart( $cart, $mattress_var_id, $this->get_code() );

            if ( $result ) {
                NYB_Constants::log( sprintf( "[%s] 已添加天絲四件組床包到購物車 | 床墊 Variation ID: %s, 床包價值: %s",
                    $this->get_code(),
                    $mattress_var_id,
                    NYB_Constants::BEDDING_VALUE_MAP[ $mattress_var_id ]
                ), $context );
            }
        }

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 剩餘: 嗜睡床墊:%d, 催眠枕:%d, 賴床墊:%d",
            $this->get_code(),
            $stats['available']['spring_mattress'],
            $stats['available']['hypnotic_pillow'],
            $stats['available']['lai_mattress']
        ), $context );

        return true;
    }
}

