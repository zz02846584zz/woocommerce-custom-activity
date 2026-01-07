<?php
/**
 * 活動7: 終極組合
 */
class NYB_Activity7 extends NYB_ActivityBase {

    public function get_code() {
        return 'bundle7';
    }

    public function get_name() {
        return '嗜睡床墊+床架+催眠枕*2，送天絲四件組床包+茸茸被';
    }

    public function get_description() {
        return '嗜睡床墊+床架+催眠枕×2，贈天絲四件組床包+茸茸被';
    }

    public function get_priority() {
        return 1;
    }

    public function is_qualified( $stats ) {
        return $stats['available']['spring_mattress'] >= 1 &&
               $stats['available']['bed_frame'] >= 1 &&
               $stats['available']['hypnotic_pillow'] >= 2;
    }

    public function apply( $cart, &$stats, $context ) {
        // 扣減數量
        if ( ! NYB_CartAnalyzer::consume_item( $stats, 'spring_mattress', 1, $this->get_code() ) ||
             ! NYB_CartAnalyzer::consume_item( $stats, 'bed_frame', 1, $this->get_code() ) ||
             ! NYB_CartAnalyzer::consume_item( $stats, 'hypnotic_pillow', 2, $this->get_code() ) ) {
            return false;
        }

        // 贈品1: 茸茸被
        if ( ! $this->gift_exists( $cart, $this->get_code(), NYB_Constants::GIFT_FLEECE_BLANKET ) ) {
            $this->add_gift( $cart, NYB_Constants::GIFT_FLEECE_BLANKET, 1, 0, $this->get_code() );
        }

        // 贈品2: 天絲四件組床包（使用虛擬商品）
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
            NYB_Constants::log( sprintf( "[%s] mattress_var_id: %s", $this->get_code(), $mattress_var_id ), $context );

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

        // 將贈品價格設為 0
        $this->set_gifts_free( $cart, $this->get_code() );

        NYB_Constants::log( sprintf( "[%s] 套用成功 | 剩餘: 嗜睡床墊:%d, 床架:%d, 催眠枕:%d",
            $this->get_code(),
            $stats['available']['spring_mattress'],
            $stats['available']['bed_frame'],
            $stats['available']['hypnotic_pillow']
        ), $context );

        return true;
    }
}

